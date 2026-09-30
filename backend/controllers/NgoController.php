<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/Audit.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class NgoController {
    public function list(): void {
        $user = AuthMiddleware::requireRoles(['DISTRICT_OFFICER', 'STATE_OFFICER', 'MOSJE_ADMIN']);
        $pdo = Database::getConnection();

        $status = $_GET['status'] ?? null;
        $districtId = $_GET['district_id'] ?? null;

        $sql = "
            SELECT n.*, d.name as district_name, u.status as user_status
            FROM ngos n
            JOIN districts d ON n.district_id = d.id
            JOIN users u ON n.user_id = u.id
            WHERE 1=1
        ";
        $params = [];

        if ($user['role'] === 'DISTRICT_OFFICER') {
            $sql .= " AND n.district_id = :did";
            $params[':did'] = $user['district_id'];
        } elseif ($districtId) {
            $sql .= " AND n.district_id = :did";
            $params[':did'] = (int)$districtId;
        }

        if ($status) {
            $sql .= " AND n.status = :status";
            $params[':status'] = strtoupper($status);
        }

        $sql .= " ORDER BY n.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        Response::success($stmt->fetchAll(), 'NGO list retrieved');
    }

    public function detail(array $params): void {
        $user = AuthMiddleware::requireRoles(['DISTRICT_OFFICER', 'STATE_OFFICER', 'MOSJE_ADMIN', 'NGO']);
        $id = (int)($params['id'] ?? 0);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT n.*, d.name as district_name, u.status as user_status
            FROM ngos n
            JOIN districts d ON n.district_id = d.id
            JOIN users u ON n.user_id = u.id
            WHERE n.id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $ngo = $stmt->fetch();

        if (!$ngo) {
            Response::notFound('NGO record not found');
        }

        // Fetch documents
        $dStmt = $pdo->prepare("SELECT * FROM ngo_documents WHERE ngo_id = :nid");
        $dStmt->execute([':nid' => $id]);
        $ngo['documents'] = $dStmt->fetchAll();

        // Fetch attached tenders
        $tStmt = $pdo->prepare("
            SELECT t.id, t.tender_number, t.title, t.status, t.progress_percentage
            FROM tender_ngos tn
            JOIN tenders t ON tn.tender_id = t.id
            WHERE tn.ngo_id = :nid
        ");
        $tStmt->execute([':nid' => $id]);
        $ngo['attached_tenders'] = $tStmt->fetchAll();

        Response::success($ngo, 'NGO details retrieved');
    }

    public function uploadDocument(array $params): void {
        $user = AuthMiddleware::requireRoles(['NGO']);
        $id = (int)($params['id'] ?? 0);

        if ((int)($user['ngo']['id'] ?? 0) !== $id) {
            Response::forbidden('You may only upload documents for your own NGO organization.');
        }

        if (!isset($_FILES['document']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Response::error('Please provide a credential document file.', 400);
        }

        $type = $_POST['document_type'] ?? 'REGISTRATION_CERTIFICATE';
        $fileName = 'ngo_doc_' . $id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.pdf';
        $targetDir = dirname(__DIR__) . '/storage/media';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $targetPath = $targetDir . '/' . $fileName;

        if (!move_uploaded_file($_FILES['document']['tmp_name'], $targetPath)) {
            Response::error('Failed to store document file.', 500);
        }

        $relPath = 'storage/media/' . $fileName;
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO ngo_documents (ngo_id, document_type, file_path, uploaded_at)
            VALUES (:nid, :type, :path, :now)
        ");
        $stmt->execute([
            ':nid' => $id,
            ':type' => $type,
            ':path' => $relPath,
            ':now' => date('Y-m-d H:i:s'),
        ]);

        Response::success(['file_path' => $relPath], 'Document uploaded successfully', 201);
    }

    public function decide(array $params): void {
        $user = AuthMiddleware::requireRoles(['DISTRICT_OFFICER', 'MOSJE_ADMIN']);
        $id = (int)($params['id'] ?? 0);
        $input = getJsonInput();

        $rawDecision = $input['decision'] ?? ($input['status'] ?? '');
        if (str_starts_with(strtoupper($rawDecision), 'APPROV')) {
            $input['decision'] = 'APPROVE';
        } elseif (str_starts_with(strtoupper($rawDecision), 'REJECT')) {
            $input['decision'] = 'REJECT';
        }

        $validator = Validator::make($input, [
            'decision' => 'required|in:APPROVE,REJECT',
            'reason' => 'required|string|min:5',
        ]);
        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM ngos WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $ngo = $stmt->fetch();

        if (!$ngo) {
            Response::notFound('NGO not found');
        }

        // STRICT RULE: Only the District Officer of the NGO's district can decide (unless MoSJE Admin)
        if ($user['role'] === 'DISTRICT_OFFICER' && (int)$ngo['district_id'] !== (int)$user['district_id']) {
            Response::forbidden("Jurisdiction restriction: Only the District Officer of District #{$ngo['district_id']} can decide this application.");
        }

        $decision = strtoupper($input['decision']);
        $newStatus = ($decision === 'APPROVE') ? 'APPROVED' : 'REJECTED';
        $now = date('Y-m-d H:i:s');

        $upd = $pdo->prepare("
            UPDATE ngos SET
                status = :status,
                decision_reason = :reason,
                decided_by = :by,
                decided_at = :now
            WHERE id = :id
        ");
        $upd->execute([
            ':status' => $newStatus,
            ':reason' => $input['reason'],
            ':by' => $user['id'],
            ':now' => $now,
            ':id' => $id,
        ]);

        Audit::log($user['id'], $user['role'], "NGO_{$decision}D", 'ngos', $id, $ngo['status'], $newStatus, $input['reason']);

        // Send notification to NGO user
        $notif = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, entity_type, entity_id, created_at)
            VALUES (:uid, :title, :msg, :type, 'ngo', :eid, :now)
        ");
        $notif->execute([
            ':uid' => $ngo['user_id'],
            ':title' => "NGO Verification Decision: {$newStatus}",
            ':msg' => "Your NGO registration application was {$newStatus} by District Authority. Reason: {$input['reason']}",
            ':type' => "NGO_{$newStatus}",
            ':eid' => $id,
            ':now' => $now,
        ]);

        Response::success([
            'id' => $id,
            'status' => $newStatus,
            'decision_reason' => $input['reason'],
            'decided_at' => $now,
        ], "NGO registration has been {$newStatus}.");
    }
}

Router::add('GET', '/api/ngos', [NgoController::class, 'list']);
Router::add('GET', '/api/ngos/{id}', [NgoController::class, 'detail']);
Router::add('POST', '/api/ngos/{id}/document', [NgoController::class, 'uploadDocument']);
Router::add('POST', '/api/ngos/{id}/decide', [NgoController::class, 'decide']);
Router::add('POST', '/api/ngos/{id}/decision', [NgoController::class, 'decide']);
