<?php
$cases = [
    ['inspector.rajesh@mosje.gov.in', 'demo@123'],
    ['inspector', 'demo@123'],
    ['admin', 'admin'],
    ['admin@master.gov.in', 'demo@123'],
    ['contractor@larseninfra.com', 'demo@123'],
    ['contractor', 'demo@123'],
    ['district.nagpur@mosje.gov.in', 'demo@123'],
    ['district', 'demo@123'],
    ['state.mh@mosje.gov.in', 'demo@123'],
    ['state', 'demo@123'],
    ['mosje.admin@gov.in', 'demo@123'],
    ['mosje', 'demo@123'],
    ['demo.ngo@example.org', 'demo@123'],
    ['ngo', 'demo@123']
];

foreach ($cases as $c) {
    $ch = curl_init('http://127.0.0.1:8000/api/auth/login');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['username' => $c[0], 'password' => $c[1]]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $res = json_decode(curl_exec($ch), true);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $role = $res['data']['user']['role'] ?? 'FAILED';
    $status = (!empty($res['success'])) ? 'SUCCESS' : 'ERROR: ' . ($res['message'] ?? 'Unknown');
    echo "Login [{$c[0]}] / [{$c[1]}]: HTTP {$code} -> Role: {$role} -> Status: {$status}\n";
}
