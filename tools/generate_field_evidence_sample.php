<?php

$fontRegular = 'C:/Windows/Fonts/segoeui.ttf';
$fontBold = 'C:/Windows/Fonts/segoeuib.ttf';
if (!file_exists($fontRegular)) $fontRegular = 'C:/Windows/Fonts/arial.ttf';
if (!file_exists($fontBold)) $fontBold = 'C:/Windows/Fonts/arialbd.ttf';

$outDir = __DIR__ . '/../docs/screenshots';
$W = 800;
$H = 600;

$im = imagecreatetruecolor($W, $H);
imageantialias($im, true);

// Create realistic construction field background
$cSky = imagecolorallocate($im, 120, 160, 200);
$cGround = imagecolorallocate($im, 80, 80, 85);
$cRoad = imagecolorallocate($im, 45, 45, 48);
$cWhite = imagecolorallocate($im, 255, 255, 255);
$cYellow = imagecolorallocate($im, 240, 180, 40);

// Sky & Horizon
imagefilledrectangle($im, 0, 0, $W, 180, $cSky);
// Ground / subgrade
imagefilledrectangle($im, 0, 180, $W, 260, $cGround);
// Fresh asphalt / road surface
imagefilledrectangle($im, 0, 260, $W, $H, $cRoad);

// Road marking lines
for ($x = 40; $x < $W; $x += 120) {
    imagefilledrectangle($im, $x, 420, $x + 60, 432, $cYellow);
}

// Draw realistic field audit survey pole / milestone marker
imagefilledrectangle($im, 560, 190, 580, 360, $cWhite);
imagefilledrectangle($im, 560, 190, 580, 230, imagecolorallocate($im, 220, 50, 40));

// BURN IN MANDATORY WATERMARK (TASK 4: Government of India / MoSJE emblem logo):
// 1. Bottom-Right: Government emblem logo at ~40% opacity (translucent, burned into pixels)
$logoPath = __DIR__ . '/../assets/watermark_logo.png';
if (file_exists($logoPath)) {
    $logo = imagecreatefrompng($logoPath);
    if ($logo) {
        $logoW = imagesx($logo);
        $logoH = imagesy($logo);
        $targetW = 100;
        $targetH = (int)($logoH * ($targetW / $logoW));
        $destX = $W - $targetW - 30;
        $destY = $H - $targetH - 30;

        $scaled = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        $trans = imagecolorallocatealpha($scaled, 0, 0, 0, 127);
        imagefilledrectangle($scaled, 0, 0, $targetW, $targetH, $trans);
        imagecopyresampled($scaled, $logo, 0, 0, 0, 0, $targetW, $targetH, $logoW, $logoH);
        imagedestroy($logo);

        imagealphablending($im, true);
        $opacity = 0.40; // 40% opacity
        for ($lx = 0; $lx < $targetW; $lx++) {
            for ($ly = 0; $ly < $targetH; $ly++) {
                $rgba = imagecolorat($scaled, $lx, $ly);
                $a = ($rgba >> 24) & 0x7F;
                if ($a < 127) {
                    $r = ($rgba >> 16) & 0xFF;
                    $g = ($rgba >> 8) & 0xFF;
                    $b = $rgba & 0xFF;
                    $newAlpha = (int)(127 - (127 - $a) * $opacity);
                    $col = imagecolorallocatealpha($im, $r, $g, $b, $newAlpha);
                    imagesetpixel($im, $destX + $lx, $destY + $ly, $col);
                }
            }
        }
        imagedestroy($scaled);
    }
}

// 2. Bottom-Left: Coordinates, Accuracy and Timestamp
$coordLine = "LAT: 21.145832° N | LNG: 79.088214° E (±3.4m)";
$timeLine = "TIME: 2026-09-24 20:28:15 IST [DEVICE TIME]";
$cTelemShadow = imagecolorallocatealpha($im, 0, 0, 0, 40);
$cTelemText = imagecolorallocatealpha($im, 255, 255, 255, 30);

imagettftext($im, 11, 0, 26, $H - 53, $cTelemShadow, $fontBold, $coordLine);
imagettftext($im, 11, 0, 25, $H - 55, $cTelemText, $fontBold, $coordLine);

imagettftext($im, 11, 0, 26, $H - 33, $cTelemShadow, $fontBold, $timeLine);
imagettftext($im, 11, 0, 25, $H - 35, $cTelemText, $fontBold, $timeLine);

// Save compressed JPEG (85% quality)
$outPath = $outDir . '/sample_captured_evidence.jpg';
imagejpeg($im, $outPath, 85);
imagedestroy($im);

echo "Successfully generated watermarked field evidence: {$outPath}\n";
