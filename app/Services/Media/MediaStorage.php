<?php

namespace App\Services\Media;

use App\Models\VideoAsset;

interface MediaStorage
{
    public function available(): bool;

    /** @return array{url: string, headers: array} */
    public function upload(VideoAsset $asset): array;

    public function finalize(VideoAsset $asset): void;

    public function playback(VideoAsset $asset): string;

    public function delete(string $key): void;

    /** Short-lived link the browser uses to upload a file straight to storage. @return array{url: string, headers: array} */
    public function uploadUrl(string $key, string $mime, \DateTimeInterface $expires): array;

    /** Size in bytes of a stored object, or null when it doesn't exist. */
    public function objectSize(string $key): ?int;

    /** Short-lived private download link. */
    public function downloadUrl(string $key, string $filename, string $mime): string;
}
