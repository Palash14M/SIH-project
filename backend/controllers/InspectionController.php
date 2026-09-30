<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/Audit.php';
require_once __DIR__ . '/../utils/EscalationMatrix.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class InspectionController {
    public function list(): void {
        $user = AuthMiddleware::authenticate();
        $pdo = Database::getConnection();

        $status = $_GET['status'] ?? null;
        $tenderId = $_GET['tender_id'] ?? null;

        $sql = "
            SELECT i.*, t.tender_number, t.title as tender_title, t.district_id,
                   u.name as inspector_name, u.email as inspector_email,
                   v.name as verified_by_name
            FROM inspections i
            JOIN tenders t ON i.tender_id = t.id
            JOIN users u ON i.inspector_id = u.id
            LEFT JOIN users v ON i.verified_by = v.id
            WHERE 1=1
        ";
        $params = [];

        // Scoping by role & jurisdiction
        if ($user['role'] === 'INSPECTOR') {
            $sql .= " AND i.inspector_id = :insp_id";
            $params[':insp_id'] = $user['id'];
        } elseif ($user['role'] === 'DISTRICT_OFFICER') {
            $sql .= " AND t.district_id = :dist_id";
            $params[':dist_id'] = $user['district_id'];
        } elseif ($user['role'] === 'STATE_OFFICER') {
            $sql .= " AND t.state_id = :state_id";
            $params[':state_id'] = $user['state_id'];
        }

        if ($status) {
            $sql .= " AND i.status = :status";
            $params[':status'] = strtoupper($status);
        }

        if ($tenderId) {
            $sql .= " AND i.tender_id = :tid";
            $params[':tid'] = (int)$tenderId;
        }

        $sql .= " ORDER BY i.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $inspections = $stmt->fetchAll();

        Response::success($inspections, 'Inspections retrieved');
    }

    public function detail(array $params): void {
        $user = AuthMiddleware::authenticate();
        $id = (int)($params['id'] ?? 0);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT i.*, t.tender_number, t.title as tender_title, t.district_id, t.state_id,
                   t.sanctioned_amount, t.actual_spent as tender_actual_spent,
                   u.name as inspector_name, u.email as inspector_email,
                   v.name as verified_by_name
            FROM inspections i
            JOIN tenders t ON i.tender_id = t.id
            JOIN users u ON i.inspector_id = u.id
            LEFT JOIN users v ON i.verified_by = v.id
            WHERE i.id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $inspection = $stmt->fetch();

        if (!$inspection) {
            Response::notFound('Inspection not found');
        }

        // Access security check: Inspector can only view own inspections
        if ($user['role'] === 'INSPECTOR' && (int)$inspection['inspector_id'] !== (int)$user['id']) {
            Response::forbidden("Inspectors are only permitted to access their own assigned inspections.");
        }

        // District Officer jurisdiction check
        if ($user['role'] === 'DISTRICT_OFFICER' && (int)$inspection['district_id'] !== (int)$user['district_id']) {
            Response::forbidden("Access denied: Inspection is outside your district jurisdiction.");
        }

        // Fetch Quality Checks
        $qStmt = $pdo->prepare("
            SELECT qc.*, qct.item_name, qct.is_critical, qct.description as template_desc
            FROM quality_checks qc
            JOIN quality_checklist_templates qct ON qc.template_id = qct.id
            WHERE qc.inspection_id = :iid
        ");
        $qStmt->execute([':iid' => $id]);
        $inspection['quality_checks'] = $qStmt->fetchAll();

        // Fetch Evidence Items
        $eStmt = $pdo->prepare("SELECT * FROM inspection_items WHERE inspection_id = :iid ORDER BY id ASC");
        $eStmt->execute([':iid' => $id]);
        $inspection['evidence_items'] = $eStmt->fetchAll();

        // Fetch Issues
        $issStmt = $pdo->prepare("SELECT * FROM issues WHERE inspection_id = :iid ORDER BY id DESC");
        $issStmt->execute([':iid' => $id]);
        $inspection['issues'] = $issStmt->fetchAll();

        // Fetch Re-inspection status if applicable
        $reStmt = $pdo->prepare("SELECT * FROM reinspections WHERE original_inspection_id = :iid ORDER BY id DESC LIMIT 1");
        $reStmt->execute([':iid' => $id]);
        $inspection['reinspection'] = $reStmt->fetch() ?: null;

        Response::success($inspection, 'Inspection details retrieved');
    }

    public function createOrSync(): void {
        $user = AuthMiddleware::requireRoles(['INSPECTOR', 'MOSJE_ADMIN']);
        $input = getJsonInput();

        $validator = Validator::make($input, [
            'tender_id' => 'required|integer',
            'remarks' => 'string',
        ]);
        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }

        $tenderId = (int)$input['tender_id'];
        $pdo = Database::getConnection();

        // Fetch Tender
        $tStmt = $pdo->prepare("SELECT * FROM tenders WHERE id = :id LIMIT 1");
        $tStmt->execute([':id' => $tenderId]);
        $tender = $tStmt->fetch();

        if (!$tender) {
            Response::notFound('Tender not found');
        }

        // 1. Calculate overall progress % from weighted milestones sum
        $milestoneUpdates = $input['milestones'] ?? [];
        $totalWeightedProgress = 0.0;
        $totalWeight = 0.0;

        if (!empty($milestoneUpdates) && is_array($milestoneUpdates)) {
            foreach ($milestoneUpdates as $mu) {
                $mid = (int)($mu['milestone_id'] ?? 0);
                $prog = (float)($mu['actual_progress'] ?? ($mu['actual_progress_pct'] ?? 0));

                $mStmt = $pdo->prepare("SELECT * FROM milestones WHERE id = :mid AND tender_id = :tid LIMIT 1");
                $mStmt->execute([':mid' => $mid, ':tid' => $tenderId]);
                $milestone = $mStmt->fetch();

                if ($milestone) {
                    $weight = (float)$milestone['planned_weight'];
                    $totalWeight += $weight;
                    $totalWeightedProgress += ($weight * ($prog / 100.0));

                    // Update milestone progress in database
                    $updM = $pdo->prepare("UPDATE milestones SET actual_progress = :prog WHERE id = :mid");
                    $updM->execute([':prog' => $prog, ':mid' => $mid]);
                }
            }
        }

        $computedProgress = $totalWeight > 0 ? round(($totalWeightedProgress / $totalWeight) * 100.0, 2) : (float)($input['overall_progress'] ?? $tender['progress_percentage']);

        // 2. Budget vs Actual and Variance Calculation
        $actualSpentRecorded = (float)($input['actual_spent_recorded'] ?? ($input['actual_spent'] ?? 0.0));
        $totalActualSpent = (float)$tender['actual_spent'] + $actualSpentRecorded;
        $sanctionedAmount = (float)$tender['sanctioned_amount'];
        $spentPercentage = $sanctionedAmount > 0 ? round(($totalActualSpent / $sanctionedAmount) * 100.0, 2) : 0.0;

        // Variance = Spent % - Progress %
        $variance = round($spentPercentage - $computedProgress, 2);

        // Threshold checks
        $threshold = (float)Config::get('VARIANCE_ALERT_THRESHOLD', 10.0);
        $varianceFlag = ($variance > $threshold) ? 1 : 0;

        // Delay flag check: past scheduled end date and progress < 100%
        $today = date('Y-m-d');
        $delayFlag = ($today > $tender['scheduled_end_date'] && $computedProgress < 100.0) ? 1 : 0;

        // Update tender overall metrics
        $updTender = $pdo->prepare("
            UPDATE tenders SET
                actual_spent = :spent,
                progress_percentage = :prog,
                spent_percentage = :spent_pct,
                variance = :variance,
                variance_flag = :v_flag,
                delay_flag = :d_flag,
                updated_at = :now
            WHERE id = :tid
        ");
        $now = date('Y-m-d H:i:s');
        $updTender->execute([
            ':spent' => $totalActualSpent,
            ':prog' => $computedProgress,
            ':spent_pct' => $spentPercentage,
            ':variance' => $variance,
            ':v_flag' => $varianceFlag,
            ':d_flag' => $delayFlag,
            ':now' => $now,
            ':tid' => $tenderId,
        ]);

        // Trigger budget-overage escalation check (Task 8)
        if ($totalActualSpent > $sanctionedAmount) {
            EscalationMatrix::checkAndEscalate($tenderId, $totalActualSpent);
        }

        // 3. Create Inspection record
        $status = !empty($input['submit_now']) ? 'SUBMITTED' : 'DRAFT';
        $issueFound = !empty($input['issue_found']) ? 1 : 0;
        $severity = $input['severity'] ?? ($issueFound ? 'MEDIUM' : 'NONE');

        $insStmt = $pdo->prepare("
            INSERT INTO inspections (
                tender_id, inspector_id, status, overall_progress, actual_spent_recorded,
                spent_basis, remarks, issue_found, severity, is_reinspection, parent_inspection_id,
                created_at, submitted_at, updated_at
            ) VALUES (
                :tid, :iid, :status, :prog, :spent,
                :basis, :remarks, :issue, :severity, :is_re, :parent,
                :now, :sub_at, :now
            )
        ");
        $insStmt->execute([
            ':tid' => $tenderId,
            ':iid' => $user['id'],
            ':status' => $status,
            ':prog' => $computedProgress,
            ':spent' => $actualSpentRecorded,
            ':basis' => $input['spent_basis'] ?? null,
            ':remarks' => $input['remarks'] ?? 'Field inspection entry',
            ':issue' => $issueFound,
            ':severity' => $severity,
            ':is_re' => !empty($input['is_reinspection']) ? 1 : 0,
            ':parent' => !empty($input['parent_inspection_id']) ? (int)$input['parent_inspection_id'] : null,
            ':now' => $now,
            ':sub_at' => ($status === 'SUBMITTED') ? $now : null,
        ]);
        $inspectionId = (int)$pdo->lastInsertId();

        // 4. Save Quality Checklist Items & Auto-raise Issue on Critical Failure
        $qualityChecks = $input['quality_checks'] ?? [];
        $autoIssueRaised = false;

        if (!empty($qualityChecks) && is_array($qualityChecks)) {
            $stmtQc = $pdo->prepare("
                INSERT INTO quality_checks (inspection_id, template_id, status, remarks, created_at)
                VALUES (:iid, :tpl, :status, :remarks, :now)
            ");
            $tplStmt = $pdo->prepare("SELECT is_critical, item_name FROM quality_checklist_templates WHERE id = :tpl LIMIT 1");

            foreach ($qualityChecks as $qc) {
                $templateId = (int)($qc['template_id'] ?? 0);
                $qcStatus = strtoupper($qc['status'] ?? 'PASS');
                $qcRemarks = $qc['remarks'] ?? null;

                $stmtQc->execute([
                    ':iid' => $inspectionId,
                    ':tpl' => $templateId,
                    ':status' => $qcStatus,
                    ':remarks' => $qcRemarks,
                    ':now' => $now,
                ]);

                // Check if critical item failed -> Auto raise issue
                $tplStmt->execute([':tpl' => $templateId]);
                $tplInfo = $tplStmt->fetch();

                if ($tplInfo && (int)$tplInfo['is_critical'] === 1 && $qcStatus === 'FAIL') {
                    $autoIssueRaised = true;
                    // Auto-raise issue in issues table
                    $issStmt = $pdo->prepare("
                        INSERT INTO issues (inspection_id, tender_id, quality_check_id, title, description, severity, status, raised_by, created_at)
                        VALUES (:iid, :tid, :qid, :title, :desc, 'CRITICAL', 'OPEN', :by, :now)
                    ");
                    $issStmt->execute([
                        ':iid' => $inspectionId,
                        ':tid' => $tenderId,
                        ':qid' => (int)$pdo->lastInsertId(),
                        ':title' => "Critical Quality Failure: " . $tplInfo['item_name'],
                        ':desc' => "Automated issue raised due to failed critical quality check. Remarks: " . ($qcRemarks ?: 'Non-compliant'),
                        ':by' => $user['id'],
                        ':now' => $now,
                    ]);

                    // Update inspection status to ISSUE_RAISED if submitted
                    $updIns = $pdo->prepare("UPDATE inspections SET issue_found = 1, severity = 'CRITICAL' WHERE id = :iid");
                    $updIns->execute([':iid' => $inspectionId]);
                }
            }
        }

        // Record budget entry if spent recorded
        if ($actualSpentRecorded > 0) {
            $bStmt = $pdo->prepare("
                INSERT INTO budget_entries (tender_id, inspection_id, amount, basis, status, recorded_by, created_at)
                VALUES (:tid, :iid, :amt, :basis, 'ACCEPTED', :by, :now)
            ");
            $bStmt->execute([
                ':tid' => $tenderId,
                ':iid' => $inspectionId,
                ':amt' => $actualSpentRecorded,
                ':basis' => $input['spent_basis'] ?? 'Measurement Book',
                ':by' => $user['id'],
                ':now' => $now,
            ]);
        }

        Audit::log($user['id'], $user['role'], 'INSPECTION_CREATED', 'inspections', $inspectionId, null, $status, "Created inspection for tender {$tender['tender_number']}");

        Response::success([
            'id' => $inspectionId,
            'status' => $status,
            'overall_progress' => $computedProgress,
            'variance' => $variance,
            'variance_flag' => (bool)$varianceFlag,
            'delay_flag' => (bool)$delayFlag,
            'auto_issue_raised' => $autoIssueRaised,
        ], 'Inspection saved successfully', 201);
    }

    public function uploadEvidence(array $params): void {
        $user = AuthMiddleware::requireRoles(['INSPECTOR', 'MOSJE_ADMIN']);
        $inspectionId = (int)($params['id'] ?? 0);

        if (!isset($_FILES['media']) || $_FILES['media']['error'] !== UPLOAD_ERR_OK) {
            Response::error('No evidence media file provided.', 400);
        }

        // Strictly enforce GPS coordinate requirement
        $lat = $_POST['latitude'] ?? null;
        $lng = $_POST['longitude'] ?? null;
        $accuracy = $_POST['accuracy'] ?? null;
        $deviceTime = $_POST['device_capture_time'] ?? null;

        if ($lat === null || $lng === null || $accuracy === null || $deviceTime === null || trim((string)$lat) === '' || trim((string)$lng) === '') {
            Response::error('GPS telemetry (latitude, longitude, accuracy) and device capture time are mandatory for evidence upload.', 422);
        }

        $lat = (float)$lat;
        $lng = (float)$lng;
        $accuracy = (float)$accuracy;

        // Block capture if GPS accuracy is worse than 100m
        if ($accuracy > 100.0) {
            Response::error("GPS accuracy (±{$accuracy}m) is unacceptable. Required accuracy must be within 100m.", 422);
        }

        $isMock = !empty($_POST['is_mock']) ? 1 : 0;

        // Server sets server_time itself. Client-supplied server time is strictly ignored!
        $serverTime = date('Y-m-d H:i:s');

        // Check time mismatch (> 24 hours difference)
        $deviceTs = strtotime($deviceTime);
        $serverTs = strtotime($serverTime);
        $timeMismatch = (abs($serverTs - $deviceTs) > 86400) ? 1 : 0;

        // Validate MIME type
        $tmpPath = $_FILES['media']['tmp_name'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/quicktime'];
        if (!in_array($mime, $allowedMimes, true)) {
            Response::error("Invalid file format ({$mime}). Only JPEG, PNG, WEBP, and MP4 evidence are allowed.", 415);
        }

        $isPhoto = str_starts_with($mime, 'image/');
        $type = $isPhoto ? 'PHOTO' : 'VIDEO';

        // Destination storage
        $ext = pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION) ?: ($isPhoto ? 'jpg' : 'mp4');
        $fileName = 'evidence_' . $inspectionId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $targetDir = dirname(__DIR__) . '/storage/media';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $targetPath = $targetDir . '/' . $fileName;
        if (!move_uploaded_file($tmpPath, $targetPath)) {
            Response::error('Failed to store media file on server.', 500);
        }

        $storedRelativePath = 'storage/media/' . $fileName;
        $fileSize = filesize($targetPath);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO inspection_items (
                inspection_id, file_path, file_type, latitude, longitude, accuracy,
                device_capture_time, server_time, mock_flag, time_mismatch, file_size, created_at
            ) VALUES (
                :iid, :path, :type, :lat, :lng, :acc,
                :dtime, :stime, :mock, :mismatch, :size, :now
            )
        ");
        $stmt->execute([
            ':iid' => $inspectionId,
            ':path' => $storedRelativePath,
            ':type' => $type,
            ':lat' => $lat,
            ':lng' => $lng,
            ':acc' => $accuracy,
            ':dtime' => date('Y-m-d H:i:s', $deviceTs),
            ':stime' => $serverTime,
            ':mock' => $isMock,
            ':mismatch' => $timeMismatch,
            ':size' => $fileSize,
            ':now' => $serverTime,
        ]);

        $itemId = (int)$pdo->lastInsertId();
        Audit::log($user['id'], $user['role'], 'EVIDENCE_UPLOADED', 'inspection_items', $itemId, null, null, "Uploaded {$type} with GPS ({$lat}, {$lng})");

        Response::success([
            'id' => $itemId,
            'file_path' => $storedRelativePath,
            'type' => $type,
            'latitude' => $lat,
            'longitude' => $lng,
            'accuracy' => $accuracy,
            'server_time' => $serverTime,
            'device_time' => date('Y-m-d H:i:s', $deviceTs),
            'time_mismatch' => (bool)$timeMismatch,
            'mock_flag' => (bool)$isMock,
        ], 'Evidence uploaded and verified successfully', 201);
    }

    // STATE MACHINE TRANSITIONS

    public function submit(array $params): void {
        $user = AuthMiddleware::requireRoles(['INSPECTOR', 'MOSJE_ADMIN']);
        $id = (int)($params['id'] ?? 0);
        $this->transitionState($id, ['DRAFT'], 'SUBMITTED', 'Inspection submitted by field inspector', $user);
    }

    public function verify(array $params): void {
        $user = AuthMiddleware::requireRoles(['DISTRICT_OFFICER', 'STATE_OFFICER', 'MOSJE_ADMIN']);
        $id = (int)($params['id'] ?? 0);
        $input = getJsonInput();

        $action = strtoupper($input['action'] ?? 'VERIFY'); // VERIFY or RAISE_ISSUE
        $reason = $input['remarks'] ?? 'Officer inspection review';

        if ($action === 'RAISE_ISSUE') {
            $this->transitionState($id, ['SUBMITTED'], 'ISSUE_RAISED', $reason, $user, ['issue_found' => 1, 'severity' => $input['severity'] ?? 'HIGH']);
        } else {
            $this->transitionState($id, ['SUBMITTED'], 'VERIFIED', $reason, $user, ['officer_remarks' => $reason]);
        }
    }

    public function notifyContractor(array $params): void {
        $user = AuthMiddleware::requireRoles(['DISTRICT_OFFICER', 'STATE_OFFICER', 'MOSJE_ADMIN']);
        $id = (int)($params['id'] ?? 0);
        $this->transitionState($id, ['ISSUE_RAISED'], 'NOTIFIED', 'Contractor and responsible senior notified of defect', $user);
    }

    public function markInResolution(array $params): void {
        $user = AuthMiddleware::authenticate();
        $id = (int)($params['id'] ?? 0);
        $this->transitionState($id, ['NOTIFIED'], 'IN_RESOLUTION', 'Corrective action initiated by contractor', $user);
    }

    public function scheduleReinspection(array $params): void {
        $user = AuthMiddleware::requireRoles(['DISTRICT_OFFICER', 'STATE_OFFICER', 'MOSJE_ADMIN']);
        $id = (int)($params['id'] ?? 0);
        $input = getJsonInput();

        $validator = Validator::make($input, [
            'scheduled_date' => 'required|date',
            'assigned_inspector_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }

        $pdo = Database::getConnection();
        $insStmt = $pdo->prepare("SELECT * FROM inspections WHERE id = :id LIMIT 1");
        $insStmt->execute([':id' => $id]);
        $ins = $insStmt->fetch();

        if (!$ins) {
            Response::notFound('Inspection not found');
        }

        // Create Reinspection Schedule Record
        $schedStmt = $pdo->prepare("
            INSERT INTO reinspections (issue_id, original_inspection_id, scheduled_date, assigned_inspector_id, status, created_at)
            VALUES (1, :orig, :sdate, :insp_id, 'SCHEDULED', :now)
        ");
        $schedStmt->execute([
            ':orig' => $id,
            ':sdate' => $input['scheduled_date'],
            ':insp_id' => (int)$input['assigned_inspector_id'],
            ':now' => date('Y-m-d H:i:s'),
        ]);

        $this->transitionState($id, ['IN_RESOLUTION', 'ISSUE_RAISED'], 'REINSPECTION_PENDING', "Scheduled re-inspection for {$input['scheduled_date']}", $user);
    }

    public function submitReinspection(array $params): void {
        $user = AuthMiddleware::requireRoles(['INSPECTOR', 'MOSJE_ADMIN']);
        $id = (int)($params['id'] ?? 0);
        $input = getJsonInput();

        $result = strtoupper($input['result'] ?? 'PASSED'); // PASSED or FAILED
        $remarks = $input['remarks'] ?? 'Re-inspection completed';

        $pdo = Database::getConnection();
        $now = date('Y-m-d H:i:s');

        // Update reinspections table
        $updRe = $pdo->prepare("
            UPDATE reinspections SET status = :status, outcome_remarks = :remarks
            WHERE original_inspection_id = :id
        ");
        $updRe->execute([':status' => $result, ':remarks' => $remarks, ':id' => $id]);

        if ($result === 'PASSED') {
            // Re-inspection passed -> Advance to CLOSED or mark issue resolved
            $this->transitionState($id, ['REINSPECTION_PENDING'], 'CLOSED', "Re-inspection passed: {$remarks}", $user);
        } else {
            // Re-inspection failed -> Cycle back to ISSUE_RAISED
            $this->transitionState($id, ['REINSPECTION_PENDING'], 'ISSUE_RAISED', "Re-inspection failed: {$remarks}", $user);
        }
    }

    public function close(array $params): void {
        $user = AuthMiddleware::requireRoles(['DISTRICT_OFFICER', 'STATE_OFFICER', 'MOSJE_ADMIN']);
        $id = (int)($params['id'] ?? 0);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM inspections WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $ins = $stmt->fetch();

        if (!$ins) {
            Response::notFound('Inspection not found');
        }

        // STRICT MANDATORY RULE: An inspection with an issue cannot be closed without a passed re-inspection!
        if ((int)$ins['issue_found'] === 1) {
            $chkRe = $pdo->prepare("
                SELECT status FROM reinspections 
                WHERE original_inspection_id = :id 
                ORDER BY id DESC LIMIT 1
            ");
            $chkRe->execute([':id' => $id]);
            $reStatus = $chkRe->fetchColumn();

            if ($reStatus !== 'PASSED') {
                Response::error("Cannot close inspection: Unresolved issues exist and mandatory re-inspection has not passed.", 400);
            }
        }

        $this->transitionState($id, ['VERIFIED', 'REINSPECTION_PENDING'], 'CLOSED', 'Inspection officially verified and closed', $user);
    }

    private function transitionState(int $inspectionId, array $allowedFromStates, string $toState, string $reason, array $user, array $extraFields = []): void {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM inspections WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $inspectionId]);
        $ins = $stmt->fetch();

        if (!$ins) {
            Response::notFound('Inspection not found');
        }

        $currentState = $ins['status'];

        if (!in_array($currentState, $allowedFromStates, true)) {
            Response::error("Invalid status transition: Cannot transition from '{$currentState}' to '{$toState}'. Allowed current states: " . implode(', ', $allowedFromStates), 400);
        }

        $now = date('Y-m-d H:i:s');
        $sql = "UPDATE inspections SET status = :toState, updated_at = :now";
        $params = [':toState' => $toState, ':now' => $now, ':id' => $inspectionId];

        if ($toState === 'VERIFIED') {
            $sql .= ", verified_by = :vby, verified_at = :vat";
            $params[':vby'] = $user['id'];
            $params[':vat'] = $now;
        }

        foreach ($extraFields as $f => $val) {
            $sql .= ", {$f} = :{$f}";
            $params[":{$f}"] = $val;
        }

        $sql .= " WHERE id = :id";

        $upd = $pdo->prepare($sql);
        $upd->execute($params);

        // Record immutable audit log
        Audit::log($user['id'], $user['role'], 'INSPECTION_STATE_TRANSITION', 'inspections', $inspectionId, $currentState, $toState, $reason);

        // Create notification for assigned inspector or district officer
        $notifStmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, entity_type, entity_id, created_at)
            VALUES (:uid, :title, :msg, :type, 'inspection', :eid, :now)
        ");
        $targetUserId = ($user['role'] === 'INSPECTOR') ? (int)$ins['verified_by'] : (int)$ins['inspector_id'];
        if ($targetUserId) {
            $notifStmt->execute([
                ':uid' => $targetUserId,
                ':title' => "Inspection #{$inspectionId} Status Updated",
                ':msg' => "Inspection transitioned from {$currentState} to {$toState}. Reason: {$reason}",
                ':type' => 'INSPECTION_UPDATE',
                ':eid' => $inspectionId,
                ':now' => $now,
            ]);
        }

        Response::success([
            'id' => $inspectionId,
            'old_status' => $currentState,
            'new_status' => $toState,
            'updated_at' => $now,
        ], "Inspection status updated to {$toState}");
    }

    public function generateInspectionCode(): void {
        $user = AuthMiddleware::requireRoles(['DISTRICT_OFFICER', 'STATE_OFFICER', 'MOSJE_ADMIN', 'MASTER_ADMIN']);
        $input = getJsonInput();

        $validator = Validator::make($input, [
            'tender_id' => 'required|integer',
            'inspector_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }

        $tenderId = (int)$input['tender_id'];
        $inspectorId = (int)$input['inspector_id'];
        $pdo = Database::getConnection();

        // Verify tender
        $tStmt = $pdo->prepare("SELECT * FROM tenders WHERE id = :id LIMIT 1");
        $tStmt->execute([':id' => $tenderId]);
        $tender = $tStmt->fetch();
        if (!$tender) {
            Response::notFound('Tender not found');
        }

        // Verify inspector
        $iStmt = $pdo->prepare("SELECT * FROM users WHERE id = :id AND role = 'INSPECTOR' LIMIT 1");
        $iStmt->execute([':id' => $inspectorId]);
        $inspector = $iStmt->fetch();
        if (!$inspector) {
            Response::error('Selected user is not a valid Field Inspector.', 400);
        }

        // Format: [first 4 letters of inspector's first name][generation year, 4 digits][generation month, 3-letter abbrev][numeric project code]
        $rawFirstName = explode(' ', trim($inspector['name']))[0];
        $cleanLetters = strtoupper(preg_replace('/[^A-Za-z]/', '', $rawFirstName));
        $namePart = str_pad(substr($cleanLetters, 0, 4), 4, 'X');

        $yearPart = date('Y');
        $monthPart = strtoupper(date('M'));

        // Project code: extract all digits from tender_number
        $numericProjectCode = preg_replace('/\D/', '', $tender['tender_number']);
        if (empty($numericProjectCode)) {
            $numericProjectCode = str_pad((string)$tender['id'], 10, '9725481605', STR_PAD_LEFT);
        }

        $code = "{$namePart}{$yearPart}{$monthPart}{$numericProjectCode}";

        $now = date('Y-m-d H:i:s');

        // Check if code already exists or create new
        $ins = $pdo->prepare("
            INSERT OR REPLACE INTO inspection_codes (
                code, tender_id, inspector_id, generated_by, is_redeemed, created_at
            ) VALUES (
                :code, :tid, :iid, :gid, 0, :now
            )
        ");
        $ins->execute([
            ':code' => $code,
            ':tid' => $tenderId,
            ':iid' => $inspectorId,
            ':gid' => $user['id'],
            ':now' => $now,
        ]);
        $codeId = (int)$pdo->lastInsertId();

        // Assign inspector to tender
        $assignStmt = $pdo->prepare("INSERT OR IGNORE INTO tender_inspectors (tender_id, inspector_id, assigned_at) VALUES (:tid, :iid, :now)");
        $assignStmt->execute([':tid' => $tenderId, ':iid' => $inspectorId, ':now' => $now]);

        // Send alert containing: inspector's name, project serial number, and this inspection code
        $notifStmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, entity_type, entity_id, created_at)
            VALUES (:uid, :title, :msg, 'INFO', 'inspection_code', :eid, :now)
        ");
        $notifStmt->execute([
            ':uid' => $inspectorId,
            ':title' => "🔑 Inspection Code: {$code}",
            ':msg' => "Inspector: {$inspector['name']} | Project Serial No: {$tender['tender_number']} | Inspection Code: {$code}. Enter this code in your app to unlock full project inspection duties.",
            ':eid' => $codeId,
            ':now' => $now,
        ]);

        Audit::log($user['id'], $user['role'], 'INSPECTION_CODE_GENERATED', 'inspection_codes', $codeId, null, $code, "Generated inspection code {$code} for inspector {$inspector['name']} on tender {$tender['tender_number']}");

        Response::success([
            'id' => $codeId,
            'code' => $code,
            'format_spec' => "[{$namePart}][{$yearPart}][{$monthPart}][{$numericProjectCode}]",
            'inspector' => [
                'id' => $inspector['id'],
                'name' => $inspector['name'],
                'phone' => $inspector['phone'],
            ],
            'tender' => [
                'id' => $tender['id'],
                'tender_number' => $tender['tender_number'],
                'title' => $tender['title'],
            ],
            'notification_alert' => "Inspector: {$inspector['name']} | Project Serial No: {$tender['tender_number']} | Inspection Code: {$code}",
            'created_at' => $now,
        ], "Inspection code '{$code}' generated and dispatched to inspector.");
    }

    public function redeemInspectionCode(): void {
        $user = AuthMiddleware::requireRoles(['INSPECTOR', 'MOSJE_ADMIN', 'MASTER_ADMIN']);
        $input = getJsonInput();

        $validator = Validator::make($input, [
            'code' => 'required|string|min:6',
        ]);
        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }

        $code = trim(strtoupper($input['code']));
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT ic.*, u.name as inspector_name, u.phone as inspector_phone
            FROM inspection_codes ic
            JOIN users u ON ic.inspector_id = u.id
            WHERE UPPER(ic.code) = :code
            LIMIT 1
        ");
        $stmt->execute([':code' => $code]);
        $record = $stmt->fetch();

        if (!$record) {
            Response::error("Invalid inspection code '{$code}'. Please check the code sent to your alerts.", 404);
        }

        // Reject if entered by any other account
        if ($user['role'] === 'INSPECTOR' && (int)$record['inspector_id'] !== (int)$user['id']) {
            Response::forbidden("Access Denied: Inspection code is tied to inspector '{$record['inspector_name']}' and cannot be redeemed by another account.");
        }

        // Check if one-time code has already been redeemed
        if ((int)$record['is_redeemed'] === 1) {
            Response::error("Inspection code '{$code}' has already been redeemed and cannot be used again.", 400);
        }

        $now = date('Y-m-d H:i:s');
        $upd = $pdo->prepare("UPDATE inspection_codes SET is_redeemed = 1, redeemed_at = :now WHERE id = :id");
        $upd->execute([':now' => $now, ':id' => $record['id']]);

        // Ensure inspector is registered for tender
        $insT = $pdo->prepare("INSERT OR IGNORE INTO tender_inspectors (tender_id, inspector_id, assigned_at) VALUES (:tid, :iid, :now)");
        $insT->execute([':tid' => $record['tender_id'], ':iid' => $user['id'], ':now' => $now]);

        // Reveal full project details
        $tStmt = $pdo->prepare("
            SELECT t.*, c.name as category_name, d.name as district_name, s.name as state_name,
                   co.name as contractor_name, co.registration_number as contractor_registration,
                   co.phone as contractor_phone, co.email as contractor_email, co.address as contractor_address,
                   co.contact_person as contractor_contact_person,
                   u.name as responsible_senior_name
            FROM tenders t
            JOIN tender_categories c ON t.category_id = c.id
            JOIN districts d ON t.district_id = d.id
            JOIN states s ON t.state_id = s.id
            JOIN contractors co ON t.contractor_id = co.id
            JOIN users u ON t.responsible_senior_id = u.id
            WHERE t.id = :id LIMIT 1
        ");
        $tStmt->execute([':id' => $record['tender_id']]);
        $tender = $tStmt->fetch();

        // Fetch milestones
        $mStmt = $pdo->prepare("SELECT * FROM milestones WHERE tender_id = :tid ORDER BY id ASC");
        $mStmt->execute([':tid' => $record['tender_id']]);
        $tender['milestones'] = $mStmt->fetchAll();

        Audit::log($user['id'], $user['role'], 'INSPECTION_CODE_REDEEMED', 'inspection_codes', $record['id'], 'ISSUED', 'REDEEMED', "Inspection code {$code} redeemed for project {$tender['tender_number']}");

        Response::success([
            'code' => $code,
            'redeemed' => true,
            'tender' => $tender,
            'message' => "Inspection code verified. Full project '{$tender['title']}' revealed and assigned to your dashboard."
        ], "Inspection code redeemed successfully.");
    }

    public function listCodes(): void {
        $user = AuthMiddleware::requireRoles(['DISTRICT_OFFICER', 'STATE_OFFICER', 'MOSJE_ADMIN', 'MASTER_ADMIN', 'INSPECTOR']);
        $pdo = Database::getConnection();

        $sql = "
            SELECT ic.*, t.tender_number, t.title as tender_title,
                   u.name as inspector_name, u.phone as inspector_phone,
                   g.name as generated_by_name
            FROM inspection_codes ic
            JOIN tenders t ON ic.tender_id = t.id
            JOIN users u ON ic.inspector_id = u.id
            JOIN users g ON ic.generated_by = g.id
            WHERE 1=1
        ";
        $params = [];
        if ($user['role'] === 'INSPECTOR') {
            $sql .= " AND ic.inspector_id = :uid";
            $params[':uid'] = $user['id'];
        }
        $sql .= " ORDER BY ic.id DESC LIMIT 50";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $codes = $stmt->fetchAll();

        Response::success($codes, 'Inspection codes retrieved');
    }
}

// Register Inspection Routes
Router::add('GET', '/api/inspections', [InspectionController::class, 'list']);
Router::add('GET', '/api/inspections/codes', [InspectionController::class, 'listCodes']);
Router::add('POST', '/api/inspections/generate-code', [InspectionController::class, 'generateInspectionCode']);
Router::add('POST', '/api/inspections/redeem-code', [InspectionController::class, 'redeemInspectionCode']);
Router::add('GET', '/api/inspections/{id}', [InspectionController::class, 'detail']);
Router::add('POST', '/api/inspections', [InspectionController::class, 'createOrSync']);
Router::add('POST', '/api/inspections/{id}/evidence', [InspectionController::class, 'uploadEvidence']);
Router::add('POST', '/api/inspections/{id}/submit', [InspectionController::class, 'submit']);
Router::add('POST', '/api/inspections/{id}/verify', [InspectionController::class, 'verify']);
Router::add('POST', '/api/inspections/{id}/notify', [InspectionController::class, 'notifyContractor']);
Router::add('POST', '/api/inspections/{id}/resolve', [InspectionController::class, 'markInResolution']);
Router::add('POST', '/api/inspections/{id}/schedule-reinspection', [InspectionController::class, 'scheduleReinspection']);
Router::add('POST', '/api/inspections/{id}/submit-reinspection', [InspectionController::class, 'submitReinspection']);
Router::add('POST', '/api/inspections/{id}/close', [InspectionController::class, 'close']);

