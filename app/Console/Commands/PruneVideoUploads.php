<?php

namespace App\Console\Commands;

use App\Models\Lesson;
use App\Models\VideoAsset;
use App\Services\Media\MediaStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class PruneVideoUploads extends Command
{
    protected $signature = 'academy:prune-video-uploads';

    protected $description = 'Remove expired staging objects and abandoned, unattached private videos';

    public function handle(MediaStorage $storage): int
    {
        if (! $storage->available()) {
            $this->error('Private storage is not configured. No objects were changed.');

            return self::FAILURE;
        }
        $failed = 0;
        $processed = 0;
        VideoAsset::where('upload_expires_at', '<', now()->subHours(2))->where('status', '!=', 'pruned')->chunkById(100, function ($assets) use ($storage, &$failed, &$processed): void {
            foreach ($assets as $asset) {
                try {
                    DB::transaction(function () use ($asset, $storage): void {
                        $locked = VideoAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
                        if ($locked->status === 'pruned') {
                            return;
                        }
                        $storage->delete($locked->upload_key);
                        $attached = Lesson::where('video_asset_id', $locked->id)->exists();
                        if (! $attached && ($locked->status !== 'ready' || $locked->created_at->lt(now()->subDay()))) {
                            $storage->delete($locked->object_key);
                            $locked->update(['status' => 'pruned']);
                        }
                    });
                    $processed++;
                } catch (Throwable) {
                    $failed++;
                }
            }
        });
        $this->info('Upload records processed: '.$processed.'. Failed: '.$failed.'.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
