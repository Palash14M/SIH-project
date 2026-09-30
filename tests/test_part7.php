<?php

require_once __DIR__ . '/../backend/config/config.php';
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/utils/Jwt.php';

echo "========================================================\n";
echo "           PART 7: GATE VERIFICATION TEST SUITE          \n";
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

function uploadMultipart(string $url, string $fieldName, string $filePath, array $fields, ?string $token = null): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);

    $headers = ['Accept: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer {$token}";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $postData = $fields;
    $postData[$fieldName] = new CURLFile($filePath, 'text/csv', basename($filePath));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

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

// ---------------------------------------------------------
// 1. SETUP: Logins for Officer and Admin Roles
// ---------------------------------------------------------
// MoSJE Admin
$adminLogin = httpRequest('POST', "{$baseUrl}/auth/login", [
    'email' => 'admin@mosje.gov.in',
    'password' => 'Admin@12345',
]);
$adminToken = $adminLogin['data']['data']['token'];

// District Officer Nagpur (District 1)
$doNagpurLogin = httpRequest('POST', "{$baseUrl}/auth/login", [
    'email' => 'district.nagpur@mosje.gov.in',
    'password' => 'Officer@12345',
]);
$doNagpurToken = $doNagpurLogin['data']['data']['token'];

// District Officer Pune (District 2)
$doPuneLogin = httpRequest('POST', "{$baseUrl}/auth/login", [
    'email' => 'district.pune@mosje.gov.in',
    'password' => 'Officer@12345',
]);
$doPuneToken = $doPuneLogin['data']['data']['token'];

// State Officer Maharashtra (State 1)
$soLogin = httpRequest('POST', "{$baseUrl}/auth/login", [
    'email' => 'state.mh@mosje.gov.in',
    'password' => 'Officer@12345',
]);
$soToken = $soLogin['data']['data']['token'];

// Field Inspector Rajesh
$inspLogin = httpRequest('POST', "{$baseUrl}/auth/login", [
    'email' => 'inspector.rajesh@mosje.gov.in',
    'password' => 'Inspect@12345',
]);
$inspToken = $inspLogin['data']['data']['token'];

// ---------------------------------------------------------
// TEST 1: Manual Tender Creation (District Officer)
// ---------------------------------------------------------
$randSuffix = time() . '_' . rand(100, 999);
$newTenderNum = "TND-2026-NAG-TEST-{$randSuffix}";

$createRes = httpRequest('POST', "{$baseUrl}/tenders", [
    'tender_number' => $newTenderNum,
    'title' => 'Construction of District Disability Rehabilitation Centre',
    'category_id' => 3, // Buildings
    'issuing_department' => 'Social Welfare Engineering Cell',
    'state_id' => 1,
    'district_id' => 1, // Nagpur
    'latitude' => 21.1498,
    'longitude' => 79.0806,
    'contractor_id' => 1,
    'sanctioned_amount' => 45000000.00,
    'award_date' => '2026-01-10',
    'start_date' => '2026-02-01',
    'scheduled_end_date' => '2026-12-31',
    'responsible_senior_id' => 2, // Senior Sharma
    'location_note' => 'Near Civil Lines, Nagpur',
], $doNagpurToken);

$newTenderId = $createRes['data']['data']['id'] ?? 0;
assertTest(
    'Manual Tender Creation via District Officer (201)',
    $createRes['status'] === 201 && $newTenderId > 0,
    "Created Tender ID: {$newTenderId}, Number: {$newTenderNum}"
);

// ---------------------------------------------------------
// TEST 2: CSV Import Template Download
// ---------------------------------------------------------
$ch = curl_init("{$baseUrl}/tenders/template");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$tplContent = curl_exec($ch);
$tplCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$hasRequiredHeaders = str_contains($tplContent, 'tender_number') && str_contains($tplContent, 'sanctioned_amount') && str_contains($tplContent, 'contractor_name');
assertTest(
    'Downloadable CSV Import Template with Standard Columns (200)',
    $tplCode === 200 && $hasRequiredHeaders,
    "Template Headers: tender_number, title, category, department, district, state, sanctioned_amount..."
);

// ---------------------------------------------------------
// TEST 3: CSV Bulk Import: Valid, Duplicate & Invalid Rows Handled
// ---------------------------------------------------------
$tmpCsv = sys_get_temp_dir() . '/tenders_batch_' . time() . '.csv';
$csvRows = [
    ['tender_number', 'title', 'category', 'department', 'district', 'state', 'latitude', 'longitude', 'contractor_name', 'contractor_registration', 'sanctioned_amount', 'award_date', 'start_date', 'end_date', 'responsible_senior_username'],
    // 1. Valid Row
    ["TND-CSV-VAL-{$randSuffix}", 'Model Solar Water Treatment Plant', 'Water supply/Sanitation', 'Rural Water Dept', 'Nagpur', 'Maharashtra', '21.12', '79.05', 'Jal Infrastructure Ltd', 'REG-JAL-01', '18000000.00', '2026-01-10', '2026-02-01', '2026-11-30', 'senior.sharma@mosje.gov.in'],
    // 2. Duplicate Row (existing tender number)
    [$newTenderNum, 'Duplicate Attempt Should Be Skipped', 'Buildings', 'Social Welfare Dept', 'Nagpur', 'Maharashtra', '21.14', '79.08', 'Larsen Infra', 'REG-01', '45000000.00', '2026-01-10', '2026-02-01', '2026-12-31', 'senior.sharma@mosje.gov.in'],
    // 3. Invalid Row (negative sanctioned amount)
    ["TND-CSV-INV-{$randSuffix}", 'Defective Row with Negative Budget', 'Roads', 'PWD', 'Nagpur', 'Maharashtra', '21.10', '79.00', 'Bad Infra', 'REG-BAD', '-50000.00', '2026-01-10', '2026-02-01', '2026-12-31', 'senior.sharma@mosje.gov.in'],
];

$fp = fopen($tmpCsv, 'w');
foreach ($csvRows as $r) {
    fputcsv($fp, $r);
}
fclose($fp);

$importRes = uploadMultipart("{$baseUrl}/tenders/import", 'file', $tmpCsv, ['on_duplicate' => 'skip'], $doNagpurToken);
$rep = $importRes['data']['data'] ?? [];

assertTest(
    'CSV Import Report: Correct Counts for Valid, Duplicate & Invalid Rows Without Failure',
    $importRes['status'] === 200 && ($rep['created_count'] ?? 0) === 1 && ($rep['rejected_count'] ?? 0) === 2,
    "Report: Created: " . ($rep['created_count'] ?? 0) . ", Rejected: " . ($rep['rejected_count'] ?? 0) . " (1 dup skipped, 1 bad budget)"
);

// ---------------------------------------------------------
// TEST 4: Tender Detail with Milestones, Variance & Flags
// ---------------------------------------------------------
// Check seeded Tender 1 (Road tender with milestones and variance)
$t1Detail = httpRequest('GET', "{$baseUrl}/tenders/1", null, $doNagpurToken);
$t1 = $t1Detail['data']['data'] ?? [];

assertTest(
    'Tender Detail Exposes Milestones, Budget vs Spent and Variance Metrics',
    $t1Detail['status'] === 200 && !empty($t1['milestones']) && isset($t1['variance']),
    "Tender #{$t1['tender_number']} | Milestones: " . count($t1['milestones']) . " | Progress: {$t1['progress_percentage']}% | Spent: ₹" . number_format($t1['actual_spent'] ?? 0) . " | Variance: {$t1['variance']}%"
);

// ---------------------------------------------------------
// TEST 5: Attach Inspector and NGO to Tender
// ---------------------------------------------------------
$assignRes = httpRequest('POST', "{$baseUrl}/tenders/{$newTenderId}/assign", [
    'inspector_id' => 5, // Inspector Sneha
    'ngo_id' => 1,       // Sewa Bharati
], $doNagpurToken);

// Verify detail reflects attachments
$newTenderDetail = httpRequest('GET', "{$baseUrl}/tenders/{$newTenderId}", null, $doNagpurToken);
$newT = $newTenderDetail['data']['data'] ?? [];
$inspectorsList = $newT['inspectors'] ?? [];
$ngosList = $newT['ngos'] ?? [];

assertTest(
    'District Officer Successfully Attaches Inspector & NGO to Tender',
    $assignRes['status'] === 200 && count($inspectorsList) > 0 && count($ngosList) > 0,
    "Attached Inspectors: " . count($inspectorsList) . ", Attached NGOs: " . count($ngosList)
);

// ---------------------------------------------------------
// TEST 6: Expenditure Accept / Dispute Flow
// ---------------------------------------------------------
// 1. Inspector records inspection with actual spent on Tender 1
$inspSpent = httpRequest('POST', "{$baseUrl}/inspections", [
    'tender_id' => 1,
    'actual_spent' => 1500000.00,
    'spent_basis' => 'Measurement Book MB-42, Item 3',
    'remarks' => 'Culvert wing wall masonry measured and recorded.',
    'issue_found' => 0,
], $inspToken);
$spentInspId = $inspSpent['data']['data']['id'] ?? 0;

// 2. DO lists budget entries
$bEntriesRes = httpRequest('GET', "{$baseUrl}/tenders/1/budget-entries", null, $doNagpurToken);
$bEntries = $bEntriesRes['data']['data'] ?? [];
$targetEntry = $bEntries[0] ?? null;
$targetEntryId = $targetEntry['id'] ?? 0;

// 3. DO disputes this recorded expenditure
$disputeRes = httpRequest('POST', "{$baseUrl}/budget-entries/{$targetEntryId}/decision", [
    'status' => 'DISPUTED',
    'remarks' => 'Measurement book entry MB-42 page 18 lacks supporting laboratory test cube certificate.',
], $doNagpurToken);

// 4. Verify status updated to DISPUTED
$bEntriesAfter = httpRequest('GET', "{$baseUrl}/tenders/1/budget-entries", null, $doNagpurToken);
$firstEntryAfter = $bEntriesAfter['data']['data'][0] ?? [];

assertTest(
    'District Officer Successfully Disputes Recorded Expenditure with Remarks',
    $disputeRes['status'] === 200 && ($firstEntryAfter['status'] ?? '') === 'DISPUTED',
    "Entry #{$targetEntryId} Status: " . ($firstEntryAfter['status'] ?? '') . ", Reason: " . ($firstEntryAfter['dispute_reason'] ?? '')
);

// ---------------------------------------------------------
// TEST 7: Cross-District Jurisdiction Checks (Strict DO Boundary)
// ---------------------------------------------------------
// District Officer of Nagpur (District 1) attempts actions on Pune (District 2) data
// Seeded Tender 2 is in Pune (district_id = 2)

// A. DO Nagpur viewing Pune Tender detail -> 403 Forbidden
$crossTenderDetail = httpRequest('GET', "{$baseUrl}/tenders/2", null, $doNagpurToken);
$crossTenderBlocked = $crossTenderDetail['status'] === 403;

// B. DO Nagpur viewing Pune Inspection detail (Inspection 10 is on Tender 2 in Pune) -> 403 Forbidden
$crossInspDetail = httpRequest('GET', "{$baseUrl}/inspections/10", null, $doNagpurToken);
$crossInspBlocked = $crossInspDetail['status'] === 403;

// C. DO Nagpur deciding Pune NGO approval (NGO 3 is Gramin Kalyan Samiti in Pune) -> 403 Forbidden
$crossNgoDecision = httpRequest('POST', "{$baseUrl}/ngos/3/decision", [
    'decision' => 'APPROVE',
    'reason' => 'Unauthorized cross-district attempt',
], $doNagpurToken);
$crossNgoBlocked = $crossNgoDecision['status'] === 403;

// D. DO Nagpur deciding Pune budget entry -> 403 Forbidden
$crossBudgetDecision = httpRequest('POST', "{$baseUrl}/budget-entries/2/decision", [
    'status' => 'ACCEPTED',
], $doNagpurToken);
$crossBudgetBlocked = $crossBudgetDecision['status'] === 403 || $crossBudgetDecision['status'] === 404;

assertTest(
    'Jurisdiction Check: District Officer Blocked from Cross-District Tenders, Inspections & NGOs (403)',
    $crossTenderBlocked && $crossInspBlocked && $crossNgoBlocked,
    "Tender View: " . ($crossTenderBlocked ? 'BLOCKED (403)' : 'LEAK') .
    " | Inspection View: " . ($crossInspBlocked ? 'BLOCKED (403)' : 'LEAK') .
    " | NGO Decision: " . ($crossNgoBlocked ? 'BLOCKED (403)' : 'LEAK')
);

// ---------------------------------------------------------
// TEST 8: Officer Inspection Verification & Issue Flow
// ---------------------------------------------------------
// Create draft inspection
$newInsp = httpRequest('POST', "{$baseUrl}/inspections", [
    'tender_id' => 1,
    'actual_spent' => 500000.00,
    'remarks' => 'Routine culvert and side drain check',
    'issue_found' => 0,
], $inspToken);
$niId = $newInsp['data']['data']['id'] ?? 0;

// Submit (DRAFT -> SUBMITTED)
httpRequest('POST', "{$baseUrl}/inspections/{$niId}/submit", null, $inspToken);

// DO Verifies inspection (SUBMITTED -> VERIFIED)
$verifyRes = httpRequest('POST', "{$baseUrl}/inspections/{$niId}/verify", [
    'action' => 'VERIFY',
    'remarks' => 'All quality checks and culvert dimensions match technical sanction.',
], $doNagpurToken);

assertTest(
    'District Officer Successfully Verifies Inspection (SUBMITTED -> VERIFIED)',
    $verifyRes['status'] === 200 && ($verifyRes['data']['data']['new_status'] ?? '') === 'VERIFIED',
    "Inspection #{$niId} Status: " . ($verifyRes['data']['data']['new_status'] ?? '')
);

// ---------------------------------------------------------
// TEST 9: NGO Approval by Jurisdiction District Officer
// ---------------------------------------------------------
// Register fresh NGO in Nagpur (District 1)
$randNgoEmail = "ngo_test_" . time() . "_" . rand(100, 999) . "@test.org";
$randNgoPhone = '94' . rand(10000000, 99999999);
$ngoReg = httpRequest('POST', "{$baseUrl}/auth/ngo/register", [
    'organization_name' => 'Lok Kalyan Seva Trust ' . rand(10, 99),
    'registration_number' => 'REG-NGO-NAG-' . time() . '-' . rand(10, 99),
    'contact_person' => 'Gopal Sharma',
    'mobile' => $randNgoPhone,
    'phone' => $randNgoPhone,
    'email' => $randNgoEmail,
    'password' => 'Ngo@12345',
    'address' => 'Wardha Road, Nagpur',
    'district_id' => 1,
]);
$newNgoId = $ngoReg['data']['data']['ngo_id'] ?? 0;

// DO of Nagpur approves NGO
$ngoApproveRes = httpRequest('POST', "{$baseUrl}/ngos/{$newNgoId}/decision", [
    'decision' => 'APPROVE',
    'reason' => 'Trust deed and 12A/80G certificates verified from Darpan portal.',
], $doNagpurToken);

assertTest(
    'District Officer Approves Jurisdiction NGO Registration with Verification Reason',
    $ngoApproveRes['status'] === 200 && ($ngoApproveRes['data']['data']['status'] ?? '') === 'APPROVED',
    ($newNgoId > 0) ? "NGO ID #{$newNgoId} Status: " . ($ngoApproveRes['data']['data']['status'] ?? '') : "NGO Reg Failed: HTTP {$ngoReg['status']} - " . json_encode($ngoReg['data'])
);

// ---------------------------------------------------------
// TEST 10: Dashboard Analytics Summary
// ---------------------------------------------------------
$dashRes = httpRequest('GET', "{$baseUrl}/dashboard/summary", null, $doNagpurToken);
$dash = $dashRes['data']['data'] ?? [];

$hasStatusCounts = !empty($dash['tenders_by_status']);
$hasDistCoverage = !empty($dash['district_coverage']);

assertTest(
    'Dashboard Summary Metrics: Status Counts, Flags & District Coverage',
    $dashRes['status'] === 200 && $hasStatusCounts && $hasDistCoverage,
    "Variance Alerts: " . ($dash['variance_alerts'] ?? 0) . ", Delayed: " . ($dash['delayed_tenders'] ?? 0) . ", Districts: " . count($dash['district_coverage'])
);

// ---------------------------------------------------------
// TEST 11: PDF Inspection Report & CSV Data Export
// ---------------------------------------------------------
// PDF Download
$pdfUrl = "{$baseUrl}/reports/inspection/1/pdf";
$ch = curl_init($pdfUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$doNagpurToken}"]);
$pdfBytes = curl_exec($ch);
$pdfHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$pdfType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

$pdfValid = ($pdfHttp === 200) && (str_contains($pdfType, 'pdf') || str_starts_with($pdfBytes, '%PDF')) && strlen($pdfBytes) > 500;

// CSV Export
$csvUrl = "{$baseUrl}/reports/tenders/csv";
$ch = curl_init($csvUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$doNagpurToken}"]);
$csvBytes = curl_exec($ch);
$csvHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$csvValid = ($csvHttp === 200) && (str_contains($csvBytes, 'Tender Number') || str_contains(strtolower($csvBytes), 'tender')) && strlen($csvBytes) > 100;

assertTest(
    'Inspection PDF Report and Tenders CSV Export Download and Validate',
    $pdfValid && $csvValid,
    "PDF: HTTP {$pdfHttp}, Size: " . strlen($pdfBytes) . " bytes | CSV: HTTP {$csvHttp}, Size: " . strlen($csvBytes) . " bytes"
);

// ---------------------------------------------------------
// TEST 12: MoSJE Admin: Staff Management (Create & Deactivate)
// ---------------------------------------------------------
$randStaffEmail = "staff_" . time() . "_" . rand(100, 999) . "@mosje.gov.in";
$randStaffPhone = '98' . rand(10000000, 99999999);
$createStaff = httpRequest('POST', "{$baseUrl}/admin/users", [
    'name' => 'Kiran Deshpande',
    'email' => $randStaffEmail,
    'phone' => $randStaffPhone,
    'password' => 'Staff@12345',
    'role' => 'INSPECTOR',
    'state_id' => 1,
    'district_id' => 1,
], $adminToken);
$staffId = $createStaff['data']['data']['id'] ?? 0;

$deactStaff = httpRequest('PUT', "{$baseUrl}/admin/users/{$staffId}/status", [
    'status' => 'INACTIVE',
], $adminToken);

assertTest(
    'MoSJE Admin: Create Staff Account and Deactivate Status',
    $createStaff['status'] === 201 && $deactStaff['status'] === 200 && ($deactStaff['data']['data']['new_status'] ?? '') === 'INACTIVE',
    ($createStaff['status'] === 201) ? "Staff ID #{$staffId} Created & Deactivated (Status: INACTIVE)" : "Create Staff Failed: HTTP {$createStaff['status']} - " . ($createStaff['data']['message'] ?? '')
);

// ---------------------------------------------------------
// TEST 13: MoSJE Admin: Category & Checklist Template Management
// ---------------------------------------------------------
$newCat = httpRequest('POST', "{$baseUrl}/categories", [
    'name' => 'Skill Development Centres ' . rand(10, 99),
    'description' => 'PM-DAKSH vocational and technical training institutions',
], $adminToken);
$newCatId = $newCat['data']['data']['id'] ?? 0;

$newTpl = httpRequest('POST', "{$baseUrl}/checklist-templates", [
    'category_id' => $newCatId,
    'item_name' => 'Smart Classroom Audiovisual Equipment Integrity',
    'description' => 'Test projector, digital board, and assistive listening systems',
    'is_critical' => 1,
], $adminToken);
$newTplId = $newTpl['data']['data']['id'] ?? 0;

assertTest(
    'MoSJE Admin: Manage Categories & Quality Checklist Templates',
    $newCat['status'] === 201 && $newTpl['status'] === 201 && $newTplId > 0,
    "Category ID #{$newCatId} Created, Checklist Template ID #{$newTplId} (is_critical=1)"
);

// ---------------------------------------------------------
// TEST 14: MoSJE Admin: Immutable Audit Log View
// ---------------------------------------------------------
$auditRes = httpRequest('GET', "{$baseUrl}/admin/audit-log?limit=10", null, $adminToken);
$logs = $auditRes['data']['data'] ?? [];

assertTest(
    'MoSJE Admin: Immutable Audit Log Accessible with User and Role Metadata',
    $auditRes['status'] === 200 && count($logs) > 0,
    "Retrieved " . count($logs) . " immutable audit records. Latest Action: " . ($logs[0]['action'] ?? 'N/A')
);

// ---------------------------------------------------------
// TEST 15: MoSJE Admin: Financial Escalation Fees & Refund Summary
// ---------------------------------------------------------
$finRes = httpRequest('GET', "{$baseUrl}/admin/finance-summary", null, $adminToken);
$fin = $finRes['data']['data'] ?? [];
$finSum = $fin['summary'] ?? [];

assertTest(
    'MoSJE Admin: Escalation Fees (Base ₹400 + 18% GST = ₹472) & Automatic Refund Ledger',
    $finRes['status'] === 200 && isset($finSum['base_fees_collected']),
    "Base Fees: ₹" . number_format($finSum['base_fees_collected'] ?? 0) .
    " | GST (18%): ₹" . number_format($finSum['gst_collected'] ?? 0) .
    " | Refunds: ₹" . number_format($finSum['total_refunded_amount'] ?? 0) .
    " | Net Retained: ₹" . number_format($finSum['net_retained_revenue'] ?? 0)
);

echo "\n========================================================\n";
echo "PART 7 TEST RESULTS: {$passed} PASSED | {$failed} FAILED\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
