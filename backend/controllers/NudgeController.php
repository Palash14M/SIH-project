<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Audit.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class NudgeController {
    public function submitNudge(): void {
        $user = AuthMiddleware::authenticate();
        $input = getJsonInput();

        $tenderId = (int)($input['tender_id'] ?? 0);
        $ngoId = (int)($input['ngo_id'] ?? 0);
        $reason = trim($input['reason'] ?? '');
        $simulateDate = $input['simulate_date'] ?? null;

        $nudgeDate = $simulateDate ?: date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        $pdo = Database::getConnection();

        // 1. Enforce 1 nudge per user per day across the entire app!
        $chkStmt = $pdo->prepare("
            SELECT COUNT(*) FROM nudges 
            WHERE user_id = :uid AND nudge_date = :ndate
        ");
        $chkStmt->execute([':uid' => $user['id'], ':ndate' => $nudgeDate]);
        $existingCount = (int)$chkStmt->fetchColumn();

        if ($existingCount > 0) {
            Response::error("Daily nudge limit reached. You can only submit 1 nudge per day across the app. Today's chance has already been used.", 429);
        }

        // Validate tender and NGO existence
        $tStmt = $pdo->prepare("SELECT title, tender_number FROM tenders WHERE id = :id LIMIT 1");
        $tStmt->execute([':id' => $tenderId]);
        $tender = $tStmt->fetch();

        if (!$tender) {
            Response::notFound('Tender not found');
        }

        $nStmt = $pdo->prepare("SELECT user_id, organization_name FROM ngos WHERE id = :id LIMIT 1");
        $nStmt->execute([':id' => $ngoId]);
        $ngo = $nStmt->fetch();

        if (!$ngo) {
            Response::notFound('NGO not found');
        }

        // 2. Validate Reason Length (minimum 10 characters)
        $isReasonValid = (mb_strlen($reason) >= 10);
        $status = $isReasonValid ? 'DELIVERED' : 'REJECTED_INVALID_REASON';

        // Record the nudge - this consumes the day's chance regardless!
        $ins = $pdo->prepare("
            INSERT INTO nudges (user_id, ngo_id, tender_id, reason, status, nudge_date, created_at)
            VALUES (:uid, :nid, :tid, :reason, :status, :ndate, :now)
        ");
        $ins->execute([
            ':uid' => $user['id'],
            ':nid' => $ngoId,
            ':tid' => $tenderId,
            ':reason' => $reason ?: '[NO REASON PROVIDED]',
            ':status' => $status,
            ':ndate' => $nudgeDate,
            ':now' => $now,
        ]);
        $nudgeId = (int)$pdo->lastInsertId();

        Audit::log($user['id'], 'PUBLIC', 'NUDGE_SUBMITTED', 'nudges', $nudgeId, null, $status, "Nudge status: {$status} on {$tender['tender_number']}");

        if ($isReasonValid) {
            // DELIVERED: Send notification to the NGO
            $notif = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, entity_type, entity_id, created_at)
                VALUES (:uid, :title, :msg, 'PUBLIC_NUDGE', 'nudge', :eid, :now)
            ");
            $notif->execute([
                ':uid' => $ngo['user_id'],
                ':title' => "Citizen Nudge Received on {$tender['tender_number']}",
                ':msg' => "A citizen nudged your organization for project '{$tender['title']}'. Reason: {$reason}",
                ':eid' => $nudgeId,
                ':now' => $now,
            ]);

            Response::success([
                'id' => $nudgeId,
                'delivered' => true,
                'chance_consumed' => true,
                'nudge_date' => $nudgeDate,
                'status' => 'DELIVERED',
            ], 'Nudge successfully delivered to the NGO.', 201);
        } else {
            // NOT DELIVERED: Reason invalid/empty. NGO receives nothing, but today's chance is STILL burned!
            Response::success([
                'id' => $nudgeId,
                'delivered' => false,
                'chance_consumed' => true,
                'nudge_date' => $nudgeDate,
                'status' => 'REJECTED_INVALID_REASON',
                'warning' => 'Reason was less than 10 characters. The nudge was NOT delivered to the NGO, and your single daily chance has been consumed.',
            ], 'Submission rejected due to invalid reason. Daily chance consumed.', 200);
        }
    }

    public function checkStatus(): void {
        $user = AuthMiddleware::authenticate();
        $date = $_GET['date'] ?? date('Y-m-d');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM nudges WHERE user_id = :uid AND nudge_date = :d");
        $stmt->execute([':uid' => $user['id'], ':d' => $date]);
        $used = ((int)$stmt->fetchColumn() > 0);

        Response::success([
            'date' => $date,
            'has_used_today' => $used,
            'remaining_chances' => $used ? 0 : 1,
        ], 'Daily nudge status checked');
    }
}

Router::add('POST', '/api/nudges', [NudgeController::class, 'submitNudge']);
Router::add('GET', '/api/nudges/status', [NudgeController::class, 'checkStatus']);
