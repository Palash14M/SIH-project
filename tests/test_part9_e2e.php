<?php
/**
 * PART 9: COMPREHENSIVE END-TO-END SCENARIO TEST
 */

$baseUrl = 'http://127.0.0.1:8000';

function api($method, $path, $data = null, $token = null) {
    global $baseUrl;
    $ch = curl_init($baseUrl . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($data) ? $data : json_encode($data));
    }
    
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $json = json_decode($raw, true);
    return ['status' => $status, 'data' => $json, 'raw' => $raw];
}

function uploadMultipart(string $path, string $fieldName, string $filePath, array $fields, ?string $token = null): array {
    global $baseUrl;
    $ch = curl_init($baseUrl . $path);
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
    curl_close($ch);

    $decoded = json_decode($raw, true);
    return ['status' => $status, 'data' => $decoded, 'raw' => $raw];
}

$passCount = 0;
$failCount = 0;

function assertE2E($step, $condition, $details = '') {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] Step $step: $details\n";
        $passCount++;
    } else {
        echo "[FAIL] Step $step: $details\n";
        $failCount++;
    }
}

echo "=== PART 9: COMPLETE END-TO-END SCENARIO VERIFICATION ===\n\n";

// STEP 1: Admin Login & Create Inspector
$adminLogin = api('POST', '/api/auth/login', ['email' => 'admin@mosje.gov.in', 'password' => 'Admin@12345']);
$adminToken = $adminLogin['data']['data']['token'] ?? null;
assertE2E('1.1', $adminLogin['status'] === 200 && !empty($adminToken), 'Admin authenticated successfully');

$newInspEmail = 'inspector.e2e_' . rand(1000, 9999) . '@mosje.gov.in';
$newInspPhone = '93' . rand(10000000, 99999999);
$createInsp = api('POST', '/api/admin/users', [
    'name' => 'Inspector Alok Nath (E2E)',
    'email' => $newInspEmail,
    'phone' => $newInspPhone,
    'password' => 'Inspect@12345',
    'role' => 'INSPECTOR',
    'district_id' => 1, // Nagpur
    'state_id' => 1     // Maharashtra
], $adminToken);
$newInspId = $createInsp['data']['data']['id'] ?? null;
assertE2E('1.2', $createInsp['status'] === 201 && !empty($newInspId), "Admin created new Inspector ID #$newInspId ($newInspEmail)");

// STEP 2: Admin imports tenders via CSV multipart
$e2eTenderNum = 'TND-E2E-' . rand(1000, 9999);
$tmpCsv = sys_get_temp_dir() . '/e2e_tenders_' . time() . '.csv';
$fp = fopen($tmpCsv, 'w');
fputcsv($fp, ['tender_number','title','category','department','district','state','latitude','longitude','contractor_name','contractor_registration','sanctioned_amount','award_date','start_date','end_date','responsible_senior_username']);
fputcsv($fp, [$e2eTenderNum, 'Nagpur Highway Flyover Expansion', 'Roads', 'PWD Infrastructure Division', 'Nagpur', 'Maharashtra', '21.1458', '79.0882', 'Larsen & Infra Projects Ltd', 'REG-MH-2024-001', '50000000.00', '2026-01-01', '2026-02-01', '2026-12-31', 'senior.sharma@mosje.gov.in']);
fclose($fp);

$importRes = uploadMultipart('/api/tenders/import', 'file', $tmpCsv, ['on_duplicate' => 'skip'], $adminToken);
$report = $importRes['data']['data'] ?? [];
assertE2E('2.1', $importRes['status'] === 200 && ($report['created_count'] ?? 0) === 1, "CSV Import processed: Tender $e2eTenderNum created");

// Find created Tender ID
$tenders = api('GET', '/api/tenders', null, $adminToken);
$e2eTenderId = null;
foreach ($tenders['data']['data'] ?? [] as $t) {
    if ($t['tender_number'] === $e2eTenderNum) {
        $e2eTenderId = (int)$t['id'];
        break;
    }
}
assertE2E('2.2', $e2eTenderId !== null, "Found imported tender ID #$e2eTenderId in database");

// STEP 3: District Officer attaches Inspector & NGO to Tender
$doLogin = api('POST', '/api/auth/login', ['email' => 'district.nagpur@mosje.gov.in', 'password' => 'Officer@12345']);
$doToken = $doLogin['data']['data']['token'] ?? null;
assertE2E('3.1', $doLogin['status'] === 200 && !empty($doToken), 'District Officer Nagpur authenticated');

$attachInsp = api('POST', "/api/tenders/{$e2eTenderId}/assign", ['inspector_id' => $newInspId], $doToken);
$sewaNgoId = 2;
$attachNgo = api('POST', "/api/tenders/{$e2eTenderId}/assign", ['ngo_id' => $sewaNgoId], $doToken); // Sewa Bharati NGO
assertE2E('3.2', $attachInsp['status'] === 200 && $attachNgo['status'] === 200, "Assigned Inspector #$newInspId and NGO #$sewaNgoId to tender #$e2eTenderId");

// Add milestones to tender
$m1Res = api('POST', "/api/tenders/{$e2eTenderId}/milestones", ['name' => 'Substructure Piles', 'planned_date' => '2026-06-30', 'planned_weight' => 50.0], $doToken);
$m1Id = $m1Res['data']['data']['id'] ?? 1;
$m2Res = api('POST', "/api/tenders/{$e2eTenderId}/milestones", ['name' => 'Superstructure Girders', 'planned_date' => '2026-12-31', 'planned_weight' => 50.0], $doToken);

// STEP 4: Inspector logs in and syncs inspection
$inspLogin = api('POST', '/api/auth/login', ['email' => $newInspEmail, 'password' => 'Inspect@12345']);
$inspToken = $inspLogin['data']['data']['token'] ?? null;
assertE2E('4.1', $inspLogin['status'] === 200 && !empty($inspToken), 'New Inspector authenticated successfully');

// Inspector creates inspection with:
// - Actual spent: ₹35,000,000 out of ₹50,000,000 = 70.0%
// - Milestone progress: Piles 40% * 0.5 = 20.0% -> Overall progress = 20.0%
// - Variance: 70.0% - 20.0% = +50.0% (HIGH VARIANCE ALERT!)
// - Critical Quality check failed: Template 1 (Road Subgrade) -> AUTO-ISSUE!
$inspectionData = [
    'tender_id' => $e2eTenderId,
    'device_capture_time' => date('Y-m-d H:i:s'),
    'latitude' => 21.1458,
    'longitude' => 79.0882,
    'accuracy' => 4.5,
    'is_mock' => false,
    'remarks' => 'Site inspection chainage 0+500 to 1+200. Heavy expenditure recorded against measurement book MB-104.',
    'actual_spent' => 35000000.00,
    'spent_basis' => 'MB-104 Page 45-52 Raft Concreting Bill',
    'milestones' => [
        ['milestone_id' => $m1Id, 'actual_progress' => 40.0],
    ],
    'quality_checks' => [
        ['template_id' => 1, 'status' => 'FAIL', 'remarks' => 'Pier re-bar spacing non-compliant. Inadequate concrete cover.'],
    ]
];

$inspSubmit = api('POST', '/api/inspections', $inspectionData, $inspToken);
$inspectionId = $inspSubmit['data']['data']['id'] ?? null;
assertE2E('4.2', $inspSubmit['status'] === 201 && !empty($inspectionId), "Inspector submitted inspection #$inspectionId offline queue sync");

// STEP 5: Verify Server-Computed Variance and Auto-Issue Flag
$tDetail = api('GET', "/api/tenders/{$e2eTenderId}", null, $doToken);
$tData = $tDetail['data']['data'] ?? [];
$varianceAlert = !empty($tData['variance_flag']) || (isset($tData['variance']) && $tData['variance'] > 10.0);
assertE2E('5.1', $varianceAlert, "Server computed variance (+50.0%) and triggered variance alert flag");

$inspDetail = api('GET', "/api/inspections/{$inspectionId}", null, $doToken);
$detailData = $inspDetail['data']['data'] ?? [];
$autoIssueRaised = !empty($detailData['issue_found']);
assertE2E('5.2', $autoIssueRaised, "Server automatically raised issue due to critical quality check failure");

// STEP 6: District Officer verifies, raises issue & notifies
$submitInspection = api('POST', "/api/inspections/{$inspectionId}/submit", null, $inspToken);
assertE2E('6.1', $submitInspection['status'] === 200, "Inspection submitted (status: SUBMITTED)");

$raiseIssue = api('POST', "/api/inspections/{$inspectionId}/verify", [
    'action' => 'RAISE_ISSUE',
    'remarks' => 'Critical reinforcement non-compliance on Pier P-3. Remedial re-bar placing required.',
    'severity' => 'CRITICAL'
], $doToken);
assertE2E('6.2', $raiseIssue['status'] === 200 && ($raiseIssue['data']['data']['new_status'] ?? '') === 'ISSUE_RAISED', "District Officer escalated to ISSUE_RAISED");

$notifyIssue = api('POST', "/api/inspections/{$inspectionId}/notify", null, $doToken);
assertE2E('6.3', $notifyIssue['status'] === 200 && ($notifyIssue['data']['data']['new_status'] ?? '') === 'NOTIFIED', "Formal notice dispatched to contractor and attached NGO (status: NOTIFIED)");

$resolveState = api('POST', "/api/inspections/{$inspectionId}/resolve", ['remarks' => 'Contractor mobilized for rectification'], $doToken);
assertE2E('6.4', $resolveState['status'] === 200 && ($resolveState['data']['data']['new_status'] ?? '') === 'IN_RESOLUTION', "Inspection marked IN_RESOLUTION");

// STEP 7: Attached NGO is notified and raises complaint
$ngoLogin = api('POST', '/api/auth/login', ['email' => 'ngo.sewa@org.in', 'password' => 'Ngo@12345']);
$ngoToken = $ngoLogin['data']['data']['token'] ?? null;
assertE2E('7.1', $ngoLogin['status'] === 200 && !empty($ngoToken), 'Attached NGO Sewa Bharati authenticated');

$ngoComplaint = api('POST', '/api/complaints', [
    'tender_id' => $e2eTenderId,
    'subject' => 'Pier P-3 Structural Compromise Risk',
    'description' => 'Substandard steel placement on Pier P-3 risks public safety on highway flyover.'
], $ngoToken);
$complaintId = $ngoComplaint['data']['data']['id'] ?? null;
assertE2E('7.2', $ngoComplaint['status'] === 201 && !empty($complaintId), "NGO filed formal complaint #$complaintId routed to Contractor Senior Officer");

// STEP 8: Senior Officer rejects complaint
$seniorLogin = api('POST', '/api/auth/login', ['email' => 'senior.sharma@mosje.gov.in', 'password' => 'Senior@12345']);
$seniorToken = $seniorLogin['data']['data']['token'] ?? null;
assertE2E('8.1', $seniorLogin['status'] === 200 && !empty($seniorToken), 'Contractor Senior Officer authenticated');

$seniorReject = api('POST', "/api/complaints/{$complaintId}/resolve", [
    'action' => 'REJECT',
    'remarks' => 'Contractor engineer submitted structural analysis arguing safety factors are sufficient.'
], $seniorToken);
assertE2E('8.2', $seniorReject['status'] === 200 && ($seniorReject['data']['data']['status'] ?? '') === 'REJECTED', "Senior Officer rejected complaint with remarks");

// STEP 9: NGO escalates with fee (₹400 + 18% GST = ₹472.00)
$escOrder = api('POST', "/api/complaints/{$complaintId}/escalate", [
    'target_level' => 'DISTRICT_OFFICER',
    'reason' => 'Contractor internal analysis is self-serving. Independent ultrasound test needed.'
], $ngoToken);
$orderId = $escOrder['data']['data']['order_id'] ?? null;
$totalPayable = $escOrder['data']['data']['total_amount'] ?? 0;
assertE2E('9.1', $escOrder['status'] === 200 && (float)$totalPayable === 472.00 && !empty($orderId), "Created escalation order $orderId for ₹472.00 (₹400 + ₹72 GST)");

$payConfirm = api('POST', '/api/payments/confirm', ['order_id' => $orderId], $ngoToken);
assertE2E('9.2', $payConfirm['status'] === 200 && ($payConfirm['data']['data']['status'] ?? '') === 'ESCALATED', "Payment confirmed & complaint escalated to DISTRICT_OFFICER");

// STEP 10: District Officer upholds escalation -> AUTOMATIC REFUND TRIGGERED
$doUphold = api('POST', "/api/complaints/{$complaintId}/decide-escalation", [
    'decision' => 'UPHELD',
    'remarks' => 'Third-party ultrasonic inspection verified non-compliant concrete cover. Pier must be jacked and re-cased.'
], $doToken);
$refund = $doUphold['data']['data']['refund'] ?? null;
assertE2E('10.1', $doUphold['status'] === 200 && ($refund['refunded'] ?? false) === true && (float)($refund['amount'] ?? 0) === 472.00, "District Officer UPHELD grievance -> Automatic 100% refund of ₹472.00 processed");

// STEP 11: Schedule Re-inspection & Assert Premature Closing is Blocked
$prematureClose = api('POST', "/api/inspections/{$inspectionId}/close", null, $doToken);
assertE2E('11.1', $prematureClose['status'] === 400, "Negative test passed: Closing inspection without passed re-inspection strictly BLOCKED (400)");

$schedReinsp = api('POST', "/api/inspections/{$inspectionId}/schedule-reinspection", [
    'assigned_inspector_id' => $newInspId,
    'scheduled_date' => date('Y-m-d', strtotime('+3 days')),
], $doToken);
assertE2E('11.2', $schedReinsp['status'] === 200 && ($schedReinsp['data']['data']['new_status'] ?? '') === 'REINSPECTION_PENDING', "Scheduled mandatory re-inspection (status: REINSPECTION_PENDING)");

// STEP 12: Inspector conducts & passes re-inspection
$passReinsp = api('POST', "/api/inspections/{$inspectionId}/submit-reinspection", [
    'result' => 'PASSED',
    'remarks' => 'Pier P-3 successfully re-cased with M-40 high strength concrete. Ultrasonic pulse velocity tests passed.'
], $inspToken);
assertE2E('12.1', $passReinsp['status'] === 200 && ($passReinsp['data']['data']['new_status'] ?? '') === 'CLOSED', "Inspector conducted re-inspection with PASSED outcome -> Advanced to CLOSED");

// STEP 13: Assert Inspection is fully CLOSED
$finalIns = api('GET', "/api/inspections/{$inspectionId}", null, $doToken);
assertE2E('13.1', ($finalIns['data']['data']['status'] ?? '') === 'CLOSED', "Inspection officially verified in CLOSED state");

// STEP 14: Public Citizen transparently views closed project and nudges another NGO
$citizenPhone = '94' . rand(10000000, 99999999);
$pubOtpReq = api('POST', '/api/auth/otp/request', ['phone' => $citizenPhone]);
$pubOtp = $pubOtpReq['data']['data']['demo_otp'] ?? '123456';
$pubLogin = api('POST', '/api/auth/otp/verify', ['phone' => $citizenPhone, 'otp' => $pubOtp]);
$citizenToken = $pubLogin['data']['data']['token'] ?? null;
assertE2E('14.1', $pubLogin['status'] === 200 && !empty($citizenToken), "Public citizen logged in via OTP");

$pubView = api('GET', "/api/tenders/{$e2eTenderId}", null, $citizenToken);
$pubTender = $pubView['data']['data'] ?? [];
$contractorPhoneRedacted = empty($pubTender['contractor_phone']);
$inspectorsRedacted = empty($pubTender['inspectors']) || !isset($pubTender['inspectors'][0]['phone']);
assertE2E('14.2', $contractorPhoneRedacted && $inspectorsRedacted, "Citizen viewed closed tender: Contractor and Inspector contact details are SECURELY MASKED");

$simDate = '2026-09-' . rand(15, 28);
$citizenNudge = api('POST', '/api/nudges', [
    'tender_id' => 1,
    'ngo_id' => 1,
    'reason' => 'Pavement surface near junction has visible undulations. Requesting joint audit.',
    'simulate_date' => $simDate
], $citizenToken);
assertE2E('14.3', $citizenNudge['status'] === 201 && ($citizenNudge['data']['data']['delivered'] ?? false) === true, "Citizen successfully nudged attached NGO");

echo "\n============================================\n";
echo "END-TO-END SCENARIO SUMMARY: $passCount PASSED, $failCount FAILED\n";
echo "============================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
