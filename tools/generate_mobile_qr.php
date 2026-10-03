<?php
// Generates a high-resolution QR code image for scanning on mobile phones

$targetUrl = 'https://smart-inspection-mosje.onrender.com/preview';
$qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=350x350&margin=15&format=png&data=' . urlencode($targetUrl);

$outDir = __DIR__ . '/../docs/screenshots';
if (!is_dir($outDir)) mkdir($outDir, 0777, true);
$outFile = $outDir . '/mobile_qr_code.png';

$qrData = file_get_contents($qrApiUrl);
if ($qrData) {
    file_put_contents($outFile, $qrData);
    echo "[SUCCESS] Saved mobile QR code to $outFile\n";
} else {
    echo "[WARN] Could not download remote QR, creating local placeholder canvas\n";
    $im = imagecreatetruecolor(350, 350);
    $bg = imagecolorallocate($im, 255, 255, 255);
    $text = imagecolorallocate($im, 17, 11, 10);
    imagefilledrectangle($im, 0, 0, 350, 350, $bg);
    imagestring($im, 5, 20, 160, $targetUrl, $text);
    imagepng($im, $outFile);
    imagedestroy($im);
}
