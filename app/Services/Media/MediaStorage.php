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
}
