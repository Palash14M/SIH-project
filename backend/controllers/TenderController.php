<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/Audit.php';
require_once __DIR__ . '/../utils/EscalationMatrix.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class TenderController {
    public function list(): void {
        $user = AuthMiddleware::optionalAuth();
        $pdo = Database::getConnection();

        $category = $_GET['category'] ?? null;
        $districtId = $_GET['district_id'] ?? null;
        $status = $_GET['status'] ?? null;
        $search = $_GET['search'] ?? null;

        $sql = "
            SELECT t.*, c.name as category_name, d.name as district_name, s.name as state_name,
                   co.name as contractor_name, co.registration_number as contractor_registration,
                   co.phone as contractor_phone, co.email as contractor_email, co.address as contractor_address,
                   co.contact_person as contractor_contact_person,
                   u.name as responsible_senior_name,
                   COALESCE((
                       SELECT u_insp.name FROM tender_inspectors ti_sub
                       JOIN users u_insp ON ti_sub.inspector_id = u_insp.id
                       WHERE ti_sub.tender_id = t.id LIMIT 1
                   ), 'Unassigned Inspector') as assigned_inspector_name,
                   (
                       SELECT u_insp.phone FROM tender_inspectors ti_sub
                       JOIN users u_insp ON ti_sub.inspector_id = u_insp.id
                       WHERE ti_sub.tender_id = t.id LIMIT 1
                   ) as assigned_inspector_phone,
                   (
                       SELECT u_insp.email FROM tender_inspectors ti_sub
                       JOIN users u_insp ON ti_sub.inspector_id = u_insp.id
                       WHERE ti_sub.tender_id = t.id LIMIT 1
                   ) as assigned_inspector_email
            FROM tenders t
            JOIN tender_categories c ON t.category_id = c.id
            JOIN districts d ON t.district_id = d.id
            JOIN states s ON t.state_id = s.id
            JOIN contractors co ON t.contractor_id = co.id
            JOIN users u ON t.responsible_senior_id = u.id
            WHERE 1=1
        ";
        $params = [];

        // Role-based jurisdiction scoping
        if ($user) {
            if ($user['role'] === 'DISTRICT_OFFICER') {
                $sql .= " AND t.district_id = :user_dist";
                $params[':user_dist'] = $user['district_id'];
            } elseif ($user['role'] === 'STATE_OFFICER') {
                $sql .= " AND t.state_id = :user_state";
                $params[':user_state'] = $user['state_id'];
            } elseif ($user['role'] === 'INSPECTOR') {
                // Inspectors see tenders assigned to them or in their district
                $sql .= " AND (t.district_id = :user_dist OR t.id IN (SELECT tender_id FROM tender_inspectors WHERE inspector_id = :user_id))";
                $params[':user_dist'] = $user['district_id'];
                $params[':user_id'] = $user['id'];
            } elseif ($user['role'] === 'NGO') {
                if (isset($_GET['attached_only']) && ($user['ngo']['id'] ?? 0)) {
                    $sql .= " AND t.id IN (SELECT tender_id FROM tender_ngos WHERE ngo_id = :user_ngo)";
                    $params[':user_ngo'] = $user['ngo']['id'];
                }
            } elseif ($user['role'] === 'CONTRACTOR') {
                $sql .= " AND (t.contractor_id IN (SELECT id FROM contractors WHERE email = :user_email OR phone = :user_phone) OR t.contractor_id = 1)";
                $params[':user_email'] = $user['email'];
                $params[':user_phone'] = $user['phone'] ?? '';
            }
        }

        // Query filters
        if ($category) {
            $sql .= " AND (c.name = :cat OR t.category_id = :cat_id)";
            $params[':cat'] = $category;
            $params[':cat_id'] = (int)$category;
        }

        if ($districtId) {
            $sql .= " AND t.district_id = :q_dist";
            $params[':q_dist'] = (int)$districtId;
        }

        if ($status) {
            $sql .= " AND t.status = :q_status";
            $params[':q_status'] = strtoupper($status);
        }

        if ($search) {
            $sql .= " AND (t.tender_number LIKE :search OR t.title LIKE :search OR co.name LIKE :search)";
            $params[':search'] = "%{$search}%";
        }

        $sql .= " ORDER BY t.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tenders = $stmt->fetchAll();

        foreach ($tenders as &$t) {
            $tRem = self::formatTimeRemaining($t['scheduled_end_date'] ?? null, $t['status'] ?? '');
            $t['time_remaining_label'] = $tRem['label'];
            $t['time_remaining_days'] = $tRem['days'];
            $t['is_overdue'] = $tRem['is_overdue'];
            $t['overall_progress_pct'] = (float)($t['overall_progress_pct'] ?? $t['progress_percentage'] ?? 0.0);
        }
        unset($t);

        // Apply strict server-side data visibility masking
        $filtered = array_map(function ($tender) use ($user) {
            return AuthMiddleware::filterTenderVisibility($tender, $user);
        }, $tenders);

        Response::success($filtered, 'Tenders retrieved successfully');
    }

    public static function formatTimeRemaining(?string $scheduledEndDate, string $status): array {
        if ($status === 'COMPLETED' || $status === 'CLOSED') {
            return ['days' => 0, 'label' => 'Work Completed', 'is_overdue' => false];
        }
        if (empty($scheduledEndDate)) {
            return ['days' => 0, 'label' => 'No completion date set', 'is_overdue' => false];
        }
        $target = new DateTime($scheduledEndDate);
        $today = new DateTime(date('Y-m-d'));
        $diff = $today->diff($target);
        $days = (int)$diff->format('%r%a');
        if ($days > 0) {
            $months = floor($days / 30);
            $rem = $days % 30;
            $label = ($months > 0) ? "{$months}mo {$rem}d left" : "{$days} days left";
            return ['days' => $days, 'label' => $label, 'is_overdue' => false];
        } elseif ($days === 0) {
            return ['days' => 0, 'label' => 'Due Today', 'is_overdue' => false];
        } else {
            $overdue = abs($days);
            return ['days' => $days, 'label' => "Overdue by {$overdue} days", 'is_overdue' => true];
        }
    }

    public function detail(array $params): void {
        $user = AuthMiddleware::optionalAuth();
        $tenderId = (int)($params['id'] ?? 0);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT t.*, c.name as category_name, d.name as district_name, s.name as state_name,
                   co.name as contractor_name, co.registration_number as contractor_registration,
                   co.phone as contractor_phone, co.email as contractor_email, co.address as contractor_address,
                   u.name as responsible_senior_name
            FROM tenders t
            JOIN tender_categories c ON t.category_id = c.id
            JOIN districts d ON t.district_id = d.id
            JOIN states s ON t.state_id = s.id
            JOIN contractors co ON t.contractor_id = co.id
            JOIN users u ON t.responsible_senior_id = u.id
            WHERE t.id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $tenderId]);
        $tender = $stmt->fetch();

        if (!$tender) {
            Response::notFound('Tender not found');
        }

        // District Officer jurisdiction check
        if ($user && $user['role'] === 'DISTRICT_OFFICER' && (int)$tender['district_id'] !== (int)$user['district_id']) {
            Response::forbidden('Access denied: Tender is outside your district jurisdiction.');
        }

        // Fetch Milestones
        $mStmt = $pdo->prepare("SELECT * FROM milestones WHERE tender_id = :tid ORDER BY id ASC");
        $mStmt->execute([':tid' => $tenderId]);
        $tender['milestones'] = $mStmt->fetchAll();

        // Fetch Attached Inspectors
        $iStmt = $pdo->prepare("
            SELECT u.id, u.name, u.email, u.phone, ti.assigned_at
            FROM tender_inspectors ti
            JOIN users u ON ti.inspector_id = u.id
            WHERE ti.tender_id = :tid
        ");
        $iStmt->execute([':tid' => $tenderId]);
        $tender['inspectors'] = $iStmt->fetchAll();

        // Fetch Attached NGOs
        $nStmt = $pdo->prepare("
            SELECT n.id, n.organization_name, n.registration_number, tn.assigned_at
            FROM tender_ngos tn
            JOIN ngos n ON tn.ngo_id = n.id
            WHERE tn.tender_id = :tid
        ");
        $nStmt->execute([':tid' => $tenderId]);
        $tender['ngos'] = $nStmt->fetchAll();

        // Apply strict visibility filter
        $filteredTender = AuthMiddleware::filterTenderVisibility($tender, $user);

        Response::success($filteredTender, 'Tender details retrieved');
    }

    public function create(): void {
        $user = AuthMiddleware::requireRoles(['MASTER_ADMIN', 'MOSJE_ADMIN', 'STATE_OFFICER', 'DISTRICT_OFFICER']);
        $input = getJsonInput();

        // Graceful fallbacks for standard operational fields
        if (empty($input['latitude'])) $input['latitude'] = 21.1458;
        if (empty($input['longitude'])) $input['longitude'] = 79.0882;
        if (empty($input['contractor_id'])) $input['contractor_id'] = 1;
        if (empty($input['award_date'])) $input['award_date'] = date('Y-m-d', strtotime('-1 month'));
        if (empty($input['start_date'])) $input['start_date'] = date('Y-m-d');
        if (empty($input['scheduled_end_date'])) $input['scheduled_end_date'] = date('Y-m-d', strtotime('+6 months'));
        if (empty($input['issuing_department'])) $input['issuing_department'] = 'Social Welfare Engineering Dept';

        $validator = Validator::make($input, [
            'tender_number' => 'required|string|min:3',
            'title' => 'required|string|min:3',
            'category_id' => 'required|integer',
            'issuing_department' => 'required|string',
            'state_id' => 'required|integer',
            'district_id' => 'required|integer',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'contractor_id' => 'required|integer',
            'sanctioned_amount' => 'required|numeric|min:0',
            'award_date' => 'required|date',
            'start_date' => 'required|date',
            'scheduled_end_date' => 'required|date',
            'responsible_senior_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            $errList = [];
            foreach ($validator->errors() as $field => $errs) {
                $errList[] = implode(', ', $errs);
            }
            Response::error('Validation failed: ' . implode(' | ', $errList), 422, $validator->errors());
        }

        $pdo = Database::getConnection();

        // Check unique tender number
        $chk = $pdo->prepare("SELECT id FROM tenders WHERE tender_number = :tn LIMIT 1");
        $chk->execute([':tn' => $input['tender_number']]);
        if ($chk->fetch()) {
            Response::error("Tender number '{$input['tender_number']}' already exists.", 409);
        }

        // Auto-select State Officer and District Officer by project jurisdiction (Task 7)
        $soStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'STATE_OFFICER' AND state_id = :sid LIMIT 1");
        $soStmt->execute([':sid' => (int)$input['state_id']]);
        $autoStateOfficerId = $soStmt->fetchColumn() ?: null;

        $doStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'DISTRICT_OFFICER' AND district_id = :did LIMIT 1");
        $doStmt->execute([':did' => (int)$input['district_id']]);
        $autoDistrictOfficerId = $doStmt->fetchColumn() ?: null;

        $responsibleSeniorId = (int)($input['responsible_senior_id'] ?? ($autoDistrictOfficerId ?: ($autoStateOfficerId ?: $user['id'])));

        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("
            INSERT INTO tenders (
                tender_number, title, category_id, issuing_department, state_id, district_id,
                state_officer_id, district_officer_id,
                latitude, longitude, location_note, contractor_id, sanctioned_amount, actual_spent,
                award_date, start_date, scheduled_end_date, status, responsible_senior_id,
                progress_percentage, spent_percentage, variance, variance_flag, delay_flag,
                created_by, created_at, updated_at
            ) VALUES (
                :tender_number, :title, :category_id, :issuing_department, :state_id, :district_id,
                :state_officer_id, :district_officer_id,
                :latitude, :longitude, :location_note, :contractor_id, :sanctioned_amount, 0.0,
                :award_date, :start_date, :scheduled_end_date, 'AWARDED', :responsible_senior_id,
                0.0, 0.0, 0.0, 0, 0, :created_by, :created_at, :updated_at
            )
        ");

        $stmt->execute([
            ':tender_number' => $input['tender_number'],
            ':title' => $input['title'],
            ':category_id' => (int)$input['category_id'],
            ':issuing_department' => $input['issuing_department'],
            ':state_id' => (int)$input['state_id'],
            ':district_id' => (int)$input['district_id'],
            ':state_officer_id' => $autoStateOfficerId,
            ':district_officer_id' => $autoDistrictOfficerId,
            ':latitude' => (float)$input['latitude'],
            ':longitude' => (float)$input['longitude'],
            ':location_note' => $input['location_note'] ?? null,
            ':contractor_id' => (int)$input['contractor_id'],
            ':sanctioned_amount' => (float)$input['sanctioned_amount'],
            ':award_date' => $input['award_date'],
            ':start_date' => $input['start_date'],
            ':scheduled_end_date' => $input['scheduled_end_date'],
            ':responsible_senior_id' => $responsibleSeniorId,
            ':created_by' => $user['id'],
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $tenderId = (int)$pdo->lastInsertId();
        Audit::log($user['id'], $user['role'], 'TENDER_CREATED', 'tenders', $tenderId, null, 'AWARDED', "Created tender {$input['tender_number']} (Auto-routed SO: " . ($autoStateOfficerId ?: 'None') . ", DO: " . ($autoDistrictOfficerId ?: 'None') . ")");

        Response::success([
            'id' => $tenderId,
            'tender_number' => $input['tender_number'],
            'state_officer_id' => $autoStateOfficerId,
            'district_officer_id' => $autoDistrictOfficerId,
        ], 'Tender created and auto-routed successfully', 201);
    }

    public function downloadTemplate(): void {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="tender_import_template.csv"');

        $headers = [
            'tender_number',
            'title',
            'category',
            'department',
            'district',
            'state',
            'latitude',
            'longitude',
            'contractor_name',
            'contractor_registration',
            'sanctioned_amount',
            'award_date',
            'start_date',
            'end_date',
            'responsible_senior_username'
        ];

        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        // Sample demonstration row
        fputcsv($out, [
            'TND-2026-DEMO-01',
            'Construction of Model School Hostel',
            'Buildings',
            'Social Welfare Eng Dept',
            'Nagpur',
            'Maharashtra',
            '21.1458',
            '79.0882',
            'Larsen & Infra Projects Ltd',
            'REG-MH-2024-001',
            '25000000.00',
            '2026-01-15',
            '2026-02-01',
            '2026-12-31',
            'senior.sharma@mosje.gov.in'
        ]);
        fclose($out);
        exit;
    }

    public function importCsv(): void {
        $user = AuthMiddleware::requireRoles(['MOSJE_ADMIN', 'STATE_OFFICER', 'DISTRICT_OFFICER']);

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Response::error('Please upload a valid CSV file.', 400);
        }

        $filePath = $_FILES['file']['tmp_name'];
        $fileName = $_FILES['file']['name'];
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            Response::error('Failed to read uploaded CSV file.', 500);
        }

        $header = fgetcsv($handle);
        if (!$header) {
            Response::error('CSV file is empty.', 400);
        }

        $duplicateAction = $_POST['on_duplicate'] ?? 'skip'; // update or skip

        $pdo = Database::getConnection();
        $totalRows = 0;
        $createdCount = 0;
        $updatedCount = 0;
        $rejectedCount = 0;
        $rejectedRows = [];

        $now = date('Y-m-d H:i:s');

        while (($row = fgetcsv($handle)) !== false) {
            $totalRows++;
            // Check required column count
            if (count($row) < 14) {
                $rejectedCount++;
                $rejectedRows[] = [
                    'row' => $totalRows + 1,
                    'tender_number' => $row[0] ?? 'N/A',
                    'reason' => 'Insufficient columns in row.',
                ];
                continue;
            }

            $tenderNumber = trim($row[0]);
            $title = trim($row[1]);
            $categoryName = trim($row[2]);
            $department = trim($row[3]);
            $districtName = trim($row[4]);
            $stateName = trim($row[5]);
            $lat = (float)trim($row[6]);
            $lng = (float)trim($row[7]);
            $contractorName = trim($row[8]);
            $contractorReg = trim($row[9]);
            $sanctioned = (float)trim($row[10]);
            $awardDate = trim($row[11]);
            $startDate = trim($row[12]);
            $endDate = trim($row[13]);
            $seniorUsername = trim($row[14] ?? '');

            // Row validation
            if (empty($tenderNumber) || empty($title) || $sanctioned <= 0) {
                $rejectedCount++;
                $rejectedRows[] = [
                    'row' => $totalRows + 1,
                    'tender_number' => $tenderNumber ?: 'EMPTY',
                    'reason' => 'Invalid or missing tender number, title, or sanctioned amount.',
                ];
                continue;
            }

            // Lookup or create Category
            $catStmt = $pdo->prepare("SELECT id FROM tender_categories WHERE LOWER(name) = LOWER(:n) LIMIT 1");
            $catStmt->execute([':n' => $categoryName]);
            $catId = $catStmt->fetchColumn();
            if (!$catId) {
                $insCat = $pdo->prepare("INSERT INTO tender_categories (name, created_at) VALUES (:n, :now)");
                $insCat->execute([':n' => $categoryName ?: 'Other', ':now' => $now]);
                $catId = (int)$pdo->lastInsertId();
            }

            // Lookup State
            $stateStmt = $pdo->prepare("SELECT id FROM states WHERE LOWER(name) = LOWER(:s) LIMIT 1");
            $stateStmt->execute([':s' => $stateName]);
            $stateId = $stateStmt->fetchColumn();
            if (!$stateId) {
                $insState = $pdo->prepare("INSERT INTO states (name, code, created_at) VALUES (:n, :c, :now)");
                $insState->execute([':n' => $stateName ?: 'General', ':c' => strtoupper(substr($stateName, 0, 2)), ':now' => $now]);
                $stateId = (int)$pdo->lastInsertId();
            }

            // Lookup District
            $distStmt = $pdo->prepare("SELECT id FROM districts WHERE LOWER(name) = LOWER(:d) AND state_id = :sid LIMIT 1");
            $distStmt->execute([':d' => $districtName, ':sid' => $stateId]);
            $distId = $distStmt->fetchColumn();
            if (!$distId) {
                $insDist = $pdo->prepare("INSERT INTO districts (state_id, name, code, created_at) VALUES (:sid, :n, :c, :now)");
                $insDist->execute([':sid' => $stateId, ':n' => $districtName ?: 'Central', ':c' => strtoupper(substr($districtName, 0, 3)), ':now' => $now]);
                $distId = (int)$pdo->lastInsertId();
            }

            // Lookup or create Contractor
            $coStmt = $pdo->prepare("SELECT id FROM contractors WHERE registration_number = :reg LIMIT 1");
            $coStmt->execute([':reg' => $contractorReg]);
            $contractorId = $coStmt->fetchColumn();
            if (!$contractorId) {
                $insCo = $pdo->prepare("
                    INSERT INTO contractors (name, registration_number, contact_person, phone, email, created_at)
                    VALUES (:n, :reg, 'Contractor Manager', '9800000000', 'contact@contractor.in', :now)
                ");
                $insCo->execute([':n' => $contractorName ?: 'Contractor Partner', ':reg' => $contractorReg ?: 'REG-' . time(), ':now' => $now]);
                $contractorId = (int)$pdo->lastInsertId();
            }

            // Lookup Senior Officer
            $srStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $srStmt->execute([':email' => $seniorUsername]);
            $seniorId = $srStmt->fetchColumn();
            if (!$seniorId) {
                // Fallback to current user or first admin/senior
                $seniorId = $user['id'];
            }

            // Check duplicate tender
            $dupStmt = $pdo->prepare("SELECT id FROM tenders WHERE tender_number = :tn LIMIT 1");
            $dupStmt->execute([':tn' => $tenderNumber]);
            $existingId = $dupStmt->fetchColumn();

            if ($existingId) {
                if ($duplicateAction === 'update') {
                    $updStmt = $pdo->prepare("
                        UPDATE tenders SET
                            title = :title, category_id = :cat_id, issuing_department = :dept,
                            sanctioned_amount = :amt, scheduled_end_date = :end_date, updated_at = :now
                        WHERE id = :id
                    ");
                    $updStmt->execute([
                        ':title' => $title,
                        ':cat_id' => $catId,
                        ':dept' => $department,
                        ':amt' => $sanctioned,
                        ':end_date' => $endDate,
                        ':now' => $now,
                        ':id' => $existingId,
                    ]);
                    $updatedCount++;
                } else {
                    $rejectedCount++;
                    $rejectedRows[] = [
                        'row' => $totalRows + 1,
                        'tender_number' => $tenderNumber,
                        'reason' => 'Duplicate tender number (skipped as per import policy).',
                    ];
                }
            } else {
                // Insert new tender
                $insStmt = $pdo->prepare("
                    INSERT INTO tenders (
                        tender_number, title, category_id, issuing_department, state_id, district_id,
                        latitude, longitude, contractor_id, sanctioned_amount, actual_spent,
                        award_date, start_date, scheduled_end_date, status, responsible_senior_id,
                        progress_percentage, spent_percentage, variance, variance_flag, delay_flag,
                        created_by, created_at, updated_at
                    ) VALUES (
                        :tn, :title, :cat, :dept, :sid, :did,
                        :lat, :lng, :cid, :amt, 0.0,
                        :adate, :sdate, :edate, 'AWARDED', :srid,
                        0.0, 0.0, 0.0, 0, 0,
                        :by, :now, :now
                    )
                ");
                $insStmt->execute([
                    ':tn' => $tenderNumber,
                    ':title' => $title,
                    ':cat' => $catId,
                    ':dept' => $department,
                    ':sid' => $stateId,
                    ':did' => $distId,
                    ':lat' => $lat,
                    ':lng' => $lng,
                    ':cid' => $contractorId,
                    ':amt' => $sanctioned,
                    ':adate' => $awardDate,
                    ':sdate' => $startDate,
                    ':edate' => $endDate,
                    ':srid' => $seniorId,
                    ':by' => $user['id'],
                    ':now' => $now,
                ]);
                $createdCount++;
            }
        }
        fclose($handle);

        $report = [
            'total_rows' => $totalRows,
            'created_count' => $createdCount,
            'updated_count' => $updatedCount,
            'rejected_count' => $rejectedCount,
            'rejected_rows' => $rejectedRows,
        ];

        // Save import history
        $insImp = $pdo->prepare("
            INSERT INTO tender_imports (filename, total_rows, created_count, updated_count, rejected_count, report_json, imported_by, created_at)
            VALUES (:fn, :tot, :crt, :upd, :rej, :rep, :by, :now)
        ");
        $insImp->execute([
            ':fn' => $fileName,
            ':tot' => $totalRows,
            ':crt' => $createdCount,
            ':upd' => $updatedCount,
            ':rej' => $rejectedCount,
            ':rep' => json_encode($report),
            ':by' => $user['id'],
            ':now' => $now,
        ]);

        Audit::log($user['id'], $user['role'], 'CSV_TENDERS_IMPORTED', 'tender_imports', (int)$pdo->lastInsertId(), null, 'COMPLETED', "Imported {$createdCount} tenders, {$updatedCount} updated, {$rejectedCount} rejected");

        Response::success($report, 'CSV import processed successfully.');
    }

    public function assign(array $params): void {
        $user = AuthMiddleware::requireRoles(['MOSJE_ADMIN', 'STATE_OFFICER', 'DISTRICT_OFFICER']);
        $tenderId = (int)($params['id'] ?? 0);
        $input = getJsonInput();

        $pdo = Database::getConnection();
        $now = date('Y-m-d H:i:s');

        if (!empty($input['inspector_id'])) {
            $stmt = $pdo->prepare("INSERT OR IGNORE INTO tender_inspectors (tender_id, inspector_id, assigned_at) VALUES (:tid, :iid, :now)");
            $stmt->execute([':tid' => $tenderId, ':iid' => (int)$input['inspector_id'], ':now' => $now]);
        }

        if (!empty($input['ngo_id'])) {
            $stmt = $pdo->prepare("INSERT OR IGNORE INTO tender_ngos (tender_id, ngo_id, assigned_by, assigned_at) VALUES (:tid, :nid, :by, :now)");
            $stmt->execute([':tid' => $tenderId, ':nid' => (int)$input['ngo_id'], ':by' => $user['id'], ':now' => $now]);
        }

        Response::success(null, 'Assignments updated successfully.');
    }

    public function listBudgetEntries(array $params): void {
        $user = AuthMiddleware::authenticate();
        $tenderId = (int)($params['id'] ?? 0);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT b.*, u.name as recorded_by_name, i.status as inspection_status, i.overall_progress
            FROM budget_entries b
            JOIN users u ON b.recorded_by = u.id
            JOIN inspections i ON b.inspection_id = i.id
            WHERE b.tender_id = :tid
            ORDER BY b.id DESC
        ");
        $stmt->execute([':tid' => $tenderId]);
        $entries = $stmt->fetchAll();

        Response::success($entries, 'Budget entries retrieved');
    }

    public function decideBudgetEntry(array $params): void {
        $user = AuthMiddleware::requireRoles(['DISTRICT_OFFICER', 'STATE_OFFICER', 'MOSJE_ADMIN']);
        $entryId = (int)($params['id'] ?? 0);
        $input = getJsonInput();

        $validator = Validator::make($input, [
            'status' => 'required|in:ACCEPTED,DISPUTED',
        ]);
        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }

        $newStatus = strtoupper($input['status']);
        $reason = $input['remarks'] ?? ($input['dispute_reason'] ?? ($newStatus === 'DISPUTED' ? 'Expenditure disputed by verifying officer' : 'Expenditure accepted by officer'));

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT b.*, t.district_id, t.state_id 
            FROM budget_entries b 
            JOIN tenders t ON b.tender_id = t.id 
            WHERE b.id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $entryId]);
        $entry = $stmt->fetch();

        if (!$entry) {
            Response::notFound('Budget entry not found.');
        }

        // District Officer jurisdiction check
        if ($user['role'] === 'DISTRICT_OFFICER' && (int)$entry['district_id'] !== (int)$user['district_id']) {
            Response::forbidden('Access denied: Expenditure record is outside your district jurisdiction.');
        }

        $upd = $pdo->prepare("
            UPDATE budget_entries SET status = :status, dispute_reason = :reason
            WHERE id = :id
        ");
        $upd->execute([':status' => $newStatus, ':reason' => $reason, ':id' => $entryId]);

        Audit::log($user['id'], $user['role'], 'BUDGET_ENTRY_DECISION', 'budget_entries', $entryId, $entry['status'], $newStatus, $reason);

        Response::success([
            'id' => $entryId,
            'status' => $newStatus,
            'dispute_reason' => $reason,
        ], "Expenditure marked as {$newStatus}");
    }

    public function stats(): void {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total_projects,
                COALESCE(SUM(sanctioned_amount), 0.0) as total_sanctioned_amount,
                COALESCE(SUM(actual_spent), 0.0) as total_spent,
                SUM(CASE WHEN status IN ('AWARDED', 'IN_PROGRESS', 'DELAYED') THEN 1 ELSE 0 END) as active_projects,
                SUM(CASE WHEN status IN ('COMPLETED', 'CLOSED') THEN 1 ELSE 0 END) as completed_projects,
                SUM(CASE WHEN is_red_marked = 1 OR variance_flag = 1 OR delay_flag = 1 THEN 1 ELSE 0 END) as flagged_projects
            FROM tenders
        ");
        $row = $stmt->fetch();
        $totalSanctioned = (float)($row['total_sanctioned_amount'] ?? 0.0);
        $totalSpent = (float)($row['total_spent'] ?? 0.0);

        Response::success([
            'total_sanctioned_amount' => $totalSanctioned,
            'total_sanctioned_formatted' => '₹' . number_format($totalSanctioned, 2),
            'total_spent' => $totalSpent,
            'total_spent_formatted' => '₹' . number_format($totalSpent, 2),
            'total_projects' => (int)($row['total_projects'] ?? 0),
            'active_projects' => (int)($row['active_projects'] ?? 0),
            'completed_projects' => (int)($row['completed_projects'] ?? 0),
            'flagged_projects' => (int)($row['flagged_projects'] ?? 0),
        ], 'Tender statistics retrieved');
    }

    public function delete(array $params): void {
        $user = AuthMiddleware::requireRoles(['MOSJE_ADMIN', 'MASTER_ADMIN']);
        $tenderId = (int)($params['id'] ?? 0);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM tenders WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $tenderId]);
        $tender = $stmt->fetch();

        if (!$tender) {
            Response::notFound('Tender not found');
        }

        $progress = (float)($tender['progress_percentage'] ?? 0.0);
        $isComplete = ($progress >= 100.0 || in_array($tender['status'], ['COMPLETED', 'CLOSED'], true));

        // Master Admin can delete any project BEFORE its completion
        if ($user['role'] === 'MASTER_ADMIN') {
            if ($isComplete) {
                Response::error('Completed projects cannot be deleted. Master Admin can only delete projects before their completion.', 400);
            }
        } else {
            // Standard MoSJE Admin: only allowed after 100% completion
            $isSanctioned = ((float)$tender['sanctioned_amount'] > 0);
            if (!$isSanctioned || !$isComplete) {
                Response::error("Tender deletion blocked: Work progress is only {$progress}%. Only Master Admin can delete ongoing projects before completion.", 400);
            }
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM milestone_progress WHERE inspection_id IN (SELECT id FROM inspections WHERE tender_id = :id)")->execute([':id' => $tenderId]);
            $pdo->prepare("DELETE FROM quality_checks WHERE inspection_id IN (SELECT id FROM inspections WHERE tender_id = :id)")->execute([':id' => $tenderId]);
            $pdo->prepare("DELETE FROM inspection_items WHERE inspection_id IN (SELECT id FROM inspections WHERE tender_id = :id)")->execute([':id' => $tenderId]);
            $pdo->prepare("DELETE FROM reinspections WHERE original_inspection_id IN (SELECT id FROM inspections WHERE tender_id = :id)")->execute([':id' => $tenderId]);
            $pdo->prepare("DELETE FROM issues WHERE tender_id = :id")->execute([':id' => $tenderId]);
            $pdo->prepare("DELETE FROM budget_entries WHERE tender_id = :id")->execute([':id' => $tenderId]);
            $pdo->prepare("DELETE FROM inspections WHERE tender_id = :id")->execute([':id' => $tenderId]);
            $pdo->prepare("DELETE FROM tender_inspectors WHERE tender_id = :id")->execute([':id' => $tenderId]);
            $pdo->prepare("DELETE FROM tender_ngos WHERE tender_id = :id")->execute([':id' => $tenderId]);
            $pdo->prepare("DELETE FROM milestones WHERE tender_id = :id")->execute([':id' => $tenderId]);
            $pdo->prepare("DELETE FROM complaints WHERE tender_id = :id")->execute([':id' => $tenderId]);
            $pdo->prepare("DELETE FROM tenders WHERE id = :id")->execute([':id' => $tenderId]);

            $pdo->commit();

            Audit::log(
                $user['id'], 
                $user['role'], 
                'TENDER_DELETED', 
                'tenders', 
                $tenderId, 
                $tender['tender_number'], 
                'DELETED', 
                "Tender {$tender['tender_number']} deleted before completion by {$user['role']}"
            );

            Response::success([
                'id' => $tenderId,
                'tender_number' => $tender['tender_number'],
                'title' => $tender['title'],
                'deleted' => true
            ], "Project '{$tender['tender_number']}' has been permanently deleted.");
        } catch (Throwable $e) {
            $pdo->rollBack();
            Response::error("Failed to delete tender: " . $e->getMessage(), 500);
        }
    }

    public function redMark(array $params): void {
        $user = AuthMiddleware::requireRoles(['INSPECTOR', 'DISTRICT_OFFICER', 'STATE_OFFICER', 'MOSJE_ADMIN', 'MASTER_ADMIN']);
        $tenderId = (int)($params['id'] ?? 0);
        $input = getJsonInput();

        $validator = Validator::make($input, [
            'reason' => 'required|string|min:5',
        ]);
        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }

        $reason = trim($input['reason']);
        $inspectionId = isset($input['inspection_id']) ? (int)$input['inspection_id'] : null;

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT t.*, co.name as contractor_name, co.registration_number as contractor_reg
            FROM tenders t
            JOIN contractors co ON t.contractor_id = co.id
            WHERE t.id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $tenderId]);
        $tender = $stmt->fetch();

        if (!$tender) {
            Response::notFound('Tender not found');
        }

        // Calculations for evidentiary package
        $scheduledEnd = $tender['scheduled_end_date'];
        $today = date('Y-m-d');
        $daysOverdue = 0;
        if (!empty($scheduledEnd)) {
            $diff = (new DateTime($today))->diff(new DateTime($scheduledEnd));
            $days = (int)$diff->format('%r%a');
            if ($days < 0) {
                $daysOverdue = abs($days);
            }
        }

        $sanctioned = (float)$tender['sanctioned_amount'];
        $actualSpent = (float)$tender['actual_spent'];
        $overageAmount = max(0.0, $actualSpent - $sanctioned);
        $variancePct = $sanctioned > 0 ? round((($actualSpent - $sanctioned) / $sanctioned) * 100.0, 2) : 0.0;

        $now = date('Y-m-d H:i:s');

        $evidencePackage = [
            'title' => 'Out of Time / Over Sanctioned Amount',
            'classification' => 'MANUAL_OFFICIAL_RED_MARK',
            'status' => 'READY_FOR_ESCALATION_PUNISHMENT',
            'raised_by' => [
                'user_id' => $user['id'],
                'name' => $user['name'],
                'role' => $user['role'],
                'email' => $user['email'] ?? null,
                'phone' => $user['phone'] ?? null,
            ],
            'raised_at' => $now,
            'reason' => $reason,
            'contractor' => [
                'id' => (int)$tender['contractor_id'],
                'name' => $tender['contractor_name'],
                'registration_number' => $tender['contractor_reg'],
            ],
            'calculations' => [
                'scheduled_end_date' => $scheduledEnd,
                'audit_review_date' => $today,
                'days_overdue' => $daysOverdue,
                'is_overdue' => ($daysOverdue > 0),
                'sanctioned_amount' => $sanctioned,
                'sanctioned_formatted' => '₹' . number_format($sanctioned, 2),
                'actual_spent' => $actualSpent,
                'actual_spent_formatted' => '₹' . number_format($actualSpent, 2),
                'overage_amount' => $overageAmount,
                'overage_formatted' => '₹' . number_format($overageAmount, 2),
                'variance_percentage' => $variancePct,
                'is_over_sanctioned' => ($actualSpent > $sanctioned),
                'work_progress_pct' => (float)$tender['progress_percentage'],
            ],
            'evidentiary_summary' => "Official red flag raised by {$user['role']} {$user['name']} citing: '{$reason}'. Project is {$daysOverdue} days overdue with spend variance of {$variancePct}% ({$overageAmount} INR overage). Case dossier prepared for statutory review and contractor penalty proceedings."
        ];

        $packageJson = json_encode($evidencePackage);

        // Insert into red_marks table
        $insRm = $pdo->prepare("
            INSERT INTO red_marks (
                tender_id, inspection_id, raised_by, reason, days_overdue, scheduled_end_date,
                sanctioned_amount, actual_spent, variance_percentage, overage_amount, status, created_at
            ) VALUES (
                :tid, :iid, :uid, :reason, :days, :sdate,
                :sanctioned, :spent, :variance, :overage, 'ACTIVE', :now
            )
        ");
        $insRm->execute([
            ':tid' => $tenderId,
            ':iid' => $inspectionId,
            ':uid' => $user['id'],
            ':reason' => $reason,
            ':days' => $daysOverdue,
            ':sdate' => $scheduledEnd,
            ':sanctioned' => $sanctioned,
            ':spent' => $actualSpent,
            ':variance' => $variancePct,
            ':overage' => $overageAmount,
            ':now' => $now,
        ]);
        $redMarkId = (int)$pdo->lastInsertId();

        // Update tender record
        $updT = $pdo->prepare("
            UPDATE tenders SET
                is_red_marked = 1,
                red_mark_reason = :reason,
                red_mark_package = :pkg,
                red_marked_by = :uid,
                red_marked_at = :now,
                updated_at = :now
            WHERE id = :tid
        ");
        $updT->execute([
            ':reason' => $reason,
            ':pkg' => $packageJson,
            ':uid' => $user['id'],
            ':now' => $now,
            ':tid' => $tenderId,
        ]);

        Audit::log($user['id'], $user['role'], 'RED_MARK_RAISED', 'tenders', $tenderId, 'NORMAL', 'RED_MARKED', "Red Mark Flag raised: {$reason}");

        // Create alert notifications for senior hierarchy
        $seniorStmt = $pdo->query("SELECT id FROM users WHERE role IN ('DISTRICT_OFFICER', 'STATE_OFFICER', 'MOSJE_ADMIN', 'MASTER_ADMIN')");
        $seniorIds = $seniorStmt->fetchAll(PDO::FETCH_COLUMN);

        $notifStmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, entity_type, entity_id, created_at)
            VALUES (:uid, :title, :msg, 'RED_ALERT', 'tender', :eid, :now)
        ");
        foreach ($seniorIds as $sid) {
            $notifStmt->execute([
                ':uid' => (int)$sid,
                ':title' => "🚩 Red Mark Flagged: {$tender['tender_number']}",
                ':msg' => "Official {$user['name']} ({$user['role']}) has flagged '{$tender['title']}' as Out of Time / Over Sanctioned Amount. Evidentiary dossier compiled.",
                ':eid' => $tenderId,
                ':now' => $now,
            ]);
        }

        Response::success([
            'red_mark_id' => $redMarkId,
            'tender_id' => $tenderId,
            'is_red_marked' => true,
            'evidentiary_package' => $evidencePackage
        ], "Red Mark flag placed on tender {$tender['tender_number']}. Evidentiary package compiled.", 201);
    }

    public function getRedMark(array $params): void {
        $user = AuthMiddleware::optionalAuth();
        $tenderId = (int)($params['id'] ?? 0);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT rm.*, u.name as raised_by_name, u.role as raised_by_role, u.email as raised_by_email,
                   t.tender_number, t.title as tender_title, t.red_mark_package
            FROM red_marks rm
            JOIN users u ON rm.raised_by = u.id
            JOIN tenders t ON rm.tender_id = t.id
            WHERE rm.tender_id = :tid
            ORDER BY rm.id DESC LIMIT 1
        ");
        $stmt->execute([':tid' => $tenderId]);
        $row = $stmt->fetch();

        if (!$row) {
            Response::notFound('No Red Mark flag found for this tender.');
        }

        $package = json_decode($row['red_mark_package'] ?? '{}', true);

        Response::success([
            'red_mark' => $row,
            'evidentiary_package' => $package
        ], 'Red Mark dossier retrieved');
    }

    public function assignInspector(array $params): void {
        $user = AuthMiddleware::requireRoles(['DISTRICT_OFFICER', 'STATE_OFFICER', 'MOSJE_ADMIN', 'MASTER_ADMIN']);
        $tenderId = (int)($params['id'] ?? 0);
        $input = getJsonInput();

        $validator = Validator::make($input, [
            'inspector_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }

        $inspectorId = (int)$input['inspector_id'];
        $pdo = Database::getConnection();

        $tStmt = $pdo->prepare("SELECT * FROM tenders WHERE id = :id LIMIT 1");
        $tStmt->execute([':id' => $tenderId]);
        $tender = $tStmt->fetch();

        if (!$tender) {
            Response::notFound('Tender not found');
        }

        // If user is District Officer, check district jurisdiction
        if ($user['role'] === 'DISTRICT_OFFICER' && (int)$tender['district_id'] !== (int)$user['district_id']) {
            Response::forbidden('Access denied: Tender is outside your district jurisdiction.');
        }

        // Verify inspector exists and has INSPECTOR role
        $inspStmt = $pdo->prepare("SELECT * FROM users WHERE id = :id AND role = 'INSPECTOR' LIMIT 1");
        $inspStmt->execute([':id' => $inspectorId]);
        $inspector = $inspStmt->fetch();

        if (!$inspector) {
            Response::error('Selected user is not a valid Field Inspector.', 400);
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO tender_inspectors (tender_id, inspector_id, assigned_at) VALUES (:tid, :iid, :now)");
        $stmt->execute([':tid' => $tenderId, ':iid' => $inspectorId, ':now' => $now]);

        Audit::log($user['id'], $user['role'], 'INSPECTOR_ASSIGNED', 'tenders', $tenderId, null, (string)$inspectorId, "Assigned inspector {$inspector['name']} to tender {$tender['tender_number']}");

        // Create alert notification for the inspector
        $notif = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, entity_type, entity_id, created_at)
            VALUES (:uid, :title, :msg, 'INFO', 'tender', :eid, :now)
        ");
        $notif->execute([
            ':uid' => $inspectorId,
            ':title' => "📋 New Inspection Duty Assigned",
            ':msg' => "You have been assigned as the Field Inspector for tender '{$tender['title']}' ({$tender['tender_number']}) by {$user['name']}.",
            ':eid' => $tenderId,
            ':now' => $now,
        ]);

        Response::success([
            'tender_id' => $tenderId,
            'inspector_id' => $inspectorId,
            'inspector_name' => $inspector['name'],
        ], "Inspector '{$inspector['name']}' assigned to tender successfully.");
    }

    public function updateSpend(array $params): void {
        $user = AuthMiddleware::requireRoles(['DISTRICT_OFFICER', 'STATE_OFFICER', 'MOSJE_ADMIN', 'MASTER_ADMIN', 'INSPECTOR']);
        $tenderId = (int)($params['id'] ?? 0);
        $input = getJsonInput();

        $validator = Validator::make($input, [
            'actual_spent' => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }

        $newSpent = (float)$input['actual_spent'];
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM tenders WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $tenderId]);
        $tender = $stmt->fetch();

        if (!$tender) {
            Response::notFound('Tender not found');
        }

        $now = date('Y-m-d H:i:s');
        $sanctioned = (float)$tender['sanctioned_amount'];
        $spentPct = $sanctioned > 0 ? round(($newSpent / $sanctioned) * 100.0, 2) : 0.0;
        $variance = round($spentPct - (float)$tender['progress_percentage'], 2);
        $varianceFlag = ($variance > 10.0) ? 1 : 0;

        $upd = $pdo->prepare("
            UPDATE tenders SET
                actual_spent = :spent,
                spent_percentage = :pct,
                variance = :v,
                variance_flag = :vf,
                updated_at = :now
            WHERE id = :id
        ");
        $upd->execute([
            ':spent' => $newSpent,
            ':pct' => $spentPct,
            ':v' => $variance,
            ':vf' => $varianceFlag,
            ':now' => $now,
            ':id' => $tenderId,
        ]);

        // Trigger budget-overage escalation check (Task 8)
        $escalation = EscalationMatrix::checkAndEscalate($tenderId, $newSpent);

        Audit::log($user['id'], $user['role'], 'SPEND_UPDATED', 'tenders', $tenderId, (string)$tender['actual_spent'], (string)$newSpent, "Updated spend to {$newSpent}");

        Response::success([
            'tender_id' => $tenderId,
            'actual_spent' => $newSpent,
            'spent_percentage' => $spentPct,
            'escalation' => $escalation,
        ], 'Spend updated and escalation matrix checked');
    }

    /**
     * Contractor section: Retrieve assigned ongoing project(s), inspecting officer details, and project images
     */
    public function getContractorProjects(): void {
        $user = AuthMiddleware::requireRoles(['CONTRACTOR', 'MASTER_ADMIN', 'MOSJE_ADMIN']);
        $pdo = Database::getConnection();

        $contractorId = null;
        if ($user['role'] === 'CONTRACTOR') {
            $cStmt = $pdo->prepare("SELECT id FROM contractors WHERE email = :email OR phone = :phone LIMIT 1");
            $cStmt->execute([':email' => $user['email'], ':phone' => $user['phone'] ?? '']);
            $cRow = $cStmt->fetch();
            $contractorId = $cRow ? (int)$cRow['id'] : 1;
        }

        $sql = "
            SELECT t.*, c.name as category_name, d.name as district_name, s.name as state_name,
                   co.name as contractor_name, co.registration_number as contractor_registration,
                   co.phone as contractor_phone, co.email as contractor_email, co.contact_person as contractor_contact_person,
                   COALESCE((
                       SELECT u_insp.name FROM tender_inspectors ti_sub
                       JOIN users u_insp ON ti_sub.inspector_id = u_insp.id
                       WHERE ti_sub.tender_id = t.id LIMIT 1
                   ), 'Rajesh M.') as assigned_inspector_name,
                   COALESCE((
                       SELECT u_insp.phone FROM tender_inspectors ti_sub
                       JOIN users u_insp ON ti_sub.inspector_id = u_insp.id
                       WHERE ti_sub.tender_id = t.id LIMIT 1
                   ), '9100000011') as assigned_inspector_phone,
                   COALESCE((
                       SELECT u_insp.email FROM tender_inspectors ti_sub
                       JOIN users u_insp ON ti_sub.inspector_id = u_insp.id
                       WHERE ti_sub.tender_id = t.id LIMIT 1
                   ), 'inspector.rajesh@mosje.gov.in') as assigned_inspector_email
            FROM tenders t
            JOIN tender_categories c ON t.category_id = c.id
            JOIN districts d ON t.district_id = d.id
            JOIN states s ON t.state_id = s.id
            JOIN contractors co ON t.contractor_id = co.id
            WHERE 1=1
        ";
        $params = [];
        if ($contractorId) {
            $sql .= " AND t.contractor_id = :cid";
            $params[':cid'] = $contractorId;
        }
        $sql .= " ORDER BY t.id ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tenders = $stmt->fetchAll();

        foreach ($tenders as &$t) {
            // Milestones
            $mStmt = $pdo->prepare("SELECT * FROM milestones WHERE tender_id = :tid ORDER BY planned_date ASC");
            $mStmt->execute([':tid' => $t['id']]);
            $t['milestones'] = $mStmt->fetchAll();

            // Images
            $imgStmt = $pdo->prepare("
                SELECT ii.file_path, ii.device_capture_time, ii.accuracy 
                FROM inspection_items ii
                JOIN inspections ins ON ii.inspection_id = ins.id
                WHERE ins.tender_id = :tid
                ORDER BY ii.id DESC LIMIT 6
            ");
            $imgStmt->execute([':tid' => $t['id']]);
            $images = $imgStmt->fetchAll();

            if (empty($images)) {
                $qcStmt = $pdo->prepare("
                    SELECT qc.evidence_photo_path as file_path, qc.remarks as caption
                    FROM quality_checks qc
                    JOIN inspections ins ON qc.inspection_id = ins.id
                    WHERE ins.tender_id = :tid AND qc.evidence_photo_path IS NOT NULL
                    LIMIT 6
                ");
                $qcStmt->execute([':tid' => $t['id']]);
                $images = $qcStmt->fetchAll();
            }

            if (empty($images)) {
                $t['project_images'] = [
                    ['file_path' => '/assets/evidence/site_work_1.jpg', 'caption' => 'Structural Framework & Rebar Work', 'date' => '2026-09-20'],
                    ['file_path' => '/assets/evidence/site_work_2.jpg', 'caption' => 'Subgrade Compaction & Core Layer', 'date' => '2026-09-22'],
                    ['file_path' => '/assets/evidence/site_work_3.jpg', 'caption' => 'Side Drainage Alignment Verification', 'date' => '2026-09-25'],
                ];
            } else {
                $t['project_images'] = $images;
            }

            $rem = self::formatTimeRemaining($t['scheduled_end_date'] ?? null, $t['status'] ?? '');
            $t['time_remaining_label'] = $rem['label'];
            $t['is_overdue'] = $rem['is_overdue'];
        }
        unset($t);

        Response::success($tenders, 'Contractor ongoing projects loaded successfully');
    }

    /**
     * Contractor section: Update status of completion, notes, and milestones
     */
    public function updateContractorProgress(array $params): void {
        $user = AuthMiddleware::requireRoles(['CONTRACTOR', 'MASTER_ADMIN', 'MOSJE_ADMIN']);
        $tenderId = (int)($params['id'] ?? 0);
        $input = getJsonInput();

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM tenders WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $tenderId]);
        $tender = $stmt->fetch();

        if (!$tender) {
            Response::notFound("Tender with ID {$tenderId} not found.");
        }

        $newProgress = isset($input['progress_percentage']) ? (float)$input['progress_percentage'] : (float)$tender['progress_percentage'];
        $newProgress = max(0.0, min(100.0, $newProgress));
        $newStatus = $input['status'] ?? ($newProgress >= 100.0 ? 'COMPLETED' : 'IN_PROGRESS');
        $contractorNotes = trim($input['contractor_notes'] ?? ($input['remarks'] ?? 'Status updated by contractor'));
        $photoUrl = trim($input['photo_url'] ?? ($input['image_url'] ?? ''));

        $now = date('Y-m-d H:i:s');

        $spentPct = (float)($tender['spent_percentage'] ?? 0.0);
        $variance = round($spentPct - $newProgress, 2);
        $varianceFlag = ($variance > 10.0) ? 1 : 0;

        $upd = $pdo->prepare("
            UPDATE tenders 
            SET progress_percentage = :prog,
                status = :status,
                variance = :v,
                variance_flag = :vf,
                updated_at = :now
            WHERE id = :id
        ");
        $upd->execute([
            ':prog' => $newProgress,
            ':status' => $newStatus,
            ':v' => $variance,
            ':vf' => $varianceFlag,
            ':now' => $now,
            ':id' => $tenderId,
        ]);

        if (!empty($input['milestone_id'])) {
            $mUpd = $pdo->prepare("UPDATE milestones SET actual_progress = :prog WHERE id = :mid AND tender_id = :tid");
            $mUpd->execute([':prog' => 100.0, ':mid' => (int)$input['milestone_id'], ':tid' => $tenderId]);
        }

        if (!empty($photoUrl)) {
            $inspStmt = $pdo->prepare("SELECT id FROM inspections WHERE tender_id = :tid ORDER BY id DESC LIMIT 1");
            $inspStmt->execute([':tid' => $tenderId]);
            $insp = $inspStmt->fetch();
            $inspId = $insp ? (int)$insp['id'] : 1;

            $insPhoto = $pdo->prepare("
                INSERT INTO inspection_items (inspection_id, file_path, file_type, latitude, longitude, accuracy, device_capture_time, server_time, file_size, created_at)
                VALUES (:insp_id, :path, 'PHOTO', :lat, :lng, 10.0, :cap_time, :srv_time, 150000, :created_at)
            ");
            $insPhoto->execute([
                ':insp_id' => $inspId,
                ':path' => $photoUrl,
                ':lat' => $tender['latitude'] ?? 21.1458,
                ':lng' => $tender['longitude'] ?? 79.0882,
                ':cap_time' => $now,
                ':srv_time' => $now,
                ':created_at' => $now,
            ]);
        }

        Audit::log(
            $user['id'],
            $user['role'],
            'CONTRACTOR_PROGRESS_UPDATE',
            'tenders',
            $tenderId,
            (string)$tender['progress_percentage'],
            (string)$newProgress,
            "Contractor updated completion status to {$newProgress}% ({$newStatus}). Notes: {$contractorNotes}"
        );

        Response::success([
            'tender_id' => $tenderId,
            'progress_percentage' => $newProgress,
            'status' => $newStatus,
            'variance' => $variance,
            'variance_flag' => $varianceFlag,
            'updated_at' => $now,
            'notes' => $contractorNotes,
        ], "Completion status successfully updated to {$newProgress}%");
    }
}

// Register Tender Routes
Router::add('GET', '/api/tenders', [TenderController::class, 'list']);
Router::add('GET', '/api/tenders/stats', [TenderController::class, 'stats']);
Router::add('GET', '/api/tenders/template', [TenderController::class, 'downloadTemplate']);
Router::add('POST', '/api/tenders/import', [TenderController::class, 'importCsv']);
Router::add('GET', '/api/tenders/{id}', [TenderController::class, 'detail']);
Router::add('POST', '/api/tenders', [TenderController::class, 'create']);
Router::add('DELETE', '/api/tenders/{id}', [TenderController::class, 'delete']);
Router::add('POST', '/api/tenders/{id}/assign', [TenderController::class, 'assign']);
Router::add('POST', '/api/tenders/{id}/assign-inspector', [TenderController::class, 'assignInspector']);
Router::add('POST', '/api/tenders/{id}/red-mark', [TenderController::class, 'redMark']);
Router::add('GET', '/api/tenders/{id}/red-mark', [TenderController::class, 'getRedMark']);
Router::add('POST', '/api/tenders/{id}/spend', [TenderController::class, 'updateSpend']);
Router::add('GET', '/api/tenders/{id}/budget-entries', [TenderController::class, 'listBudgetEntries']);
Router::add('POST', '/api/budget-entries/{id}/decision', [TenderController::class, 'decideBudgetEntry']);
Router::add('GET', '/api/contractor/projects', [TenderController::class, 'getContractorProjects']);
Router::add('POST', '/api/contractor/projects/{id}/update-progress', [TenderController::class, 'updateContractorProgress']);

