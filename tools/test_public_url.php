<?php
// Test public preview URL and complete action flow through it

$publicUrl = 'https://smart-inspection-mosje.onrender.com';
echo "=== TESTING CLOUD HOST ENDPOINT: $publicUrl ===\n\n";

function fetchPublic($path, $method = 'GET', $data = null, $token = null) {
    global $publicUrl;
    $ch = curl_init($publicUrl . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $headers = [
        'Bypass-Tunnel-Reminder: true',
        'User-Agent: MoSJE-Public-Auditor/1.0',
        'Accept: application/json'
    ];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    if ($data !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($data) ? $data : json_encode($data));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    
    $json = json_decode($raw, true);
    return ['status' => $status, 'data' => $json, 'raw' => $raw, 'error' => $err];
}

// 1. Fetch public preview root
$rootRes = fetchPublic('/');
echo "[1] Root Preview Page: HTTP {$rootRes['status']}\n";
if (str_contains($rootRes['raw'] ?? '', 'MoSJE')) {
    echo "    -> Confirmed: MoSJE Preview UI loaded successfully via public URL!\n";
}

// 2. Fetch /api/health through public tunnel
$healthRes = fetchPublic('/api/health');
echo "[2] /api/health via Public URL: HTTP {$healthRes['status']}\n";
echo "    -> Response: " . json_encode($healthRes['data']) . "\n";

// 3. Login through public tunnel
$loginRes = fetchPublic('/api/auth/login', 'POST', [
    'email' => 'admin@mosje.gov.in',
    'password' => 'Admin@12345'
]);
echo "[3] Login via Public URL: HTTP {$loginRes['status']}\n";
$token = $loginRes['data']['data']['token'] ?? null;
echo "    -> Token acquired: " . substr($token ?? 'none', 0, 25) . "...\n";

// 4. Authenticated Action: Fetch tenders through public tunnel
$tendersRes = fetchPublic('/api/tenders', 'GET', null, $token);
echo "[4] Authenticated Action (Fetch Tenders): HTTP {$tendersRes['status']}\n";
$count = count($tendersRes['data']['data'] ?? []);
echo "    -> Retrieved $count tenders successfully through public tunnel!\n\n";

if ($rootRes['status'] === 200 && $healthRes['status'] === 200 && $loginRes['status'] === 200 && $count > 0) {
    echo "=== PUBLIC PREVIEW URL VERIFICATION: FULLY OPERATIONAL ===\n";
    exit(0);
} else {
    echo "=== PUBLIC PREVIEW URL VERIFICATION: FAILED ===\n";
    exit(1);
}
