<?php
/**
 * PART 8 TEST SUITE: Institution (NGO), Complaint & Escalation, Fee Flow, Refund, Public Citizen Nudge, and Data Visibility
 */

$baseUrl = 'http://127.0.0.1:8000';

function apiCall($method, $path, $data = null, $token = null) {
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

$passCount = 0;
$failCount = 0;

function assertTest($name, $condition, $details = '') {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] $name\n";
        $passCount++;
    } else {
        echo "[FAIL] $name: $details\n";
        $failCount++;
    }
}

echo "=== PART 8: INSTITUTION, COMPLAINTS, ESCALATION, REFUND, NUDGE & DATA VISIBILITY TESTS ===\n\n";

// 1. Authenticate Actors with correct credentials
$adminLogin = apiCall('POST', '/api/auth/login', ['email' => 'admin@mosje.gov.in', 'password' => 'Admin@12345']);
$adminToken = $adminLogin['data']['data']['token'] ?? null;
assertTest('Admin Login', $adminLogin['status'] === 200 && !empty($adminToken), 'Status: ' . $adminLogin['status']);

$seniorLogin = apiCall('POST', '/api/auth/login', ['email' => 'senior.sharma@mosje.gov.in', 'password' => 'Senior@12345']);
$seniorToken = $seniorLogin['data']['data']['token'] ?? null;
assertTest('Senior Officer Login', $seniorLogin['status'] === 200 && !empty($seniorToken), 'Status: ' . $seniorLogin['status']);

$doLogin = apiCall('POST', '/api/auth/login', ['email' => 'district.nagpur@mosje.gov.in', 'password' => 'Officer@12345']);
$doToken = $doLogin['data']['data']['token'] ?? null;
assertTest('District Officer Login', $doLogin['status'] === 200 && !empty($doToken), 'Status: ' . $doLogin['status']);

$soLogin = apiCall('POST', '/api/auth/login', ['email' => 'state.mh@mosje.gov.in', 'password' => 'Officer@12345']);
$soToken = $soLogin['data']['data']['token'] ?? null;
assertTest('State Officer Login', $soLogin['status'] === 200 && !empty($soToken), 'Status: ' . $soLogin['status']);

$ngoApprovedLogin = apiCall('POST', '/api/auth/login', ['email' => 'ngo.sewa@org.in', 'password' => 'Ngo@12345']);
$ngoApprovedToken = $ngoApprovedLogin['data']['data']['token'] ?? null;
assertTest('Approved NGO Login', $ngoApprovedLogin['status'] === 200 && !empty($ngoApprovedToken), 'Status: ' . $ngoApprovedLogin['status']);

$ngoPendingLogin = apiCall('POST', '/api/auth/login', ['email' => 'ngo.gramin@org.in', 'password' => 'Ngo@12345']);
$ngoPendingToken = $ngoPendingLogin['data']['data']['token'] ?? null;
assertTest('Pending NGO Login', $ngoPendingLogin['status'] === 200 && !empty($ngoPendingToken), 'Status: ' . $ngoPendingLogin['status']);

// Public User Login via OTP
$pubTestPhone = '98' . rand(10000000, 99999999);
$pubOtpReq = apiCall('POST', '/api/auth/otp/request', ['phone' => $pubTestPhone]);
$pubOtp = $pubOtpReq['data']['data']['demo_otp'] ?? '123456';
$pubLogin = apiCall('POST', '/api/auth/otp/verify', ['phone' => $pubTestPhone, 'otp' => $pubOtp]);
$publicToken = $pubLogin['data']['data']['token'] ?? null;
assertTest('Public User OTP Login', $pubLogin['status'] === 200 && !empty($publicToken), 'Status: ' . $pubLogin['status']);

// Get Tender 1 (Nagpur Roads - attached to Sewa Bharati NGO)
$tenders = apiCall('GET', '/api/tenders', null, $adminToken);
$testTenderId = 1;
foreach ($tenders['data']['data'] ?? [] as $t) {
    if ($t['tender_number'] === 'TND-2026-RD-01') {
        $testTenderId = $t['id'];
        break;
    }
}

// TEST 1: Pending NGO blocked from raising complaints
$pendingComplaint = apiCall('POST', '/api/complaints', [
    'tender_id' => $testTenderId,
    'subject' => 'Unauthorized Pending Filing',
    'description' => 'Pending NGO should never be allowed to raise complaints.'
], $ngoPendingToken);
assertTest('Pending NGO blocked from raising complaints (403)', $pendingComplaint['status'] === 403, 'Status: ' . $pendingComplaint['status']);

// TEST 2: Approved NGO raises complaint on attached tender
$complaintSubj = 'Culvert Wing Wall Concrete Honeycombing and Voids ' . rand(1000, 9999);
$raiseComp = apiCall('POST', '/api/complaints', [
    'tender_id' => $testTenderId,
    'subject' => $complaintSubj,
    'description' => 'Extensive honeycombing observed at chainage 4+200 on the wing wall. Potential safety risk.'
], $ngoApprovedToken);
$complaintId = $raiseComp['data']['data']['id'] ?? null;
assertTest('Approved NGO raises complaint successfully (201)', $raiseComp['status'] === 201 && $complaintId, 'Status: ' . $raiseComp['status'] . ', ID: ' . $complaintId);

// TEST 3: Responsible Senior Officer reviews and rejects complaint
$rejectBySenior = apiCall('POST', "/api/complaints/{$complaintId}/resolve", [
    'action' => 'REJECT',
    'remarks' => 'Site inspected by junior engineer. Superficial plaster voids only, structural integrity intact.'
], $seniorToken);
assertTest('Senior Officer rejects complaint', $rejectBySenior['status'] === 200 && ($rejectBySenior['data']['data']['status'] ?? '') === 'REJECTED', 'Status: ' . $rejectBySenior['status']);

// TEST 4: NGO attempts invalid escalation skipping District Officer (straight to State Officer) -> BLOCKED
$invalidEsc = apiCall('POST', "/api/complaints/{$complaintId}/escalate", [
    'target_level' => 'STATE_OFFICER',
    'reason' => 'Skipping District Officer to speed up investigation.'
], $ngoApprovedToken);
assertTest('Skipping escalation level strictly blocked (400)', $invalidEsc['status'] === 400, 'Status: ' . $invalidEsc['status'] . ' msg: ' . ($invalidEsc['data']['message'] ?? ''));

// TEST 5: NGO creates valid escalation order to DISTRICT_OFFICER with ₹400 + 18% GST (₹472.00)
$validEscOrder = apiCall('POST', "/api/complaints/{$complaintId}/escalate", [
    'target_level' => 'DISTRICT_OFFICER',
    'reason' => 'Senior officer inspection was superficial. Independent core test required by District Officer.'
], $ngoApprovedToken);
$orderId = $validEscOrder['data']['data']['order_id'] ?? null;
$baseAmt = $validEscOrder['data']['data']['base_amount'] ?? 0;
$gstAmt = $validEscOrder['data']['data']['gst_amount'] ?? 0;
$totalAmt = $validEscOrder['data']['data']['total_amount'] ?? 0;

assertTest('Escalation fee calculated accurately (₹400 + 18% GST = ₹472.00)', 
    $validEscOrder['status'] === 200 && (float)$baseAmt === 400.00 && (float)$gstAmt === 72.00 && (float)$totalAmt === 472.00 && !empty($orderId),
    "Base: $baseAmt, GST: $gstAmt, Total: $totalAmt, OrderId: $orderId"
);

// TEST 6: NGO confirms payment order
$confirmPay = apiCall('POST', '/api/payments/confirm', ['order_id' => $orderId], $ngoApprovedToken);
assertTest('Escalation payment confirmed and status updated to ESCALATED',
    $confirmPay['status'] === 200 && ($confirmPay['data']['data']['status'] ?? '') === 'ESCALATED',
    'Status: ' . $confirmPay['status']
);

// TEST 7: District Officer upholds escalation -> AUTOMATIC REFUND RECORD TRIGGERED!
$upholdDec = apiCall('POST', "/api/complaints/{$complaintId}/decide-escalation", [
    'decision' => 'UPHELD',
    'remarks' => 'Core testing verified structural substandard strength. Contractor ordered to dismantle and rebuild.'
], $doToken);

$refundInfo = $upholdDec['data']['data']['refund'] ?? null;
assertTest('District Officer upholds grievance and triggers automatic refund',
    $upholdDec['status'] === 200 && ($upholdDec['data']['data']['decision'] ?? '') === 'UPHELD' &&
    $refundInfo !== null && ($refundInfo['refunded'] ?? false) === true && (float)($refundInfo['amount'] ?? 0) === 472.00,
    'Decision: ' . ($upholdDec['data']['data']['decision'] ?? '') . ', Refund: ' . json_encode($refundInfo)
);

// TEST 8: Complaint status check shows refund reference and resolution
$compDetail = apiCall('GET', "/api/complaints/{$complaintId}", null, $ngoApprovedToken);
$escHistory = $compDetail['data']['data']['escalation_history'] ?? [];
$refundFound = false;
foreach ($escHistory as $eh) {
    if (!empty($eh['refund_status']) && $eh['refund_status'] === 'PROCESSED' && (float)$eh['refund_amount'] === 472.00) {
        $refundFound = true;
        break;
    }
}
assertTest('Complaint escalation history contains PROCESSED refund of ₹472.00', $refundFound, 'History: ' . json_encode($escHistory));

// === CITIZEN NUDGE JOURNEY ===
$simDay = '2026-09-' . rand(10, 28);
$citizenPhone = '97' . rand(10000000, 99999999);
$cOtpReq = apiCall('POST', '/api/auth/otp/request', ['phone' => $citizenPhone]);
$cOtp = $cOtpReq['data']['data']['demo_otp'] ?? '123456';
$cLogin = apiCall('POST', '/api/auth/otp/verify', ['phone' => $citizenPhone, 'otp' => $cOtp]);
$citizenToken = $cLogin['data']['data']['token'] ?? null;

// TEST 9: Citizen checks daily nudge status -> 1 chance available
$statusBefore = apiCall('GET', "/api/nudges/status?date={$simDay}", null, $citizenToken);
assertTest('Citizen starts with 1 available nudge chance for simulated day',
    $statusBefore['status'] === 200 && ($statusBefore['data']['data']['remaining_chances'] ?? 0) === 1 && ($statusBefore['data']['data']['has_used_today'] ?? true) === false,
    'Status: ' . json_encode($statusBefore['data'])
);

// TEST 10: Citizen submits valid nudge (>= 10 chars) -> DELIVERED & NGO NOTIFIED
$validNudge = apiCall('POST', '/api/nudges', [
    'tender_id' => $testTenderId,
    'ngo_id' => 1,
    'reason' => 'Culvert construction appears stagnant for 3 weeks with waterlogging. Please inspect.',
    'simulate_date' => $simDay
], $citizenToken);
assertTest('Valid nudge is DELIVERED and consumes day chance',
    $validNudge['status'] === 201 && ($validNudge['data']['data']['delivered'] ?? false) === true && ($validNudge['data']['data']['status'] ?? '') === 'DELIVERED',
    'Status: ' . $validNudge['status'] . ', Msg: ' . ($validNudge['data']['message'] ?? '')
);

// TEST 11: Citizen attempts 2nd nudge on same day -> BLOCKED with 429
$secondNudge = apiCall('POST', '/api/nudges', [
    'tender_id' => $testTenderId,
    'ngo_id' => 1,
    'reason' => 'Attempting a second nudge on the exact same day.',
    'simulate_date' => $simDay
], $citizenToken);
assertTest('Second nudge on same day is blocked with HTTP 429',
    $secondNudge['status'] === 429,
    'Status: ' . $secondNudge['status'] . ', Msg: ' . ($secondNudge['data']['message'] ?? '')
);

// TEST 12: Chance-burning rule: Another citizen submits with invalid short reason (< 10 chars)
$citizenPhone2 = '96' . rand(10000000, 99999999);
$c2OtpReq = apiCall('POST', '/api/auth/otp/request', ['phone' => $citizenPhone2]);
$c2Otp = $c2OtpReq['data']['data']['demo_otp'] ?? '123456';
$c2Login = apiCall('POST', '/api/auth/otp/verify', ['phone' => $citizenPhone2, 'otp' => $c2Otp]);
$citizen2Token = $c2Login['data']['data']['token'] ?? null;

$invalidReasonNudge = apiCall('POST', '/api/nudges', [
    'tender_id' => $testTenderId,
    'ngo_id' => 1,
    'reason' => 'Short', // only 5 chars!
    'simulate_date' => $simDay
], $citizen2Token);

assertTest('Short reason (< 10 chars) burns daily chance and is NOT delivered',
    $invalidReasonNudge['status'] === 200 && 
    ($invalidReasonNudge['data']['data']['delivered'] ?? true) === false && 
    ($invalidReasonNudge['data']['data']['chance_consumed'] ?? false) === true &&
    ($invalidReasonNudge['data']['data']['status'] ?? '') === 'REJECTED_INVALID_REASON',
    'Delivered: ' . json_encode($invalidReasonNudge['data']['data']['delivered'] ?? null)
);

// TEST 13: Second attempt by citizen 2 is now BLOCKED with 429 because chance was burned!
$citizen2SecondAttempt = apiCall('POST', '/api/nudges', [
    'tender_id' => $testTenderId,
    'ngo_id' => 1,
    'reason' => 'Now trying with a much longer reason of more than ten characters.',
    'simulate_date' => $simDay
], $citizen2Token);
assertTest('Citizen with burned chance is blocked on subsequent attempts (HTTP 429)',
    $citizen2SecondAttempt['status'] === 429,
    'Status: ' . $citizen2SecondAttempt['status']
);

// === DATA VISIBILITY & PRIVACY AUDIT ===
// TEST 14: Public user requests tender detail -> Contractor and Inspector contact details are ABSENT
$pubTenderDetail = apiCall('GET', "/api/tenders/{$testTenderId}", null, $publicToken);
$pubData = $pubTenderDetail['data']['data'] ?? [];

$contractorPhoneExposed = !empty($pubData['contractor_phone']);
$contractorEmailExposed = !empty($pubData['contractor_email']);
$contractorAddressExposed = !empty($pubData['contractor_address']);

$inspectorsContactExposed = false;
foreach ($pubData['inspectors'] ?? [] as $insp) {
    if (!empty($insp['phone']) || !empty($insp['email']) || !empty($insp['name'])) {
        $inspectorsContactExposed = true;
        break;
    }
}

assertTest('Public API strictly omits contractor phone/email/address and inspector contacts',
    !$contractorPhoneExposed && !$contractorEmailExposed && !$contractorAddressExposed && !$inspectorsContactExposed,
    "Phone: " . ($pubData['contractor_phone'] ?? 'null') . ", Email: " . ($pubData['contractor_email'] ?? 'null') . ", Inspector contact exposed: " . ($inspectorsContactExposed ? 'YES' : 'NO')
);

// TEST 15: District Officer / Attached NGO requests same tender -> Contractor and Inspector contact details are VISIBLE
$doTenderDetail = apiCall('GET', "/api/tenders/{$testTenderId}", null, $doToken);
$doData = $doTenderDetail['data']['data'] ?? [];
$contractorPhoneVisibleToDO = !empty($doData['contractor_phone']);
$inspectorsVisibleToDO = false;
foreach ($doData['inspectors'] ?? [] as $insp) {
    if (!empty($insp['phone']) || !empty($insp['email'])) {
        $inspectorsVisibleToDO = true;
        break;
    }
}

assertTest('District Officer and Attached NGOs have full access to contractor & inspector contacts',
    $contractorPhoneVisibleToDO && $inspectorsVisibleToDO,
    "DO Contractor Phone: " . ($doData['contractor_phone'] ?? 'null') . ", Inspector Contacts Visible: " . ($inspectorsVisibleToDO ? 'YES' : 'NO')
);

echo "\n============================================\n";
echo "PART 8 TEST SUMMARY: $passCount PASSED, $failCount FAILED\n";
echo "============================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
