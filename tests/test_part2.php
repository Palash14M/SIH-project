<?php

require_once __DIR__ . '/../backend/config/config.php';
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/utils/Jwt.php';

echo "========================================================\n";
echo "           PART 2: GATE VERIFICATION TEST SUITE          \n";
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
// TEST 1: Valid Login (Admin)
// ---------------------------------------------------------
$adminLogin = httpRequest('POST', "{$baseUrl}/auth/login", [
    'email' => 'admin@mosje.gov.in',
    'password' => 'Admin@12345',
]);
$adminToken = $adminLogin['data']['data']['token'] ?? null;
assertTest(
    'Admin Login Happy Path',
    $adminLogin['status'] === 200 && !empty($adminToken),
    "HTTP {$adminLogin['status']}, Token received: " . substr($adminToken ?? '', 0, 25) . "..."
);

// ---------------------------------------------------------
// TEST 2: Wrong Password Rejected (401)
// ---------------------------------------------------------
$wrongPass = httpRequest('POST', "{$baseUrl}/auth/login", [
    'email' => 'admin@mosje.gov.in',
    'password' => 'WrongPassword123!',
]);
assertTest(
    'Wrong Password Rejected',
    $wrongPass['status'] === 401 && ($wrongPass['data']['success'] ?? true) === false,
    "HTTP {$wrongPass['status']} - Message: " . ($wrongPass['data']['message'] ?? '')
);

// ---------------------------------------------------------
// TEST 3: Inspector Login
// ---------------------------------------------------------
$inspLogin = httpRequest('POST', "{$baseUrl}/auth/login", [
    'email' => 'inspector.rajesh@mosje.gov.in',
    'password' => 'Inspect@12345',
]);
$inspToken = $inspLogin['data']['data']['token'] ?? null;
assertTest(
    'Inspector Login Happy Path',
    $inspLogin['status'] === 200 && !empty($inspToken),
    "HTTP {$inspLogin['status']}, Role: " . ($inspLogin['data']['data']['user']['role'] ?? '')
);

// ---------------------------------------------------------
// TEST 4: Public Citizen OTP Happy Path
// ---------------------------------------------------------
$testPhone = '99' . rand(10000000, 99999999);
$otpReq = httpRequest('POST', "{$baseUrl}/auth/otp/request", ['phone' => $testPhone]);
$demoOtp = $otpReq['data']['data']['demo_otp'] ?? null;
assertTest(
    'Citizen OTP Request Happy Path',
    $otpReq['status'] === 200 && !empty($demoOtp),
    "HTTP {$otpReq['status']}, Demo OTP: {$demoOtp}"
);

$otpVerify = httpRequest('POST', "{$baseUrl}/auth/otp/verify", [
    'phone' => $testPhone,
    'otp' => $demoOtp,
]);
$publicToken = $otpVerify['data']['data']['token'] ?? null;
assertTest(
    'Citizen OTP Verify Happy Path',
    $otpVerify['status'] === 200 && !empty($publicToken),
    "HTTP {$otpVerify['status']}, Role: " . ($otpVerify['data']['data']['user']['role'] ?? '')
);

// ---------------------------------------------------------
// TEST 5: OTP Expired Flow
// ---------------------------------------------------------
// Manually insert an expired OTP in database
$pdo = Database::getConnection();
$expiredPhone = '98' . rand(10000000, 99999999);
$expiredOtp = '654321';
$pastTime = date('Y-m-d H:i:s', time() - 400);
$pdo->prepare("
    INSERT INTO otp_codes (phone, code, attempts, expires_at, created_at)
    VALUES (:p, :c, 0, :exp, :cre)
")->execute([':p' => $expiredPhone, ':c' => $expiredOtp, ':exp' => $pastTime, ':cre' => $pastTime]);

$expiredVerify = httpRequest('POST', "{$baseUrl}/auth/otp/verify", [
    'phone' => $expiredPhone,
    'otp' => $expiredOtp,
]);
assertTest(
    'Expired OTP Rejected',
    $expiredVerify['status'] === 400 && str_contains($expiredVerify['data']['message'] ?? '', 'expired'),
    "HTTP {$expiredVerify['status']} - " . ($expiredVerify['data']['message'] ?? '')
);

// ---------------------------------------------------------
// TEST 6: OTP Rate Limit (Max 5/hr)
// ---------------------------------------------------------
$ratePhone = '97' . rand(10000000, 99999999);
// Make 5 requests (the limit)
for ($i = 1; $i <= 5; $i++) {
    httpRequest('POST', "{$baseUrl}/auth/otp/request", ['phone' => $ratePhone]);
}
// 6th request must be rejected with 429
$rateLimitReq = httpRequest('POST', "{$baseUrl}/auth/otp/request", ['phone' => $ratePhone]);
assertTest(
    'OTP Rate Limit Triggered (429 on 6th request)',
    $rateLimitReq['status'] === 429,
    "HTTP {$rateLimitReq['status']} - " . ($rateLimitReq['data']['message'] ?? '')
);

// ---------------------------------------------------------
// TEST 7: Unapproved NGO Blocked from Officer/Admin Endpoints
// ---------------------------------------------------------
// Register a fresh pending NGO to guarantee idempotency across multiple test runs
$uniqueNgoReg = 'TEST-NGO-' . time() . '-' . rand(100, 999);
$pendingEmail = 'testpending.' . time() . rand(10, 99) . '@org.in';
$pendingRegRes = httpRequest('POST', "{$baseUrl}/auth/ngo/register", [
    'organization_name' => 'Gramin Idempotent Test NGO',
    'registration_number' => $uniqueNgoReg,
    'contact_person' => 'Ramesh Test',
    'mobile' => '9822' . rand(100000, 999999),
    'email' => $pendingEmail,
    'password' => 'Ngo@12345',
    'state_id' => 1,
    'district_id' => 1,
    'address' => 'Rural Test Center',
]);

$pendingNgoLogin = httpRequest('POST', "{$baseUrl}/auth/login", [
    'email' => $pendingEmail,
    'password' => 'Ngo@12345',
]);
$pendingNgoToken = $pendingNgoLogin['data']['data']['token'] ?? null;
assertTest(
    'Pending NGO Authenticates with Warning Status',
    $pendingNgoLogin['status'] === 200 && ($pendingNgoLogin['data']['data']['user']['ngo']['status'] ?? '') === 'PENDING',
    "Status: " . ($pendingNgoLogin['data']['data']['user']['ngo']['status'] ?? '')
);

// Try accessing admin endpoints with pending NGO token -> Must return 403
$ngoAdminAccess = httpRequest('GET', "{$baseUrl}/admin/users", null, $pendingNgoToken);
assertTest(
    'Pending NGO Rejected from Staff/Admin Endpoints (403)',
    $ngoAdminAccess['status'] === 403,
    "HTTP {$ngoAdminAccess['status']} - " . ($ngoAdminAccess['data']['message'] ?? '')
);

// ---------------------------------------------------------
// TEST 8: Role Middleware: Public Token Rejected on Staff Endpoint (403)
// ---------------------------------------------------------
$publicAdminAccess = httpRequest('GET', "{$baseUrl}/admin/users", null, $publicToken);
assertTest(
    'Public Token Blocked on Staff Endpoint (403)',
    $publicAdminAccess['status'] === 403,
    "HTTP {$publicAdminAccess['status']} - " . ($publicAdminAccess['data']['message'] ?? '')
);

// ---------------------------------------------------------
// TEST 9: Role Middleware: Inspector Cannot Reach Admin Endpoint (403)
// ---------------------------------------------------------
$inspAdminAccess = httpRequest('GET', "{$baseUrl}/admin/users", null, $inspToken);
assertTest(
    'Inspector Token Blocked on Admin Endpoint (403)',
    $inspAdminAccess['status'] === 403,
    "HTTP {$inspAdminAccess['status']} - " . ($inspAdminAccess['data']['message'] ?? '')
);

// ---------------------------------------------------------
// TEST 10: Admin Endpoint Works for Admin Token (200)
// ---------------------------------------------------------
$adminList = httpRequest('GET', "{$baseUrl}/admin/users", null, $adminToken);
$userCount = count($adminList['data']['data'] ?? []);
assertTest(
    'Admin Token Successfully Reaches Admin Endpoint (200)',
    $adminList['status'] === 200 && $userCount > 0,
    "HTTP {$adminList['status']} - Retrieved {$userCount} users"
);

// ---------------------------------------------------------
// TEST 11: Admin Creates New Staff & Updates Status
// ---------------------------------------------------------
$newStaffEmail = 'test.inspector.' . time() . '@mosje.gov.in';
$createStaff = httpRequest('POST', "{$baseUrl}/admin/users", [
    'name' => 'Automated Test Inspector',
    'email' => $newStaffEmail,
    'phone' => '9888' . substr((string)time(), -6),
    'password' => 'Inspect@12345',
    'role' => 'INSPECTOR',
    'district_id' => 1,
], $adminToken);

$newStaffId = $createStaff['data']['data']['id'] ?? 0;
assertTest(
    'Admin Creates Staff Account',
    $createStaff['status'] === 201 && $newStaffId > 0,
    "HTTP {$createStaff['status']} - Created ID: {$newStaffId}"
);

$deactivateStaff = httpRequest('PUT', "{$baseUrl}/admin/users/{$newStaffId}/status", [
    'status' => 'INACTIVE',
], $adminToken);
assertTest(
    'Admin Deactivates Staff Account',
    $deactivateStaff['status'] === 200 && ($deactivateStaff['data']['data']['new_status'] ?? '') === 'INACTIVE',
    "HTTP {$deactivateStaff['status']} - New Status: " . ($deactivateStaff['data']['data']['new_status'] ?? '')
);

// Deactivated staff cannot login
$inactiveLogin = httpRequest('POST', "{$baseUrl}/auth/login", [
    'email' => $newStaffEmail,
    'password' => 'Inspect@12345',
]);
assertTest(
    'Deactivated Staff Login Rejected (403)',
    $inactiveLogin['status'] === 403,
    "HTTP {$inactiveLogin['status']} - " . ($inactiveLogin['data']['message'] ?? '')
);

// ---------------------------------------------------------
// SUMMARY
// ---------------------------------------------------------
echo "\n========================================================\n";
echo "TEST RESULTS: {$passed} PASSED | {$failed} FAILED\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
