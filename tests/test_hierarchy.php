<?php
$ch = curl_init('http://127.0.0.1:8000/api/auth/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email' => 'admin@mosje.gov.in', 'password' => 'Admin@12345']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$res = json_decode(curl_exec($ch), true);
$token = $res['data']['token'] ?? null;
curl_close($ch);

$ch2 = curl_init('http://127.0.0.1:8000/api/hierarchy');
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
$res2 = json_decode(curl_exec($ch2), true);
curl_close($ch2);

echo "Admin Role: " . ($res2['data']['user_role'] ?? 'none') . "\n";
echo "Contacts visible to Admin: " . (($res2['data']['contacts_visible'] ?? false) ? 'YES' : 'NO') . "\n";
echo "First official phone: " . ($res2['data']['officials_catalog'][0]['phone'] ?? 'none') . "\n";

// Test Public (unauthenticated)
$ch3 = curl_init('http://127.0.0.1:8000/api/hierarchy');
curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
$res3 = json_decode(curl_exec($ch3), true);
curl_close($ch3);

echo "Public Role: " . ($res3['data']['user_role'] ?? 'none') . "\n";
echo "Contacts visible to Public: " . (($res3['data']['contacts_visible'] ?? false) ? 'YES' : 'NO') . "\n";
echo "Can nudge (Public): " . (($res3['data']['can_nudge'] ?? false) ? 'YES' : 'NO') . "\n";
echo "Public first official phone: " . ($res3['data']['officials_catalog'][0]['phone'] ?? 'null') . "\n";
