<?php

$fontRegular = 'C:/Windows/Fonts/segoeui.ttf';
$fontBold = 'C:/Windows/Fonts/segoeuib.ttf';

if (!file_exists($fontRegular)) $fontRegular = 'C:/Windows/Fonts/arial.ttf';
if (!file_exists($fontBold)) $fontBold = 'C:/Windows/Fonts/arialbd.ttf';

$outDir = __DIR__ . '/../docs/screenshots';
if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}

function hexColor($im, $hex, $alpha = 0) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return imagecolorallocatealpha($im, $r, $g, $b, (int)$alpha);
}

function drawRoundedRect($im, $x1, $y1, $x2, $y2, $radius, $color) {
    $x1 = (int)$x1; $y1 = (int)$y1; $x2 = (int)$x2; $y2 = (int)$y2; $radius = (int)$radius;
    imagefilledrectangle($im, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
    imagefilledrectangle($im, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
    imagefilledellipse($im, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($im, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($im, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($im, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
}

function initCanvas() {
    $width = 390;
    $height = 844;
    $im = imagecreatetruecolor($width, $height);
    imageantialias($im, true);

    $bg = hexColor($im, '#F5F5F5');
    imagefilledrectangle($im, 0, 0, $width, $height, $bg);

    return $im;
}

function drawTopHeader($im, $title, $subtitle, $fontBold, $fontRegular) {
    // Orange header banner
    $primary = hexColor($im, '#F76C45');
    imagefilledrectangle($im, 0, 0, 390, 95, $primary);

    // Status bar
    $white = hexColor($im, '#FFFFFF');
    imagettftext($im, 10, 0, 24, 24, $white, $fontBold, '09:41');
    imagefilledellipse($im, 195, 20, 75, 18, hexColor($im, '#000000'));
    imagettftext($im, 10, 0, 320, 24, $white, $fontBold, '5G 100%');

    // Title & subtitle
    imagettftext($im, 14, 0, 20, 60, $white, $fontBold, $title);
    imagettftext($im, 10, 0, 20, 80, hexColor($im, '#FDE8E2'), $fontRegular, $subtitle);
}

// -------------------------------------------------------------
// SCREENSHOT 1: Officer Tender Detail (officer_tender_detail.png)
// -------------------------------------------------------------
$im1 = initCanvas();
drawTopHeader($im1, 'Tender: TND-2026-RD-01', 'Nagpur Ring Road Phase-3 Strengthening', $fontBold, $fontRegular);

// Overview Card
drawRoundedRect($im1, 16, 110, 374, 280, 12, hexColor($im1, '#FFFFFF'));
imagettftext($im1, 10, 0, 30, 134, hexColor($im1, '#F76C45'), $fontBold, 'ROADS • PWD MAHARASHTRA');
imagettftext($im1, 12, 0, 30, 156, hexColor($im1, '#110B0A'), $fontBold, 'Sanctioned: ₹4.50 Cr  |  Spent: ₹11.58 Cr');

// Variance Alert Ribbon
drawRoundedRect($im1, 30, 170, 360, 205, 8, hexColor($im1, '#FDE8E2'));
imagettftext($im1, 10, 0, 42, 192, hexColor($im1, '#D32F2F'), $fontBold, '[!] CRITICAL VARIANCE ALERT: +187.33%');

// Progress Bar
imagettftext($im1, 10, 0, 30, 226, hexColor($im1, '#6B6564'), $fontRegular, 'Actual Progress: 70% (Target: 75%)');
drawRoundedRect($im1, 30, 235, 360, 247, 6, hexColor($im1, '#EAE6E5'));
drawRoundedRect($im1, 30, 235, 261, 247, 6, hexColor($im1, '#F76C45')); // 70% width
imagettftext($im1, 9, 0, 30, 268, hexColor($im1, '#2E7D32'), $fontBold, 'Contractor: Larsen & Infra Projects Ltd');

// Milestones Section
imagettftext($im1, 11, 0, 20, 305, hexColor($im1, '#110B0A'), $fontBold, 'Milestone Progress Schedule');
$milestones = [
    ['1. Earthwork & Embankment', 'Weight 25% • 100% Done', '#2E7D32'],
    ['2. Granular Sub-base (WMM)', 'Weight 30% • 66.7% Done', '#ED6C02'],
    ['3. Dense Bituminous Layer', 'Weight 35% • 0% Done', '#6B6564'],
    ['4. Road Markings & Drainage', 'Weight 10% • 0% Done', '#6B6564'],
];

$my = 318;
foreach ($milestones as $m) {
    drawRoundedRect($im1, 16, $my, 374, $my + 44, 10, hexColor($im1, '#FFFFFF'));
    imagettftext($im1, 10, 0, 30, $my + 22, hexColor($im1, '#110B0A'), $fontBold, $m[0]);
    imagettftext($im1, 9, 0, 30, $my + 37, hexColor($im1, $m[2]), $fontRegular, $m[1]);
    $my += 52;
}

// Budget vs Actual Decision Card
$by = $my + 6;
imagettftext($im1, 11, 0, 20, $by + 16, hexColor($im1, '#110B0A'), $fontBold, 'Expenditure Verification (Accept/Dispute)');
$by += 26;
drawRoundedRect($im1, 16, $by, 374, $by + 95, 12, hexColor($im1, '#FFFFFF'));
imagettftext($im1, 10, 0, 30, $by + 24, hexColor($im1, '#110B0A'), $fontBold, 'MB-42 Page 18: Rs. 15,00,000.00');
imagettftext($im1, 9, 0, 30, $by + 40, hexColor($im1, '#D32F2F'), $fontRegular, 'Status: DISPUTED (Lab cube report missing)');

// Action buttons (Accept & Dispute)
drawRoundedRect($im1, 30, $by + 52, 185, $by + 82, 6, hexColor($im1, '#2E7D32'));
imagettftext($im1, 10, 0, 85, $by + 71, hexColor($im1, '#FFFFFF'), $fontBold, 'Accept');

drawRoundedRect($im1, 205, $by + 52, 360, $by + 82, 6, hexColor($im1, '#D32F2F'));
imagettftext($im1, 10, 0, 260, $by + 71, hexColor($im1, '#FFFFFF'), $fontBold, 'Dispute');

// Bottom Action Bar: Export PDF & Export CSV
drawRoundedRect($im1, 16, 765, 190, 805, 8, hexColor($im1, '#F76C45'));
imagettftext($im1, 10, 0, 48, 790, hexColor($im1, '#FFFFFF'), $fontBold, 'Download PDF');

drawRoundedRect($im1, 200, 765, 374, 805, 8, hexColor($im1, '#110B0A'));
imagettftext($im1, 10, 0, 250, 790, hexColor($im1, '#FFFFFF'), $fontBold, 'Export CSV');

imagepng($im1, "{$outDir}/officer_tender_detail.png");
imagedestroy($im1);
echo " -> Rendered officer_tender_detail.png\n";

// -------------------------------------------------------------
// SCREENSHOT 2: Officer Inspection Review (officer_inspection_review.png)
// -------------------------------------------------------------
$im2 = initCanvas();
drawTopHeader($im2, 'Inspection Verification', 'Inspection #62 • Tender TND-2026-RD-01', $fontBold, $fontRegular);

// Evidence Media Preview Card with Burned Watermark
drawRoundedRect($im2, 16, 110, 374, 380, 12, hexColor($im2, '#110B0A'));

// Visual simulated photo area
drawRoundedRect($im2, 20, 114, 370, 376, 10, hexColor($im2, '#2C3E50'));
imagettftext($im2, 12, 0, 110, 220, hexColor($im2, '#95A5A6'), $fontBold, '[CameraX Live Field Evidence]');
imagettftext($im2, 9, 0, 85, 240, hexColor($im2, '#BDC3C7'), $fontRegular, 'Subgrade layer compaction & alignment');

// Burned watermark bottom-right
imagettftext($im2, 11, 0, 180, 360, hexColor($im2, '#FFFFFF', 70), $fontBold, 'for the people to the people');

// Telemetry overlay bottom-left
imagettftext($im2, 8, 0, 28, 350, hexColor($im2, '#FFFFFF', 40), $fontRegular, '2026-09-24 20:30:15 IST');
imagettftext($im2, 8, 0, 28, 365, hexColor($im2, '#FFFFFF', 40), $fontRegular, 'LAT: 21.1458 N, LNG: 79.0882 E (+/-4.2m)');

// Metadata Ribbon
drawRoundedRect($im2, 16, 395, 374, 465, 10, hexColor($im2, '#FFFFFF'));
imagettftext($im2, 10, 0, 28, 418, hexColor($im2, '#110B0A'), $fontBold, 'Inspector: Rajesh M. (ID: 4)  |  Status: SUBMITTED');
imagettftext($im2, 9, 0, 28, 436, hexColor($im2, '#2E7D32'), $fontBold, 'Time Mismatch: NO (Synced within tolerance)');
imagettftext($im2, 9, 0, 28, 452, hexColor($im2, '#6B6564'), $fontRegular, 'Device Time: 20:30:15 | Server Time: 20:30:16');

// Quality Checklist Card
imagettftext($im2, 11, 0, 20, 490, hexColor($im2, '#110B0A'), $fontBold, 'Quality Checklist Verification');
$qcs = [
    ['Subgrade Soil Compaction Test', 'PASS', '#2E7D32'],
    ['Bitumen Mix Temperature Check', 'PASS', '#2E7D32'],
    ['Side Drainage Alignment Gradient', 'PASS', '#2E7D32'],
];

$qy = 502;
foreach ($qcs as $q) {
    drawRoundedRect($im2, 16, $qy, 374, $qy + 38, 8, hexColor($im2, '#FFFFFF'));
    imagettftext($im2, 9, 0, 28, $qy + 23, hexColor($im2, '#110B0A'), $fontBold, $q[0]);
    drawRoundedRect($im2, 310, $qy + 8, 360, $qy + 30, 4, hexColor($im2, '#E8F5E9'));
    imagettftext($im2, 9, 0, 320, $qy + 23, hexColor($im2, $q[2]), $fontBold, $q[1]);
    $qy += 44;
}

// Verification Action Buttons
$ay = $qy + 15;
drawRoundedRect($im2, 16, $ay, 190, $ay + 42, 8, hexColor($im2, '#2E7D32'));
imagettftext($im2, 10, 0, 40, $ay + 26, hexColor($im2, '#FFFFFF'), $fontBold, 'Verify & Approve');

drawRoundedRect($im2, 200, $ay, 374, $ay + 42, 8, hexColor($im2, '#D32F2F'));
imagettftext($im2, 10, 0, 245, $ay + 26, hexColor($im2, '#FFFFFF'), $fontBold, 'Raise Issue');

$ay += 50;
drawRoundedRect($im2, 16, $ay, 374, $ay + 42, 8, hexColor($im2, '#F76C45'));
imagettftext($im2, 10, 0, 115, $ay + 26, hexColor($im2, '#FFFFFF'), $fontBold, 'Schedule Re-inspection');

imagepng($im2, "{$outDir}/officer_inspection_review.png");
imagedestroy($im2);
echo " -> Rendered officer_inspection_review.png\n";

// -------------------------------------------------------------
// SCREENSHOT 3: Officer Analytics Dashboard (officer_dashboard.png)
// -------------------------------------------------------------
$im3 = initCanvas();
drawTopHeader($im3, 'MoSJE Executive Dashboard', 'Smart Real-Time Monitoring & Inspection', $fontBold, $fontRegular);

// 4 Metric KPI Cards
$metrics = [
    ['Active Tenders', '8', '#F76C45', 16, 110],
    ['Variance Alerts', '11', '#D32F2F', 200, 110],
    ['Delayed Tenders', '1', '#ED6C02', 16, 185],
    ['Overdue Re-insp', '0', '#2E7D32', 200, 185],
];

foreach ($metrics as $m) {
    drawRoundedRect($im3, $m[3], $m[4], $m[3] + 174, $m[4] + 65, 10, hexColor($im3, '#FFFFFF'));
    imagettftext($im3, 18, 0, $m[3] + 18, $m[4] + 36, hexColor($im3, $m[2]), $fontBold, $m[1]);
    imagettftext($im3, 9, 0, $m[3] + 18, $m[4] + 52, hexColor($im3, '#6B6564'), $fontRegular, $m[0]);
}

// District Coverage Table
imagettftext($im3, 11, 0, 20, 280, hexColor($im3, '#110B0A'), $fontBold, 'Jurisdiction & District Coverage');
drawRoundedRect($im3, 16, 292, 374, 520, 12, hexColor($im3, '#FFFFFF'));

$districts = [
    ['Nagpur (MH)', '12 Tenders', '70.0% Avg Prog', 'Rs 45.0 Cr'],
    ['Pune (MH)', '8 Tenders', '55.5% Avg Prog', 'Rs 38.5 Cr'],
    ['Lucknow (UP)', '4 Tenders', '40.0% Avg Prog', 'Rs 28.0 Cr'],
    ['Varanasi (UP)', '3 Tenders', '35.0% Avg Prog', 'Rs 22.5 Cr'],
];

$dy = 308;
foreach ($districts as $d) {
    imagettftext($im3, 10, 0, 26, $dy + 20, hexColor($im3, '#110B0A'), $fontBold, $d[0]);
    imagettftext($im3, 8, 0, 130, $dy + 20, hexColor($im3, '#6B6564'), $fontRegular, $d[1]);
    imagettftext($im3, 8, 0, 205, $dy + 20, hexColor($im3, '#2E7D32'), $fontBold, $d[2]);
    imagettftext($im3, 8, 0, 290, $dy + 20, hexColor($im3, '#F76C45'), $fontBold, $d[3]);
    imageline($im3, 26, $dy + 38, 364, $dy + 38, hexColor($im3, '#F5F5F5'));
    $dy += 48;
}

// Quick Actions & Reports Download
imagettftext($im3, 11, 0, 20, 550, hexColor($im3, '#110B0A'), $fontBold, 'Report Downloads & Exports');

drawRoundedRect($im3, 16, 565, 374, 625, 10, hexColor($im3, '#FFFFFF'));
imagettftext($im3, 10, 0, 30, 592, hexColor($im3, '#110B0A'), $fontBold, 'Inspection Technical Audit Report (PDF)');
imagettftext($im3, 8, 0, 30, 608, hexColor($im3, '#6B6564'), $fontRegular, 'Complete telemetry, evidence stills & signoff log');
drawRoundedRect($im3, 300, 577, 360, 612, 6, hexColor($im3, '#F76C45'));
imagettftext($im3, 9, 0, 316, 599, hexColor($im3, '#FFFFFF'), $fontBold, 'PDF');

drawRoundedRect($im3, 16, 640, 374, 700, 10, hexColor($im3, '#FFFFFF'));
imagettftext($im3, 10, 0, 30, 667, hexColor($im3, '#110B0A'), $fontBold, 'All Tenders Master Data (CSV)');
imagettftext($im3, 8, 0, 30, 683, hexColor($im3, '#6B6564'), $fontRegular, 'Milestones, budgets, contractor details & variance');
drawRoundedRect($im3, 300, 652, 360, 687, 6, hexColor($im3, '#110B0A'));
imagettftext($im3, 9, 0, 316, 674, hexColor($im3, '#FFFFFF'), $fontBold, 'CSV');

imagepng($im3, "{$outDir}/officer_dashboard.png");
imagedestroy($im3);
echo " -> Rendered officer_dashboard.png\n";

// -------------------------------------------------------------
// SCREENSHOT 4: MoSJE Admin Management (admin_management.png)
// -------------------------------------------------------------
$im4 = initCanvas();
drawTopHeader($im4, 'MoSJE National Admin Console', 'User Roles, Checklists & Financial Summary', $fontBold, $fontRegular);

// Financial Ledger KPI Ribbon
drawRoundedRect($im4, 16, 110, 374, 195, 12, hexColor($im4, '#110B0A'));
imagettftext($im4, 10, 0, 28, 134, hexColor($im4, '#F76C45'), $fontBold, 'ESCALATION FEES & REFUNDS LEDGER');
imagettftext($im4, 9, 0, 28, 154, hexColor($im4, '#FFFFFF'), $fontRegular, 'Fees Collected: ₹5,200  |  GST (18%): ₹936');
imagettftext($im4, 9, 0, 28, 172, hexColor($im4, '#2E7D32'), $fontBold, 'Refunds on Upheld: ₹2,832  |  Net Retained: ₹3,304');
imagettftext($im4, 8, 0, 28, 188, hexColor($im4, '#A09A98'), $fontRegular, 'Statutory Fee: ₹400 base + 18% GST (₹72) = ₹472/txn');

// Staff Management Card
imagettftext($im4, 11, 0, 20, 225, hexColor($im4, '#110B0A'), $fontBold, 'Staff Accounts Directory');
$staffList = [
    ['Rajesh M.', 'inspector.rajesh@mosje.gov.in', 'INSPECTOR', 'ACTIVE'],
    ['Virendra Deshmukh', 'district.nagpur@mosje.gov.in', 'DISTRICT_OFFICER', 'ACTIVE'],
    ['Dr. Arvind Patil', 'state.mh@mosje.gov.in', 'STATE_OFFICER', 'ACTIVE'],
    ['Kiran Deshpande', 'staff_inactive@mosje.gov.in', 'INSPECTOR', 'INACTIVE'],
];

$sy = 238;
foreach ($staffList as $s) {
    drawRoundedRect($im4, 16, $sy, 374, $sy + 48, 8, hexColor($im4, '#FFFFFF'));
    imagettftext($im4, 10, 0, 28, $sy + 20, hexColor($im4, '#110B0A'), $fontBold, $s[0]);
    imagettftext($im4, 8, 0, 28, $sy + 36, hexColor($im4, '#6B6564'), $fontRegular, $s[1]);

    $statusColor = ($s[3] === 'ACTIVE') ? '#2E7D32' : '#D32F2F';
    drawRoundedRect($im4, 290, $sy + 12, 360, $sy + 36, 4, hexColor($im4, '#F5F5F5'));
    imagettftext($im4, 8, 0, 300, $sy + 28, hexColor($im4, $statusColor), $fontBold, $s[3]);
    $sy += 56;
}

// Checklist Templates Card
imagettftext($im4, 11, 0, 20, $sy + 15, hexColor($im4, '#110B0A'), $fontBold, 'Quality Checklist Rules Engine');
$sy += 25;
drawRoundedRect($im4, 16, $sy, 374, $sy + 70, 10, hexColor($im4, '#FFFFFF'));
imagettftext($im4, 10, 0, 28, $sy + 24, hexColor($im4, '#110B0A'), $fontBold, '6 Categories  |  20 Inspection Checklists');
imagettftext($im4, 8, 0, 28, $sy + 42, hexColor($im4, '#6B6564'), $fontRegular, 'Roads, Flyovers, Buildings, Water Supply, Welfare, Other');
imagettftext($im4, 9, 0, 28, $sy + 60, hexColor($im4, '#D32F2F'), $fontBold, 'Critical checks trigger automated issues upon failure');

// Audit Log Viewer
$sy += 85;
imagettftext($im4, 11, 0, 20, $sy + 15, hexColor($im4, '#110B0A'), $fontBold, 'Immutable Audit Log Stream');
$sy += 25;
drawRoundedRect($im4, 16, $sy, 374, $sy + 75, 10, hexColor($im4, '#FFFFFF'));
imagettftext($im4, 9, 0, 28, $sy + 22, hexColor($im4, '#110B0A'), $fontBold, 'INSPECTION_STATE_TRANSITION • SUBMITTED -> VERIFIED');
imagettftext($im4, 8, 0, 28, $sy + 38, hexColor($im4, '#6B6564'), $fontRegular, 'User: District Officer Nagpur (ID: 3) | 2026-09-24 20:32');
imagettftext($im4, 8, 0, 28, $sy + 54, hexColor($im4, '#2E7D32'), $fontRegular, 'Hash-verified PDO prepared statement entry');

imagepng($im4, "{$outDir}/admin_management.png");
imagedestroy($im4);
echo " -> Rendered admin_management.png\n";

echo "ALL PART 7 SCREENSHOTS GENERATED CLEANLY!\n";
