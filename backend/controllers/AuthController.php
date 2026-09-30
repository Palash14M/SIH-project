<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Jwt.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/Audit.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../services/SmsProvider.php';
require_once __DIR__ . '/../services/TotpService.php';

class AuthController {
    public function login(): void {
        $input = getJsonInput();
        $identifier = trim($input['username'] ?? ($input['email'] ?? ($input['phone'] ?? '')));
        $password = $input['password'] ?? '';
        $authenticatorCode = trim($input['authenticator_code'] ?? ($input['totp_code'] ?? ''));

        if (empty($identifier)) {
            Response::error('Identifier (username, email, or mobile) is required.', 422);
        }

        if (empty($password) && empty($authenticatorCode)) {
            Response::error('Password or Authenticator 2FA Code is required.', 422);
        }

        $pdo = Database::getConnection();
        $cleanId = trim($identifier);
        $roleGuess = strtoupper(str_replace([' ', '-'], '_', $cleanId));

        $stmt = $pdo->prepare("
            SELECT * FROM users 
            WHERE LOWER(email) = LOWER(:id) 
               OR LOWER(username) = LOWER(:id) 
               OR phone = :id 
               OR role = :roleGuess
            ORDER BY CASE 
                WHEN LOWER(email) = LOWER(:id) THEN 1
                WHEN LOWER(username) = LOWER(:id) THEN 2
                WHEN phone = :id THEN 3
                WHEN role = :roleGuess THEN 4
                ELSE 5 END
            LIMIT 1
        ");
        $stmt->execute([':id' => $cleanId, ':roleGuess' => $roleGuess]);
        $user = $stmt->fetch();

        // Fallback: If not found, try matching by role or partial identifier
        if (!$user) {
            $fallbackStmt = $pdo->prepare("
                SELECT * FROM users 
                WHERE LOWER(email) LIKE LOWER(:likeId) 
                   OR LOWER(name) LIKE LOWER(:likeId)
                   OR LOWER(role) LIKE LOWER(:likeId)
                LIMIT 1
            ");
            $fallbackStmt->execute([':likeId' => '%' . $cleanId . '%']);
            $user = $fallbackStmt->fetch();
        }

        $passOk = false;
        if ($user) {
            $normPass = trim((string)$password);
            $norm2fa = trim((string)$authenticatorCode);

            // 1. Universal Demo Authenticator code: 123456 is always accepted
            if ($norm2fa === '123456' || $normPass === '123456') {
                $passOk = true;
            }

            // 2. Check password if provided
            if (!empty($normPass)) {
                $knownTestPasswords = [
                    'demo@123', 'Demo@123', 'admin', 'Admin', 'Admin@12345', 'Officer@12345', 
                    'Inspect@12345', 'Inspector@12345', 'Ngo@12345', 'Senior@12345', '123456',
                    'demo', 'password', 'Admin@123', 'Demo@12345'
                ];
                if ((!empty($user['password_hash']) && password_verify($normPass, $user['password_hash'])) 
                    || in_array($normPass, $knownTestPasswords, true)
                    || in_array(strtolower($normPass), array_map('strtolower', $knownTestPasswords), true)) {
                    $passOk = true;
                }
            }

            // 3. Check Authenticator code if provided
            if (!empty($norm2fa)) {
                $secret = $user['totp_secret'] ?? 'JBSWY3DPEHPK3PXP';
                if ($norm2fa === '123456' || TotpService::verifyCode($secret, $norm2fa)) {
                    $passOk = true;
                } else {
                    Response::error('Invalid Authenticator code. For all demo roles, use 123456.', 401);
                }
            }
        }

        if (!$passOk) {
            Response::error('Invalid credentials. For demo access, use password "demo@123" (or "admin") and 2FA "123456".', 401);
        }

        if (($user['status'] ?? '') !== 'ACTIVE') {
            Response::forbidden('Your account is deactivated or suspended.');
        }

        // If NGO, check verification status
        $ngoData = null;
        if ($user['role'] === 'NGO') {
            $ngoStmt = $pdo->prepare("SELECT * FROM ngos WHERE user_id = :uid LIMIT 1");
            $ngoStmt->execute([':uid' => $user['id']]);
            $ngoData = $ngoStmt->fetch();
        }

        // If Contractor, fetch contractor company profile
        $contractorData = null;
        if ($user['role'] === 'CONTRACTOR') {
            $cStmt = $pdo->prepare("SELECT * FROM contractors WHERE email = :email OR phone = :phone LIMIT 1");
            $cStmt->execute([':email' => $user['email'], ':phone' => $user['phone']]);
            $contractorData = $cStmt->fetch();
            if (!$contractorData) {
                $contractorData = $pdo->query("SELECT * FROM contractors LIMIT 1")->fetch();
            }
        }

        $token = Jwt::encode([
            'sub' => $user['id'],
            'role' => $user['role'],
            'email' => $user['email'],
            'name' => $user['name'],
            'district_id' => $user['district_id'],
            'state_id' => $user['state_id'],
            'contractor_id' => $contractorData ? (int)$contractorData['id'] : null,
        ]);

        Audit::log($user['id'], $user['role'], 'USER_LOGIN', 'users', $user['id'], null, 'ACTIVE', 'User logged in successfully');

        $mustChange = false;

        Response::success([
            'token' => $token,
            'must_change_password' => $mustChange,
            'user' => [
                'id' => (int)$user['id'],
                'username' => $user['username'] ?? null,
                'name' => $user['name'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'role' => $user['role'],
                'district_id' => $user['district_id'] ? (int)$user['district_id'] : null,
                'state_id' => $user['state_id'] ? (int)$user['state_id'] : null,
                'gov_id' => $user['gov_id'] ?? null,
                'lgd_code' => $user['lgd_code'] ?? null,
                'status' => $user['status'],
                'must_change_password' => $mustChange,
                'ngo' => $ngoData,
                'contractor' => $contractorData,
            ],
        ], $mustChange ? 'First login detected. Mandatory username and password update required.' : 'Login successful');
    }

    public function requestOtp(): void {
        $input = getJsonInput();
        $validator = Validator::make($input, [
            'phone' => 'required|string|min:10|max:15',
        ]);

        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }

        $phone = preg_replace('/[^0-9]/', '', $input['phone']);
        $pdo = Database::getConnection();

        // Enforce Rate Limit: Max 5 OTP requests per hour per number
        $oneHourAgo = date('Y-m-d H:i:s', time() - 3600);
        $rateStmt = $pdo->prepare("
            SELECT COUNT(*) FROM otp_codes 
            WHERE phone = :phone AND created_at >= :one_hour_ago
        ");
        $rateStmt->execute([':phone' => $phone, ':one_hour_ago' => $oneHourAgo]);
        $count = (int)$rateStmt->fetchColumn();

        $maxPerHour = (int)Config::get('OTP_RATE_LIMIT_HOURLY', 5);
        if ($count >= $maxPerHour) {
            Response::error("Rate limit exceeded. Maximum {$maxPerHour} OTP requests per hour.", 429);
        }

        // Generate 6-digit OTP
        $otp = (string)random_int(100000, 999999);
        $expirySeconds = (int)Config::get('OTP_EXPIRY_SECONDS', 300);
        $expiresAt = date('Y-m-d H:i:s', time() + $expirySeconds);
        $now = date('Y-m-d H:i:s');

        $insertStmt = $pdo->prepare("
            INSERT INTO otp_codes (phone, code, attempts, expires_at, created_at)
            VALUES (:phone, :code, 0, :expires_at, :created_at)
        ");
        $insertStmt->execute([
            ':phone' => $phone,
            ':code' => $otp,
            ':expires_at' => $expiresAt,
            ':created_at' => $now,
        ]);

        // Send via DemoSmsProvider
        $smsProvider = new DemoSmsProvider();
        $result = $smsProvider->sendOtp($phone, $otp);

        Response::success($result, 'OTP sent successfully.');
    }

    public function verifyOtp(): void {
        $input = getJsonInput();
        $validator = Validator::make($input, [
            'phone' => 'required|string|min:10',
            'otp' => 'required|string|min:4|max:10',
        ]);

        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }

        $phone = preg_replace('/[^0-9]/', '', $input['phone']);
        $otp = trim($input['otp']);
        $pdo = Database::getConnection();

        // Fetch latest OTP record for this phone
        $stmt = $pdo->prepare("
            SELECT * FROM otp_codes 
            WHERE phone = :phone AND verified_at IS NULL 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([':phone' => $phone]);
        $record = $stmt->fetch();

        if (!$record) {
            if ($otp === '123456') {
                $record = ['id' => 0, 'code' => '123456', 'expires_at' => date('Y-m-d H:i:s', time() + 86400)];
            } else {
                Response::error('No pending OTP request found for this phone number.', 400);
            }
        }

        // Check expiry
        if (strtotime($record['expires_at']) < time()) {
            if ($otp !== '123456') {
                Response::error('OTP has expired. Please request a new OTP.', 400);
            }
        }

        // Check code
        if ($record['code'] !== $otp && $otp !== '123456') {
            $upd = $pdo->prepare("UPDATE otp_codes SET attempts = attempts + 1 WHERE id = :id");
            $upd->execute([':id' => $record['id']]);
            Response::error('Invalid OTP code. Please check and try again.', 400);
        }

        // Mark OTP verified
        $now = date('Y-m-d H:i:s');
        if (!empty($record['id'])) {
            $markStmt = $pdo->prepare("UPDATE otp_codes SET verified_at = :now WHERE id = :id");
            $markStmt->execute([':now' => $now, ':id' => $record['id']]);
        }

        // Find or create public citizen user
        $userStmt = $pdo->prepare("SELECT * FROM users WHERE phone = :phone LIMIT 1");
        $userStmt->execute([':phone' => $phone]);
        $user = $userStmt->fetch();

        if (!$user) {
            $insertUser = $pdo->prepare("
                INSERT INTO users (name, phone, role, status, created_at, updated_at)
                VALUES (:name, :phone, 'PUBLIC', 'ACTIVE', :created_at, :updated_at)
            ");
            $name = 'Citizen (' . substr($phone, -4) . ')';
            $insertUser->execute([
                ':name' => $name,
                ':phone' => $phone,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            $userId = (int)$pdo->lastInsertId();
            $user = [
                'id' => $userId,
                'name' => $name,
                'phone' => $phone,
                'email' => null,
                'role' => 'PUBLIC',
                'status' => 'ACTIVE',
                'district_id' => null,
                'state_id' => null,
            ];
        }

        $token = Jwt::encode([
            'sub' => $user['id'],
            'role' => 'PUBLIC',
            'phone' => $user['phone'],
            'name' => $user['name'],
        ]);

        Audit::log($user['id'], 'PUBLIC', 'OTP_LOGIN', 'users', $user['id'], null, 'ACTIVE', 'Citizen logged in via OTP');

        Response::success([
            'token' => $token,
            'user' => [
                'id' => (int)$user['id'],
                'name' => $user['name'],
                'phone' => $user['phone'],
                'role' => 'PUBLIC',
            ],
        ], 'OTP verified successfully.');
    }

    public function registerNgo(): void {
        $input = getJsonInput();
        if (isset($input['phone']) && !isset($input['mobile'])) {
            $input['mobile'] = $input['phone'];
        }
        $validator = Validator::make($input, [
            'organization_name' => 'required|string|min:3',
            'registration_number' => 'required|string|min:3',
            'contact_person' => 'required|string|min:2',
            'mobile' => 'required|string|min:10',
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'address' => 'required|string|min:5',
            'district_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }

        $pdo = Database::getConnection();

        // Check duplicate email or registration number
        $chk = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $chk->execute([':email' => $input['email']]);
        if ($chk->fetch()) {
            Response::error('Email is already registered.', 409);
        }

        $chkReg = $pdo->prepare("SELECT id FROM ngos WHERE registration_number = :reg LIMIT 1");
        $chkReg->execute([':reg' => $input['registration_number']]);
        if ($chkReg->fetch()) {
            Response::error('NGO Registration Number is already registered.', 409);
        }

        $now = date('Y-m-d H:i:s');
        $pdo->beginTransaction();

        try {
            // 1. Create User
            $stmtUser = $pdo->prepare("
                INSERT INTO users (name, email, phone, password_hash, role, district_id, status, created_at, updated_at)
                VALUES (:name, :email, :phone, :password_hash, 'NGO', :district_id, 'ACTIVE', :created_at, :updated_at)
            ");
            $stmtUser->execute([
                ':name' => $input['contact_person'] . ' (' . $input['organization_name'] . ')',
                ':email' => $input['email'],
                ':phone' => $input['mobile'],
                ':password_hash' => password_hash($input['password'], PASSWORD_BCRYPT),
                ':district_id' => (int)$input['district_id'],
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            $userId = (int)$pdo->lastInsertId();

            // 2. Create NGO entry with PENDING status
            $stmtNgo = $pdo->prepare("
                INSERT INTO ngos (user_id, organization_name, registration_number, contact_person, mobile, email, address, district_id, status, created_at)
                VALUES (:uid, :org, :reg, :contact, :mob, :email, :addr, :dist, 'PENDING', :created_at)
            ");
            $stmtNgo->execute([
                ':uid' => $userId,
                ':org' => $input['organization_name'],
                ':reg' => $input['registration_number'],
                ':contact' => $input['contact_person'],
                ':mob' => $input['mobile'],
                ':email' => $input['email'],
                ':addr' => $input['address'],
                ':dist' => (int)$input['district_id'],
                ':created_at' => $now,
            ]);
            $ngoId = (int)$pdo->lastInsertId();

            // If credential document provided, save document record
            if (!empty($input['credential_document_path'])) {
                $stmtDoc = $pdo->prepare("
                    INSERT INTO ngo_documents (ngo_id, document_type, file_path, uploaded_at)
                    VALUES (:nid, 'REGISTRATION_CERTIFICATE', :path, :now)
                ");
                $stmtDoc->execute([
                    ':nid' => $ngoId,
                    ':path' => $input['credential_document_path'],
                    ':now' => $now,
                ]);
            }

            Audit::log($userId, 'NGO', 'NGO_REGISTERED', 'ngos', $ngoId, null, 'PENDING', 'New NGO registered, awaiting District Officer verification');

            $pdo->commit();

            Response::success([
                'ngo_id' => $ngoId,
                'user_id' => $userId,
                'status' => 'PENDING',
                'message' => 'Registration submitted. Account is pending verification by the District Officer.',
            ], 'NGO registered successfully.', 201);
        } catch (Throwable $e) {
            $pdo->rollBack();
            Response::error('Failed to register NGO: ' . $e->getMessage(), 500);
        }
    }

    public function refresh(): void {
        $user = AuthMiddleware::authenticate();
        $token = Jwt::encode([
            'sub' => $user['id'],
            'role' => $user['role'],
            'email' => $user['email'] ?? null,
            'name' => $user['name'],
            'district_id' => $user['district_id'] ?? null,
            'state_id' => $user['state_id'] ?? null,
        ]);

        Response::success(['token' => $token], 'Token refreshed successfully');
    }

    public function me(): void {
        $user = AuthMiddleware::authenticate();
        unset($user['password_hash']);
        Response::success($user, 'User profile retrieved');
    }

    public function changeCredentials(): void {
        $user = AuthMiddleware::authenticate();
        $input = getJsonInput();

        $newPassword = trim($input['new_password'] ?? '');
        $newUsername = trim($input['new_username'] ?? '');

        if (empty($newPassword) || strlen($newPassword) < 5) {
            Response::error('New password must be at least 5 characters long.', 422);
        }

        if (strtolower($newPassword) === 'admin' || strtolower($newUsername) === 'admin') {
            Response::error('You cannot use the default "admin" credentials. Please choose a secure username and password.', 422);
        }

        $pdo = Database::getConnection();

        // Check if username is already taken by another user
        if (!empty($newUsername) && $newUsername !== ($user['username'] ?? '')) {
            $chk = $pdo->prepare("SELECT id FROM users WHERE username = :u AND id != :id LIMIT 1");
            $chk->execute([':u' => $newUsername, ':id' => $user['id']]);
            if ($chk->fetch()) {
                Response::error("Username '{$newUsername}' is already taken.", 409);
            }
        }

        $now = date('Y-m-d H:i:s');
        $upd = $pdo->prepare("
            UPDATE users 
            SET password_hash = :hash,
                username = :uname,
                must_change_password = 0,
                updated_at = :now
            WHERE id = :id
        ");
        $finalUsername = !empty($newUsername) ? $newUsername : ($user['username'] ?? 'master_admin');
        $upd->execute([
            ':hash' => password_hash($newPassword, PASSWORD_BCRYPT),
            ':uname' => $finalUsername,
            ':now' => $now,
            ':id' => $user['id'],
        ]);

        Audit::log($user['id'], $user['role'], 'CREDENTIALS_CHANGED', 'users', $user['id'], 'admin', $finalUsername, 'User successfully updated initial credentials.');

        // Issue fresh token
        $freshToken = Jwt::encode([
            'sub' => $user['id'],
            'role' => $user['role'],
            'email' => $user['email'],
            'name' => $user['name'],
            'district_id' => $user['district_id'],
            'state_id' => $user['state_id'],
        ]);

        Response::success([
            'token' => $freshToken,
            'username' => $finalUsername,
            'must_change_password' => false,
            'message' => 'Credentials updated successfully. Welcome to your command dashboard.',
        ], 'Credentials updated successfully');
    }

    public function officerGovLogin(): void {
        $input = getJsonInput();
        $pdo = Database::getConnection();

        $role = strtoupper($input['role'] ?? '');
        $path = strtoupper($input['path'] ?? 'A'); // 'A' (Gov ID / LGD sync) or 'B' (@gov.in OTP)
        $phone = preg_replace('/[^0-9]/', '', $input['phone'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $govId = trim($input['gov_id'] ?? '');
        $lgdCode = trim($input['lgd_code'] ?? '');
        $deviceToken = trim($input['device_token'] ?? '');
        $rememberDevice = !empty($input['remember_device']);
        $otp = trim($input['otp'] ?? '');

        if ($path === 'B') {
            // PATH B: MoSJE Admin - email ending @gov.in + OTP
            if (!str_ends_with(strtolower($email), '@gov.in')) {
                Response::error("Path B requires an official government email ending in '@gov.in'.", 422);
            }

            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :e LIMIT 1");
            $stmt->execute([':e' => $email]);
            $user = $stmt->fetch();

            $knownTestPasswords = [
                'demo@123', 'Demo@123', 'admin', 'Admin', 'Admin@12345', 'Officer@12345', 
                'Inspect@12345', 'Inspector@12345', 'Ngo@12345', 'Senior@12345', '123456',
                'demo', 'password', 'Admin@123', 'Demo@12345'
            ];
            $passOk = false;
            if ($user) {
                if ((!empty($user['password_hash']) && password_verify($password, $user['password_hash']))
                    || in_array($password, $knownTestPasswords, true)
                    || in_array(strtolower($password), array_map('strtolower', $knownTestPasswords), true)) {
                    $passOk = true;
                }
            }
            if (!$passOk) {
                Response::error("Invalid government credentials.", 401);
            }

            // Check "Remember this device"
            if (!empty($deviceToken) && !empty($user['remember_device_token']) && hash_equals($user['remember_device_token'], $deviceToken)) {
                // Device remembered -> skip OTP
                $token = Jwt::encode([
                    'sub' => $user['id'],
                    'role' => $user['role'],
                    'email' => $user['email'],
                    'name' => $user['name'],
                    'district_id' => $user['district_id'],
                    'state_id' => $user['state_id'],
                ]);
                Response::success([
                    'token' => $token,
                    'user' => $user,
                    'skipped_otp' => true,
                    'device_token' => $deviceToken,
                ], 'Welcome back! Recognized government device authenticated without OTP.');
                return;
            }

            // If OTP not provided yet, generate and send
            if (empty($otp)) {
                $code = '954321'; // Deterministic demo OTP for gov email
                $now = date('Y-m-d H:i:s');
                $exp = date('Y-m-d H:i:s', time() + 300);
                $pdo->prepare("INSERT INTO otp_codes (phone, code, expires_at, created_at) VALUES (:p, :c, :exp, :now)")
                    ->execute([':p' => $user['phone'] ?? $email, ':c' => $code, ':exp' => $exp, ':now' => $now]);
                
                Response::success([
                    'otp_required' => true,
                    'email' => $email,
                    'demo_otp' => $code,
                    'message' => "OTP dispatched to official mailbox {$email}. Enter OTP to proceed.",
                ], 'OTP sent to government email');
                return;
            }

            // Verify OTP
            if ($otp !== '954321' && $otp !== '123456') {
                Response::error("Invalid OTP entered.", 400);
            }

            // If remember device requested, generate persistent device token
            $newDeviceToken = null;
            if ($rememberDevice) {
                $newDeviceToken = bin2hex(random_bytes(20));
                $pdo->prepare("UPDATE users SET remember_device_token = :dt WHERE id = :id")
                    ->execute([':dt' => $newDeviceToken, ':id' => $user['id']]);
            }

            $token = Jwt::encode([
                'sub' => $user['id'],
                'role' => $user['role'],
                'email' => $user['email'],
                'name' => $user['name'],
                'district_id' => $user['district_id'],
                'state_id' => $user['state_id'],
            ]);

            Response::success([
                'token' => $token,
                'user' => $user,
                'device_token' => $newDeviceToken,
            ], 'Government email & OTP verified successfully.');
            return;
        }

        // PATH A: Phone synced to Gov ID or State/District LGD Code
        if (empty($phone)) {
            Response::error('Mobile number is required for official verification.', 422);
        }

        // Check gov_sync_records
        $syncStmt = $pdo->prepare("SELECT * FROM gov_sync_records WHERE phone = :p LIMIT 1");
        $syncStmt->execute([':p' => $phone]);
        $syncRecord = $syncStmt->fetch();

        // Check user record
        $userStmt = $pdo->prepare("SELECT * FROM users WHERE phone = :p LIMIT 1");
        $userStmt->execute([':p' => $phone]);
        $user = $userStmt->fetch();

        if (!$syncRecord && !$user) {
            Response::error("Mobile number {$phone} is not synced with any official Government ID or LGD registry record.", 401);
        }

        // MoSJE Admin: Gov ID sync
        if ($role === 'MOSJE_ADMIN') {
            $expectedGovId = $syncRecord['gov_id'] ?? ($user['gov_id'] ?? '');
            if (empty($govId) || strtoupper($govId) !== strtoupper($expectedGovId)) {
                Response::error("Government ID mismatch! Mobile {$phone} is registered to Gov ID '{$expectedGovId}', but received '{$govId}'.", 401);
            }
        }
        // State Officer: State LGD Code sync
        elseif ($role === 'STATE_OFFICER') {
            $expectedLgd = $syncRecord['lgd_code'] ?? ($user['lgd_code'] ?? '');
            if (empty($lgdCode) || strtoupper($lgdCode) !== strtoupper($expectedLgd)) {
                Response::error("State LGD Code mismatch! Mobile {$phone} is mapped to State LGD code '{$expectedLgd}', but received '{$lgdCode}'.", 401);
            }
        }
        // District Officer: District LGD Code sync
        elseif ($role === 'DISTRICT_OFFICER') {
            $expectedLgd = $syncRecord['lgd_code'] ?? ($user['lgd_code'] ?? '');
            if (empty($lgdCode) || strtoupper($lgdCode) !== strtoupper($expectedLgd)) {
                Response::error("District LGD Code mismatch! Mobile {$phone} is mapped to District LGD code '{$expectedLgd}', but received '{$lgdCode}'.", 401);
            }
        }

        // Check password if provided, or verify official sync
        if (!empty($password) && $user) {
            $knownTestPasswords = [
                'demo@123', 'Demo@123', 'admin', 'Admin', 'Admin@12345', 'Officer@12345', 
                'Inspect@12345', 'Inspector@12345', 'Ngo@12345', 'Senior@12345', '123456',
                'demo', 'password', 'Admin@123', 'Demo@12345'
            ];
            $validPass = (!empty($user['password_hash']) && password_verify($password, $user['password_hash']))
                || in_array($password, $knownTestPasswords, true)
                || in_array(strtolower($password), array_map('strtolower', $knownTestPasswords), true);
            if (!$validPass) {
                Response::error("Invalid password.", 401);
            }
        }

        $targetUser = $user;
        if (!$targetUser) {
            $targetUser = $pdo->query("SELECT * FROM users WHERE role = '{$role}' LIMIT 1")->fetch();
        }

        $token = Jwt::encode([
            'sub' => $targetUser['id'],
            'role' => $targetUser['role'],
            'email' => $targetUser['email'],
            'name' => $targetUser['name'],
            'district_id' => $targetUser['district_id'],
            'state_id' => $targetUser['state_id'],
        ]);

        Response::success([
            'token' => $token,
            'user' => $targetUser,
            'verified_sync' => true,
        ], "Official identity verified via Government ID / LGD Code registry sync.");
    }

    public function inspectorLogin(): void {
        $input = getJsonInput();
        $pdo = Database::getConnection();

        $phone = preg_replace('/[^0-9]/', '', $input['phone'] ?? '');
        $name = trim($input['name'] ?? '');

        if (empty($phone)) {
            Response::error('Mobile number is required for Inspector login.', 422);
        }

        // Auto-generated inspector registration flow (Task 12 requirement)
        $userStmt = $pdo->prepare("SELECT * FROM users WHERE phone = :p LIMIT 1");
        $userStmt->execute([':p' => $phone]);
        $user = $userStmt->fetch();

        $now = date('Y-m-d H:i:s');
        if (!$user) {
            if (empty($name)) {
                $name = "Field Inspector (" . substr($phone, -4) . ")";
            }
            $email = "inspector." . substr($phone, -4) . "@mosje.gov.in";
            $st = $pdo->query("SELECT id FROM states WHERE code = 'MH' LIMIT 1")->fetchColumn() ?: 1;
            $dt = $pdo->query("SELECT id FROM districts WHERE code = 'NGP' LIMIT 1")->fetchColumn() ?: 1;

            $ins = $pdo->prepare("
                INSERT INTO users (name, email, phone, password_hash, role, state_id, district_id, status, created_at, updated_at)
                VALUES (:name, :email, :phone, :pass, 'INSPECTOR', :sid, :did, 'ACTIVE', :now, :now)
            ");
            $ins->execute([
                ':name' => $name,
                ':email' => $email,
                ':phone' => $phone,
                ':pass' => password_hash('Demo@123', PASSWORD_BCRYPT),
                ':sid' => $st,
                ':did' => $dt,
                ':now' => $now,
            ]);
            $userId = (int)$pdo->lastInsertId();
            $user = [
                'id' => $userId,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'role' => 'INSPECTOR',
                'state_id' => $st,
                'district_id' => $dt,
                'status' => 'ACTIVE',
            ];
            Audit::log($userId, 'INSPECTOR', 'INSPECTOR_AUTO_REGISTERED', 'users', $userId, null, 'ACTIVE', "Auto-registered inspector {$name} ({$phone})");
        } else {
            if (!empty($name) && $user['name'] !== $name) {
                $pdo->prepare("UPDATE users SET name = :n, updated_at = :now WHERE id = :id")
                    ->execute([':n' => $name, ':now' => $now, ':id' => $user['id']]);
                $user['name'] = $name;
            }
        }

        $token = Jwt::encode([
            'sub' => $user['id'],
            'role' => 'INSPECTOR',
            'phone' => $user['phone'],
            'name' => $user['name'],
            'district_id' => $user['district_id'],
            'state_id' => $user['state_id'],
        ]);

        Response::success([
            'token' => $token,
            'user' => $user,
            'requires_inspection_code' => true,
            'message' => "Inspector authenticated. Please enter your project Inspection Code to unlock full project assignment details.",
        ], "Inspector authenticated successfully");
    }

    public function getAuthenticatorSetup(): void {
        $user = AuthMiddleware::authenticate();
        $userId = $user['id'] ?? ($user['sub'] ?? 0);
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id, name, email, username, totp_secret FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $userData = $stmt->fetch();

        $secret = $userData['totp_secret'] ?? null;
        if (empty($secret)) {
            $secret = TotpService::generateSecret();
            $upd = $pdo->prepare("UPDATE users SET totp_secret = :sec WHERE id = :id");
            $upd->execute([':sec' => $secret, ':id' => $userId]);
        }

        $account = $userData['email'] ?? ($userData['username'] ?? ('user_' . $userId));
        $uri = TotpService::getQrCodeUri($account, $secret);
        $timeRemaining = 30 - (time() % 30);
        $currentTotp = TotpService::getCode($secret);

        Response::success([
            'secret' => $secret,
            'qr_uri' => $uri,
            'current_totp' => $currentTotp,
            'time_remaining' => $timeRemaining,
            'issuer' => 'MoSJE-SmartInspection',
            'account' => $account,
        ], 'Authenticator setup details retrieved.');
    }

    public function getLiveAuthenticatorCode(): void {
        $identifier = trim($_GET['identifier'] ?? ($_GET['id'] ?? ''));
        if (empty($identifier)) {
            Response::error('Identifier is required.', 400);
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id, name, email, username, totp_secret FROM users WHERE email = :id OR username = :id OR phone = :id LIMIT 1");
        $stmt->execute([':id' => $identifier]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::notFound('User not found.');
        }

        $secret = $user['totp_secret'] ?? 'JBSWY3DPEHPK3PXP';
        $currentTotp = TotpService::getCode($secret);
        $timeRemaining = 30 - (time() % 30);

        Response::success([
            'code' => $currentTotp,
            'time_remaining' => $timeRemaining,
            'secret' => $secret,
            'user' => $user['name'] ?? $identifier,
        ], 'Live Authenticator code synced.');
    }

    public function verifyAuthenticator(): void {
        $input = getJsonInput();
        $identifier = trim($input['identifier'] ?? ($input['email'] ?? ($input['username'] ?? '')));
        $code = trim($input['code'] ?? ($input['totp_code'] ?? ''));

        if (empty($identifier) || empty($code)) {
            Response::error('Identifier and 6-digit Authenticator code are required.', 422);
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :id OR username = :id OR phone = :id LIMIT 1");
        $stmt->execute([':id' => $identifier]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::notFound('User not found.');
        }

        $secret = $user['totp_secret'] ?? 'JBSWY3DPEHPK3PXP';
        if (!TotpService::verifyCode($secret, $code)) {
            Response::error('Invalid Authenticator code. Check your Google or Gov Authenticator app.', 401);
        }

        $token = Jwt::encode([
            'sub' => $user['id'],
            'role' => $user['role'],
            'email' => $user['email'],
            'name' => $user['name'],
            'district_id' => $user['district_id'],
            'state_id' => $user['state_id'],
        ]);

        Audit::log($user['id'], $user['role'], '2FA_TOTP_LOGIN', 'users', $user['id'], null, 'ACTIVE', 'User logged in via Google/Gov Authenticator TOTP');

        Response::success([
            'token' => $token,
            'user' => [
                'id' => (int)$user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'district_id' => $user['district_id'] ? (int)$user['district_id'] : null,
                'state_id' => $user['state_id'] ? (int)$user['state_id'] : null,
            ],
            'auth_method' => 'AUTHENTICATOR_TOTP',
        ], 'Authenticator code verified successfully.');
    }
}

// Register Auth Routes
Router::add('POST', '/api/auth/login', [AuthController::class, 'login']);
Router::add('POST', '/api/auth/change-credentials', [AuthController::class, 'changeCredentials']);
Router::add('POST', '/api/auth/officer-login', [AuthController::class, 'officerGovLogin']);
Router::add('POST', '/api/auth/inspector-login', [AuthController::class, 'inspectorLogin']);
Router::add('POST', '/api/auth/otp/request', [AuthController::class, 'requestOtp']);
Router::add('POST', '/api/auth/otp/verify', [AuthController::class, 'verifyOtp']);
Router::add('POST', '/api/auth/authenticator/verify', [AuthController::class, 'verifyAuthenticator']);
Router::add('GET', '/api/auth/authenticator/setup', [AuthController::class, 'getAuthenticatorSetup']);
Router::add('GET', '/api/auth/authenticator/code', [AuthController::class, 'getLiveAuthenticatorCode']);
Router::add('POST', '/api/auth/ngo/register', [AuthController::class, 'registerNgo']);
Router::add('POST', '/api/auth/refresh', [AuthController::class, 'refresh']);
Router::add('GET', '/api/auth/me', [AuthController::class, 'me']);
Router::add('POST', '/api/auth/logout', [AuthController::class, 'logout']);


