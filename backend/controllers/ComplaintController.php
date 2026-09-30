<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/Audit.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../services/PaymentProvider.php';

class ComplaintController {
    public function list(): void {
        $user = AuthMiddleware::authenticate();
        $pdo = Database::getConnection();

        $sql = "
            SELECT c.*, t.tender_number, t.title as tender_title, n.organization_name as ngo_name,
                   u.name as current_assignee_name
            FROM complaints c
            JOIN tenders t ON c.tender_id = t.id
            JOIN ngos n ON c.ngo_id = n.id
            JOIN users u ON c.current_assignee_id = u.id
            WHERE 1=1
        ";
        $params = [];

        if ($user['role'] === 'NGO') {
            $sql .= " AND c.ngo_id = :nid";
            $params[':nid'] = $user['ngo']['id'] ?? 0;
        } elseif ($user['role'] === 'SENIOR_OFFICER') {
            $sql .= " AND c.current_assignee_id = :uid";
            $params[':uid'] = $user['id'];
        } elseif ($user['role'] === 'DISTRICT_OFFICER') {
            $sql .= " AND (t.district_id = :did OR c.current_assignee_id = :uid)";
            $params[':did'] = $user['district_id'];
            $params[':uid'] = $user['id'];
        }

        $sql .= " ORDER BY c.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        Response::success($stmt->fetchAll(), 'Complaints retrieved');
    }

    public function detail(array $params): void {
        $user = AuthMiddleware::authenticate();
        $id = (int)($params['id'] ?? 0);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT c.*, t.tender_number, t.title as tender_title, t.district_id, n.organization_name as ngo_name,
                   u.name as current_assignee_name
            FROM complaints c
            JOIN tenders t ON c.tender_id = t.id
            JOIN ngos n ON c.ngo_id = n.id
            JOIN users u ON c.current_assignee_id = u.id
            WHERE c.id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $complaint = $stmt->fetch();

        if (!$complaint) {
            Response::notFound('Complaint not found');
        }

        // Jurisdiction check for District Officer
        if ($user['role'] === 'DISTRICT_OFFICER' && (int)$complaint['district_id'] !== (int)$user['district_id'] && (int)$complaint['current_assignee_id'] !== (int)$user['id']) {
            Response::forbidden('Access denied: Complaint is outside your district jurisdiction.');
        }

        // Fetch Escalation History & Payments & Refunds
        $eStmt = $pdo->prepare("
            SELECT e.*, p.order_id, p.transaction_id, p.status as payment_status,
                   r.refund_reference, r.amount as refund_amount, r.status as refund_status
            FROM escalations e
            LEFT JOIN payments p ON e.payment_id = p.id
            LEFT JOIN refunds r ON p.id = r.payment_id
            WHERE e.complaint_id = :cid
            ORDER BY e.id ASC
        ");
        $eStmt->execute([':cid' => $id]);
        $complaint['escalation_history'] = $eStmt->fetchAll();

        Response::success($complaint, 'Complaint details retrieved');
    }

    public function create(): void {
        // STRICT RULE: Only approved NGOs can raise complaints!
        $user = AuthMiddleware::requireRoles(['NGO']);
        $input = getJsonInput();

        $validator = Validator::make($input, [
            'tender_id' => 'required|integer',
            'subject' => 'required|string|min:5',
            'description' => 'required|string|min:10',
        ]);
        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }

        $tenderId = (int)$input['tender_id'];
        $ngoId = (int)($user['ngo']['id'] ?? 0);
        $pdo = Database::getConnection();

        // Verify NGO is attached to this tender
        $attStmt = $pdo->prepare("SELECT COUNT(*) FROM tender_ngos WHERE tender_id = :tid AND ngo_id = :nid");
        $attStmt->execute([':tid' => $tenderId, ':nid' => $ngoId]);
        if ((int)$attStmt->fetchColumn() === 0) {
            Response::forbidden('Your NGO is not attached to this project.');
        }

        // Fetch Tender to get the responsible senior officer
        $tStmt = $pdo->prepare("SELECT responsible_senior_id, tender_number FROM tenders WHERE id = :id LIMIT 1");
        $tStmt->execute([':id' => $tenderId]);
        $tender = $tStmt->fetch();

        if (!$tender) {
            Response::notFound('Tender not found');
        }

        $seniorId = (int)$tender['responsible_senior_id'];
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare("
            INSERT INTO complaints (
                tender_id, ngo_id, issue_id, subject, description,
                current_level, current_assignee_id, status, created_at, updated_at
            ) VALUES (
                :tid, :nid, :iid, :subj, :desc,
                'SENIOR_OFFICER', :assignee, 'PENDING', :now, :now
            )
        ");
        $stmt->execute([
            ':tid' => $tenderId,
            ':nid' => $ngoId,
            ':iid' => !empty($input['issue_id']) ? (int)$input['issue_id'] : null,
            ':subj' => $input['subject'],
            ':desc' => $input['description'],
            ':assignee' => $seniorId,
            ':now' => $now,
        ]);

        $complaintId = (int)$pdo->lastInsertId();
        Audit::log($user['id'], 'NGO', 'COMPLAINT_RAISED', 'complaints', $complaintId, null, 'PENDING', "NGO raised complaint on tender {$tender['tender_number']}");

        // Notify Senior Officer
        $notif = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, entity_type, entity_id, created_at)
            VALUES (:uid, :title, :msg, 'COMPLAINT_NEW', 'complaint', :eid, :now)
        ");
        $notif->execute([
            ':uid' => $seniorId,
            ':title' => "New Complaint Assigned",
            ':msg' => "Complaint #{$complaintId} on project {$tender['tender_number']} requires your review.",
            ':eid' => $complaintId,
            ':now' => $now,
        ]);

        Response::success([
            'id' => $complaintId,
            'status' => 'PENDING',
            'current_level' => 'SENIOR_OFFICER',
            'assigned_senior_id' => $seniorId,
        ], 'Complaint created and routed to contractor responsible senior.', 201);
    }

    public function resolve(array $params): void {
        $user = AuthMiddleware::authenticate();
        $id = (int)($params['id'] ?? 0);
        $input = getJsonInput();

        $validator = Validator::make($input, [
            'action' => 'required|in:RESOLVE,REJECT',
            'remarks' => 'required|string|min:5',
        ]);
        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM complaints WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $c = $stmt->fetch();

        if (!$c) {
            Response::notFound('Complaint not found');
        }

        // Only current assignee or higher authority can resolve
        if ((int)$c['current_assignee_id'] !== (int)$user['id'] && !in_array($user['role'], ['MOSJE_ADMIN', 'STATE_OFFICER'])) {
            Response::forbidden('Only the currently assigned officer or higher authority can act on this complaint.');
        }

        $action = strtoupper($input['action']);
        $newStatus = ($action === 'RESOLVE') ? 'RESOLVED' : 'REJECTED';
        $now = date('Y-m-d H:i:s');

        $upd = $pdo->prepare("
            UPDATE complaints SET
                status = :status,
                resolution_remarks = :remarks,
                updated_at = :now
            WHERE id = :id
        ");
        $upd->execute([
            ':status' => $newStatus,
            ':remarks' => $input['remarks'],
            ':now' => $now,
            ':id' => $id,
        ]);

        Audit::log($user['id'], $user['role'], "COMPLAINT_{$newStatus}", 'complaints', $id, $c['status'], $newStatus, $input['remarks']);

        Response::success([
            'id' => $id,
            'status' => $newStatus,
            'remarks' => $input['remarks'],
        ], "Complaint has been {$newStatus}.");
    }

    public function initiateEscalation(array $params): void {
        $user = AuthMiddleware::requireRoles(['NGO']);
        $id = (int)($params['id'] ?? 0);
        $input = getJsonInput();

        $validator = Validator::make($input, [
            'target_level' => 'required|in:DISTRICT_OFFICER,STATE_OFFICER,MOSJE_ADMIN',
            'reason' => 'required|string|min:10',
        ]);
        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT c.*, t.district_id, t.state_id FROM complaints c JOIN tenders t ON c.tender_id = t.id WHERE c.id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $c = $stmt->fetch();

        if (!$c) {
            Response::notFound('Complaint not found');
        }

        // Verify escalation chain step by step (no skipping!)
        $currentLevel = $c['current_level'];
        $targetLevel = $input['target_level'];

        $validChain = [
            'SENIOR_OFFICER' => 'DISTRICT_OFFICER',
            'DISTRICT_OFFICER' => 'STATE_OFFICER',
            'STATE_OFFICER' => 'MOSJE_ADMIN',
        ];

        if (!isset($validChain[$currentLevel]) || $validChain[$currentLevel] !== $targetLevel) {
            Response::error("Invalid escalation step: From '{$currentLevel}', you can only escalate directly to '{$validChain[$currentLevel]}'. Skipping levels is strictly prohibited.", 400);
        }

        // Calculate Fee: ₹400 base + 18% GST (₹72) = ₹472.00
        $baseFee = (float)Config::get('ESCALATION_BASE_FEE', 400.00);
        $gstRate = (float)Config::get('ESCALATION_GST_RATE', 0.18);
        $gstAmount = round($baseFee * $gstRate, 2);
        $totalAmount = round($baseFee + $gstAmount, 2);

        // Create Payment Order via PaymentProvider
        $paymentProvider = new DemoPaymentProvider();
        $order = $paymentProvider->createOrder($totalAmount, 'INR', [
            'complaint_id' => $id,
            'from_level' => $currentLevel,
            'to_level' => $targetLevel,
            'reason' => $input['reason'],
        ]);

        // Insert pending payment record
        $now = date('Y-m-d H:i:s');
        $pStmt = $pdo->prepare("
            INSERT INTO payments (order_id, user_id, amount, gst_amount, total_amount, currency, status, metadata, created_at)
            VALUES (:oid, :uid, :amt, :gst, :tot, 'INR', 'CREATED', :meta, :now)
        ");
        $pStmt->execute([
            ':oid' => $order['order_id'],
            ':uid' => $user['id'],
            ':amt' => $baseFee,
            ':gst' => $gstAmount,
            ':tot' => $totalAmount,
            ':meta' => json_encode(['complaint_id' => $id, 'target_level' => $targetLevel, 'reason' => $input['reason']]),
            ':now' => $now,
        ]);

        Response::success([
            'order_id' => $order['order_id'],
            'receipt' => $order['receipt'],
            'base_amount' => $baseFee,
            'gst_rate_percent' => ($gstRate * 100) . '%',
            'gst_amount' => $gstAmount,
            'total_amount' => $totalAmount,
            'current_level' => $currentLevel,
            'target_level' => $targetLevel,
            'reason' => $input['reason'],
        ], 'Escalation payment order created. Please confirm payment to finalize escalation.');
    }

    public function confirmPayment(): void {
        $user = AuthMiddleware::requireRoles(['NGO']);
        $input = getJsonInput();

        $validator = Validator::make($input, [
            'order_id' => 'required|string',
        ]);
        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }

        $orderId = $input['order_id'];
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = :oid LIMIT 1");
        $stmt->execute([':oid' => $orderId]);
        $payment = $stmt->fetch();

        if (!$payment) {
            Response::notFound('Payment order not found');
        }

        // Verify with PaymentProvider
        $paymentProvider = new DemoPaymentProvider();
        $verifyResult = $paymentProvider->verifyPayment($orderId, $input);

        if (!$verifyResult['success']) {
            Response::error($verifyResult['error_message'] ?? 'Payment verification failed', 400);
        }

        $now = date('Y-m-d H:i:s');
        $updPay = $pdo->prepare("
            UPDATE payments SET
                status = 'SUCCESS',
                method = :method,
                transaction_id = :txnid,
                verified_at = :now
            WHERE id = :id
        ");
        $updPay->execute([
            ':method' => $verifyResult['method'],
            ':txnid' => $verifyResult['transaction_id'],
            ':now' => $now,
            ':id' => $payment['id'],
        ]);

        $meta = json_decode($payment['metadata'] ?? '{}', true);
        $complaintId = (int)($meta['complaint_id'] ?? 0);
        $targetLevel = $meta['target_level'] ?? 'DISTRICT_OFFICER';
        $reason = $meta['reason'] ?? 'Escalated by NGO';

        // Find appropriate assignee for target level
        $cStmt = $pdo->prepare("SELECT c.*, t.district_id, t.state_id FROM complaints c JOIN tenders t ON c.tender_id = t.id WHERE c.id = :id LIMIT 1");
        $cStmt->execute([':id' => $complaintId]);
        $comp = $cStmt->fetch();

        $newAssigneeId = 1; // default Admin
        if ($targetLevel === 'DISTRICT_OFFICER') {
            $doStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'DISTRICT_OFFICER' AND district_id = :did LIMIT 1");
            $doStmt->execute([':did' => $comp['district_id']]);
            $newAssigneeId = (int)($doStmt->fetchColumn() ?: 1);
        } elseif ($targetLevel === 'STATE_OFFICER') {
            $soStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'STATE_OFFICER' AND state_id = :sid LIMIT 1");
            $soStmt->execute([':sid' => $comp['state_id']]);
            $newAssigneeId = (int)($soStmt->fetchColumn() ?: 1);
        }

        // Advance complaint level and status
        $updComp = $pdo->prepare("
            UPDATE complaints SET
                current_level = :lvl,
                current_assignee_id = :assignee,
                status = 'ESCALATED',
                updated_at = :now
            WHERE id = :id
        ");
        $updComp->execute([
            ':lvl' => $targetLevel,
            ':assignee' => $newAssigneeId,
            ':now' => $now,
            ':id' => $complaintId,
        ]);

        // Record escalation record
        $insEsc = $pdo->prepare("
            INSERT INTO escalations (
                complaint_id, from_level, to_level, payment_id, fee_amount, gst_amount,
                total_amount, reason, status, created_at
            ) VALUES (
                :cid, :from_lvl, :to_lvl, :pid, :fee, :gst,
                :tot, :reason, 'IN_REVIEW', :now
            )
        ");
        $insEsc->execute([
            ':cid' => $complaintId,
            ':from_lvl' => $comp['current_level'],
            ':to_lvl' => $targetLevel,
            ':pid' => $payment['id'],
            ':fee' => $payment['amount'],
            ':gst' => $payment['gst_amount'],
            ':tot' => $payment['total_amount'],
            ':reason' => $reason,
            ':now' => $now,
        ]);

        Audit::log($user['id'], 'NGO', 'COMPLAINT_ESCALATED', 'complaints', $complaintId, $comp['current_level'], $targetLevel, "Paid escalation fee {$payment['total_amount']} via {$verifyResult['method']}");

        Response::success([
            'order_id' => $orderId,
            'transaction_id' => $verifyResult['transaction_id'],
            'complaint_id' => $complaintId,
            'new_level' => $targetLevel,
            'status' => 'ESCALATED',
            'receipt' => [
                'base_fee' => (float)$payment['amount'],
                'gst_amount' => (float)$payment['gst_amount'],
                'total_paid' => (float)$payment['total_amount'],
                'status' => 'SUCCESS',
                'timestamp' => $now,
            ],
        ], 'Payment confirmed. Complaint escalated successfully to ' . $targetLevel);
    }

    public function decideEscalation(array $params): void {
        $user = AuthMiddleware::requireRoles(['DISTRICT_OFFICER', 'STATE_OFFICER', 'MOSJE_ADMIN']);
        $id = (int)($params['id'] ?? 0);
        $input = getJsonInput();

        $validator = Validator::make($input, [
            'decision' => 'required|in:UPHELD,REJECTED',
            'remarks' => 'required|string|min:5',
        ]);
        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }

        $pdo = Database::getConnection();

        // Fetch latest escalation record for this complaint
        $stmt = $pdo->prepare("
            SELECT e.*, p.id as payment_id, p.total_amount, p.user_id as payer_user_id
            FROM escalations e
            JOIN payments p ON e.payment_id = p.id
            WHERE e.complaint_id = :cid
            ORDER BY e.id DESC LIMIT 1
        ");
        $stmt->execute([':cid' => $id]);
        $esc = $stmt->fetch();

        if (!$esc) {
            Response::notFound('No active escalation record found for this complaint.');
        }

        $decision = strtoupper($input['decision']);
        $now = date('Y-m-d H:i:s');
        $refundData = null;

        // If UPHELD -> Automatic refund triggered!
        if ($decision === 'UPHELD') {
            $paymentProvider = new DemoPaymentProvider();
            $refundResult = $paymentProvider->processRefund(
                (string)$esc['payment_id'],
                (float)$esc['total_amount'],
                "Complaint #{$id} upheld by {$user['role']}"
            );

            $insRef = $pdo->prepare("
                INSERT INTO refunds (payment_id, amount, status, reason, refund_reference, processed_at)
                VALUES (:pid, :amt, 'PROCESSED', :reason, :ref, :now)
            ");
            $insRef->execute([
                ':pid' => $esc['payment_id'],
                ':amt' => $esc['total_amount'],
                ':reason' => "Complaint upheld by {$user['role']}",
                ':ref' => $refundResult['refund_id'],
                ':now' => $now,
            ]);

            $refundData = [
                'refunded' => true,
                'amount' => (float)$esc['total_amount'],
                'refund_reference' => $refundResult['refund_id'],
                'processed_at' => $now,
            ];
        }

        // Update escalation record
        $updEsc = $pdo->prepare("
            UPDATE escalations SET
                status = :status,
                decided_by = :by,
                decision_remarks = :remarks,
                resolved_at = :now
            WHERE id = :id
        ");
        $updEsc->execute([
            ':status' => $decision,
            ':by' => $user['id'],
            ':remarks' => $input['remarks'],
            ':now' => $now,
            ':id' => $esc['id'],
        ]);

        // Update complaint
        $newCompStatus = ($decision === 'UPHELD') ? 'RESOLVED' : 'REJECTED';
        $updComp = $pdo->prepare("
            UPDATE complaints SET status = :status, resolution_remarks = :remarks, updated_at = :now WHERE id = :id
        ");
        $updComp->execute([':status' => $newCompStatus, ':remarks' => $input['remarks'], ':now' => $now, ':id' => $id]);

        Audit::log($user['id'], $user['role'], "ESCALATION_{$decision}", 'escalations', (int)$esc['id'], 'IN_REVIEW', $decision, $input['remarks']);

        Response::success([
            'complaint_id' => $id,
            'decision' => $decision,
            'remarks' => $input['remarks'],
            'refund' => $refundData,
        ], "Escalation decided as {$decision}" . ($refundData ? ' (Fee refunded)' : ' (Fee retained)'));
    }
}

Router::add('GET', '/api/complaints', [ComplaintController::class, 'list']);
Router::add('GET', '/api/complaints/{id}', [ComplaintController::class, 'detail']);
Router::add('POST', '/api/complaints', [ComplaintController::class, 'create']);
Router::add('POST', '/api/complaints/{id}/resolve', [ComplaintController::class, 'resolve']);
Router::add('POST', '/api/complaints/{id}/escalate', [ComplaintController::class, 'initiateEscalation']);
Router::add('POST', '/api/payments/confirm', [ComplaintController::class, 'confirmPayment']);
Router::add('POST', '/api/complaints/{id}/decide-escalation', [ComplaintController::class, 'decideEscalation']);
