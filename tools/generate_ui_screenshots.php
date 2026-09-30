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

function drawCameraIcon($im, $cx, $cy, $color) {
    // Body
    imagefilledrectangle($im, $cx - 10, $cy - 6, $cx + 10, $cy + 8, $color);
    // Top flash bump
    imagefilledrectangle($im, $cx - 4, $cy - 9, $cx + 4, $cy - 6, $color);
    // Lens
    imagefilledellipse($im, $cx, $cy + 1, 8, 8, hexColor($im, '#FFFFFF'));
    imagefilledellipse($im, $cx, $cy + 1, 4, 4, $color);
}

function drawHomeIcon($im, $cx, $cy, $color) {
    imagefilledrectangle($im, $cx - 6, $cy, $cx + 6, $cy + 7, $color);
    imagefilledpolygon($im, [$cx, $cy - 7, $cx - 8, $cy, $cx + 8, $cy], $color);
}

function drawTenderIcon($im, $cx, $cy, $color) {
    imagefilledrectangle($im, $cx - 6, $cy - 7, $cx + 6, $cy + 7, $color);
    $cW = hexColor($im, '#FFFFFF');
    imageline($im, $cx - 4, $cy - 3, $cx + 4, $cy - 3, $cW);
    imageline($im, $cx - 4, $cy, $cx + 4, $cy, $cW);
    imageline($im, $cx - 4, $cy + 3, $cx + 2, $cy + 3, $cW);
}

function drawAlertIcon($im, $cx, $cy, $color) {
    imagefilledellipse($im, $cx, $cy + 2, 10, 8, $color);
    imagefilledellipse($im, $cx, $cy - 3, 6, 6, $color);
    imagefilledrectangle($im, $cx - 1, $cy + 6, $cx + 1, $cy + 8, $color);
}

function drawProfileIcon($im, $cx, $cy, $color) {
    imagefilledellipse($im, $cx, $cy - 3, 8, 8, $color);
    imagefilledellipse($im, $cx, $cy + 6, 14, 8, $color);
}

$roles = [
    'role_inspector_home.png' => [
        'name' => 'Rajesh M.',
        'roleTitle' => 'Field Inspector • Nagpur Zone',
        'hasCameraFab' => true,
        'shortcuts' => [
            ['My Tenders', 'Assigned field projects'],
            ['Field Camera', 'Burned watermark & GPS'],
            ['Quality Checks', 'MoSJE Category rules'],
            ['Offline Queue', '0 pending Room syncs'],
        ],
        'tenders' => [
            ['TND-MH-NGP-2026-001', 'Ring Road Phase-3 Strengthening', 'ROADS', 'IN_PROGRESS', 70, true],
            ['TND-MH-NGP-2026-003', 'Dr. Ambedkar Model Residential School', 'BUILDING', 'AWARDED', 25, false],
        ]
    ],
    'role_district_officer_home.png' => [
        'name' => 'Virendra Deshmukh',
        'roleTitle' => 'District Officer • Nagpur',
        'hasCameraFab' => false,
        'shortcuts' => [
            ['District Tenders', 'Jurisdiction milestones'],
            ['Verify Proofs', 'Review field evidence'],
            ['NGO Approvals', 'Verify registrations'],
            ['CSV Import', 'Upload tender sheets'],
        ],
        'tenders' => [
            ['TND-MH-NGP-2026-001', 'Ring Road Phase-3 Strengthening', 'ROADS', 'IN_PROGRESS', 70, true],
            ['TND-MH-NGP-2026-004', 'Kanhan River Bulk Water Augmentation', 'WATER SUPPLY', 'IN_PROGRESS', 40, false],
        ]
    ],
    'role_state_officer_home.png' => [
        'name' => 'Dr. Arvind Patil',
        'roleTitle' => 'State Officer • Maharashtra',
        'hasCameraFab' => false,
        'shortcuts' => [
            ['State Tenders', 'Statewide project oversight'],
            ['Variance Flags', 'Expenditure vs progress'],
            ['Delayed Works', 'Overdue project alerts'],
            ['Export Reports', 'Download PDF & CSV logs'],
        ],
        'tenders' => [
            ['TND-MH-PUN-2026-002', 'Pune Metro Link Elevated Corridor', 'BRIDGES', 'DELAYED', 35, true],
            ['TND-MH-NGP-2026-001', 'Ring Road Phase-3 Strengthening', 'ROADS', 'IN_PROGRESS', 70, true],
        ]
    ],
    'role_mosje_admin_home.png' => [
        'name' => 'MoSJE Administrator',
        'roleTitle' => 'National Administrator • New Delhi',
        'hasCameraFab' => false,
        'shortcuts' => [
            ['All Tenders', 'National tender repository'],
            ['Staff Directory', 'Create & manage officials'],
            ['Checklist Engine', 'Category quality rules'],
            ['Audit & Fees', 'Immutable audit & refunds'],
        ],
        'tenders' => [
            ['TND-UP-LKO-2026-005', 'Shaheed Path Surface Re-carpeting', 'ROADS', 'IN_PROGRESS', 55, false],
            ['TND-MH-PUN-2026-002', 'Pune Metro Link Elevated Corridor', 'BRIDGES', 'DELAYED', 35, true],
        ]
    ],
    'role_ngo_home.png' => [
        'name' => 'Sewa Bharati Trust',
        'roleTitle' => 'NGO Social Auditor • Nagpur',
        'hasCameraFab' => false,
        'shortcuts' => [
            ['Attached Works', 'Monitored public tenders'],
            ['Raise Complaint', 'Report contractor issues'],
            ['Escalations & Fee', 'Track ₹472 fees & refunds'],
            ['Public Nudges', 'Citizen alerts received'],
        ],
        'tenders' => [
            ['TND-MH-NGP-2026-001', 'Ring Road Phase-3 Strengthening', 'ROADS', 'IN_PROGRESS', 70, true],
            ['TND-MH-NGP-2026-003', 'Dr. Ambedkar Model Residential School', 'BUILDING', 'AWARDED', 25, false],
        ]
    ],
    'role_public_home.png' => [
        'name' => 'Citizen Transparency Portal',
        'roleTitle' => 'Public Access • MoSJE Transparency',
        'hasCameraFab' => false,
        'shortcuts' => [
            ['Tender Explorer', 'Public works & progress'],
            ['Budget & Spent', 'Milestone expenditure'],
            ['Nudge NGO', 'Prompt social monitors (1/day)'],
            ['Public Outcomes', 'Completed project audits'],
        ],
        'tenders' => [
            ['TND-MH-NGP-2026-001', 'Ring Road Phase-3 Strengthening', 'ROADS', 'IN_PROGRESS', 70, true],
            ['TND-MH-PUN-2026-002', 'Pune Metro Link Elevated Corridor', 'BRIDGES', 'DELAYED', 35, true],
        ]
    ],
];

foreach ($roles as $filename => $data) {
    $W = 390;
    $H = 844;
    $im = imagecreatetruecolor($W, $H);
    imageantialias($im, true);

    // Palette
    $cBg = hexColor($im, '#F5F5F5');
    $cWhite = hexColor($im, '#FFFFFF');
    $cPrimary = hexColor($im, '#F76C45');
    $cPrimaryLight = hexColor($im, '#FDE8E2');
    $cTextPrimary = hexColor($im, '#110B0A');
    $cTextSec = hexColor($im, '#6B6564');
    $cTextTert = hexColor($im, '#A09A98');
    $cDivider = hexColor($im, '#EAE6E5');
    $cDanger = hexColor($im, '#D32F2F');
    $cWarning = hexColor($im, '#ED6C02');
    $cSuccess = hexColor($im, '#2E7D32');

    // Fill screen background
    imagefilledrectangle($im, 0, 0, $W, $H, $cBg);

    // 1. Status Bar
    imagefilledrectangle($im, 0, 0, $W, 40, $cPrimary);
    imagettftext($im, 10, 0, 20, 26, $cWhite, $fontBold, '09:41');
    drawRoundedRect($im, (int)($W/2 - 45), 10, (int)($W/2 + 45), 30, 8, hexColor($im, '#000000'));
    imagettftext($im, 9, 0, $W - 65, 26, $cWhite, $fontBold, '5G 100%');

    // 2. Orange App Header
    $headerBottom = 175;
    imagefilledrectangle($im, 0, 40, $W, $headerBottom, $cPrimary);
    imagettftext($im, 8, 0, 18, 62, $cPrimaryLight, $fontBold, 'MOSJE • PM-AJAY & INFRA MONITOR');
    imagettftext($im, 15, 0, 18, 92, $cWhite, $fontBold, 'Namaste, ' . $data['name']);
    
    // Role Badge
    $badgeWidth = (int)(strlen($data['roleTitle']) * 6.5 + 16);
    drawRoundedRect($im, 18, 102, 18 + $badgeWidth, 122, 10, hexColor($im, '#FFFFFF', 100));
    imagettftext($im, 8, 0, 26, 116, $cWhite, $fontBold, $data['roleTitle']);

    // Search bar
    drawRoundedRect($im, 18, 130, $W - 18, 168, 8, $cWhite);
    imagefilledellipse($im, 32, 149, 8, 8, hexColor($im, '#FFFFFF'));
    imageellipse($im, 32, 149, 8, 8, $cTextTert);
    imageline($im, 35, 152, 39, 156, $cTextTert);
    imagettftext($im, 9, 0, 46, 153, $cTextTert, $fontRegular, 'Search tender ID, department or contractor...');

    // 3. Stats Strip
    $yStats = 185;
    $statW = (int)(($W - 36 - 16) / 3);
    // Stat 1
    drawRoundedRect($im, 18, $yStats, 18 + $statW, $yStats + 55, 10, $cWhite);
    imagettftext($im, 13, 0, 18 + 36, $yStats + 28, $cPrimary, $fontBold, '8');
    imagettftext($im, 7.5, 0, 18 + 18, $yStats + 44, $cTextSec, $fontRegular, 'Active Tenders');

    // Stat 2
    $xS2 = 18 + $statW + 8;
    drawRoundedRect($im, $xS2, $yStats, $xS2 + $statW, $yStats + 55, 10, $cWhite);
    imagettftext($im, 13, 0, $xS2 + 36, $yStats + 28, $cDanger, $fontBold, '2');
    imagettftext($im, 7.5, 0, $xS2 + 16, $yStats + 44, $cTextSec, $fontRegular, 'Variance Alert');

    // Stat 3
    $xS3 = $xS2 + $statW + 8;
    drawRoundedRect($im, $xS3, $yStats, $xS3 + $statW, $yStats + 55, 10, $cWhite);
    imagettftext($im, 13, 0, $xS3 + 36, $yStats + 28, $cWarning, $fontBold, '1');
    imagettftext($im, 7.5, 0, $xS3 + 18, $yStats + 44, $cTextSec, $fontRegular, 'Delayed Work');

    // 4. Role Services & Actions Header
    $ySec = 260;
    imagettftext($im, 10, 0, 18, $ySec, $cTextPrimary, $fontBold, 'Role Services & Actions');

    // 4 Shortcuts Grid
    $gridY = 270;
    $cardW = (int)(($W - 36 - 10) / 2);
    $cardH = 82;
    $sc = $data['shortcuts'];

    // Row 1
    drawRoundedRect($im, 18, $gridY, 18 + $cardW, $gridY + $cardH, 10, $cWhite);
    imagefilledellipse($im, 36, $gridY + 22, 24, 24, $cPrimaryLight);
    imagefilledpolygon($im, [33, $gridY + 17, 33, $gridY + 27, 41, $gridY + 22], $cPrimary);
    imagettftext($im, 9, 0, 26, $gridY + 48, $cTextPrimary, $fontBold, $sc[0][0]);
    imagettftext($im, 7.5, 0, 26, $gridY + 64, $cTextSec, $fontRegular, $sc[0][1]);

    $xCol2 = 18 + $cardW + 10;
    drawRoundedRect($im, $xCol2, $gridY, $xCol2 + $cardW, $gridY + $cardH, 10, $cWhite);
    imagefilledellipse($im, $xCol2 + 18, $gridY + 22, 24, 24, $cPrimaryLight);
    imagefilledpolygon($im, [$xCol2 + 15, $gridY + 17, $xCol2 + 15, $gridY + 27, $xCol2 + 23, $gridY + 22], $cPrimary);
    imagettftext($im, 9, 0, $xCol2 + 10, $gridY + 48, $cTextPrimary, $fontBold, $sc[1][0]);
    imagettftext($im, 7.5, 0, $xCol2 + 10, $gridY + 64, $cTextSec, $fontRegular, $sc[1][1]);

    // Row 2
    $gridY2 = $gridY + $cardH + 10;
    drawRoundedRect($im, 18, $gridY2, 18 + $cardW, $gridY2 + $cardH, 10, $cWhite);
    imagefilledellipse($im, 36, $gridY2 + 22, 24, 24, $cPrimaryLight);
    imagefilledpolygon($im, [33, $gridY2 + 17, 33, $gridY2 + 27, 41, $gridY2 + 22], $cPrimary);
    imagettftext($im, 9, 0, 26, $gridY2 + 48, $cTextPrimary, $fontBold, $sc[2][0]);
    imagettftext($im, 7.5, 0, 26, $gridY2 + 64, $cTextSec, $fontRegular, $sc[2][1]);

    drawRoundedRect($im, $xCol2, $gridY2, $xCol2 + $cardW, $gridY2 + $cardH, 10, $cWhite);
    imagefilledellipse($im, $xCol2 + 18, $gridY2 + 22, 24, 24, $cPrimaryLight);
    imagefilledpolygon($im, [$xCol2 + 15, $gridY2 + 17, $xCol2 + 15, $gridY2 + 27, $xCol2 + 23, $gridY2 + 22], $cPrimary);
    imagettftext($im, 9, 0, $xCol2 + 10, $gridY2 + 48, $cTextPrimary, $fontBold, $sc[3][0]);
    imagettftext($im, 7.5, 0, $xCol2 + 10, $gridY2 + 64, $cTextSec, $fontRegular, $sc[3][1]);

    // 5. Active Tender Feed
    $yFeedHeader = $gridY2 + $cardH + 24;
    imagettftext($im, 10, 0, 18, $yFeedHeader, $cTextPrimary, $fontBold, 'Active Tender Feed');
    imagettftext($im, 8, 0, $W - 80, $yFeedHeader, $cPrimary, $fontBold, 'View All ->');

    $yT = $yFeedHeader + 10;
    foreach ($data['tenders'] as $t) {
        $tH = 88;
        drawRoundedRect($im, 18, $yT, $W - 18, $yT + $tH, 10, $cWhite);

        // Category & Status
        imagettftext($im, 7.5, 0, 28, $yT + 18, $cPrimary, $fontBold, $t[2]);

        $stCol = $t[3] === 'IN_PROGRESS' ? $cWarning : ($t[3] === 'DELAYED' ? $cDanger : $cSuccess);
        drawRoundedRect($im, $W - 100, $yT + 8, $W - 28, $yT + 22, 4, $stCol);
        imagettftext($im, 6.5, 0, $W - 94, $yT + 18, $cWhite, $fontBold, $t[3]);

        // Tender Number & Title
        imagettftext($im, 8, 0, 28, $yT + 34, $cTextSec, $fontRegular, $t[0]);
        imagettftext($im, 9, 0, 28, $yT + 50, $cTextPrimary, $fontBold, $t[1]);

        // Divider
        imageline($im, 28, $yT + 62, $W - 28, $yT + 62, $cDivider);

        // Progress & Variance
        imagettftext($im, 8, 0, 28, $yT + 77, $cPrimary, $fontBold, 'Progress: ' . $t[4] . '%');
        if ($t[5]) {
            drawRoundedRect($im, $W - 130, $yT + 67, $W - 28, $yT + 81, 4, $cDanger);
            imagettftext($im, 6.5, 0, $W - 124, $yT + 77, $cWhite, $fontBold, 'VARIANCE > 10%');
        }

        $yT += $tH + 10;
    }

    // 6. Bottom Navigation Bar
    $navY = $H - 64;
    imagefilledrectangle($im, 0, $navY, $W, $H, $cWhite);
    imageline($im, 0, $navY, $W, $navY, $cDivider);

    $navW = (int)($W / 4);
    // Tab 1: Home (Active)
    drawHomeIcon($im, 48, $navY + 22, $cPrimary);
    imagettftext($im, 7, 0, 38, $navY + 46, $cPrimary, $fontBold, 'Home');

    // Tab 2: Tenders
    drawTenderIcon($im, $navW + 48, $navY + 22, $cTextTert);
    imagettftext($im, 7, 0, $navW + 36, $navY + 46, $cTextTert, $fontBold, 'Tenders');

    // Tab 3: Alerts
    drawAlertIcon($im, ($navW * 2) + 48, $navY + 22, $cTextTert);
    imagettftext($im, 7, 0, ($navW * 2) + 38, $navY + 46, $cTextTert, $fontBold, 'Alerts');

    // Tab 4: Profile
    drawProfileIcon($im, ($navW * 3) + 48, $navY + 22, $cTextTert);
    imagettftext($im, 7, 0, ($navW * 3) + 36, $navY + 46, $cTextTert, $fontBold, 'Profile');

    // Inspector Centered Camera FAB
    if (!empty($data['hasCameraFab'])) {
        $fabX = (int)($W / 2);
        $fabY = $navY - 14;
        imagefilledellipse($im, $fabX, $fabY, 56, 56, $cPrimary);
        imageellipse($im, $fabX, $fabY, 58, 58, $cWhite);
        imageellipse($im, $fabX, $fabY, 60, 60, $cWhite);
        drawCameraIcon($im, $fabX, $fabY, $cWhite);
    }

    $savePath = $outDir . '/' . $filename;
    imagepng($im, $savePath);
    imagedestroy($im);
    echo "Generated clean screenshot: {$savePath}\n";
}

// 7. Generate Camera Watermark Preview Screenshot
$W = 390;
$H = 844;
$im = imagecreatetruecolor($W, $H);
imageantialias($im, true);

// Dark camera viewport
$cBlack = hexColor($im, '#000000');
$cDarkGray = hexColor($im, '#18181A');
$cWhite = hexColor($im, '#FFFFFF');
$cPrimary = hexColor($im, '#F76C45');
$cSuccess = hexColor($im, '#2E7D32');

imagefilledrectangle($im, 0, 0, $W, $H, $cDarkGray);

// Grid overlay
$cGrid = hexColor($im, '#FFFFFF', 100);
imageline($im, (int)($W/3), 0, (int)($W/3), $H - 120, $cGrid);
imageline($im, (int)(($W/3)*2), 0, (int)(($W/3)*2), $H - 120, $cGrid);
imageline($im, 0, (int)(($H - 120)/3), $W, (int)(($H - 120)/3), $cGrid);
imageline($im, 0, (int)((($H - 120)/3)*2), $W, (int)((($H - 120)/3)*2), $cGrid);

// Top GPS Banner
drawRoundedRect($im, 14, 50, $W - 14, 88, 8, hexColor($im, '#000000', 50));
imagefilledellipse($im, 32, 69, 10, 10, $cSuccess);
imagettftext($im, 9, 0, 46, 73, $cWhite, $fontBold, 'GPS Locked (+/- 3.8m accuracy)');
imagettftext($im, 8, 0, $W - 130, 73, hexColor($im, '#E0E0E0'), $fontRegular, 'CameraX Live Lens');

// Center Reticle
imagettftext($im, 24, 0, (int)($W/2 - 14), (int)(($H - 120)/2 + 10), hexColor($im, '#FFFFFF', 70), $fontRegular, '+');

// BOTTOM-LEFT: Date-Time and Live Coordinates
$telemStr = "2026-09-24 20:25:40 IST\nLAT: 21.1458 N, LNG: 79.0882 E\nALT: 310m | ACC: 3.8m (Mock: NO)";
imagettftext($im, 7.5, 0, 14, $H - 165, hexColor($im, '#000000', 40), $fontBold, $telemStr);
imagettftext($im, 7.5, 0, 13, $H - 166, hexColor($im, '#FFFFFF', 30), $fontBold, $telemStr);

// BOTTOM-RIGHT: Pixel Burned-in Translucent Watermark (40% opacity)
// Alpha in GD is 0 (opaque) to 127 (transparent). 40% opacity = ~76 alpha
$wmText = 'for the people to the people';
$cWmShadow = hexColor($im, '#000000', 60);
$cWmText = hexColor($im, '#FFFFFF', 76); // ~40% opacity
imagettftext($im, 9.5, 0, $W - 204, $H - 149, $cWmShadow, $fontBold, $wmText);
imagettftext($im, 9.5, 0, $W - 205, $H - 150, $cWmText, $fontBold, $wmText);

// Bottom Control Bar with Shutter
imagefilledrectangle($im, 0, $H - 120, $W, $H, $cBlack);
imagefilledellipse($im, (int)($W/2), $H - 60, 68, 68, $cWhite);
imagefilledellipse($im, (int)($W/2), $H - 60, 58, 58, $cPrimary);
imagefilledellipse($im, (int)($W/2), $H - 60, 50, 50, $cWhite);
imagettftext($im, 9, 0, 26, $H - 55, $cWhite, $fontBold, 'Cancel');

$camPath = $outDir . '/camera_watermark_preview.png';
imagepng($im, $camPath);
imagedestroy($im);
echo "Generated camera screenshot: {$camPath}\n";

echo "All clean UI screenshots generated successfully!\n";
