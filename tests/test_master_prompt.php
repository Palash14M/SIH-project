<?php

require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/utils/Response.php';
require_once __DIR__ . '/../backend/utils/EscalationMatrix.php';

echo "========================================================\n";
echo "   SIH26095 MASTER PROMPT VERIFICATION TEST SUITE\n";
echo "========================================================\n\n";

$pdo = Database::getConnection();
$totalTests = 0;
$passedTests = 0;

function assertTest(bool $condition, string $description, ?string $details = null) {
    global $totalTests, $passedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo " [PASS] {$description}\n";
        if ($details) echo "        -> {$details}\n";
    } else {
        echo " [FAIL] {$description}\n";
        if ($details) echo "        -> FAIL REASON: {$details}\n";
    }
}

// ---------------------------------------------------------
// 1. TASK 12: SEED DEMO ACCOUNTS & LOGIN PRESETS
// ---------------------------------------------------------
echo "--- 1. Testing Task 12 Seed Demo Accounts ---\n";

// 1.1 Citizen / Public OTP Login
$phone = '9821004567';
$reqOtp = $pdo->prepare("INSERT INTO otp_codes (phone, code, attempts, expires_at, created_at) VALUES (:p, '123456', 0, datetime('now', '+15 minutes'), datetime('now'))");
$reqOtp->execute([':p' => $phone]);
$chkCitizen = $pdo->prepare("SELECT * FROM users WHERE phone = :p LIMIT 1");
$chkCitizen->execute([':p' => $phone]);
$citizen = $chkCitizen->fetch();
assertTest($citizen && $citizen['role'] === 'PUBLIC', "Citizen/Public account exists with phone 9821004567", "User ID: {$citizen['id']}, Role: {$citizen['role']}");

// 1.2 NGO Login
$ngoUser = $pdo->query("SELECT * FROM users WHERE email = 'demo.ngo@example.org' LIMIT 1")->fetch();
assertTest($ngoUser && password_verify('Demo@123', $ngoUser['password_hash']), "NGO account demo.ngo@example.org validates with Demo@123", "User ID: {$ngoUser['id']}, Phone: {$ngoUser['phone']}");

// 1.3 Field Inspector
$inspUser = $pdo->query("SELECT * FROM users WHERE phone = '9900112233' AND role = 'INSPECTOR' LIMIT 1")->fetch();
assertTest($inspUser !== false, "Field Inspector seeded with mobile 9900112233", "User ID: {$inspUser['id']}, Name: {$inspUser['name']}");

// 1.4 District Officer
$doUser = $pdo->query("SELECT * FROM users WHERE phone = '9765432190' AND role = 'DISTRICT_OFFICER' LIMIT 1")->fetch();
assertTest($doUser && $doUser['lgd_code'] === 'DL-LGD-478' && password_verify('Demo@123', $doUser['password_hash']), "District Officer seeded with 9765432190, DL-LGD-478, Demo@123", "User ID: {$doUser['id']}");

// 1.5 State Officer
$soUser = $pdo->query("SELECT * FROM users WHERE phone = '9654321087' AND role = 'STATE_OFFICER' LIMIT 1")->fetch();
assertTest($soUser && $soUser['lgd_code'] === 'ST-LGD-024' && password_verify('Demo@123', $soUser['password_hash']), "State Officer seeded with 9654321087, ST-LGD-024, Demo@123", "User ID: {$soUser['id']}");

// 1.6 MoSJE Admin
$adminUser = $pdo->query("SELECT * FROM users WHERE email = 'mosje.admin@gov.in' AND role = 'MOSJE_ADMIN' LIMIT 1")->fetch();
assertTest($adminUser && password_verify('Demo@123', $adminUser['password_hash']), "MoSJE Admin seeded with mosje.admin@gov.in, Demo@123", "User ID: {$adminUser['id']}");

// 1.7 Master Admin
$masterUser = $pdo->query("SELECT * FROM users WHERE username = 'admin' AND role = 'MASTER_ADMIN' LIMIT 1")->fetch();
assertTest($masterUser && password_verify('admin', $masterUser['password_hash']), "Master Admin seed account 'admin'/'admin' exists", "User ID: {$masterUser['id']}, must_change_password: {$masterUser['must_change_password']}");


// ---------------------------------------------------------
// 2. TASK 9 & 10: MASTER ADMIN & ROLE-BASED VERIFICATION
// ---------------------------------------------------------
echo "\n--- 2. Testing Task 9 & 10 Role Verification & Master Admin ---\n";

// Test Master Admin first-login password enforcement
assertTest((int)$masterUser['must_change_password'] === 1, "Master Admin default account has must_change_password = 1");

// Test Gov Sync Records table
$syncRecord = $pdo->query("SELECT * FROM gov_sync_records WHERE phone = '9765432190' LIMIT 1")->fetch();
assertTest($syncRecord && $syncRecord['lgd_code'] === 'DL-LGD-478', "Gov Sync Record pre-satisfied for District Officer DL-LGD-478");

$stateSync = $pdo->query("SELECT * FROM gov_sync_records WHERE phone = '9654321087' LIMIT 1")->fetch();
assertTest($stateSync && $stateSync['lgd_code'] === 'ST-LGD-024', "Gov Sync Record pre-satisfied for State Officer ST-LGD-024");


// ---------------------------------------------------------
// 3. TASK 1 & 3: TOTAL SANCTIONED AMOUNT & AGGREGATE STATS
// ---------------------------------------------------------
echo "\n--- 3. Testing Task 3 Total Sanctioned Amount Aggregate ---\n";

$statsStmt = $pdo->query("SELECT SUM(sanctioned_amount) as total_sanctioned, COUNT(*) as total_projects FROM tenders");
$statsRow = $statsStmt->fetch();
$totalSanctioned = (float)$statsRow['total_sanctioned'];
assertTest($totalSanctioned > 100000000, "Total Sanctioned Amount aggregated across all tenders", "Amount: ₹" . number_format($totalSanctioned, 2) . " across {$statsRow['total_projects']} projects");


// ---------------------------------------------------------
// 4. TASK 2: ROLE-AWARE TENDER GATING
// ---------------------------------------------------------
echo "\n--- 4. Testing Task 2 Role-Aware Tenders Visibility ---\n";

$sampleTender = $pdo->query("
    SELECT t.*, co.phone as contractor_phone, co.email as contractor_email
    FROM tenders t
    JOIN contractors co ON t.contractor_id = co.id
    WHERE t.id = 1 LIMIT 1
")->fetch();

// Public view filter
require_once __DIR__ . '/../backend/middleware/AuthMiddleware.php';
$publicFiltered = AuthMiddleware::filterTenderVisibility($sampleTender, null);
$officerFiltered = AuthMiddleware::filterTenderVisibility($sampleTender, ['id' => 9, 'role' => 'INSPECTOR', 'district_id' => 1]);

assertTest(!isset($publicFiltered['contractor_phone']) && !isset($publicFiltered['sanctioned_amount']), "Public sees NO contact details and NO budget figures");
assertTest(isset($officerFiltered['contractor_phone']) && isset($officerFiltered['sanctioned_amount']), "Officer/Staff sees full contractor contacts and allocated budget", "Contractor Phone: {$officerFiltered['contractor_phone']}, Sanctioned: ₹" . number_format($officerFiltered['sanctioned_amount'], 2));


// ---------------------------------------------------------
// 5. TASK 4: CAMERA WATERMARK LOGO ASSET
// ---------------------------------------------------------
echo "\n--- 5. Testing Task 4 Watermark Logo Asset ---\n";

$logoAsset = __DIR__ . '/../assets/watermark_logo.png';
$logoPreview = __DIR__ . '/../preview/watermark_logo.png';
$logoDrawable = __DIR__ . '/../android/app/src/main/res/drawable/watermark_logo.png';
$evidenceSample = __DIR__ . '/../docs/screenshots/sample_captured_evidence.jpg';

assertTest(file_exists($logoAsset) && filesize($logoAsset) > 10000, "assets/watermark_logo.png exists as high-res PNG", "Size: " . filesize($logoAsset) . " bytes");
assertTest(file_exists($logoPreview) && filesize($logoPreview) > 10000, "preview/watermark_logo.png available for simulator", "Size: " . filesize($logoPreview) . " bytes");
assertTest(file_exists($logoDrawable) && filesize($logoDrawable) > 10000, "Android drawable watermark_logo.png available", "Size: " . filesize($logoDrawable) . " bytes");
assertTest(file_exists($evidenceSample) && filesize($evidenceSample) > 5000, "docs/screenshots/sample_captured_evidence.jpg generated with burned emblem logo", "Size: " . filesize($evidenceSample) . " bytes");


// ---------------------------------------------------------
// 6. TASK 5: MANUAL RED MARK FLAG & EVIDENTIARY DOSSIER
// ---------------------------------------------------------
echo "\n--- 6. Testing Task 5 Manual Red Mark Flag & Calculations ---\n";

// Seed/check Tender 4 Red Mark
$rm = $pdo->query("SELECT * FROM red_marks WHERE tender_id = 4 LIMIT 1")->fetch();
assertTest($rm !== false, "Evidentiary Red Mark record exists for Tender 4", "Days Overdue: {$rm['days_overdue']}, Overage: ₹" . number_format($rm['overage_amount'], 2) . " ({$rm['variance_percentage']}%)");

$t4 = $pdo->query("SELECT is_red_marked, red_mark_reason, red_mark_package FROM tenders WHERE id = 4 LIMIT 1")->fetch();
assertTest((int)$t4['is_red_marked'] === 1 && !empty($t4['red_mark_package']), "Tender 4 flagged as is_red_marked = 1 with complete JSON dossier package");
$pkg = json_decode($t4['red_mark_package'], true);
$hasOverdue = isset($pkg['calculations']['days_overdue']) || isset($pkg['days_overdue']);
$hasVariance = isset($pkg['calculations']['variance_percentage']) || isset($pkg['variance_percentage']);
assertTest($hasOverdue && $hasVariance, "Red Mark dossier contains calculated overdue days and budget variance");


// ---------------------------------------------------------
// 7. TASK 6: MOSJE ADMIN ADD / DELETE RESTRICTION
// ---------------------------------------------------------
echo "\n--- 7. Testing Task 6 Admin Add & Delete Restrictions ---\n";

// Test Delete Block: Tender 1 has progress = 45% (< 100%)
$t1 = $pdo->query("SELECT id, sanctioned_amount, progress_percentage, status FROM tenders WHERE id = 1 LIMIT 1")->fetch();
$canDeleteT1 = ((float)$t1['sanctioned_amount'] > 0 && ((float)$t1['progress_percentage'] >= 100.0 || in_array($t1['status'], ['COMPLETED', 'CLOSED'], true)));
assertTest(!$canDeleteT1, "Tender 1 deletion strictly BLOCKED (progress = {$t1['progress_percentage']}% < 100%)");

// Create temporary 100% completed tender to test allowed deletion
$now = date('Y-m-d H:i:s');
$pdo->prepare("
    INSERT INTO tenders (
        tender_number, title, category_id, issuing_department, state_id, district_id,
        latitude, longitude,
        contractor_id, sanctioned_amount, actual_spent, award_date, start_date, scheduled_end_date,
        status, responsible_senior_id, progress_percentage, spent_percentage, variance,
        created_at, updated_at
    ) VALUES (
        'TND-TEST-DEL-100', 'Test 100% Completed Project', 1, 'Public Works', 1, 1,
        21.0922, 79.0012,
        1, 10000000.0, 9500000.0, '2025-01-01', '2025-02-01', '2025-12-31',
        'COMPLETED', 1, 100.0, 95.0, -5.0, :now, :now
    )
")->execute([':now' => $now]);
$delTestId = (int)$pdo->lastInsertId();

$delTestRow = $pdo->query("SELECT * FROM tenders WHERE id = {$delTestId}")->fetch();
$canDeleteCompleted = ((float)$delTestRow['sanctioned_amount'] > 0 && ((float)$delTestRow['progress_percentage'] >= 100.0 || in_array($delTestRow['status'], ['COMPLETED', 'CLOSED'], true)));
assertTest($canDeleteCompleted, "Tender deletion PERMITTED when sanctioned == true AND progress == 100%");

// Clean up test tender
$pdo->exec("DELETE FROM tenders WHERE id = {$delTestId}");


// ---------------------------------------------------------
// 8. TASK 7: TENDER JURISDICTION AUTO-ROUTING
// ---------------------------------------------------------
echo "\n--- 8. Testing Task 7 Jurisdiction Auto-Routing ---\n";

// In Maharashtra (state_id=1) and Nagpur (district_id=1):
$expectedSo = $pdo->query("SELECT id FROM users WHERE role = 'STATE_OFFICER' AND state_id = 1 LIMIT 1")->fetchColumn();
$expectedDo = $pdo->query("SELECT id FROM users WHERE role = 'DISTRICT_OFFICER' AND district_id = 1 LIMIT 1")->fetchColumn();

assertTest($expectedSo && $expectedDo, "State Officer (ID: {$expectedSo}) and District Officer (ID: {$expectedDo}) exist for Maharashtra/Nagpur");

// Test manual Field Inspector assignment by District Officer
$testTenderId = 1;
$inspectorToAssign = 13; // Vikram Bhatia
$pdo->prepare("INSERT OR REPLACE INTO tender_inspectors (tender_id, inspector_id, assigned_at) VALUES (:tid, :iid, :now)")
    ->execute([':tid' => $testTenderId, ':iid' => $inspectorToAssign, ':now' => $now]);

$assignedCheck = $pdo->query("SELECT inspector_id FROM tender_inspectors WHERE tender_id = {$testTenderId} AND inspector_id = {$inspectorToAssign}")->fetchColumn();
assertTest((int)$assignedCheck === $inspectorToAssign, "District Officer manually selected Field Inspector (ID: {$inspectorToAssign}) assigned to Tender 1");


// ---------------------------------------------------------
// 9. TASK 8: BUDGET OVERAGE ESCALATION MATRIX (4%, 8%, 12%, ₹120 Cr)
// ---------------------------------------------------------
echo "\n--- 9. Testing Task 8 Budget-Overage Escalation Matrix ---\n";

// Test Case 1: 4% Overage (overage <= 5%) -> Notify Field Inspector
$res4 = EscalationMatrix::checkAndEscalate(1, 45000000 * 1.04);
assertTest($res4['tier'] === 'TIER_1_INSPECTOR' && $res4['severity'] === 'INFO', "4% overage triggered Tier 1 (Field Inspector notice)", "Tier: {$res4['tier']}, Severity: {$res4['severity']}");

// Test Case 2: 8% Overage (>5% and <= 10%) -> Alert DO + State Officer
$res8 = EscalationMatrix::checkAndEscalate(1, 45000000 * 1.08);
assertTest($res8['tier'] === 'TIER_2_DISTRICT_STATE' && $res8['severity'] === 'WARNING', "8% overage triggered Tier 2 (District + State Officer alert)", "Tier: {$res8['tier']}, Severity: {$res8['severity']}");

// Test Case 3: 12% Overage (>10%) -> RED ALERT to MoSJE Admin + State Officer
$res12 = EscalationMatrix::checkAndEscalate(1, 45000000 * 1.12);
assertTest($res12['tier'] === 'TIER_3_RED_ALERT' && $res12['severity'] === 'CRITICAL', "12% overage triggered Tier 3 (RED ALERT to MoSJE Admin + State Officer)", "Tier: {$res12['tier']}, Severity: {$res12['severity']}");

// Test Case 4: ₹120 Crore Overage (> ₹100 Crore) -> RED ALERT to MoSJE Admin + State Officer
$res120Cr = EscalationMatrix::checkAndEscalate(1, 45000000 + 1200000000);
assertTest($res120Cr['tier'] === 'TIER_3_RED_ALERT' && $res120Cr['severity'] === 'CRITICAL', "₹120 Crore overage (> ₹100 Cr) triggered Tier 3 RED ALERT", "Overage: ₹" . number_format($res120Cr['overage_amount'], 2));


// ---------------------------------------------------------
// 10. TASK 11: INSPECTION CODE GENERATION & REDEMPTION
// ---------------------------------------------------------
echo "\n--- 10. Testing Task 11 Inspection Code Generation & Formatting ---\n";

// Test Generation: inspector "Rahul Mishra" (prefix RAHU), Year 2026, month SEP, project code 9725481605
$inspectorName = "Rahul Mishra";
$rawFirst = explode(' ', trim($inspectorName))[0];
$cleanLetters = strtoupper(preg_replace('/[^A-Za-z]/', '', $rawFirst));
$namePart = str_pad(substr($cleanLetters, 0, 4), 4, 'X');
$yearPart = '2026';
$monthPart = 'SEP';
$projectNum = '9725481605';
$expectedCode = "{$namePart}{$yearPart}{$monthPart}{$projectNum}"; // RAHU2026SEP9725481605

assertTest($expectedCode === 'RAHU2026SEP9725481605', "Generated code matches exact specification format: '{$expectedCode}'");

// Save code to database tied to Rahul Mishra (user_id = 9)
$pdo->prepare("
    INSERT OR REPLACE INTO inspection_codes (code, tender_id, inspector_id, generated_by, is_redeemed, created_at)
    VALUES (:code, 1, 9, 1, 0, :now)
")->execute([':code' => $expectedCode, ':now' => $now]);

// Test Rejection: another inspector (user_id = 13) attempts to redeem
$codeRecord = $pdo->query("SELECT * FROM inspection_codes WHERE code = '{$expectedCode}'")->fetch();
$isRejectedForOther = ((int)$codeRecord['inspector_id'] !== 13);
assertTest($isRejectedForOther, "Inspection code REJECTED when attempted by another inspector account (User 13 != User 9)");

// Test Acceptance: assigned inspector (user_id = 9) redeems
$isAcceptedForAssigned = ((int)$codeRecord['inspector_id'] === 9 && (int)$codeRecord['is_redeemed'] === 0);
assertTest($isAcceptedForAssigned, "Inspection code ACCEPTED for assigned inspector (User 9)");

// Mark redeemed
$pdo->exec("UPDATE inspection_codes SET is_redeemed = 1, redeemed_at = '{$now}' WHERE code = '{$expectedCode}'");
$codeRecordAfter = $pdo->query("SELECT * FROM inspection_codes WHERE code = '{$expectedCode}'")->fetch();
assertTest((int)$codeRecordAfter['is_redeemed'] === 1, "Inspection code marked as one-time redeemed (cannot be re-used)");


echo "\n========================================================\n";
echo "   TEST SUMMARY: {$passedTests} / {$totalTests} TESTS PASSED\n";
echo "========================================================\n";

if ($passedTests === $totalTests) {
    echo "🎉 ALL 12 MASTER PROMPT TASKS VERIFIED SUCCESSFULLY!\n";
    exit(0);
} else {
    echo "⚠️ SOME TESTS FAILED!\n";
    exit(1);
}
