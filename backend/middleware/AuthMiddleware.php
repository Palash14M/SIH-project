<?php

require_once __DIR__ . '/../utils/Jwt.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../config/database.php';

class AuthMiddleware {
    public static function getBearerToken(): ?string {
        $headers = '';
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }

        if (!empty($headers) && preg_match('/Bearer\s+(.*)$/i', $headers, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public static function authenticate(): array {
        $token = self::getBearerToken();
        if (!$token) {
            Response::unauthorized('Authentication token is required.');
        }

        $payload = Jwt::decode($token);
        if (!$payload) {
            Response::unauthorized('Invalid or expired authentication token.');
        }

        // Fetch user from DB to verify active status
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $payload['sub'] ?? 0]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::unauthorized('User not found.');
        }

        if (isset($user['status']) && $user['status'] !== 'ACTIVE') {
            Response::forbidden('User account is inactive or suspended.');
        }

        // Check if NGO and unapproved
        if ($user['role'] === 'NGO') {
            $ngoStmt = $pdo->prepare("SELECT * FROM ngos WHERE user_id = :uid LIMIT 1");
            $ngoStmt->execute([':uid' => $user['id']]);
            $ngo = $ngoStmt->fetch();
            $user['ngo'] = $ngo;
            $user['ngo_status'] = $ngo['status'] ?? 'PENDING';
        }

        return $user;
    }

    public static function requireRoles(array $allowedRoles): array {
        $user = self::authenticate();

        $role = $user['role'];
        $isPermitted = in_array($role, $allowedRoles, true);

        // MASTER_ADMIN is supreme over MOSJE_ADMIN and all lower roles
        if ($role === 'MASTER_ADMIN') {
            $isPermitted = true;
        }

        if (!$isPermitted) {
            Response::forbidden("Access denied. Role '{$user['role']}' is not permitted for this action.");
        }

        if ($user['role'] === 'NGO' && in_array('NGO', $allowedRoles, true)) {
            if (($user['ngo_status'] ?? '') !== 'APPROVED') {
                Response::forbidden("NGO account is pending approval by the District Officer.");
            }
        }

        return $user;
    }

    public static function optionalAuth(): ?array {
        $token = self::getBearerToken();
        if (!$token) {
            return null;
        }

        $payload = Jwt::decode($token);
        if (!$payload) {
            return null;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $payload['sub'] ?? 0]);
        $user = $stmt->fetch();

        if ($user && ($user['status'] ?? '') === 'ACTIVE') {
            if ($user['role'] === 'NGO') {
                $ngoStmt = $pdo->prepare("SELECT * FROM ngos WHERE user_id = :uid LIMIT 1");
                $ngoStmt->execute([':uid' => $user['id']]);
                $user['ngo'] = $ngoStmt->fetch();
                $user['ngo_status'] = $user['ngo']['status'] ?? 'PENDING';
            }
            return $user;
        }

        return null;
    }

    /**
     * Data visibility filter:
     * Contact details of contractors and inspectors (phone, email, address)
     * are never shown to the Public or unapproved/unattached entities.
     * Only visible to higher authorities (ADMIN, STATE_OFFICER, DISTRICT_OFFICER, SENIOR_OFFICER)
     * and approved attached NGOs.
     */
    public static function filterTenderVisibility(array $tender, ?array $user): array {
        $role = $user['role'] ?? 'PUBLIC';
        $userId = $user['id'] ?? 0;

        $isHigherAuthority = in_array($role, ['MASTER_ADMIN', 'MOSJE_ADMIN', 'STATE_OFFICER', 'DISTRICT_OFFICER', 'INSPECTOR', 'SENIOR_OFFICER'], true);
        
        $isApprovedNgo = false;
        if ($role === 'NGO' && ($user['ngo_status'] ?? '') === 'APPROVED') {
            $isApprovedNgo = true;
        }

        $canViewFullDetails = ($isHigherAuthority || $isApprovedNgo) && ($role !== 'PUBLIC');

        if (!$canViewFullDetails) {
            // Strictly strip contractor and inspector contact info for Public users
            unset($tender['contractor_phone']);
            unset($tender['contractor_email']);
            unset($tender['contractor_address']);
            unset($tender['contractor_contact_person']);
            unset($tender['assigned_inspector_phone']);
            unset($tender['assigned_inspector_email']);

            // Public accounts see no budget fields (TASK 2 requirement)
            if ($role === 'PUBLIC') {
                unset($tender['sanctioned_amount']);
                unset($tender['actual_spent']);
            }

            // If inspectors attached list exists, strip inspector contact details
            if (isset($tender['inspectors']) && is_array($tender['inspectors'])) {
                foreach ($tender['inspectors'] as &$inspector) {
                    if ($role === 'PUBLIC') {
                        // Public users may not see inspector identities or contact details at all
                        $inspector = [
                            'id' => $inspector['id'] ?? 0,
                            'role' => 'INSPECTOR',
                            'assigned_at' => $inspector['assigned_at'] ?? null,
                        ];
                    } else {
                        unset($inspector['phone']);
                        unset($inspector['email']);
                    }
                }
                unset($inspector);
            }
        }

        return $tender;
    }
}
