<?php

@mkdir(__DIR__ . '/../assets', 0777, true);
@mkdir(__DIR__ . '/../android/app/src/main/res/drawable', 0777, true);

$srcPath = 'C:/Users/Admin/.gemini/antigravity-ide/brain/4a6b67be-d83c-48db-954f-5330f9573f8c/watermark_logo_1790341204623.jpg';
if (!file_exists($srcPath)) {
    echo "Source image not found at $srcPath\n";
    exit(1);
}

$img = imagecreatefromjpeg($srcPath);
$w = imagesx($img);
$h = imagesy($img);

// Create transparent PNG with emblem (monochrome golden/white on alpha)
$out = imagecreatetruecolor($w, $h);
imagealphablending($out, false);
imagesavealpha($out, true);
$transparent = imagecolorallocatealpha($out, 0, 0, 0, 127);
imagefilledrectangle($out, 0, 0, $w, $h, $transparent);

for ($x = 0; $x < $w; $x++) {
    for ($y = 0; $y < $h; $y++) {
        $rgb = imagecolorat($img, $x, $y);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        $lum = ($r + $g + $b) / 3;
        
        if ($lum > 35) {
            $alpha = 0;
            if ($lum < 75) {
                $alpha = (int)(127 - (($lum - 35) / 40.0) * 127);
            }
            $col = imagecolorallocatealpha($out, $r, $g, $b, $alpha);
            imagesetpixel($out, $x, $y, $col);
        }
    }
}

// Resize to standard watermark emblem dimension (256x256)
$emblemSize = 256;
$scaled = imagecreatetruecolor($emblemSize, $emblemSize);
imagealphablending($scaled, false);
imagesavealpha($scaled, true);
imagefilledrectangle($scaled, 0, 0, $emblemSize, $emblemSize, $transparent);
imagecopyresampled($scaled, $out, 0, 0, 0, 0, $emblemSize, $emblemSize, $w, $h);

$dest1 = __DIR__ . '/../assets/watermark_logo.png';
$dest2 = __DIR__ . '/../android/app/src/main/res/drawable/watermark_logo.png';
$dest3 = __DIR__ . '/../preview/watermark_logo.png';

imagepng($scaled, $dest1);
imagepng($scaled, $dest2);
imagepng($scaled, $dest3);

echo "SUCCESS: Saved watermark emblem logo to:\n";
echo "1. $dest1 (" . filesize($dest1) . " bytes)\n";
echo "2. $dest2 (" . filesize($dest2) . " bytes)\n";
echo "3. $dest3 (" . filesize($dest3) . " bytes)\n";
