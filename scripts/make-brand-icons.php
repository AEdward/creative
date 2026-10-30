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
