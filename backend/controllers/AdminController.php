<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/Audit.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class AdminController {
    public function listUsers(): void {
        AuthMiddleware::requireRoles(['MOSJE_ADMIN']);

        $pdo = Database::getConnection();
        $roleFilter = $_GET['role'] ?? null;
        $districtFilter = $_GET['district_id'] ?? null;

        $sql = "
            SELECT u.id, u.name, u.email, u.phone, u.role, u.status, u.district_id, u.state_id,
                   d.name as district_name, s.name as state_name, u.created_at
            FROM users u
            LEFT JOIN districts d ON u.district_id = d.id
            LEFT JOIN states s ON u.state_id = s.id
            WHERE 1=1
        ";
        $params = [];

        if ($roleFilter) {
            $sql .= " AND u.role = :role";
            $params[':role'] = $roleFilter;
        }

        if ($districtFilter) {
            $sql .= " AND u.district_id = :dist";
            $params[':dist'] = (int)$districtFilter;
        }

        $sql .= " ORDER BY u.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        Response::success($users, 'Users retrieved successfully');
    }

    public function createUser(): void {
        $admin = AuthMiddleware::requireRoles(['MOSJE_ADMIN']);
        $input = getJsonInput();

        $validator = Validator::make($input, [
            'name' => 'required|string|min:3',
            'email' => 'required|email',
            'phone' => 'required|string|min:10',
            'password' => 'required|string|min:6',
            'role' => 'required|in:INSPECTOR,DISTRICT_OFFICER,STATE_OFFICER,MOSJE_ADMIN,SENIOR_OFFICER',
        ]);

        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }

        $pdo = Database::getConnection();

        // Check duplicate email
        $chk = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $chk->execute([':email' => $input['email']]);
        if ($chk->fetch()) {
            Response::error('User with this email already exists.', 409);
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, phone, password_hash, role, state_id, district_id, status, created_at, updated_at)
            VALUES (:name, :email, :phone, :password_hash, :role, :state_id, :district_id, 'ACTIVE', :created_at, :updated_at)
        ");

        $stmt->execute([
            ':name' => $input['name'],
            ':email' => $input['email'],
            ':phone' => $input['phone'],
            ':password_hash' => password_hash($input['password'], PASSWORD_BCRYPT),
            ':role' => $input['role'],
            ':state_id' => !empty($input['state_id']) ? (int)$input['state_id'] : null,
            ':district_id' => !empty($input['district_id']) ? (int)$input['district_id'] : null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $newUserId = (int)$pdo->lastInsertId();
        Audit::log($admin['id'], 'MOSJE_ADMIN', 'STAFF_CREATED', 'users', $newUserId, null, 'ACTIVE', "Admin created staff {$input['email']} with role {$input['role']}");

        Response::success([
            'id' => $newUserId,
            'name' => $input['name'],
            'email' => $input['email'],
            'role' => $input['role'],
            'status' => 'ACTIVE',
        ], 'Staff account created successfully', 201);
    }

    public function updateUserStatus(array $params): void {
        $admin = AuthMiddleware::requireRoles(['MOSJE_ADMIN']);
        $userId = (int)($params['id'] ?? 0);
        $input = getJsonInput();

        $validator = Validator::make($input, [
            'status' => 'required|in:ACTIVE,INACTIVE,SUSPENDED',
        ]);

        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id, email, status FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::notFound('User not found.');
        }

        $oldStatus = $user['status'];
        $newStatus = $input['status'];
        $now = date('Y-m-d H:i:s');

        $upd = $pdo->prepare("UPDATE users SET status = :status, updated_at = :now WHERE id = :id");
        $upd->execute([':status' => $newStatus, ':now' => $now, ':id' => $userId]);

        Audit::log($admin['id'], 'MOSJE_ADMIN', 'USER_STATUS_CHANGE', 'users', $userId, $oldStatus, $newStatus, "Changed status of {$user['email']} from {$oldStatus} to {$newStatus}");

        Response::success([
            'id' => $userId,
            'email' => $user['email'],
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ], "User status updated to {$newStatus}");
    }

    public function auditLog(): void {
        AuthMiddleware::requireRoles(['MOSJE_ADMIN', 'STATE_OFFICER']);

        $pdo = Database::getConnection();
        $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 50;

        $stmt = $pdo->prepare("
            SELECT a.*, u.name as user_name, u.email as user_email
            FROM audit_log a
            LEFT JOIN users u ON a.user_id = u.id
            ORDER BY a.id DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll();

        Response::success($logs, 'Audit logs retrieved');
    }

    public function financeSummary(): void {
        AuthMiddleware::requireRoles(['MOSJE_ADMIN']);
        $pdo = Database::getConnection();

        $stmtPay = $pdo->query("
            SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'SUCCESS' THEN 1 ELSE 0 END) as successful_orders,
                SUM(CASE WHEN status = 'SUCCESS' THEN amount ELSE 0 END) as base_fees_collected,
                SUM(CASE WHEN status = 'SUCCESS' THEN gst_amount ELSE 0 END) as gst_collected,
                SUM(CASE WHEN status = 'SUCCESS' THEN total_amount ELSE 0 END) as total_fees_collected
            FROM payments
        ");
        $paySummary = $stmtPay->fetch();

        $stmtRef = $pdo->query("
            SELECT 
                COUNT(*) as total_refunds,
                SUM(amount) as total_refunded_amount
            FROM refunds
            WHERE status = 'PROCESSED'
        ");
        $refSummary = $stmtRef->fetch();

        $totalCollected = (float)($paySummary['total_fees_collected'] ?? 0);
        $totalRefunded = (float)($refSummary['total_refunded_amount'] ?? 0);
        $netRetained = round($totalCollected - $totalRefunded, 2);

        $stmtTxns = $pdo->query("
            SELECT p.id, p.order_id, p.amount, p.gst_amount, p.total_amount, p.status, p.transaction_id, p.created_at,
                   u.name as user_name, u.email as user_email,
                   e.complaint_id, e.from_level, e.to_level, e.status as escalation_status,
                   r.refund_reference, r.amount as refund_amount, r.processed_at as refunded_at
            FROM payments p
            JOIN users u ON p.user_id = u.id
            LEFT JOIN escalations e ON e.payment_id = p.id
            LEFT JOIN refunds r ON r.payment_id = p.id
            ORDER BY p.id DESC
            LIMIT 50
        ");
        $transactions = $stmtTxns->fetchAll();

        Response::success([
            'summary' => [
                'total_orders' => (int)($paySummary['total_orders'] ?? 0),
                'successful_orders' => (int)($paySummary['successful_orders'] ?? 0),
                'base_fees_collected' => (float)($paySummary['base_fees_collected'] ?? 0),
                'gst_collected' => (float)($paySummary['gst_collected'] ?? 0),
                'total_fees_collected' => $totalCollected,
                'total_refunds_count' => (int)($refSummary['total_refunds'] ?? 0),
                'total_refunded_amount' => $totalRefunded,
                'net_retained_revenue' => $netRetained,
                'gst_rate_percent' => (float)Config::get('GST_RATE_PERCENT', 18.0),
            ],
            'recent_transactions' => $transactions,
        ], 'Financial summary retrieved');
    }

    public function masterDashboard(): void {
        AuthMiddleware::requireRoles(['MASTER_ADMIN']);
        $pdo = Database::getConnection();

        // 1. Counts grouped by role
        $roleStmt = $pdo->query("
            SELECT role, COUNT(*) as count,
                   SUM(CASE WHEN status = 'ACTIVE' THEN 1 ELSE 0 END) as active_count,
                   SUM(CASE WHEN status != 'ACTIVE' THEN 1 ELSE 0 END) as inactive_count
            FROM users
            GROUP BY role
        ");
        $rolesSummary = $roleStmt->fetchAll();

        // 2. All accounts with activity metadata
        $usersStmt = $pdo->query("
            SELECT u.id, u.username, u.name, u.email, u.phone, u.role, u.status, u.created_at,
                   u.gov_id, u.lgd_code, u.must_change_password,
                   d.name as district_name, s.name as state_name,
                   (
                       SELECT action FROM audit_log WHERE user_id = u.id ORDER BY id DESC LIMIT 1
                   ) as last_action,
                   (
                       SELECT created_at FROM audit_log WHERE user_id = u.id ORDER BY id DESC LIMIT 1
                   ) as last_active_at
            FROM users u
            LEFT JOIN districts d ON u.district_id = d.id
            LEFT JOIN states s ON u.state_id = s.id
            ORDER BY 
                CASE u.role
                    WHEN 'MASTER_ADMIN' THEN 1
                    WHEN 'MOSJE_ADMIN' THEN 2
                    WHEN 'STATE_OFFICER' THEN 3
                    WHEN 'DISTRICT_OFFICER' THEN 4
                    WHEN 'INSPECTOR' THEN 5
                    WHEN 'NGO' THEN 6
                    ELSE 7
                END ASC, u.id DESC
        ");
        $allAccounts = $usersStmt->fetchAll();
        foreach ($allAccounts as &$acc) {
            if ($acc['role'] === 'MASTER_ADMIN') {
                $acc['demo_password'] = 'admin';
                $acc['login_method'] = 'Username & Password (admin / admin)';
            } elseif ($acc['role'] === 'PUBLIC') {
                $acc['demo_password'] = 'Mobile OTP (Demo: 123456)';
                $acc['login_method'] = 'Mobile Number + OTP';
            } else {
                $acc['demo_password'] = 'Demo@123';
                $acc['login_method'] = 'Email / Mobile + Password (Demo@123)';
            }
            $acc['gov_sync_status'] = !empty($acc['lgd_code']) ? "LGD Sync Pre-Satisfied ({$acc['lgd_code']})" : "Central Gov-ID Verified";
        }
        unset($acc);

        // 3. System activity stream (all users, all actions)
        $auditStmt = $pdo->query("
            SELECT a.*, u.name as user_name, u.email as user_email
            FROM audit_log a
            LEFT JOIN users u ON a.user_id = u.id
            ORDER BY a.id DESC
            LIMIT 40
        ");
        $activityStream = $auditStmt->fetchAll();

        // 4. Global Tender & Budget KPI
        $kpi = $pdo->query("
            SELECT 
                COUNT(*) as total_tenders,
                SUM(CASE WHEN status = 'IN_PROGRESS' THEN 1 ELSE 0 END) as in_progress_tenders,
                SUM(CASE WHEN status = 'COMPLETED' OR status = 'CLOSED' THEN 1 ELSE 0 END) as completed_tenders,
                SUM(sanctioned_amount) as total_sanctioned_amount,
                SUM(actual_spent) as total_actual_spent,
                SUM(CASE WHEN is_red_marked = 1 THEN 1 ELSE 0 END) as total_red_marked,
                SUM(CASE WHEN variance_flag = 1 THEN 1 ELSE 0 END) as total_variance_flagged
            FROM tenders
        ")->fetch();

        Response::success([
            'role_distribution' => $rolesSummary,
            'accounts' => $allAccounts,
            'recent_activity' => $activityStream,
            'kpis' => [
                'total_users' => count($allAccounts),
                'total_tenders' => (int)($kpi['total_tenders'] ?? 0),
                'total_sanctioned_amount' => (float)($kpi['total_sanctioned_amount'] ?? 0),
                'total_actual_spent' => (float)($kpi['total_actual_spent'] ?? 0),
                'total_red_marked' => (int)($kpi['total_red_marked'] ?? 0),
                'total_variance_flagged' => (int)($kpi['total_variance_flagged'] ?? 0),
            ]
        ], 'Master Admin Dashboard loaded successfully');
    }
}

// Register Admin Routes
Router::add('GET', '/api/admin/users', [AdminController::class, 'listUsers']);
Router::add('POST', '/api/admin/users', [AdminController::class, 'createUser']);
Router::add('PUT', '/api/admin/users/{id}/status', [AdminController::class, 'updateUserStatus']);
Router::add('GET', '/api/admin/audit-log', [AdminController::class, 'auditLog']);
Router::add('GET', '/api/admin/finance-summary', [AdminController::class, 'financeSummary']);
Router::add('GET', '/api/admin/master-dashboard', [AdminController::class, 'masterDashboard']);

