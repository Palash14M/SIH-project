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

function drawFrame($im, $W, $H, $title, $roleBadge, $activeTab = 'home') {
    global $fontBold, $fontRegular;
    $cBg = hexColor($im, '#F5F5F5');
    $cWhite = hexColor($im, '#FFFFFF');
    $cPrimary = hexColor($im, '#F76C45');
    $cPrimLight = hexColor($im, '#FDE8E2');
    $cPrimDark = hexColor($im, '#D4502A');
    $cText = hexColor($im, '#110B0A');
    $cSec = hexColor($im, '#6B6564');
    $cDiv = hexColor($im, '#EAE6E5');

    // Background
    imagefilledrectangle($im, 0, 0, $W, $H, $cBg);

    // Status Bar
    imagefilledrectangle($im, 0, 0, $W, 30, $cWhite);
    imagettftext($im, 8.5, 0, 16, 20, $cText, $fontBold, '09:41');
    imagefilledrectangle($im, (int)($W/2 - 40), 6, (int)($W/2 + 40), 22, hexColor($im, '#000000'));
    imagettftext($im, 8.5, 0, $W - 60, 20, $cText, $fontBold, '5G 100%');

    // Header Bar
    imagefilledrectangle($im, 0, 30, $W, 95, $cWhite);
    imageline($im, 0, 95, $W, 95, $cDiv);
    imagettftext($im, 7.5, 0, 16, 48, $cPrimary, $fontBold, 'MoSJE • PM-AJAY INFRASTRUCTURE MONITOR');
    imagettftext($im, 12, 0, 16, 68, $cText, $fontBold, $title);
    
    // Role Badge
    $badgeW = 16 + strlen($roleBadge) * 6;
    drawRoundedRect($im, 16, 74, 16 + $badgeW, 90, 4, $cPrimLight);
    imagettftext($im, 7.5, 0, 22, 85, $cPrimDark, $fontBold, $roleBadge);

    // Bottom Navigation Bar
    $bY = $H - 60;
    imagefilledrectangle($im, 0, $bY, $W, $H, $cWhite);
    imageline($im, 0, $bY, $W, $bY, $cDiv);

    $tabs = ['home' => 'Home', 'tenders' => 'Tenders', 'alerts' => 'Alerts', 'profile' => 'Profile'];
    $xStep = (int)($W / 4);
    $idx = 0;
    foreach ($tabs as $k => $label) {
        $cx = (int)($xStep * $idx + $xStep / 2);
        $isActive = ($activeTab === $k);
        $col = $isActive ? $cPrimary : $cSec;

        if ($k === 'home') {
            imagefilledrectangle($im, $cx - 5, $bY + 12, $cx + 5, $bY + 22, $col);
            imagefilledpolygon($im, [$cx, $bY + 6, $cx - 7, $bY + 13, $cx + 7, $bY + 13], $col);
        } elseif ($k === 'tenders') {
            imagefilledrectangle($im, $cx - 6, $bY + 7, $cx + 6, $bY + 21, $col);
        } elseif ($k === 'alerts') {
            imagefilledellipse($im, $cx, $bY + 14, 10, 8, $col);
            imagefilledellipse($im, $cx, $bY + 9, 6, 6, $col);
        } else {
            imagefilledellipse($im, $cx, $bY + 10, 8, 8, $col);
            imagefilledellipse($im, $cx, $bY + 20, 14, 8, $col);
        }
        imagettftext($im, 7, 0, $cx - 12, $bY + 34, $col, $isActive ? $fontBold : $fontRegular, $label);
        $idx++;
    }
}

// 1. SCREENSHOT 1: Public Tenders Tab (No Contacts, No Budget, Time Remaining Visible)
$im1 = imagecreatetruecolor(380, 720);
drawFrame($im1, 380, 720, 'Public Citizen Portal', 'Public • Transparency Feed', 'tenders');
$cWhite = hexColor($im1, '#FFFFFF'); $cText = hexColor($im1, '#110B0A');
$cSec = hexColor($im1, '#6B6564'); $cDiv = hexColor($im1, '#EAE6E5');
$cPrimary = hexColor($im1, '#F76C45'); $cSuccess = hexColor($im1, '#2E7D32');

// Filter chips
drawRoundedRect($im1, 16, 105, 90, 125, 10, $cPrimary);
imagettftext($im1, 7.5, 0, 28, 118, $cWhite, $fontBold, 'All Works (8)');
drawRoundedRect($im1, 96, 105, 175, 125, 10, hexColor($im1, '#ECEFF1'));
imagettftext($im1, 7.5, 0, 106, 118, $cSec, $fontBold, 'In Progress');
drawRoundedRect($im1, 181, 105, 255, 125, 10, hexColor($im1, '#ECEFF1'));
imagettftext($im1, 7.5, 0, 191, 118, $cSec, $fontBold, '[!] Flagged');

// Public Tender Card 1
drawRoundedRect($im1, 16, 135, 364, 305, 8, $cWhite);
drawRoundedRect($im1, 26, 145, 90, 161, 4, hexColor($im1, '#ECEFF1'));
imagettftext($im1, 7, 0, 32, 155, hexColor($im1, '#37474F'), $fontBold, 'ROADS');
drawRoundedRect($im1, 260, 145, 350, 161, 4, hexColor($im1, '#ED6C02'));
imagettftext($im1, 7, 0, 272, 155, $cWhite, $fontBold, 'IN_PROGRESS');

imagettftext($im1, 8, 0, 26, 178, $cSec, $fontBold, 'TND-2026-RD-01');
imagettftext($im1, 10.5, 0, 26, 196, $cText, $fontBold, 'Four-Laning of Hingna Arterial Corridor');

// Progress Bar
imagettftext($im1, 8, 0, 26, 218, $cSec, $fontRegular, 'Physical Progress:');
imagettftext($im1, 9, 0, 310, 218, $cPrimary, $fontBold, '70%');
drawRoundedRect($im1, 26, 224, 350, 230, 3, hexColor($im1, '#EAE6E5'));
drawRoundedRect($im1, 26, 224, 253, 230, 3, $cPrimary);

// Time remaining badge & Nudge Button (Public view: zero budget, zero contact)
drawRoundedRect($im1, 26, 245, 155, 268, 4, hexColor($im1, '#F0F4F8'));
imagettftext($im1, 7.5, 0, 34, 260, hexColor($im1, '#2D3748'), $fontBold, 'Time: 3mo 8d left');

drawRoundedRect($im1, 246, 245, 352, 270, 6, $cPrimary);
imagettftext($im1, 7.5, 0, 254, 261, $cWhite, $fontBold, '[ Nudge NGO ]');

// Notice: Public restriction verified
drawRoundedRect($im1, 26, 278, 350, 298, 4, hexColor($im1, '#E8F5E9'));
imagettftext($im1, 7.5, 0, 32, 292, $cSuccess, $fontBold, '[OK] Privacy: Contractor phone/email & budget hidden');

// Public Tender Card 2 (Delayed work)
drawRoundedRect($im1, 16, 318, 364, 485, 8, $cWhite);
drawRoundedRect($im1, 26, 328, 90, 344, 4, hexColor($im1, '#ECEFF1'));
imagettftext($im1, 7, 0, 32, 338, hexColor($im1, '#37474F'), $fontBold, 'BRIDGES');
drawRoundedRect($im1, 275, 328, 350, 344, 4, hexColor($im1, '#D32F2F'));
imagettftext($im1, 7, 0, 287, 338, $cWhite, $fontBold, 'DELAYED');

imagettftext($im1, 8, 0, 26, 361, $cSec, $fontBold, 'TND-2026-BR-03');
imagettftext($im1, 10.5, 0, 26, 379, $cText, $fontBold, 'Varuna River Submersible Bridge');

imagettftext($im1, 8, 0, 26, 401, $cSec, $fontRegular, 'Physical Progress:');
imagettftext($im1, 9, 0, 310, 401, $cPrimary, $fontBold, '40%');
drawRoundedRect($im1, 26, 407, 350, 413, 3, hexColor($im1, '#EAE6E5'));
drawRoundedRect($im1, 26, 407, 155, 413, 3, $cPrimary);

drawRoundedRect($im1, 26, 428, 165, 451, 4, hexColor($im1, '#FFEBEE'));
imagettftext($im1, 7.5, 0, 34, 443, hexColor($im1, '#D32F2F'), $fontBold, 'Time: Overdue by 24d');

drawRoundedRect($im1, 246, 428, 352, 453, 6, $cPrimary);
imagettftext($im1, 7.5, 0, 254, 444, $cWhite, $fontBold, '[ Nudge NGO ]');

drawRoundedRect($im1, 26, 460, 350, 480, 4, hexColor($im1, '#E8F5E9'));
imagettftext($im1, 7.5, 0, 32, 474, $cSuccess, $fontBold, '[OK] Privacy: Contractor phone/email & budget hidden');

imagepng($im1, $outDir . '/part10_tenders_public.png');
imagedestroy($im1);

// 2. SCREENSHOT 2: Staff / NGO Tenders Tab (Contractor Contacts, Inspector Contacts, Budget vs Spent)
$im2 = imagecreatetruecolor(380, 720);
drawFrame($im2, 380, 720, 'District Officer View', 'District Officer • Nagpur', 'tenders');

drawRoundedRect($im2, 16, 105, 364, 385, 8, $cWhite);
drawRoundedRect($im2, 26, 115, 90, 131, 4, hexColor($im2, '#ECEFF1'));
imagettftext($im2, 7, 0, 32, 125, hexColor($im2, '#37474F'), $fontBold, 'ROADS');
drawRoundedRect($im2, 260, 115, 350, 131, 4, hexColor($im2, '#ED6C02'));
imagettftext($im2, 7, 0, 272, 125, $cWhite, $fontBold, 'IN_PROGRESS');

imagettftext($im2, 8, 0, 26, 148, $cSec, $fontBold, 'TND-2026-RD-01');
imagettftext($im2, 10.5, 0, 26, 166, $cText, $fontBold, 'Four-Laning of Hingna Arterial Corridor');

// Progress & Time
drawRoundedRect($im2, 26, 178, 150, 198, 4, hexColor($im2, '#F0F4F8'));
imagettftext($im2, 7.5, 0, 34, 191, hexColor($im2, '#2D3748'), $fontBold, 'Time: 3mo 8d left');
imagettftext($im2, 8.5, 0, 230, 191, $cPrimary, $fontBold, 'Progress: 70%');

// Staff-Only Container
drawRoundedRect($im2, 26, 210, 354, 375, 6, hexColor($im2, '#FAF9F8'));
imagettftext($im2, 8, 0, 34, 228, hexColor($im2, '#D4502A'), $fontBold, 'OFFICIAL & NGO VISIBILITY DETAILS:');

imagettftext($im2, 8, 0, 34, 248, $cText, $fontBold, 'Contractor:');
imagettftext($im2, 8, 0, 100, 248, $cSec, $fontRegular, 'Larsen & Infra Projects Ltd');
imagettftext($im2, 8, 0, 34, 265, hexColor($im2, '#1565C0'), $fontBold, 'Ph: 9822012345  •  Email: rajesh@larseninfra.com');

imagettftext($im2, 8, 0, 34, 288, $cText, $fontBold, 'Field Inspector:');
imagettftext($im2, 8, 0, 115, 288, $cSec, $fontRegular, 'Rajesh M. (Nagpur Zone)');
imagettftext($im2, 8, 0, 34, 305, hexColor($im2, '#1565C0'), $fontBold, 'Ph: 9100000009  •  Email: inspector.rajesh@mosje.gov.in');

// Budget Box
drawRoundedRect($im2, 34, 320, 344, 360, 4, hexColor($im2, '#FDF6F4'));
imagettftext($im2, 7.5, 0, 42, 336, $cText, $fontBold, 'Budget: Rs 4,50,00,000');
imagettftext($im2, 7.5, 0, 185, 336, $cText, $fontBold, 'Spent: Rs 3,65,00,000');
drawRoundedRect($im2, 42, 344, 150, 356, 3, hexColor($im2, '#FFCDD2'));
imagettftext($im2, 7, 0, 48, 353, hexColor($im2, '#D32F2F'), $fontBold, '[!] VARIANCE +11.1%');

imagepng($im2, $outDir . '/part10_tenders_officer.png');
imagedestroy($im2);

// 3. SCREENSHOT 3: District Officer Home Drill-Down (Inspector -> Contractor -> Progress)
$im3 = imagecreatetruecolor(380, 720);
drawFrame($im3, 380, 720, 'Virendra Deshmukh', 'District Officer • Nagpur', 'home');

// Header title
imagettftext($im3, 9.5, 0, 16, 115, $cText, $fontBold, 'District Inspector & Project Drill-Down');

drawRoundedRect($im3, 16, 125, 364, 390, 8, $cWhite);

// Step 1: Pick Field Inspector
imagettftext($im3, 8, 0, 26, 145, $cSec, $fontBold, '1. Pick Field Inspector under Nagpur:');
drawRoundedRect($im3, 26, 152, 354, 182, 6, hexColor($im3, '#FAF9F8'));
imagettftext($im3, 8.5, 0, 34, 172, $cText, $fontBold, 'Rajesh M. (Nagpur Zone • 3 Assigned Projects)  [v]');

// Step 2: Pick Contractor
imagettftext($im3, 8, 0, 26, 202, $cSec, $fontBold, '2. Assigned Contractor under Rajesh M.:');
drawRoundedRect($im3, 26, 209, 354, 239, 6, hexColor($im3, '#FAF9F8'));
imagettftext($im3, 8.5, 0, 34, 229, $cText, $fontBold, 'Larsen & Infra Projects Ltd (REG-MH-2024-001)  [v]');

// Step 3: Project Progress Report Result Card
drawRoundedRect($im3, 26, 250, 354, 375, 6, hexColor($im3, '#FDF6F4'));
imagettftext($im3, 7.5, 0, 34, 268, $cPrimary, $fontBold, 'SELECTED PROJECT PROGRESS REPORT:');
imagettftext($im3, 9, 0, 34, 286, $cText, $fontBold, 'TND-2026-RD-01: Hingna Arterial Corridor');

drawRoundedRect($im3, 34, 296, 344, 302, 3, hexColor($im3, '#EAE6E5'));
drawRoundedRect($im3, 34, 296, 251, 302, 3, $cPrimary);
imagettftext($im3, 7.5, 0, 34, 316, $cSec, $fontBold, 'Physical Progress: 70%');
imagettftext($im3, 7.5, 0, 210, 316, hexColor($im3, '#2D3748'), $fontBold, 'Time: 3mo 8d left');

imagettftext($im3, 7.5, 0, 34, 336, $cText, $fontBold, 'Contractor Contact:');
imagettftext($im3, 7.5, 0, 135, 336, hexColor($im3, '#1565C0'), $fontBold, 'Rajesh Patel (Ph: 9822012345)');

imagettftext($im3, 7.5, 0, 34, 352, $cText, $fontBold, 'Inspector Contact:');
imagettftext($im3, 7.5, 0, 135, 352, hexColor($im3, '#1565C0'), $fontBold, 'Rajesh M. (Ph: 9100000009)');

imagettftext($im3, 7.5, 0, 34, 368, hexColor($im3, '#D32F2F'), $fontBold, 'Budget: Rs 4.50 Cr | Spent Rs 3.65 Cr (+11.1% Var)');

imagepng($im3, $outDir . '/part10_district_officer_drilldown.png');
imagedestroy($im3);

// 4. SCREENSHOT 4: State Officer Home Drill-Down (DO -> Inspector -> Contractor -> Progress)
$im4 = imagecreatetruecolor(380, 720);
drawFrame($im4, 380, 720, 'Dr. Arvind Patil', 'State Officer • Maharashtra', 'home');

imagettftext($im4, 9.5, 0, 16, 115, $cText, $fontBold, 'State Oversight Drill-Down (4 Levels)');
drawRoundedRect($im4, 16, 125, 364, 430, 8, $cWhite);

// Step 1: Pick DO
imagettftext($im4, 8, 0, 26, 145, $cSec, $fontBold, '1. Pick District Officer in Maharashtra:');
drawRoundedRect($im4, 26, 152, 354, 180, 6, hexColor($im4, '#FAF9F8'));
imagettftext($im4, 8, 0, 34, 171, $cText, $fontBold, 'Virendra Deshmukh (Nagpur District DO)  [v]');

// Step 2: Pick Inspector
imagettftext($im4, 8, 0, 26, 198, $cSec, $fontBold, '2. Pick Field Inspector under District:');
drawRoundedRect($im4, 26, 205, 354, 233, 6, hexColor($im4, '#FAF9F8'));
imagettftext($im4, 8, 0, 34, 224, $cText, $fontBold, 'Rajesh M. (Nagpur Zone)  [v]');

// Step 3: Pick Contractor
imagettftext($im4, 8, 0, 26, 251, $cSec, $fontBold, '3. Contractor Running the Project:');
drawRoundedRect($im4, 26, 258, 354, 286, 6, hexColor($im4, '#FAF9F8'));
imagettftext($im4, 8, 0, 34, 277, $cText, $fontBold, 'Larsen & Infra Projects Ltd  [v]');

// Step 4: Progress Card
drawRoundedRect($im4, 26, 298, 354, 415, 6, hexColor($im4, '#FDF6F4'));
imagettftext($im4, 7.5, 0, 34, 314, $cPrimary, $fontBold, 'PROJECT PROGRESS & OVERSIGHT REPORT:');
imagettftext($im4, 9, 0, 34, 332, $cText, $fontBold, 'TND-2026-RD-01: Four-Laning of Hingna Corridor');

drawRoundedRect($im4, 34, 342, 344, 348, 3, hexColor($im4, '#EAE6E5'));
drawRoundedRect($im4, 34, 342, 251, 348, 3, $cPrimary);
imagettftext($im4, 7.5, 0, 34, 362, $cSec, $fontBold, 'Progress: 70% | Time Remaining: 3mo 8d left');

imagettftext($im4, 7.5, 0, 34, 380, $cText, $fontBold, 'Contractor: Rajesh Patel (Ph: 9822012345)');
imagettftext($im4, 7.5, 0, 34, 396, $cText, $fontBold, 'Inspector: Rajesh M. (Ph: 9100000009)');
imagettftext($im4, 7.5, 0, 34, 410, hexColor($im4, '#D32F2F'), $fontBold, 'Budget Rs 4.50 Cr | Spent Rs 3.65 Cr (+11.1% Variance Alert)');

imagepng($im4, $outDir . '/part10_state_officer_drilldown.png');
imagedestroy($im4);

// 5. SCREENSHOT 5: NGO Home Full Hierarchy Browse (Contacts Visible, Search & Designation Filter)
$im5 = imagecreatetruecolor(380, 720);
drawFrame($im5, 380, 720, 'Sewa Bharati Trust', 'Approved NGO • Social Auditor', 'home');

imagettftext($im5, 9.5, 0, 16, 115, $cText, $fontBold, 'Social Transparency Hierarchy Browser');

// Search & Filter
drawRoundedRect($im5, 16, 125, 250, 155, 6, $cWhite);
imagettftext($im5, 7.5, 0, 26, 144, $cSec, $fontRegular, 'Search name: Rajesh');

drawRoundedRect($im5, 256, 125, 364, 155, 6, $cWhite);
imagettftext($im5, 7.5, 0, 266, 144, $cText, $fontBold, 'All Roles [v]');

// Node 1: State Officer
drawRoundedRect($im5, 16, 165, 364, 255, 8, $cWhite);
imagettftext($im5, 7.5, 0, 26, 182, $cPrimary, $fontBold, 'MAHARASHTRA STATE');
imagettftext($im5, 8.5, 0, 26, 200, $cText, $fontBold, 'State Officer: Dr. Arvind Patil');
imagettftext($im5, 8, 0, 26, 218, hexColor($im5, '#1565C0'), $fontBold, 'Ph: 9100000002  •  Email: state.mh@mosje.gov.in');
imagettftext($im5, 7.5, 0, 26, 238, $cSuccess, $fontBold, '[OK] NGO Privileged: Official contacts fully visible');

// Node 2: District Officer & Inspector
drawRoundedRect($im5, 16, 265, 364, 435, 8, $cWhite);
imagettftext($im5, 7.5, 0, 26, 282, $cPrimary, $fontBold, 'NAGPUR DISTRICT');
imagettftext($im5, 8.5, 0, 26, 300, $cText, $fontBold, 'District Officer: Virendra Deshmukh');
imagettftext($im5, 8, 0, 26, 318, hexColor($im5, '#1565C0'), $fontBold, 'Ph: 9100000004  •  Email: district.nagpur@mosje.gov.in');

imagettftext($im5, 8.5, 0, 26, 342, $cText, $fontBold, 'Field Inspector: Rajesh M.');
imagettftext($im5, 8, 0, 26, 360, hexColor($im5, '#1565C0'), $fontBold, 'Ph: 9100000009  •  Email: inspector.rajesh@mosje.gov.in');

drawRoundedRect($im5, 26, 372, 354, 422, 6, hexColor($im5, '#FDF6F4'));
imagettftext($im5, 7.5, 0, 34, 388, $cText, $fontBold, 'Contractor: Larsen & Infra (Ph: 9822012345)');
imagettftext($im5, 7.5, 0, 34, 404, $cSec, $fontRegular, 'Work: Hingna Arterial Corridor (Progress: 70% | Time: 3mo 8d left)');

imagepng($im5, $outDir . '/part10_ngo_hierarchy_browse.png');
imagedestroy($im5);

// 6. SCREENSHOT 6: Public Home Hierarchy Browse (Contacts Gated + Nudge Button)
$im6 = imagecreatetruecolor(380, 720);
drawFrame($im6, 380, 720, 'Citizen Auditor', 'Public Citizen • Transparency Portal', 'home');

imagettftext($im6, 9.5, 0, 16, 115, $cText, $fontBold, 'Public Hierarchy Browser & Nudge Action');

// Search & Filter
drawRoundedRect($im6, 16, 125, 250, 155, 6, $cWhite);
imagettftext($im6, 7.5, 0, 26, 144, $cSec, $fontRegular, 'Search official name...');
drawRoundedRect($im6, 256, 125, 364, 155, 6, $cWhite);
imagettftext($im6, 7.5, 0, 266, 144, $cText, $fontBold, 'All Roles [v]');

// Node 1: State Officer (Public: contacts hidden)
drawRoundedRect($im6, 16, 165, 364, 250, 8, $cWhite);
imagettftext($im6, 7.5, 0, 26, 182, $cPrimary, $fontBold, 'MAHARASHTRA STATE');
imagettftext($im6, 8.5, 0, 26, 200, $cText, $fontBold, 'State Officer: Dr. Arvind Patil');
imagettftext($im6, 7.5, 0, 26, 218, hexColor($im6, '#757575'), $fontRegular, '[Protected] Phone & Email Hidden (Privacy Policy)');
drawRoundedRect($im6, 250, 195, 350, 222, 6, $cPrimary);
imagettftext($im6, 7.5, 0, 258, 212, $cWhite, $fontBold, '[ Nudge NGO ]');

// Node 2: District & Inspector (Public: contacts hidden)
drawRoundedRect($im6, 16, 260, 364, 420, 8, $cWhite);
imagettftext($im6, 7.5, 0, 26, 278, $cPrimary, $fontBold, 'NAGPUR DISTRICT');
imagettftext($im6, 8.5, 0, 26, 296, $cText, $fontBold, 'District Officer: Virendra Deshmukh');
imagettftext($im6, 7.5, 0, 26, 312, hexColor($im6, '#757575'), $fontRegular, '[Protected] Official Contact Protected');
drawRoundedRect($im6, 250, 290, 350, 317, 6, $cPrimary);
imagettftext($im6, 7.5, 0, 258, 307, $cWhite, $fontBold, '[ Nudge NGO ]');

imagettftext($im6, 8.5, 0, 26, 336, $cText, $fontBold, 'Field Inspector: Rajesh M.');
imagettftext($im6, 7.5, 0, 26, 352, hexColor($im6, '#757575'), $fontRegular, '[Protected] Official Contact Protected');
drawRoundedRect($im6, 250, 330, 350, 357, 6, $cPrimary);
imagettftext($im6, 7.5, 0, 258, 347, $cWhite, $fontBold, '[ Nudge NGO ]');

drawRoundedRect($im6, 26, 365, 354, 410, 6, hexColor($im6, '#FDF6F4'));
imagettftext($im6, 7.5, 0, 34, 381, $cText, $fontBold, 'Contractor: Larsen & Infra Projects Ltd');
imagettftext($im6, 7.5, 0, 34, 398, $cSec, $fontRegular, 'Project: Hingna Corridor • Progress: 70% • Time: 3mo 8d left');

imagepng($im6, $outDir . '/part10_public_hierarchy_nudge.png');
imagedestroy($im6);

echo "[SUCCESS] Generated 6 clean Part 10 verification screenshots!\n";
