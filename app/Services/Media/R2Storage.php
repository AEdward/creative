<?php

namespace App\Services\Media;

use App\Models\VideoAsset;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class R2Storage implements MediaStorage
{
    public function available(): bool
    {
        $config = config('filesystems.disks.r2');

        return filled($config['key']) && filled($config['secret']) && filled($config['bucket']) && str_starts_with($config['endpoint'] ?? '', 'https://');
    }

    public function upload(VideoAsset $asset): array
    {
        $upload = Storage::disk('r2')->temporaryUploadUrl($asset->upload_key, $asset->upload_expires_at, ['ContentType' => 'video/mp4']);
        $upload['headers']['Content-Type'] = ['video/mp4'];

        return $upload;
    }

    public function finalize(VideoAsset $asset): void
    {
        $disk = Storage::disk('r2');
        $client = $disk->getClient();
        $bucket = config('filesystems.disks.r2.bucket');
        $validate = function (string $key) use ($client, $bucket, $asset): void {
            $head = $client->headObject(['Bucket' => $bucket, 'Key' => $key]);
            if ((int) $head['ContentLength'] !== $asset->expected_bytes || strtolower(explode(';', $head['ContentType'] ?? '')[0]) !== 'video/mp4') {
                throw new RuntimeException('Invalid video metadata.');
            }
            $first = $client->getObject(['Bucket' => $bucket, 'Key' => $key, 'Range' => 'bytes=0-15']);
            $header = (string) $first['Body'];
            if (strlen($header) < 12 || substr($header, 4, 4) !== 'ftyp') {
                throw new RuntimeException('Invalid MP4 header.');
            }
        };
        $validate($asset->upload_key);
        $disk->copy($asset->upload_key, $asset->object_key);
        $validate($asset->object_key);
    }

    public function playback(VideoAsset $asset): string
    {
        return Storage::disk('r2')->temporaryUrl($asset->object_key, now()->addMinutes(5), ['ResponseContentType' => 'video/mp4', 'ResponseContentDisposition' => 'inline']);
    }

    public function delete(string $key): void
    {
        Storage::disk('r2')->delete($key);
    }
}
