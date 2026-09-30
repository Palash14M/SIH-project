<?php

require_once __DIR__ . '/../backend/config/config.php';
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/utils/Jwt.php';

echo "========================================================\n";
echo "           PART 5: GATE VERIFICATION TEST SUITE          \n";
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

// ---------------------------------------------------------
// TEST 1: Wrong credentials rejected (401)
// ---------------------------------------------------------
$badLogin = httpRequest('POST', "{$baseUrl}/auth/login", [
    'email' => 'admin@mosje.gov.in',
    'password' => 'CompletelyWrongPassword!',
]);
assertTest(
    'Wrong Credentials Rejected with 401 Error',
    $badLogin['status'] === 401 && ($badLogin['data']['success'] ?? true) === false,
    "HTTP {$badLogin['status']} - Message: " . ($badLogin['data']['message'] ?? '')
);

// ---------------------------------------------------------
// TEST 2: Role Login: MoSJE Admin
// ---------------------------------------------------------
$adminLogin = httpRequest('POST', "{$baseUrl}/auth/login", [
    'email' => 'admin@mosje.gov.in',
    'password' => 'Admin@12345',
]);
$adminToken = $adminLogin['data']['data']['token'] ?? null;
$adminRole = $adminLogin['data']['data']['user']['role'] ?? null;
assertTest(
    'MoSJE Admin Authenticates Successfully',
    $adminLogin['status'] === 200 && $adminRole === 'MOSJE_ADMIN' && !empty($adminToken),
    "Role: {$adminRole}, Name: " . ($adminLogin['data']['data']['user']['name'] ?? '')
);

// ---------------------------------------------------------
// TEST 3: Role Login: State Officer
// ---------------------------------------------------------
$stateLogin = httpRequest('POST', "{$baseUrl}/auth/login", [
    'email' => 'state.mh@mosje.gov.in',
    'password' => 'Officer@12345',
]);
$stateToken = $stateLogin['data']['data']['token'] ?? null;
$stateRole = $stateLogin['data']['data']['user']['role'] ?? null;
assertTest(
    'State Officer Authenticates Successfully',
    $stateLogin['status'] === 200 && $stateRole === 'STATE_OFFICER' && !empty($stateToken),
    "Role: {$stateRole}, Name: " . ($stateLogin['data']['data']['user']['name'] ?? '')
);

// ---------------------------------------------------------
// TEST 4: Role Login: District Officer
// ---------------------------------------------------------
$doLogin = httpRequest('POST', "{$baseUrl}/auth/login", [
    'email' => 'district.nagpur@mosje.gov.in',
    'password' => 'Officer@12345',
]);
$doToken = $doLogin['data']['data']['token'] ?? null;
$doRole = $doLogin['data']['data']['user']['role'] ?? null;
assertTest(
    'District Officer Authenticates Successfully',
    $doLogin['status'] === 200 && $doRole === 'DISTRICT_OFFICER' && !empty($doToken),
    "Role: {$doRole}, District ID: " . ($doLogin['data']['data']['user']['district_id'] ?? '')
);

// ---------------------------------------------------------
// TEST 5: Role Login: Inspector
// ---------------------------------------------------------
$inspLogin = httpRequest('POST', "{$baseUrl}/auth/login", [
    'email' => 'inspector.rajesh@mosje.gov.in',
    'password' => 'Inspect@12345',
]);
$inspToken = $inspLogin['data']['data']['token'] ?? null;
$inspRole = $inspLogin['data']['data']['user']['role'] ?? null;
assertTest(
    'Field Inspector Authenticates Successfully',
    $inspLogin['status'] === 200 && $inspRole === 'INSPECTOR' && !empty($inspToken),
    "Role: {$inspRole}, Name: " . ($inspLogin['data']['data']['user']['name'] ?? '')
);

// ---------------------------------------------------------
// TEST 6: Role Login: Approved NGO
// ---------------------------------------------------------
$ngoLogin = httpRequest('POST', "{$baseUrl}/auth/login", [
    'email' => 'ngo.sewa@org.in',
    'password' => 'Ngo@12345',
]);
$ngoToken = $ngoLogin['data']['data']['token'] ?? null;
$ngoRole = $ngoLogin['data']['data']['user']['role'] ?? null;
$ngoStatus = $ngoLogin['data']['data']['user']['ngo']['status'] ?? null;
assertTest(
    'Approved NGO Authenticates Successfully',
    $ngoLogin['status'] === 200 && $ngoRole === 'NGO' && $ngoStatus === 'APPROVED' && !empty($ngoToken),
    "Role: {$ngoRole}, NGO Status: {$ngoStatus}"
);

// ---------------------------------------------------------
// TEST 7: Public Citizen OTP End-to-End Flow
// ---------------------------------------------------------
$citizenPhone = '95' . rand(10000000, 99999999);
$otpReq = httpRequest('POST', "{$baseUrl}/auth/otp/request", ['phone' => $citizenPhone]);
$demoOtp = $otpReq['data']['data']['demo_otp'] ?? null;
$otpVerify = httpRequest('POST', "{$baseUrl}/auth/otp/verify", [
    'phone' => $citizenPhone,
    'otp' => $demoOtp,
]);
$citizenToken = $otpVerify['data']['data']['token'] ?? null;
$citizenRole = $otpVerify['data']['data']['user']['role'] ?? null;
assertTest(
    'Public Citizen OTP Flow Works End-to-End',
    $otpVerify['status'] === 200 && $citizenRole === 'PUBLIC' && !empty($citizenToken),
    "Role: {$citizenRole}, Phone: {$citizenPhone}, Token Acquired"
);

// ---------------------------------------------------------
// TEST 8: Expired / Invalid Token Forces Re-login (401)
// ---------------------------------------------------------
$invalidTokenReq = httpRequest('GET', "{$baseUrl}/notifications", null, "invalid.or.expired.jwt.token");
assertTest(
    'Expired / Tampered Token Forces Re-login (401)',
    $invalidTokenReq['status'] === 401,
    "HTTP {$invalidTokenReq['status']} - " . ($invalidTokenReq['data']['message'] ?? '')
);

// ---------------------------------------------------------
// TEST 9: Role Home Data: Inspector Home Feed
// ---------------------------------------------------------
$inspHomeTenders = httpRequest('GET', "{$baseUrl}/tenders", null, $inspToken);
$inspTendersList = $inspHomeTenders['data']['data'] ?? [];
assertTest(
    'Inspector Home Loads Assigned Tenders Data',
    $inspHomeTenders['status'] === 200 && is_array($inspTendersList),
    "Retrieved " . count($inspTendersList) . " tender(s) for Inspector Home feed"
);

// ---------------------------------------------------------
// TEST 10: Role Home Data: District Officer Home Feed
// ---------------------------------------------------------
$doHomeTenders = httpRequest('GET', "{$baseUrl}/tenders", null, $doToken);
$doTendersList = $doHomeTenders['data']['data'] ?? [];
assertTest(
    'District Officer Home Loads Jurisdiction Tenders Data',
    $doHomeTenders['status'] === 200 && is_array($doTendersList),
    "Retrieved " . count($doTendersList) . " district tender(s)"
);

// ---------------------------------------------------------
// TEST 11: Role Home Data: Public Citizen Feed Masks Contractor Contacts
// ---------------------------------------------------------
$publicHomeTenders = httpRequest('GET', "{$baseUrl}/tenders", null, $citizenToken);
$pList = $publicHomeTenders['data']['data'] ?? [];
$firstPTender = $pList[0] ?? [];
$publicHasContractorPhone = isset($firstPTender['contractor_phone']);
$publicHasContractorEmail = isset($firstPTender['contractor_email']);
assertTest(
    'Public Home Feed Enforces Strict Data Masking',
    $publicHomeTenders['status'] === 200 && !$publicHasContractorPhone && !$publicHasContractorEmail,
    "Contractor Contacts Masked: YES | Public Summary Accessible: YES"
);

// ---------------------------------------------------------
// TEST 12: Notifications Endpoint for Home Badge
// ---------------------------------------------------------
$notifRes = httpRequest('GET', "{$baseUrl}/notifications/unread-count", null, $doToken);
$unreadCount = $notifRes['data']['data']['unread_count'] ?? null;
assertTest(
    'Notifications Unread Count Accessible for Role Home Badge',
    $notifRes['status'] === 200 && $unreadCount !== null,
    "Unread count: {$unreadCount}"
);

echo "\n========================================================\n";
echo "PART 5 TEST RESULTS: {$passed} PASSED | {$failed} FAILED\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
