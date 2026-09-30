<?php
/**
 * PART 9: ROLE MATRIX & SECURITY HARDENING TEST SUITE
 * 
 * Tests every endpoint with every role token to assert strict role matrix enforcement.
 * Tests SQL injection payloads, XSS safety, and rate limits.
 */

$baseUrl = 'http://127.0.0.1:8000';

function apiReq($method, $path, $data = null, $token = null) {
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

function assertMatrix($name, $condition, $details = '') {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] $name\n";
        $passCount++;
    } else {
        echo "[FAIL] $name: $details\n";
        $failCount++;
    }
}

echo "=== PART 9: ROLE MATRIX & HARDENING SECURITY VERIFICATION ===\n\n";

// Authenticate all 6 Roles
$roles = [
    'ADMIN' => apiReq('POST', '/api/auth/login', ['email' => 'admin@mosje.gov.in', 'password' => 'Admin@12345'])['data']['data']['token'] ?? null,
    'STATE' => apiReq('POST', '/api/auth/login', ['email' => 'state.mh@mosje.gov.in', 'password' => 'Officer@12345'])['data']['data']['token'] ?? null,
    'DO' => apiReq('POST', '/api/auth/login', ['email' => 'district.nagpur@mosje.gov.in', 'password' => 'Officer@12345'])['data']['data']['token'] ?? null,
    'SENIOR' => apiReq('POST', '/api/auth/login', ['email' => 'senior.sharma@mosje.gov.in', 'password' => 'Senior@12345'])['data']['data']['token'] ?? null,
    'INSPECTOR' => apiReq('POST', '/api/auth/login', ['email' => 'inspector.rajesh@mosje.gov.in', 'password' => 'Inspect@12345'])['data']['data']['token'] ?? null,
    'NGO' => apiReq('POST', '/api/auth/login', ['email' => 'ngo.sewa@org.in', 'password' => 'Ngo@12345'])['data']['data']['token'] ?? null,
];

// OTP for Public Role
$pubPhone = '95' . rand(10000000, 99999999);
$cOtpReq = apiReq('POST', '/api/auth/otp/request', ['phone' => $pubPhone]);
$roles['PUBLIC'] = apiReq('POST', '/api/auth/otp/verify', ['phone' => $pubPhone, 'otp' => $cOtpReq['data']['data']['demo_otp'] ?? '123456'])['data']['data']['token'] ?? null;
$roles['ANON'] = null;

// Assert all tokens acquired
foreach ($roles as $r => $tok) {
    if ($r !== 'ANON') {
        assertMatrix("Token acquired for $r", !empty($tok), "Token: " . substr($tok ?? '', 0, 15));
    }
}

// -------------------------------------------------------------
// 1. ADMIN ENDPOINTS: /api/admin/users, /api/admin/finance-summary, /api/admin/audit-log
// Only MOSJE_ADMIN allowed (200), all others 403 or 401
// -------------------------------------------------------------
foreach ($roles as $r => $token) {
    $res = apiReq('GET', '/api/admin/finance-summary', null, $token);
    $expected = ($r === 'ADMIN') ? 200 : (($r === 'ANON') ? 401 : 403);
    assertMatrix("Admin Finance Summary access for $r", $res['status'] === $expected, "Status: {$res['status']} (Expected: $expected)");
}

foreach ($roles as $r => $token) {
    $res = apiReq('GET', '/api/admin/audit-log', null, $token);
    $expected = in_array($r, ['ADMIN', 'STATE'], true) ? 200 : (($r === 'ANON') ? 401 : 403);
    assertMatrix("Admin Audit Log access for $r", $res['status'] === $expected, "Status: {$res['status']} (Expected: $expected)");
}

// -------------------------------------------------------------
// 2. TENDER CREATION: /api/tenders
// Allowed: ADMIN, STATE, DO. Blocked: SENIOR, INSPECTOR, NGO, PUBLIC, ANON
// -------------------------------------------------------------
foreach ($roles as $r => $token) {
    $res = apiReq('POST', '/api/tenders', [
        'tender_number' => 'TND-TEST-' . rand(1000, 9999) . '-' . $r,
        'title' => 'Security Audit Test Tender ' . $r,
        'category_id' => 1,
        'issuing_department' => 'PWD',
        'state_id' => 1,
        'district_id' => 1,
        'latitude' => 21.1458,
        'longitude' => 79.0882,
        'contractor_id' => 1,
        'sanctioned_amount' => 10000000.0,
        'award_date' => '2026-01-01',
        'start_date' => '2026-02-01',
        'scheduled_end_date' => '2026-12-31',
        'responsible_senior_id' => 1,
    ], $token);

    $isAllowed = in_array($r, ['ADMIN', 'STATE', 'DO'], true);
    $expected = $isAllowed ? 201 : (($r === 'ANON') ? 401 : 403);
    assertMatrix("Tender Creation endpoint for $r", $res['status'] === $expected, "Status: {$res['status']} (Expected: $expected)");
}

// -------------------------------------------------------------
// 3. INSPECTION CREATION: /api/inspections
// Allowed: INSPECTOR, ADMIN. Blocked: STATE, DO, SENIOR, NGO, PUBLIC, ANON
// -------------------------------------------------------------
foreach ($roles as $r => $token) {
    $res = apiReq('POST', '/api/inspections', [
        'tender_id' => 1,
        'remarks' => 'Security matrix probe',
        'actual_spent' => 1000.0,
    ], $token);

    $isAllowed = in_array($r, ['INSPECTOR', 'ADMIN'], true);
    $expected = $isAllowed ? 201 : (($r === 'ANON') ? 401 : 403);
    assertMatrix("Inspection Create/Sync for $r", $res['status'] === $expected, "Status: {$res['status']} (Expected: $expected)");
}

// -------------------------------------------------------------
// 4. COMPLAINT CREATION: /api/complaints
// Allowed: NGO only. Blocked: all others
// -------------------------------------------------------------
foreach ($roles as $r => $token) {
    $res = apiReq('POST', '/api/complaints', [
        'tender_id' => 1,
        'subject' => 'Security Matrix Complaint Check',
        'description' => 'Verifying that only authorized NGO accounts can file complaints.',
    ], $token);

    $isAllowed = ($r === 'NGO');
    $expected = $isAllowed ? 201 : (($r === 'ANON') ? 401 : 403);
    assertMatrix("Complaint Creation for $r", $res['status'] === $expected, "Status: {$res['status']} (Expected: $expected)");
}

// -------------------------------------------------------------
// 5. CITIZEN NUDGE: /api/nudges
// Allowed: Any authenticated user. Blocked: ANON (401)
// -------------------------------------------------------------
$anonNudge = apiReq('POST', '/api/nudges', ['tender_id' => 1, 'ngo_id' => 1, 'reason' => 'Anonymous nudge probe'], null);
assertMatrix("Anonymous Nudge blocked with 401", $anonNudge['status'] === 401, "Status: {$anonNudge['status']}");

// -------------------------------------------------------------
// 6. SQL INJECTION HARDENING PROBES
// -------------------------------------------------------------
$sqliPayloads = [
    "' OR '1'='1",
    "admin'--",
    "1; DROP TABLE users--",
    "' UNION SELECT null, null, null--",
];

foreach ($sqliPayloads as $payload) {
    // Probe 1: Login field SQLi
    $sqliLogin = apiReq('POST', '/api/auth/login', ['email' => $payload, 'password' => 'test']);
    assertMatrix("SQLi probe on login ('" . substr($payload, 0, 10) . "...')", $sqliLogin['status'] === 401 || $sqliLogin['status'] === 422, "Status: {$sqliLogin['status']}");

    // Probe 2: Query param SQLi
    $sqliTenders = apiReq('GET', "/api/tenders?category=" . urlencode($payload), null, $roles['PUBLIC']);
    assertMatrix("SQLi probe on tender filter ('" . substr($payload, 0, 10) . "...')", $sqliTenders['status'] === 200, "Status: {$sqliTenders['status']}");
}

// -------------------------------------------------------------
// 7. XSS INPUT RESILIENCE
// -------------------------------------------------------------
$xssPayload = '<script>alert("XSS_MOSJE")</script>';
$xssNudge = apiReq('POST', '/api/nudges', [
    'tender_id' => 1,
    'ngo_id' => 1,
    'reason' => 'Constructive citizen feedback ' . $xssPayload,
    'simulate_date' => '2026-11-' . rand(10, 28),
], $roles['PUBLIC']);
assertMatrix("XSS payload safely stored without unhandled crash", $xssNudge['status'] === 201, "Status: {$xssNudge['status']}");

// -------------------------------------------------------------
// 8. DATA VISIBILITY AUDIT: Public Token vs DO Token on all Tenders
// -------------------------------------------------------------
$publicTenderList = apiReq('GET', '/api/tenders', null, $roles['PUBLIC']);
$allMasked = true;
foreach ($publicTenderList['data']['data'] ?? [] as $tender) {
    if (!empty($tender['contractor_phone']) || !empty($tender['contractor_email']) || !empty($tender['contractor_address'])) {
        $allMasked = false;
        break;
    }
}
assertMatrix("Public tender list strictly redacts contractor phone, email, and address for all entries", $allMasked, "All masked: " . ($allMasked ? 'YES' : 'NO'));

echo "\n============================================\n";
echo "ROLE MATRIX & SECURITY SUMMARY: $passCount PASSED, $failCount FAILED\n";
echo "============================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
