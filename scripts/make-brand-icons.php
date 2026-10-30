<?php

// Converts the official logo files to WebP and builds favicon/app icons.
// Put logo-mark.png (cap icon) and logo-full.png (full lockup) in public/images/brand, then:
//   php scripts/make-brand-icons.php

$dir = __DIR__.'/../public/images/brand';

$load = function (string $name) use ($dir) {
    $path = "$dir/$name.png";
    if (! is_file($path)) {
        return null;
    }
    $image = imagecreatefrompng($path) ?: exit("Invalid PNG: $name.png\n");
    imagepalettetotruecolor($image);
    imagesavealpha($image, true);

    return $image;
};

foreach (['logo-mark', 'logo-full'] as $name) {
    if ($image = $load($name)) {
        imagewebp($image, "$dir/$name.webp", 90);
        echo "wrote $name.webp\n";
    }
}

$mark = $load('logo-mark') ?? exit("Missing logo-mark.png\n");
$w = imagesx($mark);
$h = imagesy($mark);
$side = min($w, $h);

// Small copy for the header/footer (44px shown, 4x for high-density screens).
$small = imagescale($mark, 176, 176, IMG_BICUBIC);
imagesavealpha($small, true);
imagewebp($small, "$dir/logo-mark-176.webp", 90);
echo "wrote logo-mark-176.webp
";

// Browsers and iOS still expect PNG for tab and home-screen icons.
foreach (['favicon-32.png' => 32, 'favicon-192.png' => 192, 'apple-touch-icon.png' => 180] as $name => $size) {
    $icon = imagecreatetruecolor($size, $size);
    imagealphablending($icon, false);
    imagesavealpha($icon, true);
    imagefill($icon, 0, 0, imagecolorallocatealpha($icon, 0, 0, 0, 127));
    // iOS shows icons on a solid tile, so use the brand night background.
    if ($name === 'apple-touch-icon.png') {
        imagefill($icon, 0, 0, imagecolorallocate($icon, 11, 15, 26));
        imagealphablending($icon, true);
    }
    imagecopyresampled($icon, $mark, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), $size, $size, $side, $side);
    imagepng($icon, "$dir/$name");
    echo "wrote $name\n";
}

// Wordmarks: white lettering for dark backgrounds, dark lettering for light ones.
// The on-dark master may sit on solid black, so black is converted to transparency.
foreach (['wordmark-on-dark' => true, 'wordmark-on-light' => false] as $name => $keyBlack) {
    if (! is_file("$dir/$name.png")) {
        continue;
    }
    $src = imagecreatefrompng("$dir/$name.png");
    imagepalettetotruecolor($src);
    $w = imagesx($src);
    $h = imagesy($src);
    $out = imagecreatetruecolor($w, $h);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    $clear = imagecolorallocatealpha($out, 0, 0, 0, 127);
    imagefill($out, 0, 0, $clear);
    [$minX, $minY, $maxX, $maxY] = [$w, $h, 0, 0];
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $c = imagecolorsforindex($src, imagecolorat($src, $x, $y));
            $a = 1 - $c['alpha'] / 127;
            [$r, $g, $b] = [$c['red'], $c['green'], $c['blue']];
            if ($keyBlack) {
                // Colour-to-alpha against black: brightest channel becomes opacity.
                $k = max($r, $g, $b) / 255;
                $a *= $k;
                if ($k > 0) {
                    [$r, $g, $b] = [min(255, $r / $k), min(255, $g / $k), min(255, $b / $k)];
                }
            }
            if ($a < 0.04) {
                continue;
            }
            imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, (int) $r, (int) $g, (int) $b, (int) round(127 * (1 - $a))));
            [$minX, $minY, $maxX, $maxY] = [min($minX, $x), min($minY, $y), max($maxX, $x), max($maxY, $y)];
        }
    }
    $pad = 4;
    $trim = imagecrop($out, ['x' => max(0, $minX - $pad), 'y' => max(0, $minY - $pad), 'width' => min($w, $maxX - $minX + 1 + 2 * $pad), 'height' => min($h, $maxY - $minY + 1 + 2 * $pad)]);
    imagesavealpha($trim, true);
    imagewebp($trim, "$dir/$name.webp", 90);
    // 2x of the 30px header height.
    $small = imagescale($trim, (int) round(imagesx($trim) * 60 / imagesy($trim)), 60, IMG_BICUBIC);
    imagesavealpha($small, true);
    imagewebp($small, "$dir/$name-60.webp", 90);
    echo "wrote $name.webp (".imagesx($trim).'x'.imagesy($trim).") and $name-60.webp\n";
}
