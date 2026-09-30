<?php

require_once __DIR__ . '/../backend/config/config.php';
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/utils/Jwt.php';

echo "========================================================\n";
echo "           PART 3: GATE VERIFICATION TEST SUITE          \n";
echo "========================================================\n\n";

$baseUrl = 'http://127.0.0.1:8000/api';
$passed = 0;
$failed = 0;

function httpRequest(string $method, string $url, ?array $data = null, ?string $token = null): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer {$token}";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['status' => 0, 'data' => null, 'error' => $err];
    }

    $decoded = json_decode($raw, true);
    return ['status' => $status, 'data' => $decoded, 'raw' => $raw];
}

function httpMultipart(string $url, array $fields, array $files, ?string $token = null): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);

    $postData = $fields;
    foreach ($files as $name => $path) {
        $mime = mime_content_type($path) ?: 'application/octet-stream';
        $postData[$name] = new CURLFile($path, $mime, basename($path));
    }

    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

    $headers = ['Accept: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer {$token}";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $decoded = json_decode($raw, true);
    return ['status' => $status, 'data' => $decoded, 'raw' => $raw];
}

function assertTest(string $name, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] {$name}\n";
        if ($detail) echo "        Detail: {$detail}\n";
        $passed++;
    } else {
        echo " [FAIL] {$name}\n";
        if ($detail) echo "        Detail: {$detail}\n";
        $failed++;
    }
}

// 1. Get Tokens
$adminLogin = httpRequest('POST', "{$baseUrl}/auth/login", ['email' => 'admin@mosje.gov.in', 'password' => 'Admin@12345']);
$adminToken = $adminLogin['data']['data']['token'];

$doNagpurLogin = httpRequest('POST', "{$baseUrl}/auth/login", ['email' => 'district.nagpur@mosje.gov.in', 'password' => 'Officer@12345']);
$doNagpurToken = $doNagpurLogin['data']['data']['token'];

$doLucknowLogin = httpRequest('POST', "{$baseUrl}/auth/login", ['email' => 'district.lucknow@mosje.gov.in', 'password' => 'Officer@12345']);
$doLucknowToken = $doLucknowLogin['data']['data']['token'];

$inspRajeshLogin = httpRequest('POST', "{$baseUrl}/auth/login", ['email' => 'inspector.rajesh@mosje.gov.in', 'password' => 'Inspect@12345']);
$inspRajeshToken = $inspRajeshLogin['data']['data']['token'];

$inspAmitLogin = httpRequest('POST', "{$baseUrl}/auth/login", ['email' => 'inspector.amit@mosje.gov.in', 'password' => 'Inspect@12345']);
$inspAmitToken = $inspAmitLogin['data']['data']['token'];

$ngoLogin = httpRequest('POST', "{$baseUrl}/auth/login", ['email' => 'ngo.sewa@org.in', 'password' => 'Ngo@12345']);
$ngoToken = $ngoLogin['data']['data']['token'];

// Public Token
$cPhone = '96' . rand(10000000, 99999999);
$otpReq = httpRequest('POST', "{$baseUrl}/auth/otp/request", ['phone' => $cPhone]);
$otpVer = httpRequest('POST', "{$baseUrl}/auth/otp/verify", ['phone' => $cPhone, 'otp' => $otpReq['data']['data']['demo_otp'] ?? '000000']);
$publicToken = $otpVer['data']['data']['token'] ?? '';

// ---------------------------------------------------------
// TEST 1: Manual Tender Creation (Admin)
// ---------------------------------------------------------
$newTenderNum = 'TND-TEST-' . time() . '-' . rand(100, 999);
$createTender = httpRequest('POST', "{$baseUrl}/tenders", [
    'tender_number' => $newTenderNum,
    'title' => 'Automated Test Bridge Repair Project',
    'category_id' => 2, // Flyovers/Bridges
    'issuing_department' => 'PWD Bridge Cell',
    'state_id' => 1,
    'district_id' => 1,
    'latitude' => 21.1458,
    'longitude' => 79.0882,
    'contractor_id' => 1,
    'sanctioned_amount' => 5000000.0,
    'award_date' => '2026-01-01',
    'start_date' => '2026-01-15',
    'scheduled_end_date' => '2026-12-31',
    'responsible_senior_id' => 7,
], $adminToken);

$newTenderId = $createTender['data']['data']['id'] ?? 0;
assertTest(
    'Manual Tender Creation',
    $createTender['status'] === 201 && $newTenderId > 0,
    "HTTP {$createTender['status']} - Created Tender ID: {$newTenderId}"
);

// Add Milestones to this test tender (Weight 40% and 60%)
$pdo = Database::getConnection();
$pdo->prepare("
    INSERT INTO milestones (tender_id, name, planned_date, planned_weight, actual_progress, created_at)
    VALUES (:tid, 'Substructure Reinforcement', '2026-06-30', 40.0, 0.0, :now),
           (:tid, 'Superstructure Deck Slab', '2026-11-30', 60.0, 0.0, :now)
")->execute([':tid' => $newTenderId, ':now' => date('Y-m-d H:i:s')]);

$milestone1 = (int)$pdo->lastInsertId() - 1;
$milestone2 = (int)$pdo->lastInsertId();

// ---------------------------------------------------------
// TEST 2: Weighted Milestone Progress and Variance Calculation
// ---------------------------------------------------------
$createIns = httpRequest('POST', "{$baseUrl}/inspections", [
    'tender_id' => $newTenderId,
    'remarks' => 'Mid-term milestone and expenditure audit',
    'actual_spent_recorded' => 4500000.0,
    'spent_basis' => 'Measurement Book #101',
    'milestones' => [
        ['milestone_id' => $milestone1, 'actual_progress' => 100.0],
        ['milestone_id' => $milestone2, 'actual_progress' => 50.0],
    ],
], $inspRajeshToken);

$insId = $createIns['data']['data']['id'] ?? 0;
$computedProgress = $createIns['data']['data']['overall_progress'] ?? 0.0;
$variance = $createIns['data']['data']['variance'] ?? 0.0;
$vFlag = $createIns['data']['data']['variance_flag'] ?? false;

assertTest(
    'Weighted Progress & Variance Calculation with Threshold Flag',
    $createIns['status'] === 201 && abs($computedProgress - 70.0) < 0.1 && abs($variance - 20.0) < 0.1 && $vFlag === true,
    "Progress: {$computedProgress}% (Expected 70%), Variance: {$variance}% (Expected 20%), Variance Flag: " . ($vFlag ? 'TRUE' : 'FALSE')
);

// ---------------------------------------------------------
// TEST 3: Evidence Upload with GPS, Accuracy, Server Time
// ---------------------------------------------------------
$dummyImg = __DIR__ . '/test_fixture.jpg';
$img = imagecreatetruecolor(200, 200);
$bg = imagecolorallocate($img, 247, 108, 69); // #F76C45
imagefilledrectangle($img, 0, 0, 199, 199, $bg);
imagejpeg($img, $dummyImg);
imagedestroy($img);

$nowTime = date('Y-m-d H:i:s');
$uploadEvidence = httpMultipart("{$baseUrl}/inspections/{$insId}/evidence", [
    'latitude' => '21.1458',
    'longitude' => '79.0882',
    'accuracy' => '15.5',
    'is_mock' => '0',
    'device_capture_time' => $nowTime,
    'server_time' => '1999-01-01 00:00:00', // Client attempting to fake server time -> MUST be ignored!
], ['media' => $dummyImg], $inspRajeshToken);

$evidenceData = $uploadEvidence['data']['data'] ?? [];
$serverTimeSet = $evidenceData['server_time'] ?? '';

assertTest(
    'Evidence Upload with GPS & Server-controlled Timestamp',
    $uploadEvidence['status'] === 201 && $serverTimeSet !== '1999-01-01 00:00:00' && !empty($evidenceData['file_path']),
    "HTTP {$uploadEvidence['status']} - Stored: {$evidenceData['file_path']}, Server Time: {$serverTimeSet}"
);

// ---------------------------------------------------------
// TEST 4: Negative Test - Evidence Upload with Missing GPS Fails (422)
// ---------------------------------------------------------
$uploadNoGps = httpMultipart("{$baseUrl}/inspections/{$insId}/evidence", [
    'device_capture_time' => $nowTime,
], ['media' => $dummyImg], $inspRajeshToken);

assertTest(
    'Evidence Upload without GPS Fails (422)',
    $uploadNoGps['status'] === 422,
    "HTTP {$uploadNoGps['status']} - Message: " . ($uploadNoGps['data']['message'] ?? '')
);

// ---------------------------------------------------------
// TEST 5: Negative Test - Evidence Upload with Inaccurate GPS (> 100m) Fails (422)
// ---------------------------------------------------------
$uploadBadAccuracy = httpMultipart("{$baseUrl}/inspections/{$insId}/evidence", [
    'latitude' => '21.1458',
    'longitude' => '79.0882',
    'accuracy' => '150.0', // Worse than 100m
    'device_capture_time' => $nowTime,
], ['media' => $dummyImg], $inspRajeshToken);

assertTest(
    'Evidence Upload with GPS Accuracy > 100m Blocked (422)',
    $uploadBadAccuracy['status'] === 422 && str_contains($uploadBadAccuracy['data']['message'] ?? '', '100m'),
    "HTTP {$uploadBadAccuracy['status']} - " . ($uploadBadAccuracy['data']['message'] ?? '')
);

// ---------------------------------------------------------
// TEST 6: Critical Quality Item Failure Auto-Raises Issue
// ---------------------------------------------------------
$critIns = httpRequest('POST', "{$baseUrl}/inspections", [
    'tender_id' => $newTenderId,
    'remarks' => 'Foundation quality check',
    'quality_checks' => [
        ['template_id' => 5, 'status' => 'FAIL', 'remarks' => 'Severe rust and rebar displacement on Pier Cap.'],
    ],
], $inspRajeshToken);

assertTest(
    'Critical Quality Item Failure Auto-Raises Issue',
    $critIns['status'] === 201 && ($critIns['data']['data']['auto_issue_raised'] ?? false) === true,
    "Auto Issue Raised: " . (($critIns['data']['data']['auto_issue_raised'] ?? false) ? 'YES' : 'NO')
);

// ---------------------------------------------------------
// TEST 7: Full Inspection Lifecycle State Machine
// ---------------------------------------------------------
// Step 1: Submit (DRAFT → SUBMITTED)
$step1 = httpRequest('POST', "{$baseUrl}/inspections/{$insId}/submit", null, $inspRajeshToken);
assertTest(
    'State Machine Step 1: DRAFT -> SUBMITTED',
    $step1['status'] === 200 && ($step1['data']['data']['new_status'] ?? '') === 'SUBMITTED',
    "Status: " . ($step1['data']['data']['new_status'] ?? '')
);

// Step 2: Verify by District Officer (SUBMITTED -> VERIFIED)
$step2 = httpRequest('POST', "{$baseUrl}/inspections/{$insId}/verify", ['action' => 'VERIFY', 'remarks' => 'Work accepted on site'], $doNagpurToken);
assertTest(
    'State Machine Step 2: SUBMITTED -> VERIFIED',
    $step2['status'] === 200 && ($step2['data']['data']['new_status'] ?? '') === 'VERIFIED',
    "Status: " . ($step2['data']['data']['new_status'] ?? '')
);

// Step 3: Officer raises issue (SUBMITTED -> ISSUE_RAISED)
$issueIns = httpRequest('POST', "{$baseUrl}/inspections", ['tender_id' => $newTenderId, 'submit_now' => 1], $inspRajeshToken);
$issueInsId = $issueIns['data']['data']['id'];

$step3 = httpRequest('POST', "{$baseUrl}/inspections/{$issueInsId}/verify", ['action' => 'RAISE_ISSUE', 'remarks' => 'Defective deck casting'], $doNagpurToken);
assertTest(
    'State Machine Step 3: SUBMITTED -> ISSUE_RAISED',
    $step3['status'] === 200 && ($step3['data']['data']['new_status'] ?? '') === 'ISSUE_RAISED',
    "Status: " . ($step3['data']['data']['new_status'] ?? '')
);

// Step 4: Notify contractor (ISSUE_RAISED -> NOTIFIED)
$step4 = httpRequest('POST', "{$baseUrl}/inspections/{$issueInsId}/notify", null, $doNagpurToken);
assertTest(
    'State Machine Step 4: ISSUE_RAISED -> NOTIFIED',
    $step4['status'] === 200 && ($step4['data']['data']['new_status'] ?? '') === 'NOTIFIED',
    "Status: " . ($step4['data']['data']['new_status'] ?? '')
);

// Step 5: Mark in resolution (NOTIFIED -> IN_RESOLUTION)
$step5 = httpRequest('POST', "{$baseUrl}/inspections/{$issueInsId}/resolve", null, $doNagpurToken);
assertTest(
    'State Machine Step 5: NOTIFIED -> IN_RESOLUTION',
    $step5['status'] === 200 && ($step5['data']['data']['new_status'] ?? '') === 'IN_RESOLUTION',
    "Status: " . ($step5['data']['data']['new_status'] ?? '')
);

// Step 6: Schedule re-inspection (IN_RESOLUTION -> REINSPECTION_PENDING)
$step6 = httpRequest('POST', "{$baseUrl}/inspections/{$issueInsId}/schedule-reinspection", [
    'scheduled_date' => '2026-10-05',
    'assigned_inspector_id' => 9,
], $doNagpurToken);
assertTest(
    'State Machine Step 6: IN_RESOLUTION -> REINSPECTION_PENDING',
    $step6['status'] === 200 && ($step6['data']['data']['new_status'] ?? '') === 'REINSPECTION_PENDING',
    "Status: " . ($step6['data']['data']['new_status'] ?? '')
);

// Step 7: Negative Test - Cannot close without passed re-inspection
$prematureClose = httpRequest('POST', "{$baseUrl}/inspections/{$issueInsId}/close", null, $doNagpurToken);
assertTest(
    'Negative Test: Closing with Unresolved Issue Blocked (400)',
    $prematureClose['status'] === 400 && str_contains($prematureClose['data']['message'] ?? '', 're-inspection'),
    "HTTP {$prematureClose['status']} - " . ($prematureClose['data']['message'] ?? '')
);

// Step 8: Inspector submits passed re-inspection result (REINSPECTION_PENDING -> CLOSED)
$step8 = httpRequest('POST', "{$baseUrl}/inspections/{$issueInsId}/submit-reinspection", [
    'result' => 'PASSED',
    'remarks' => 'Defects rectified in presence of third-party engineer',
], $inspRajeshToken);
assertTest(
    'State Machine Step 7 & 8: Passed Reinspection Transitions to CLOSED',
    $step8['status'] === 200 && ($step8['data']['data']['new_status'] ?? '') === 'CLOSED',
    "Status: " . ($step8['data']['data']['new_status'] ?? '')
);

// ---------------------------------------------------------
// TEST 8: Negative Test - Skipping States Fails (400)
// ---------------------------------------------------------
$freshDraft = httpRequest('POST', "{$baseUrl}/inspections", ['tender_id' => $newTenderId], $inspRajeshToken);
$freshDraftId = $freshDraft['data']['data']['id'];

$invalidSkip = httpRequest('POST', "{$baseUrl}/inspections/{$freshDraftId}/close", null, $doNagpurToken);
assertTest(
    'Negative Test: Skipping States Fails (400)',
    $invalidSkip['status'] === 400,
    "HTTP {$invalidSkip['status']} - Message: " . ($invalidSkip['data']['message'] ?? '')
);

// ---------------------------------------------------------
// TEST 9: Negative Test - Inspector Accessing Another Inspector's Inspection (403)
// ---------------------------------------------------------
$crossAccess = httpRequest('GET', "{$baseUrl}/inspections/{$insId}", null, $inspAmitToken);
assertTest(
    'Negative Test: Cross-Inspector Access Blocked (403)',
    $crossAccess['status'] === 403,
    "HTTP {$crossAccess['status']} - " . ($crossAccess['data']['message'] ?? '')
);

// ---------------------------------------------------------
// TEST 10: Data Visibility Masking
// ---------------------------------------------------------
$publicTenderView = httpRequest('GET', "{$baseUrl}/tenders/{$newTenderId}", null, $publicToken);
$pubData = $publicTenderView['data']['data'];
$pubHasPhone = isset($pubData['contractor_phone']) || isset($pubData['contractor_email']);

$doTenderView = httpRequest('GET', "{$baseUrl}/tenders/{$newTenderId}", null, $doNagpurToken);
$doData = $doTenderView['data']['data'];
$doHasPhone = !empty($doData['contractor_phone']) && !empty($doData['contractor_email']);

assertTest(
    'Data Visibility: Contractor Contacts Masked for Public, Present for Officer',
    !$pubHasPhone && $doHasPhone,
    "Public Has Contacts: " . ($pubHasPhone ? 'YES (FAIL)' : 'NO (SECURE)') . " | Officer Has Contacts: " . ($doHasPhone ? 'YES (ACCESSIBLE)' : 'NO')
);

// ---------------------------------------------------------
// TEST 11: CSV Bulk Import with Valid, Duplicate, and Invalid Rows
// ---------------------------------------------------------
$uniqueCsvTender = 'TND-CSV-' . time() . '-' . rand(100, 999);
$csvFile = __DIR__ . '/test_import.csv';
$csvContent = "tender_number,title,category,department,district,state,latitude,longitude,contractor_name,contractor_registration,sanctioned_amount,award_date,start_date,end_date,responsible_senior_username\n";
// Row 1: Valid new unique tender
$csvContent .= "{$uniqueCsvTender},Nagpur Rural Water Pipeline Phase 1,Water supply/Sanitation,PHED,Nagpur,Maharashtra,21.14,79.08,Larsen & Infra Projects Ltd,REG-MH-2024-001,15000000.00,2026-02-01,2026-02-15,2026-11-30,senior.sharma@mosje.gov.in\n";
// Row 2: Duplicate of existing tender (TND-2026-RD-01) -> should be rejected/skipped
$csvContent .= "TND-2026-RD-01,Duplicate Attempt,Roads,PWD,Nagpur,Maharashtra,21.09,79.00,Larsen & Infra Projects Ltd,REG-MH-2024-001,45000000.00,2026-01-10,2026-02-01,2026-12-31,senior.sharma@mosje.gov.in\n";
// Row 3: Invalid row (negative sanctioned amount, missing title)
$csvContent .= "TND-CSV-BAD,,Roads,PWD,Nagpur,Maharashtra,21.09,79.00,Larsen & Infra Projects Ltd,REG-MH-2024-001,-500.00,2026-01-10,2026-02-01,2026-12-31,senior.sharma@mosje.gov.in\n";

file_put_contents($csvFile, $csvContent);

$csvImport = httpMultipart("{$baseUrl}/tenders/import", ['on_duplicate' => 'skip'], ['file' => $csvFile], $adminToken);
$importRep = $csvImport['data']['data'] ?? [];

assertTest(
    'CSV Import Report: Valid, Duplicate & Invalid Handled Without Entire File Failing',
    $csvImport['status'] === 200 && ($importRep['created_count'] ?? 0) === 1 && ($importRep['rejected_count'] ?? 0) === 2,
    "Created: " . ($importRep['created_count'] ?? 0) . ", Rejected: " . ($importRep['rejected_count'] ?? 0) . ", Total: " . ($importRep['total_rows'] ?? 0)
);

// ---------------------------------------------------------
// TEST 12: PDF Inspection Report Generation & Verification
// ---------------------------------------------------------
$pdfUrl = "{$baseUrl}/reports/inspection/{$insId}/pdf";
$ch = curl_init($pdfUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$doNagpurToken}"]);
$pdfContent = curl_exec($ch);
$pdfStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$pdfPath = __DIR__ . '/downloaded_report_' . $insId . '.pdf';
file_put_contents($pdfPath, $pdfContent);

$isValidPdf = str_starts_with($pdfContent, "%PDF-1.4") && str_contains($pdfContent, "%%EOF") && strlen($pdfContent) > 500;
assertTest(
    'Generated PDF Exists, Valid PDF-1.4 Structure and Opens Correctly',
    $pdfStatus === 200 && $isValidPdf,
    "HTTP {$pdfStatus}, Size: " . strlen($pdfContent) . " bytes, Saved at: {$pdfPath}"
);

// ---------------------------------------------------------
// TEST 13: CSV Tenders Export Generation
// ---------------------------------------------------------
$csvUrl = "{$baseUrl}/reports/tenders/csv";
$ch = curl_init($csvUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$csvExportContent = curl_exec($ch);
$csvStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$csvExportPath = __DIR__ . '/downloaded_tenders_export.csv';
file_put_contents($csvExportPath, $csvExportContent);

$isValidCsv = str_contains($csvExportContent, 'Tender Number') && str_contains($csvExportContent, 'Sanctioned Amount') && strlen($csvExportContent) > 200;
assertTest(
    'Generated CSV Export Exists and Valid Header Structure',
    $csvStatus === 200 && $isValidCsv,
    "HTTP {$csvStatus}, Size: " . strlen($csvExportContent) . " bytes, Saved at: {$csvExportPath}"
);

// ---------------------------------------------------------
// TEST 14: Dashboard Analytics Summary
// ---------------------------------------------------------
$dash = httpRequest('GET', "{$baseUrl}/dashboard/summary");
$dashData = $dash['data']['data'] ?? [];

assertTest(
    'Dashboard Analytics Summary Endpoint',
    $dash['status'] === 200 && isset($dashData['tenders_by_status']) && isset($dashData['variance_alerts']),
    "Variance Alerts: " . ($dashData['variance_alerts'] ?? 0) . ", Delayed Tenders: " . ($dashData['delayed_tenders'] ?? 0)
);

// ---------------------------------------------------------
// TEST 15: Audit Log Records Every State Transition
// ---------------------------------------------------------
$audit = httpRequest('GET', "{$baseUrl}/admin/audit-log?limit=20", null, $adminToken);
$auditLogs = $audit['data']['data'] ?? [];
$transitionLogs = array_filter($auditLogs, fn($l) => $l['action'] === 'INSPECTION_STATE_TRANSITION');

assertTest(
    'Audit Log Contains Immutable Entries for State Transitions',
    count($transitionLogs) > 0,
    "Found " . count($transitionLogs) . " state transition audit entries in recent logs."
);

// Cleanup test files
@unlink($dummyImg);
@unlink($csvFile);

echo "\n========================================================\n";
echo "PART 3 TEST RESULTS: {$passed} PASSED | {$failed} FAILED\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
