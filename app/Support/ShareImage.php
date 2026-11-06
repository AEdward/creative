<?php

namespace App\Support;

use App\Models\Course;
use Illuminate\Support\Facades\Storage;

/**
 * 1200×630 link-preview images (WhatsApp, Telegram, Facebook, X, LinkedIn): the course image (or the
 * home hero) cropped to fill, a dark fade at the bottom, and the Creative Lab wordmark. Generated once
 * and cached on the server; the cache key changes when the course changes.
 */
class ShareImage
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    public function forCourse(Course $course): string
    {
        $source = $course->thumbnail_key && Storage::disk('local')->exists($course->thumbnail_key)
            ? Storage::disk('local')->path($course->thumbnail_key)
            : $this->categoryImage($course) ?? public_path('images/home/hero.webp');

        return $this->cached('course-'.$course->id.'-'.md5($source.'|'.$course->updated_at?->timestamp), $source);
    }

    public function default(): string
    {
        return $this->cached('site-'.md5_file(public_path('images/home/hero.webp')), public_path('images/home/hero.webp'));
    }

    private function categoryImage(Course $course): ?string
    {
        $slug = $course->category?->slug;
        foreach (['webp', 'png'] as $ext) {
            if ($slug && is_file($path = public_path("images/categories/{$slug}.{$ext}"))) {
                return $path;
            }
        }

        return null;
    }

    /** Path of the cached JPEG, creating it if needed. */
    private function cached(string $name, string $source): string
    {
        $key = 'share-images/'.$name.'.jpg';
        $disk = Storage::disk('local');
        if (! $disk->exists($key)) {
            $disk->makeDirectory('share-images');
            $this->render($source, $disk->path($key));
        }

        return $disk->path($key);
    }

    private function render(string $source, string $target): void
    {
        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 11, 16, 37));
        if ($image = $this->open($source)) {
            // Crop to fill 1200×630 from the centre.
            [$w, $h] = [imagesx($image), imagesy($image)];
            $scale = max(self::WIDTH / $w, self::HEIGHT / $h);
            [$cw, $ch] = [(int) round(self::WIDTH / $scale), (int) round(self::HEIGHT / $scale)];
            imagecopyresampled($canvas, $image, 0, 0, (int) (($w - $cw) / 2), (int) (($h - $ch) / 2), self::WIDTH, self::HEIGHT, $cw, $ch);
            imagedestroy($image);
        }
        // Dark fade over the bottom third so the logo reads on any picture.
        for ($y = 380; $y < self::HEIGHT; $y++) {
            $alpha = (int) max(0, 127 - ($y - 380) / 250 * 100);
            imagefilledrectangle($canvas, 0, $y, self::WIDTH, $y, imagecolorallocatealpha($canvas, 11, 16, 37, $alpha));
        }
        if ($logo = $this->open(public_path('images/brand/wordmark-on-dark.png'))) {
            $lw = 380;
            $lh = (int) round(imagesy($logo) * $lw / imagesx($logo));
            imagecopyresampled($canvas, $logo, 56, self::HEIGHT - $lh - 48, 0, 0, $lw, $lh, imagesx($logo), imagesy($logo));
            imagedestroy($logo);
        }
        imagejpeg($canvas, $target, 84);
        imagedestroy($canvas);
    }

    private function open(string $path): \GdImage|false
    {
        if (! is_file($path)) {
            return false;
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'webp' => @imagecreatefromwebp($path),
            'png' => @imagecreatefrompng($path),
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            default => @imagecreatefromstring((string) file_get_contents($path)),
        };
    }
}
