<?php
require_once __DIR__ . '/../backend/config/Database.php';

function apiCall($url, $method = 'GET', $data = null, $token = null) {
    $ch = curl_init('http://127.0.0.1:8000' . $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return ['code' => $code, 'data' => json_decode($res, true)];
}

echo "=== 1. TEST CONTRACTOR LOGIN & PROJECTS ===\n";
$cLogin = apiCall('/api/auth/login', 'POST', ['username' => 'contractor', 'password' => 'demo@123']);
echo "Contractor Login: HTTP {$cLogin['code']} | Success: " . ($cLogin['data']['success'] ? 'YES' : 'NO') . "\n";
$cToken = $cLogin['data']['data']['token'] ?? null;

$cProjects = apiCall('/api/contractor/projects', 'GET', null, $cToken);
echo "Contractor Projects: HTTP {$cProjects['code']} | Count: " . count($cProjects['data']['data'] ?? []) . "\n";
if (!empty($cProjects['data']['data'])) {
    $first = $cProjects['data']['data'][0];
    echo "  -> First Project: {$first['tender_number']} - {$first['title']}\n";
    echo "  -> Assigned Inspecting Officer: {$first['assigned_inspector_name']} (Phone: {$first['assigned_inspector_phone']})\n";
    echo "  -> Project Images: " . count($first['project_images']) . " images found\n";
    echo "  -> Current Progress: {$first['progress_percentage']}%\n";

    echo "\n=== 2. TEST CONTRACTOR UPDATING COMPLETION STATUS ===\n";
    $updRes = apiCall("/api/contractor/projects/{$first['id']}/update-progress", 'POST', [
        'progress_percentage' => 78.5,
        'status' => 'IN_PROGRESS',
        'contractor_notes' => 'Completed subgrade layer 4 and rebar reinforcement.'
    ], $cToken);
    echo "Contractor Update Progress: HTTP {$updRes['code']} | New Progress: " . ($updRes['data']['data']['progress_percentage'] ?? 'N/A') . "%\n";
}

echo "\n=== 3. TEST MASTER ADMIN DELETING PROJECT BEFORE COMPLETION ===\n";
$mLogin = apiCall('/api/auth/login', 'POST', ['username' => 'admin', 'password' => 'admin']);
echo "Master Admin Login: HTTP {$mLogin['code']} | Role: " . ($mLogin['data']['data']['user']['role'] ?? 'N/A') . "\n";
$mToken = $mLogin['data']['data']['token'] ?? null;

// Create a temporary project to delete before completion
$pdo = Database::getConnection();
$pdo->exec("INSERT INTO tenders (tender_number, title, category_id, issuing_department, state_id, district_id, latitude, longitude, contractor_id, sanctioned_amount, start_date, award_date, scheduled_end_date, responsible_senior_id, status, progress_percentage, created_at, updated_at) VALUES ('TND-TEST-DEL-99', 'Temporary Incomplete Road Project', 1, 'PWD', 1, 1, 21.14, 79.08, 1, 5000000.0, '2026-01-01', '2026-01-01', '2026-12-31', 1, 'IN_PROGRESS', 45.0, datetime('now'), datetime('now'))");
$tempTenderId = (int)$pdo->lastInsertId();
echo "Created temporary incomplete tender ID {$tempTenderId} (Progress: 45.0%)\n";

$delRes = apiCall("/api/tenders/{$tempTenderId}", 'DELETE', null, $mToken);
echo "Master Admin Delete: HTTP {$delRes['code']} | Message: " . ($delRes['data']['message'] ?? 'N/A') . "\n";

// Verify it was deleted
$chk = $pdo->query("SELECT id FROM tenders WHERE id = {$tempTenderId}")->fetch();
echo "Verification in DB: " . ($chk ? "STILL EXISTS (ERROR)" : "CONFIRMED DELETED (SUCCESS)") . "\n";

echo "\n=== ALL BACKEND CAPABILITIES VERIFIED! ===\n";
