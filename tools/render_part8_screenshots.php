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

function drawBottomNav($im, $activeIdx, $fontBold, $fontRegular) {
    $white = hexColor($im, '#FFFFFF');
    $primary = hexColor($im, '#F76C45');
    $dark = hexColor($im, '#110B0A');
    $grey = hexColor($im, '#8E8E93');

    // Bar background
    drawRoundedRect($im, 0, 770, 390, 844, 0, $white);
    imageline($im, 0, 770, 390, 770, hexColor($im, '#E5E5EA'));

    $tabs = ['Home', 'Audits', 'Grievances', 'Profile'];
    $xOffsets = [35, 130, 225, 320];

    for ($i = 0; $i < count($tabs); $i++) {
        $color = ($i === $activeIdx) ? $primary : $grey;
        $font = ($i === $activeIdx) ? $fontBold : $fontRegular;
        imagettftext($im, 9, 0, $xOffsets[$i], 810, $color, $font, $tabs[$i]);
    }
}

// =========================================================================
// 1. SCREENSHOT: institution_ngo_review.png
// =========================================================================
$im1 = initCanvas();
drawTopHeader($im1, 'NGO Social Audit Portal', 'Sewa Bharati Trust (Approved Auditor)', $fontBold, $fontRegular);

// Approved status badge card
$white = hexColor($im1, '#FFFFFF');
$textDark = hexColor($im1, '#110B0A');
$textGrey = hexColor($im1, '#666666');
$green = hexColor($im1, '#2E7D32');
$greenBg = hexColor($im1, '#E8F5E9');
$orange = hexColor($im1, '#F76C45');

drawRoundedRect($im1, 16, 110, 374, 180, 12, $greenBg);
imagettftext($im1, 11, 0, 32, 134, $green, $fontBold, 'NGO STATUS: APPROVED (Verified Social Auditor)');
imagettftext($im1, 9, 0, 32, 154, $textDark, $fontRegular, 'District: Nagpur, MH | Darpan Reg: NGO-MH-2021-9981');
imagettftext($im1, 9, 0, 32, 170, $textDark, $fontRegular, 'Authorized: Monitor attached projects & raise grievances');

// Attached Project Card
drawRoundedRect($im1, 16, 195, 374, 430, 12, $white);
imagettftext($im1, 12, 0, 32, 222, $textDark, $fontBold, 'TND-2026-RD-01: Hingna Arterial Corridor');
imagettftext($im1, 9, 0, 32, 242, $textGrey, $fontRegular, 'Category: Roads | Sanctioned Amount: Rs. 4,50,00,000');
imagettftext($im1, 9, 0, 32, 260, $textDark, $fontBold, 'Progress: 45.0% | Actual Spent: Rs. 1,98,00,000');

// Contractor Contact info (Strictly visible to attached approved NGO!)
drawRoundedRect($im1, 28, 275, 362, 345, 8, hexColor($im1, '#F8F9FA'));
imagettftext($im1, 9, 0, 38, 295, $orange, $fontBold, 'CONTRACTOR CONTACT (AUDITOR ACCESS)');
imagettftext($im1, 9, 0, 38, 313, $textDark, $fontRegular, 'Contractor: Larsen & Infra Projects Ltd (Reg: REG-MH-2024-001)');
imagettftext($im1, 9, 0, 38, 331, $textDark, $fontRegular, 'Contact: Rajesh Patel | Ph: +91 9822012345 | rajesh@larsen.in');

// Inspector Contact info
drawRoundedRect($im1, 28, 355, 362, 415, 8, hexColor($im1, '#F8F9FA'));
imagettftext($im1, 9, 0, 38, 375, $orange, $fontBold, 'ASSIGNED MOSJE FIELD INSPECTOR');
imagettftext($im1, 9, 0, 38, 393, $textDark, $fontRegular, 'Inspector: Rajesh M. | Ph: +91 9100000011 | Nagpur Division');

// Active Grievances & Refund Status
drawRoundedRect($im1, 16, 445, 374, 605, 12, $white);
imagettftext($im1, 12, 0, 32, 472, $textDark, $fontBold, 'Grievance Redressal & Refund Tracker');

drawRoundedRect($im1, 28, 485, 362, 590, 8, hexColor($im1, '#E8F5E9'));
imagettftext($im1, 10, 0, 38, 508, $green, $fontBold, 'COMPLAINT #101: UPHELD BY DO');
imagettftext($im1, 9, 0, 38, 526, $textDark, $fontRegular, 'Subject: Culvert Wing Wall Structural Cracks');
imagettftext($im1, 8, 0, 38, 544, $textDark, $fontBold, 'Decision: Core test confirmed substandard mix. Rebuild ordered.');
imagettftext($im1, 9, 0, 38, 568, $green, $fontBold, 'REFUND PROCESSED: Rs. 472.00 (100% Refunded)');

// Raise Complaint Button
drawRoundedRect($im1, 16, 620, 374, 665, 10, $orange);
imagettftext($im1, 11, 0, 100, 648, $white, $fontBold, 'Raise Project Grievance');

drawBottomNav($im1, 0, $fontBold, $fontRegular);
imagepng($im1, "{$outDir}/institution_ngo_review.png");
imagedestroy($im1);
echo " -> Rendered institution_ngo_review.png\n";

// =========================================================================
// 2. SCREENSHOT: institution_complaint_escalation.png
// =========================================================================
$im2 = initCanvas();
drawTopHeader($im2, 'Grievance Escalation', 'MoSJE Anti-Fraud Statutory Deposit', $fontBold, $fontRegular);

// Grievance summary card
drawRoundedRect($im2, 16, 110, 374, 210, 12, $white);
imagettftext($im2, 12, 0, 32, 134, $textDark, $fontBold, 'Escalation to District Officer (DO Nagpur)');
imagettftext($im2, 9, 0, 32, 154, $textGrey, $fontRegular, 'Complaint #101 | Project: TND-2026-RD-01 (Nagpur Roads)');
imagettftext($im2, 9, 0, 32, 174, hexColor($im2, '#D32F2F'), $fontBold, 'Senior Officer Decision: REJECTED (Reported minor cracks)');
imagettftext($im2, 9, 0, 32, 194, $textDark, $fontRegular, 'Escalation Reason: Core drilling sample required to test safety');

// Fee breakdown card (Strictly 400 + 18% GST = 472)
drawRoundedRect($im2, 16, 225, 374, 385, 12, $white);
imagettftext($im2, 11, 0, 32, 252, $textDark, $fontBold, 'Statutory Escalation Fee Breakdown');
imagettftext($im2, 10, 0, 32, 280, $textDark, $fontRegular, 'Base Statutory Deposit:');
imagettftext($im2, 10, 0, 300, 280, $textDark, $fontBold, 'Rs. 400.00');

imagettftext($im2, 10, 0, 32, 308, $textDark, $fontRegular, 'GST (18% CGST 9% + SGST 9%):');
imagettftext($im2, 10, 0, 300, 308, $textDark, $fontBold, 'Rs. 72.00');

imageline($im2, 32, 325, 358, 325, hexColor($im2, '#E0E0E0'));

imagettftext($im2, 11, 0, 32, 350, $orange, $fontBold, 'Total Deposit Payable:');
imagettftext($im2, 12, 0, 285, 350, $orange, $fontBold, 'Rs. 472.00');

// Policy Alert Box
drawRoundedRect($im2, 16, 400, 374, 510, 10, hexColor($im2, '#FFF3E0'));
imagettftext($im2, 10, 0, 30, 424, hexColor($im2, '#E65100'), $fontBold, 'AUTOMATIC REFUND POLICY NOTICE:');
imagettftext($im2, 9, 0, 30, 446, $textDark, $fontRegular, '1. This deposit deters frivolous delays on national infrastructure.');
imagettftext($im2, 9, 0, 30, 466, $textDark, $fontBold, '2. IF GRIEVANCE IS UPHELD: 100% of Rs. 472.00 is refunded');
imagettftext($im2, 9, 0, 30, 484, $textDark, $fontBold, '   automatically to your bank account with zero deduction.');
imagettftext($im2, 9, 0, 30, 502, $textDark, $fontRegular, '3. If rejected, the statutory fee is retained by the exchequer.');

// Simulated Payment Confirmation / Receipt Card
drawRoundedRect($im2, 16, 525, 374, 690, 12, $white);
imagettftext($im2, 11, 0, 32, 552, $green, $fontBold, 'Payment Receipt: DEMO_ORD_9812A4F');
imagettftext($im2, 9, 0, 32, 574, $textDark, $fontRegular, 'Transaction Ref: TXN_DEMO_77182903');
imagettftext($im2, 9, 0, 32, 594, $textDark, $fontRegular, 'Method: NetBanking / UPI Verified | Status: SUCCESS');
imagettftext($im2, 9, 0, 32, 614, $textDark, $fontBold, 'Amount Paid: Rs. 472.00 (Including Rs. 72.00 GST)');
imagettftext($im2, 9, 0, 32, 634, $textDark, $fontRegular, 'Current Level: DISTRICT_OFFICER (DO Nagpur)');
imagettftext($im2, 9, 0, 32, 654, $green, $fontBold, 'Refund Status: ELIGIBLE UPON UPHOLD DECISION');

// Submit / Escalation button
drawRoundedRect($im2, 16, 705, 374, 750, 10, $orange);
imagettftext($im2, 11, 0, 95, 733, $white, $fontBold, 'Escalation Submitted & Active');

drawBottomNav($im2, 2, $fontBold, $fontRegular);
imagepng($im2, "{$outDir}/institution_complaint_escalation.png");
imagedestroy($im2);
echo " -> Rendered institution_complaint_escalation.png\n";

// =========================================================================
// 3. SCREENSHOT: public_tender_explorer.png
// =========================================================================
$im3 = initCanvas();
drawTopHeader($im3, 'MoSJE Public Tender Explorer', 'Transparent Citizen Infrastructure Portal', $fontBold, $fontRegular);

// Search Bar
drawRoundedRect($im3, 16, 110, 374, 150, 10, $white);
imagettftext($im3, 10, 0, 32, 135, $textGrey, $fontRegular, 'Search projects by tender no, title, or district...');

// Filter Chips
drawRoundedRect($im3, 16, 160, 80, 185, 12, $orange);
imagettftext($im3, 9, 0, 36, 177, $white, $fontBold, 'All (8)');

drawRoundedRect($im3, 90, 160, 175, 185, 12, $white);
imagettftext($im3, 9, 0, 105, 177, $textDark, $fontRegular, 'Roads (2)');

drawRoundedRect($im3, 185, 160, 275, 185, 12, $white);
imagettftext($im3, 9, 0, 200, 177, $textDark, $fontRegular, 'Buildings (2)');

drawRoundedRect($im3, 285, 160, 374, 185, 12, $white);
imagettftext($im3, 9, 0, 300, 177, $textDark, $fontRegular, 'Flyover (1)');

// Public Tender Card 1: Four-Laning of Hingna Arterial Corridor
drawRoundedRect($im3, 16, 200, 374, 460, 12, $white);
drawRoundedRect($im3, 290, 212, 362, 232, 8, hexColor($im3, '#E8F5E9'));
imagettftext($im3, 8, 0, 298, 226, $green, $fontBold, 'IN PROGRESS');

imagettftext($im3, 11, 0, 32, 226, $textDark, $fontBold, 'TND-2026-RD-01');
imagettftext($im3, 10, 0, 32, 246, $textDark, $fontBold, 'Four-Laning of Hingna Arterial Corridor');
imagettftext($im3, 9, 0, 32, 266, $textGrey, $fontRegular, 'Nagpur, Maharashtra | PWD Infrastructure Division');
imagettftext($im3, 9, 0, 32, 286, $textDark, $fontRegular, 'Sanctioned: Rs. 4,50,00,000 | Spent: Rs. 1,98,00,000 (44%)');
imagettftext($im3, 9, 0, 32, 306, $textDark, $fontBold, 'Physical Progress: 45.0% | Variance: -1.0% (On Schedule)');

// Data Privacy Enforcement Notice (Public sees contractor name, but contact info is strictly stripped!)
drawRoundedRect($im3, 28, 320, 362, 395, 8, hexColor($im3, '#FAFAFA'));
imagettftext($im3, 9, 0, 36, 340, $textDark, $fontBold, 'Contractor: Larsen & Infra Projects Ltd');
imagettftext($im3, 8, 0, 36, 360, hexColor($im3, '#C62828'), $fontBold, '[CONTACT DETAILS RESTRICTED UNDER CITIZEN PRIVACY LAW]');
imagettftext($im3, 8, 0, 36, 378, $textGrey, $fontRegular, 'Phone, email & address visible only to attached NGO & DO');

// Citizen Nudge Action Button
drawRoundedRect($im3, 28, 410, 362, 445, 8, $orange);
imagettftext($im3, 10, 0, 68, 432, $white, $fontBold, 'Nudge Attached NGO (Sewa Bharati Trust)');

// Public Tender Card 2: Flyover (Delayed / Variance flagged)
drawRoundedRect($im3, 16, 475, 374, 730, 12, $white);
drawRoundedRect($im3, 290, 487, 362, 507, 8, hexColor($im3, '#FFEBEE'));
imagettftext($im3, 8, 0, 300, 501, hexColor($im3, '#C62828'), $fontBold, 'DELAYED');

imagettftext($im3, 11, 0, 32, 501, $textDark, $fontBold, 'TND-2026-FL-02');
imagettftext($im3, 10, 0, 32, 521, $textDark, $fontBold, 'Swargate Elevated Grade Separator');
imagettftext($im3, 9, 0, 32, 541, $textGrey, $fontRegular, 'Pune, Maharashtra | PMC Urban Works');
imagettftext($im3, 9, 0, 32, 561, $textDark, $fontRegular, 'Sanctioned: Rs. 9,20,00,000 | Spent: Rs. 6,80,00,000 (73.9%)');
imagettftext($im3, 9, 0, 32, 581, hexColor($im3, '#C62828'), $fontBold, 'Physical Progress: 60.0% | Variance: +13.9% (COST OVERRUN)');

drawRoundedRect($im3, 28, 595, 362, 670, 8, hexColor($im3, '#FAFAFA'));
imagettftext($im3, 9, 0, 36, 615, $textDark, $fontBold, 'Contractor: Bharat Road Builders Pvt Ltd');
imagettftext($im3, 8, 0, 36, 635, hexColor($im3, '#C62828'), $fontBold, '[CONTACT DETAILS RESTRICTED UNDER CITIZEN PRIVACY LAW]');
imagettftext($im3, 8, 0, 36, 653, $textGrey, $fontRegular, 'Inspector identities masked for unbiased public safety');

drawRoundedRect($im3, 28, 685, 362, 720, 8, $orange);
imagettftext($im3, 10, 0, 85, 707, $white, $fontBold, 'Nudge Attached NGO for Audit');

drawBottomNav($im3, 0, $fontBold, $fontRegular);
imagepng($im3, "{$outDir}/public_tender_explorer.png");
imagedestroy($im3);
echo " -> Rendered public_tender_explorer.png\n";

// =========================================================================
// 4. SCREENSHOT: public_nudge_screen.png
// =========================================================================
$im4 = initCanvas();
drawTopHeader($im4, 'Citizen Social Audit Nudge', 'Mobilize Attached NGO for Field Review', $fontBold, $fontRegular);

// Project & Attached NGO Target
drawRoundedRect($im4, 16, 110, 374, 215, 12, $white);
imagettftext($im4, 11, 0, 32, 134, $textDark, $fontBold, 'Project: TND-2026-RD-01');
imagettftext($im4, 10, 0, 32, 154, $textDark, $fontRegular, 'Four-Laning of Hingna Arterial Corridor, Nagpur');
imagettftext($im4, 9, 0, 32, 174, $textGrey, $fontRegular, 'Physical Progress: 45.0% | Sanctioned: Rs. 4,50,00,000');
imagettftext($im4, 10, 0, 32, 198, $orange, $fontBold, 'Target NGO: Sewa Bharati Trust (Approved Social Auditor)');

// Daily Quota Box
drawRoundedRect($im4, 16, 230, 374, 275, 10, hexColor($im4, '#E8F5E9'));
imagettftext($im4, 10, 0, 32, 258, $green, $fontBold, 'DAILY AUDIT QUOTA: 1 CHANCE AVAILABLE TODAY');

// Strict Warning Box (Chance burning rule!)
drawRoundedRect($im4, 16, 290, 374, 440, 10, hexColor($im4, '#FFEBEE'));
imagettftext($im4, 10, 0, 30, 314, hexColor($im4, '#C62828'), $fontBold, 'MANDATORY CHANCE-BURNING RULE:');
imagettftext($im4, 8, 0, 30, 334, $textDark, $fontRegular, '1. Strictly ONE nudge per calendar day across all projects.');
imagettftext($im4, 8, 0, 30, 352, $textDark, $fontBold, '2. Constructive reason of AT LEAST 10 CHARS is mandatory.');
imagettftext($im4, 8, 0, 30, 372, hexColor($im4, '#C62828'), $fontBold, '3. CRITICAL: SUBMITTING WITHOUT VALID REASON STILL');
imagettftext($im4, 8, 0, 30, 390, hexColor($im4, '#C62828'), $fontBold, '   BURNS YOUR DAY\'S CHANCE & SENDS NOTHING TO NGO!');
imagettftext($im4, 8, 0, 30, 410, $textDark, $fontRegular, '4. Valid nudges alert the NGO for immediate field inspection.');

// Reason Input Box
drawRoundedRect($im4, 16, 450, 374, 570, 10, $white);
imagettftext($im4, 10, 0, 30, 474, $textDark, $fontBold, 'Audit Grievance Reason (Min. 10 chars required):');
imagettftext($im4, 9, 0, 30, 504, $textDark, $fontRegular, 'Culvert construction appears stagnant for 3 weeks with heavy');
imagettftext($im4, 9, 0, 30, 524, $textDark, $fontRegular, 'waterlogging. Local school traffic severely affected. Please');
imagettftext($im4, 9, 0, 30, 544, $textDark, $fontRegular, 'inspect structural quality and safety barricades.');

// Character count notice
imagettftext($im4, 9, 0, 270, 595, $green, $fontBold, '158 / 10 chars (Valid)');

// Submit Button
drawRoundedRect($im4, 16, 615, 374, 665, 10, $orange);
imagettftext($im4, 11, 0, 85, 646, $white, $fontBold, 'Submit Nudge & Alert NGO');

// Explanatory footnote
imagettftext($im4, 8, 0, 32, 690, $textGrey, $fontRegular, '* Nudge submission will be permanently recorded in the civic audit ledger.');
imagettftext($im4, 8, 0, 32, 705, $textGrey, $fontRegular, '  Your next nudge chance will replenish tomorrow at 00:00 IST.');

drawBottomNav($im4, 1, $fontBold, $fontRegular);
imagepng($im4, "{$outDir}/public_nudge_screen.png");
imagedestroy($im4);
echo " -> Rendered public_nudge_screen.png\n";

echo "=== All 4 Part 8 screenshots rendered successfully! ===\n";
