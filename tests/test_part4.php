<?php

require_once __DIR__ . '/../backend/config/config.php';
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/utils/Jwt.php';

echo "========================================================\n";
echo "           PART 4: GATE VERIFICATION TEST SUITE          \n";
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

// Tokens
$adminLogin = httpRequest('POST', "{$baseUrl}/auth/login", ['email' => 'admin@mosje.gov.in', 'password' => 'Admin@12345']);
$adminToken = $adminLogin['data']['data']['token'];

$doNagpurLogin = httpRequest('POST', "{$baseUrl}/auth/login", ['email' => 'district.nagpur@mosje.gov.in', 'password' => 'Officer@12345']);
$doNagpurToken = $doNagpurLogin['data']['data']['token'];

$doPuneLogin = httpRequest('POST', "{$baseUrl}/auth/login", ['email' => 'district.pune@mosje.gov.in', 'password' => 'Officer@12345']);
$doPuneToken = $doPuneLogin['data']['data']['token'];

$doLucknowLogin = httpRequest('POST', "{$baseUrl}/auth/login", ['email' => 'district.lucknow@mosje.gov.in', 'password' => 'Officer@12345']);
$doLucknowToken = $doLucknowLogin['data']['data']['token'];

// Approved NGO: Sewa Bharati (Nagpur, attached to Tender 1)
$sewaLogin = httpRequest('POST', "{$baseUrl}/auth/login", ['email' => 'ngo.sewa@org.in', 'password' => 'Ngo@12345']);
$sewaToken = $sewaLogin['data']['data']['token'];
$sewaNgoId = $sewaLogin['data']['data']['user']['ngo']['id'];

// Fresh Pending NGO in Pune (District 2) for repeatable jurisdiction test
$uniqueGraminReg = 'NGO-PUNE-' . time() . '-' . rand(100, 999);
$freshPendingNgo = httpRequest('POST', "{$baseUrl}/auth/ngo/register", [
    'name' => 'Gramin Kalyan Trust ' . rand(100, 999),
    'registration_number' => $uniqueGraminReg,
    'contact_person' => 'Kavita Joshi',
    'phone' => '9833' . rand(100000, 999999),
    'email' => 'gramin.' . time() . rand(10, 99) . '@org.in',
    'password' => 'Ngo@12345',
    'state_id' => 1,
    'district_id' => 2, // Pune
    'address' => '45 Shivajinagar, Pune, Maharashtra',
]);
$graminEmail = $freshPendingNgo['data']['data']['email'] ?? 'ngo.gramin@org.in';
$graminLogin = httpRequest('POST', "{$baseUrl}/auth/login", ['email' => $graminEmail, 'password' => 'Ngo@12345']);
$graminToken = $graminLogin['data']['data']['token'];
$graminNgoId = $graminLogin['data']['data']['user']['ngo']['id'];

// Public Citizen with fresh phone for repeatable daily quota
$freshCitizenPhone = '97' . rand(10000000, 99999999);
$otpReq = httpRequest('POST', "{$baseUrl}/auth/otp/request", ['phone' => $freshCitizenPhone]);
$otpVer = httpRequest('POST', "{$baseUrl}/auth/otp/verify", ['phone' => $freshCitizenPhone, 'otp' => $otpReq['data']['data']['demo_otp']]);
$citizenToken = $otpVer['data']['data']['token'];

// ---------------------------------------------------------
// TEST 1: Pending NGO Cannot Raise a Complaint (403)
// ---------------------------------------------------------
$pendingComplaint = httpRequest('POST', "{$baseUrl}/complaints", [
    'tender_id' => 1,
    'subject' => 'Unapproved Complaint Attempt',
    'description' => 'Pending NGO should be blocked from raising complaints.',
], $graminToken);

assertTest(
    'Pending NGO Blocked from Raising Complaint (403)',
    $pendingComplaint['status'] === 403,
    "HTTP {$pendingComplaint['status']} - " . ($pendingComplaint['data']['message'] ?? '')
);

// ---------------------------------------------------------
// TEST 2: Jurisdiction Check on NGO Approval: Wrong District Officer Fails (403)
// Gramin Kalyan is in Pune (District #2). District Officer Nagpur (District #1) tries to decide -> MUST FAIL!
// ---------------------------------------------------------
$wrongDoDecision = httpRequest('POST', "{$baseUrl}/ngos/{$graminNgoId}/decide", [
    'decision' => 'APPROVE',
    'reason' => 'Approved by Nagpur DO (Wrong District)',
], $doNagpurToken);

assertTest(
    'Jurisdiction Check: Wrong District Officer Blocked from NGO Approval (403)',
    $wrongDoDecision['status'] === 403,
    "HTTP {$wrongDoDecision['status']} - " . ($wrongDoDecision['data']['message'] ?? '')
);

// ---------------------------------------------------------
// TEST 3: Correct District Officer (Pune) Approves Gramin Kalyan
// ---------------------------------------------------------
$correctDoDecision = httpRequest('POST', "{$baseUrl}/ngos/{$graminNgoId}/decide", [
    'decision' => 'APPROVE',
    'reason' => 'Darpan registration and audit reports verified in order.',
], $doPuneToken);

assertTest(
    'Correct District Officer Approves NGO (200)',
    $correctDoDecision['status'] === 200 && ($correctDoDecision['data']['data']['status'] ?? '') === 'APPROVED',
    "Status: " . ($correctDoDecision['data']['data']['status'] ?? '')
);

// ---------------------------------------------------------
// TEST 4: Approved NGO (Sewa Bharati) Raises Complaint on Attached Project
// ---------------------------------------------------------
$createComplaint = httpRequest('POST', "{$baseUrl}/complaints", [
    'tender_id' => 1, // Attached to Sewa Bharati
    'subject' => 'Subgrade Compaction Inadequate near Junction',
    'description' => 'Field social audit observed significant subsidence on subgrade chainage 3+100.',
], $sewaToken);

$newComplaintId = $createComplaint['data']['data']['id'] ?? 0;
assertTest(
    'Approved Attached NGO Successfully Raises Complaint',
    $createComplaint['status'] === 201 && $newComplaintId > 0,
    "HTTP {$createComplaint['status']} - Complaint ID: {$newComplaintId}, Level: SENIOR_OFFICER"
);

// ---------------------------------------------------------
// TEST 5: Senior Officer Rejects Complaint
// ---------------------------------------------------------
$seniorLogin = httpRequest('POST', "{$baseUrl}/auth/login", ['email' => 'senior.sharma@mosje.gov.in', 'password' => 'Senior@12345']);
$seniorToken = $seniorLogin['data']['data']['token'];

$rejectComplaint = httpRequest('POST', "{$baseUrl}/complaints/{$newComplaintId}/resolve", [
    'action' => 'REJECT',
    'remarks' => 'Contractor submitted test lab cube report showing 98% compaction. No issue found.',
], $seniorToken);

assertTest(
    'Senior Officer Rejects Complaint with Reason',
    $rejectComplaint['status'] === 200 && ($rejectComplaint['data']['data']['status'] ?? '') === 'REJECTED',
    "Status: " . ($rejectComplaint['data']['data']['status'] ?? '')
);

// ---------------------------------------------------------
// TEST 6: Escalation Chain: Skipping Levels is Strictly Blocked (400)
// From SENIOR_OFFICER, attempting to skip to MOSJE_ADMIN or STATE_OFFICER must fail!
// ---------------------------------------------------------
$skipEscalation = httpRequest('POST', "{$baseUrl}/complaints/{$newComplaintId}/escalate", [
    'target_level' => 'MOSJE_ADMIN', // Skipping District and State!
    'reason' => 'Escalating directly to Ministry National Admin',
], $sewaToken);

assertTest(
    'Escalation Chain: Skipping Levels Blocked (400)',
    $skipEscalation['status'] === 400 && str_contains($skipEscalation['data']['message'] ?? '', 'Invalid escalation step'),
    "HTTP {$skipEscalation['status']} - " . ($skipEscalation['data']['message'] ?? '')
);

// ---------------------------------------------------------
// TEST 7: Valid Escalation Order: ₹400 + 18% GST (₹472.00) Fee Calculation
// SENIOR_OFFICER -> DISTRICT_OFFICER
// ---------------------------------------------------------
$validEscalation = httpRequest('POST', "{$baseUrl}/complaints/{$newComplaintId}/escalate", [
    'target_level' => 'DISTRICT_OFFICER',
    'reason' => 'Lab cube report was from non-accredited facility. Field core sampling required.',
], $sewaToken);

$escData = $validEscalation['data']['data'] ?? [];
$orderId = $escData['order_id'] ?? '';
$baseFee = (float)($escData['base_amount'] ?? 0);
$gstAmount = (float)($escData['gst_amount'] ?? 0);
$totalAmount = (float)($escData['total_amount'] ?? 0);

assertTest(
    'Escalation Fee Calculation: Base ₹400 + 18% GST (₹72) = ₹472 Total',
    $validEscalation['status'] === 200 && abs($baseFee - 400.0) < 0.01 && abs($gstAmount - 72.0) < 0.01 && abs($totalAmount - 472.0) < 0.01,
    "Base: ₹{$baseFee}, GST: ₹{$gstAmount}, Total: ₹{$totalAmount}, Order ID: {$orderId}"
);

// ---------------------------------------------------------
// TEST 8: Confirm Payment & Escalate Complaint
// ---------------------------------------------------------
$confirmPay = httpRequest('POST', "{$baseUrl}/payments/confirm", [
    'order_id' => $orderId,
    'amount' => 472.00,
    'method' => 'UPI',
], $sewaToken);

assertTest(
    'Payment Confirmation & Complaint Level Advanced to DISTRICT_OFFICER',
    $confirmPay['status'] === 200 && ($confirmPay['data']['data']['new_level'] ?? '') === 'DISTRICT_OFFICER',
    "New Level: " . ($confirmPay['data']['data']['new_level'] ?? '') . ", Txn: " . ($confirmPay['data']['data']['transaction_id'] ?? '')
);

// ---------------------------------------------------------
// TEST 9: Higher Authority Upholds Complaint -> Triggers Automatic Refund!
// District Officer (Nagpur) reviews and UPHOLDS the complaint
// ---------------------------------------------------------
$upholdDecision = httpRequest('POST', "{$baseUrl}/complaints/{$newComplaintId}/decide-escalation", [
    'decision' => 'UPHELD',
    'remarks' => 'Independent core test confirmed substandard subgrade compaction. Contractor ordered to rebuild.',
], $doNagpurToken);

$upholdData = $upholdDecision['data']['data'] ?? [];
$refundInfo = $upholdData['refund'] ?? [];
$isRefunded = ($refundInfo['refunded'] ?? false) === true;
$refundAmt = (float)($refundInfo['amount'] ?? 0);

assertTest(
    'Higher Authority Upholds Complaint -> Triggers Automatic Refund (₹472.00)',
    $upholdDecision['status'] === 200 && $isRefunded && abs($refundAmt - 472.0) < 0.01,
    "Refunded: " . ($isRefunded ? 'YES' : 'NO') . ", Amount: ₹{$refundAmt}, Ref: " . ($refundInfo['refund_reference'] ?? '')
);

// ---------------------------------------------------------
// TEST 10: Higher Authority Rejects Complaint -> Fee is Retained (No Refund)
// Let's create and escalate a second complaint on Tender 1 to test fee retention
// ---------------------------------------------------------
$comp2 = httpRequest('POST', "{$baseUrl}/complaints", [
    'tender_id' => 1,
    'subject' => 'Minor aesthetic kerb paint issue',
    'description' => 'Kerb paint shade looks slightly yellowish instead of bright white.',
], $sewaToken);
$comp2Id = $comp2['data']['data']['id'];

// Senior rejects
httpRequest('POST', "{$baseUrl}/complaints/{$comp2Id}/resolve", ['action' => 'REJECT', 'remarks' => 'IRC standard shade approved.'], $seniorToken);

// Escalate with fee
$esc2 = httpRequest('POST', "{$baseUrl}/complaints/{$comp2Id}/escalate", ['target_level' => 'DISTRICT_OFFICER', 'reason' => 'Requesting color spectrophotometer check.'], $sewaToken);
$esc2Order = $esc2['data']['data']['order_id'];
httpRequest('POST', "{$baseUrl}/payments/confirm", ['order_id' => $esc2Order, 'amount' => 472.00], $sewaToken);

// District Officer REJECTS escalation
$rejectEscDecision = httpRequest('POST', "{$baseUrl}/complaints/{$comp2Id}/decide-escalation", [
    'decision' => 'REJECTED',
    'remarks' => 'Frivolous complaint. Paint batch certificate satisfies MoRTH specs.',
], $doNagpurToken);

$rejectData = $rejectEscDecision['data']['data'] ?? [];
$hasRefundOnReject = !empty($rejectData['refund']);

assertTest(
    'Higher Authority Rejects Complaint -> Fee Retained (No Refund)',
    $rejectEscDecision['status'] === 200 && !$hasRefundOnReject,
    "Decision: " . ($rejectData['decision'] ?? '') . ", Refund Issued: " . ($hasRefundOnReject ? 'YES (FAIL)' : 'NO (FEE RETAINED)')
);

// ---------------------------------------------------------
// TEST 11: Public Citizen Nudge: 1st Valid Nudge Delivered (201)
// ---------------------------------------------------------
$todayDate = '2026-09-25';
$validNudge = httpRequest('POST', "{$baseUrl}/nudges", [
    'tender_id' => 1,
    'ngo_id' => $sewaNgoId,
    'reason' => 'Please verify the subgrade work near Hingna junction thoroughly.',
    'simulate_date' => $todayDate,
], $citizenToken);

assertTest(
    'Citizen Nudge Happy Path: Valid Reason (>=10 chars) Delivered',
    $validNudge['status'] === 201 && ($validNudge['data']['data']['delivered'] ?? false) === true,
    "HTTP {$validNudge['status']} - Delivered: YES, Chance Consumed: YES"
);

// ---------------------------------------------------------
// TEST 12: Public Citizen Nudge: 2nd Nudge on Same Day Refused (429)
// ---------------------------------------------------------
$secondNudge = httpRequest('POST', "{$baseUrl}/nudges", [
    'tender_id' => 1,
    'ngo_id' => $sewaNgoId,
    'reason' => 'Another reminder on the same day for extra emphasis.',
    'simulate_date' => $todayDate,
], $citizenToken);

assertTest(
    'Citizen Nudge Rule: 2nd Nudge on Same Day Blocked (429)',
    $secondNudge['status'] === 429,
    "HTTP {$secondNudge['status']} - " . ($secondNudge['data']['message'] ?? '')
);

// ---------------------------------------------------------
// TEST 13: Public Citizen Nudge: Next Day (Simulated) Works
// Short/Invalid Reason Burns Today's Chance & Is NOT Delivered to NGO
// ---------------------------------------------------------
$nextDay = '2026-09-26';
$shortReasonNudge = httpRequest('POST', "{$baseUrl}/nudges", [
    'tender_id' => 1,
    'ngo_id' => $sewaNgoId,
    'reason' => 'Short', // Less than 10 characters!
    'simulate_date' => $nextDay,
], $citizenToken);

$shortData = $shortReasonNudge['data']['data'] ?? [];
$isDelivered = ($shortData['delivered'] ?? true) === false;
$isBurned = ($shortData['chance_consumed'] ?? false) === true;

assertTest(
    'Invalid Reason (<10 chars) Burns Day Chance But Does NOT Deliver to NGO',
    $isDelivered && $isBurned,
    "Delivered: " . (!empty($shortData['delivered']) ? 'YES (FAIL)' : 'NO (CORRECT)') . ", Chance Consumed: YES"
);

// Second attempt on that simulated next day is ALSO blocked because chance was burned!
$afterBurnAttempt = httpRequest('POST', "{$baseUrl}/nudges", [
    'tender_id' => 1,
    'ngo_id' => $sewaNgoId,
    'reason' => 'Now trying with a much longer valid reason on the same day after burning chance.',
    'simulate_date' => $nextDay,
], $citizenToken);

assertTest(
    'Burned Chance Prevents Any Further Nudges on Same Day (429)',
    $afterBurnAttempt['status'] === 429,
    "HTTP {$afterBurnAttempt['status']} - " . ($afterBurnAttempt['data']['message'] ?? '')
);

// ---------------------------------------------------------
// TEST 14: Notifications Verification
// NGO received notification for delivered nudge, but NOT for rejected short reason nudge!
// ---------------------------------------------------------
$ngoNotifs = httpRequest('GET', "{$baseUrl}/notifications", null, $sewaToken);
$nList = $ngoNotifs['data']['data'] ?? [];
$nudgeNotifs = array_filter($nList, fn($n) => $n['type'] === 'PUBLIC_NUDGE');

assertTest(
    'Delivered Nudge Created Notification for NGO (1 received, 0 for short reason)',
    count($nudgeNotifs) >= 1,
    "Found " . count($nudgeNotifs) . " citizen nudge notification(s) in NGO inbox."
);

echo "\n========================================================\n";
echo "PART 4 TEST RESULTS: {$passed} PASSED | {$failed} FAILED\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
