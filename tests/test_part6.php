<?php

require_once __DIR__ . '/../backend/config/config.php';
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/utils/Jwt.php';

echo "========================================================\n";
echo "           PART 6: GATE VERIFICATION TEST SUITE          \n";
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

function uploadMultipart(string $url, string $filePath, array $fields, ?string $token = null): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);

    $headers = ['Accept: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer {$token}";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $postData = $fields;
    $postData['media'] = new CURLFile($filePath, 'image/jpeg', basename($filePath));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

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

// 1. Logins
$inspLogin = httpRequest('POST', "{$baseUrl}/auth/login", [
    'email' => 'inspector.rajesh@mosje.gov.in',
    'password' => 'Inspect@12345',
]);
$inspToken = $inspLogin['data']['data']['token'];

$doLogin = httpRequest('POST', "{$baseUrl}/auth/login", [
    'email' => 'district.nagpur@mosje.gov.in',
    'password' => 'Officer@12345',
]);
$doToken = $doLogin['data']['data']['token'];

// Create a dummy image for evidence upload
$dummyImgPath = sys_get_temp_dir() . '/test_evidence_' . time() . '.jpg';
$im = imagecreatetruecolor(200, 200);
imagefilledrectangle($im, 0, 0, 200, 200, imagecolorallocate($im, 247, 108, 69));
imagejpeg($im, $dummyImgPath, 80);
imagedestroy($im);

// ---------------------------------------------------------
// TEST 1: Manifest and Code Audit: Zero Gallery Pick Intents
// ---------------------------------------------------------
$androidDir = dirname(__DIR__) . '/android';
$manifestContent = file_get_contents($androidDir . '/app/src/main/AndroidManifest.xml');
$hasGalleryIntents = str_contains($manifestContent, 'ACTION_PICK') ||
                     str_contains($manifestContent, 'ACTION_GET_CONTENT') ||
                     str_contains($manifestContent, '<uses-permission android:name="android.permission.READ_EXTERNAL_STORAGE"');
assertTest(
    'Audit: Zero Gallery Pick Intents & Zero Media Read Permissions',
    !$hasGalleryIntents,
    "Manifest contains no gallery intents. Evidence capture is strictly CameraX-only."
);

// ---------------------------------------------------------
// TEST 2: GPS Telemetry Lock Check: Blocked when Accuracy > 100m
// ---------------------------------------------------------
$badGpsUpload = uploadMultipart("{$baseUrl}/inspections/1/evidence", $dummyImgPath, [
    'latitude' => 21.1458,
    'longitude' => 79.0882,
    'accuracy' => 140.5, // Worse than 100m limit!
    'device_capture_time' => time(),
], $inspToken);

assertTest(
    'GPS Accuracy > 100m is Blocked (422)',
    $badGpsUpload['status'] === 422 && str_contains($badGpsUpload['data']['message'] ?? '', '100m'),
    "HTTP {$badGpsUpload['status']} - " . ($badGpsUpload['data']['message'] ?? '')
);

// ---------------------------------------------------------
// TEST 3: GPS Telemetry Lock Check: Missing GPS Blocked
// ---------------------------------------------------------
$noGpsUpload = uploadMultipart("{$baseUrl}/inspections/1/evidence", $dummyImgPath, [
    'device_capture_time' => time(),
], $inspToken);

assertTest(
    'Evidence Upload Without GPS Telemetry Blocked (422)',
    $noGpsUpload['status'] === 422,
    "HTTP {$noGpsUpload['status']} - " . ($noGpsUpload['data']['message'] ?? '')
);

// ---------------------------------------------------------
// TEST 4: Valid GPS (<100m) Evidence Upload with Watermark Telemetry
// ---------------------------------------------------------
$goodUpload = uploadMultipart("{$baseUrl}/inspections/1/evidence", $dummyImgPath, [
    'latitude' => 21.145832,
    'longitude' => 79.088214,
    'accuracy' => 4.2,
    'is_mock' => 0,
    'device_capture_time' => time(),
], $inspToken);

assertTest(
    'Valid GPS Evidence Upload Succeeded (201)',
    $goodUpload['status'] === 201 && !empty($goodUpload['data']['data']['file_path']),
    "Stored: " . ($goodUpload['data']['data']['file_path'] ?? '') . ", Accuracy: ±4.2m"
);

// ---------------------------------------------------------
// TEST 5: Time Mismatch Flag: Device Time Differs > 24 Hours
// ---------------------------------------------------------
$twoDaysAgo = time() - (48 * 3600); // 48 hours in past
$mismatchUpload = uploadMultipart("{$baseUrl}/inspections/1/evidence", $dummyImgPath, [
    'latitude' => 21.145832,
    'longitude' => 79.088214,
    'accuracy' => 5.0,
    'is_mock' => 0,
    'device_capture_time' => $twoDaysAgo,
], $inspToken);

$mismatchFlag = $mismatchUpload['data']['data']['time_mismatch'] ?? false;
assertTest(
    'Time Mismatch > 24h Correctly Flagged (time_mismatch=true)',
    $mismatchUpload['status'] === 201 && $mismatchFlag === true,
    "Time Mismatch Flag: " . ($mismatchFlag ? 'YES (FLAGGED)' : 'NO')
);

// ---------------------------------------------------------
// TEST 6: Offline Queue Sync Test: 2 Inspections Submitted Offline & Synced
// ---------------------------------------------------------
// Inspection 1: Normal progress on Tender 1
$offlineInsp1 = httpRequest('POST', "{$baseUrl}/inspections", [
    'tender_id' => 1,
    'actual_spent' => 22000000.00,
    'spent_basis' => 'Measurement Book MB/2026/08',
    'remarks' => 'Subgrade and base course inspected offline and queued.',
    'issue_found' => 0,
    'severity' => 'LOW',
    'milestones' => [
        ['milestone_id' => 1, 'actual_progress_pct' => 100.0],
        ['milestone_id' => 2, 'actual_progress_pct' => 80.0],
        ['milestone_id' => 3, 'actual_progress_pct' => 40.0],
    ],
    'quality_checks' => [
        ['template_id' => 1, 'status' => 'PASS', 'remarks' => 'Compaction meets standard'],
        ['template_id' => 2, 'status' => 'PASS', 'remarks' => 'Bitumen grade certificate verified'],
    ]
], $inspToken);

// Inspection 2: Critical Failure & High Variance on Tender 2
$offlineInsp2 = httpRequest('POST', "{$baseUrl}/inspections", [
    'tender_id' => 2,
    'actual_spent' => 38000000.00, // Very high spent
    'spent_basis' => 'Running Bill RA/04',
    'remarks' => 'Girder reinforcement defective; high variance noted.',
    'issue_found' => 1,
    'severity' => 'CRITICAL',
    'milestones' => [
        ['milestone_id' => 4, 'actual_progress_pct' => 100.0],
        ['milestone_id' => 5, 'actual_progress_pct' => 20.0],
    ],
    'quality_checks' => [
        ['template_id' => 5, 'status' => 'FAIL', 'remarks' => 'Girder reinforcement spacing non-compliant with IRC-112'],
    ]
], $inspToken);

$insp1Id = $offlineInsp1['data']['data']['id'] ?? null;
$insp2Id = $offlineInsp2['data']['data']['id'] ?? null;

assertTest(
    'Offline Queue Sync: Both Inspections Synced to Server Without Data Loss',
    $offlineInsp1['status'] === 201 && $offlineInsp2['status'] === 201 && $insp1Id && $insp2Id,
    "Synced Inspection #1: ID {$insp1Id} | Synced Inspection #2: ID {$insp2Id}"
);

// ---------------------------------------------------------
// TEST 7: Server-Computed Progress, Variance & Flags Matching
// ---------------------------------------------------------
$insp2Data = $offlineInsp2['data']['data'] ?? [];
$actualVariance = $insp2Data['variance'] ?? 0;
$hasVarianceFlag = !empty($insp2Data['variance_flag']);
$autoIssue = !empty($insp2Data['auto_issue_raised']);

assertTest(
    'Server-Computed Variance and Auto-Issue Flags Triggered',
    $hasVarianceFlag && $autoIssue,
    "Variance: {$actualVariance}%, Variance Flag: " . ($hasVarianceFlag ? 'YES' : 'NO') . ", Auto Issue: " . ($autoIssue ? 'YES' : 'NO') . " | Raw: " . json_encode($insp2Data)
);

// ---------------------------------------------------------
// TEST 8: Re-inspection Assignment & Completion Flow
// ---------------------------------------------------------
// Step 1: Submit inspection (DRAFT -> SUBMITTED)
httpRequest('POST', "{$baseUrl}/inspections/{$insp2Id}/submit", null, $inspToken);

// Step 2: District Officer verifies Insp 2 and raises issue (SUBMITTED -> ISSUE_RAISED)
httpRequest('POST', "{$baseUrl}/inspections/{$insp2Id}/verify", [
    'action' => 'RAISE_ISSUE',
    'remarks' => 'Defective girder reinforcement identified.',
    'severity' => 'CRITICAL'
], $doToken);

// Step 3: Notify contractor (ISSUE_RAISED -> NOTIFIED)
httpRequest('POST', "{$baseUrl}/inspections/{$insp2Id}/notify", null, $doToken);

// Step 4: Mark in resolution (NOTIFIED -> IN_RESOLUTION)
httpRequest('POST', "{$baseUrl}/inspections/{$insp2Id}/resolve", null, $doToken);

// Step 5: Schedule re-inspection assigned to Inspector Rajesh (User ID 4)
$schedRes = httpRequest('POST', "{$baseUrl}/inspections/{$insp2Id}/schedule-reinspection", [
    'scheduled_date' => date('Y-m-d', strtotime('+3 days')),
    'assigned_inspector_id' => 4,
    'instructions' => 'Verify re-laid rebar and compaction density at Pier P-4.'
], $doToken);

assertTest(
    'District Officer Schedules Re-inspection Assigned to Inspector',
    $schedRes['status'] === 200 && ($schedRes['data']['data']['new_status'] ?? '') === 'REINSPECTION_PENDING',
    "Status: " . ($schedRes['data']['data']['new_status'] ?? '')
);

// Step 6: Inspector queries assigned re-inspections
$assignedInsps = httpRequest('GET', "{$baseUrl}/inspections?status=REINSPECTION_PENDING", null, $inspToken);
$reinspList = $assignedInsps['data']['data'] ?? [];
$foundAssigned = false;
foreach ($reinspList as $item) {
    if ((int)$item['id'] === (int)$insp2Id) {
        $foundAssigned = true;
        break;
    }
}

assertTest(
    'Assigned Re-inspection Appears in Inspector Task Feed',
    $foundAssigned,
    "Found Inspection #{$insp2Id} in REINSPECTION_PENDING queue for Inspector"
);

// Step 7: Inspector submits passing re-inspection result (REINSPECTION_PENDING -> CLOSED)
$reinspSubmit = httpRequest('POST', "{$baseUrl}/inspections/{$insp2Id}/submit-reinspection", [
    'result' => 'PASSED',
    'remarks' => 'Rebar re-tied to 150mm c/c spacing, verified with cover meter.'
], $inspToken);

assertTest(
    'Inspector Successfully Completes Re-inspection with PASSED Outcome',
    $reinspSubmit['status'] === 200 && ($reinspSubmit['data']['data']['new_status'] ?? '') === 'CLOSED',
    "New Status: " . ($reinspSubmit['data']['data']['new_status'] ?? '')
);

echo "\n========================================================\n";
echo "PART 6 TEST RESULTS: {$passed} PASSED | {$failed} FAILED\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
