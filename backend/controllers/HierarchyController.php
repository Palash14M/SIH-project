<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class HierarchyController {
    public function getHierarchy(): void {
        $user = AuthMiddleware::optionalAuth();
        $role = $user['role'] ?? 'PUBLIC';
        $pdo = Database::getConnection();

        $search = trim($_GET['search'] ?? '');
        $designation = trim($_GET['designation'] ?? '');
        $stateFilter = isset($_GET['state_id']) ? (int)$_GET['state_id'] : null;
        $districtFilter = isset($_GET['district_id']) ? (int)$_GET['district_id'] : null;

        // Is user authorized to see contact details? (NGO or Staff)
        $canSeeContacts = in_array($role, ['MOSJE_ADMIN', 'STATE_OFFICER', 'DISTRICT_OFFICER', 'SENIOR_OFFICER', 'INSPECTOR'], true)
            || ($role === 'NGO' && ($user['ngo_status'] ?? '') === 'APPROVED');

        // 1. Fetch States
        $stateSql = "SELECT * FROM states WHERE 1=1";
        $stateParams = [];
        if ($stateFilter) {
            $stateSql .= " AND id = :sid";
            $stateParams[':sid'] = $stateFilter;
        }
        $stateSql .= " ORDER BY name ASC";
        $sStmt = $pdo->prepare($stateSql);
        $sStmt->execute($stateParams);
        $states = $sStmt->fetchAll();

        // 2. Fetch all active officials
        $userSql = "
            SELECT u.id, u.name, u.email, u.phone, u.role, u.state_id, u.district_id,
                   d.name as district_name, s.name as state_name
            FROM users u
            LEFT JOIN districts d ON u.district_id = d.id
            LEFT JOIN states s ON u.state_id = s.id
            WHERE u.status = 'ACTIVE' AND u.role IN ('STATE_OFFICER', 'DISTRICT_OFFICER', 'INSPECTOR', 'SENIOR_OFFICER')
        ";
        $uStmt = $pdo->query($userSql);
        $allOfficials = $uStmt->fetchAll();

        // 3. Fetch all tenders with contractor & inspector mappings
        $tSql = "
            SELECT t.id as tender_id, t.tender_number, t.title, t.status, 
                   t.progress_percentage,
                   t.sanctioned_amount, t.actual_spent, t.scheduled_end_date, t.variance_flag, t.delay_flag,
                   t.district_id, t.state_id,
                   co.id as contractor_id, co.name as contractor_name, co.phone as contractor_phone,
                   co.email as contractor_email, co.address as contractor_address,
                   ti.inspector_id
            FROM tenders t
            JOIN contractors co ON t.contractor_id = co.id
            LEFT JOIN tender_inspectors ti ON t.id = ti.tender_id
            ORDER BY t.id DESC
        ";
        $tStmt = $pdo->query($tSql);
        $allTenderRows = $tStmt->fetchAll();

        // Map tenders by inspector_id and district_id
        $tendersByInspector = [];
        $contractorsByInspector = [];
        foreach ($allTenderRows as $tr) {
            $inspId = $tr['inspector_id'] ? (int)$tr['inspector_id'] : 0;
            
            // Format time remaining
            $timeRem = self::formatTimeRemaining($tr['scheduled_end_date'], $tr['status']);
            $tr['time_remaining_label'] = $timeRem['label'];
            $tr['is_overdue'] = $timeRem['is_overdue'];

            if (!$canSeeContacts) {
                unset($tr['contractor_phone']);
                unset($tr['contractor_email']);
                unset($tr['contractor_address']);
                unset($tr['sanctioned_amount']);
                unset($tr['actual_spent']);
            }

            if ($inspId > 0) {
                $tendersByInspector[$inspId][] = $tr;
                $contractorsByInspector[$inspId][$tr['contractor_id']] = [
                    'contractor_id' => $tr['contractor_id'],
                    'contractor_name' => $tr['contractor_name'],
                    'contractor_phone' => $canSeeContacts ? ($tr['contractor_phone'] ?? null) : null,
                    'contractor_email' => $canSeeContacts ? ($tr['contractor_email'] ?? null) : null,
                    'contractor_address' => $canSeeContacts ? ($tr['contractor_address'] ?? null) : null,
                    'project' => [
                        'tender_id' => $tr['tender_id'],
                        'tender_number' => $tr['tender_number'],
                        'title' => $tr['title'],
                        'status' => $tr['status'],
                        'progress_percentage' => $tr['progress_percentage'],
                        'time_remaining_label' => $tr['time_remaining_label'],
                        'is_overdue' => $tr['is_overdue'],
                    ]
                ];
            }
        }

        // 4. Build Structured Tree
        $tree = [];
        $flatOfficials = [];

        foreach ($states as $st) {
            $stateId = (int)$st['id'];

            // Find State Officers
            $stateOfficers = [];
            foreach ($allOfficials as $off) {
                if ($off['role'] === 'STATE_OFFICER' && (int)$off['state_id'] === $stateId) {
                    $item = self::sanitizeOfficial($off, $canSeeContacts);
                    $stateOfficers[] = $item;
                    $flatOfficials[] = $item;
                }
            }

            // Fetch Districts for this state
            $distStmt = $pdo->prepare("SELECT * FROM districts WHERE state_id = :sid ORDER BY name ASC");
            $distStmt->execute([':sid' => $stateId]);
            $districts = $distStmt->fetchAll();

            $distList = [];
            foreach ($districts as $dst) {
                $distId = (int)$dst['id'];
                if ($districtFilter && $distId !== $districtFilter) {
                    continue;
                }

                // District Officers
                $districtOfficers = [];
                // Field Inspectors
                $inspectors = [];

                foreach ($allOfficials as $off) {
                    if ((int)$off['district_id'] === $distId) {
                        if ($off['role'] === 'DISTRICT_OFFICER') {
                            $item = self::sanitizeOfficial($off, $canSeeContacts);
                            $districtOfficers[] = $item;
                            $flatOfficials[] = $item;
                        } elseif ($off['role'] === 'INSPECTOR') {
                            $item = self::sanitizeOfficial($off, $canSeeContacts);
                            $inspId = (int)$off['id'];
                            $item['contractors'] = array_values($contractorsByInspector[$inspId] ?? []);
                            $item['assigned_tenders'] = $tendersByInspector[$inspId] ?? [];
                            $inspectors[] = $item;
                            $flatOfficials[] = $item;
                        }
                    }
                }

                $distList[] = [
                    'id' => $distId,
                    'name' => $dst['name'],
                    'code' => $dst['code'],
                    'district_officers' => $districtOfficers,
                    'field_inspectors' => $inspectors,
                ];
            }

            $tree[] = [
                'id' => $stateId,
                'name' => $st['name'],
                'code' => $st['code'],
                'state_officers' => $stateOfficers,
                'districts' => $distList,
            ];
        }

        // Filter flat list if search or designation is requested
        $filteredOfficials = $flatOfficials;
        if (!empty($designation)) {
            $filteredOfficials = array_values(array_filter($filteredOfficials, function ($o) use ($designation) {
                return strtoupper($o['role']) === strtoupper($designation);
            }));
        }
        if (!empty($search)) {
            $sLower = strtolower($search);
            $filteredOfficials = array_values(array_filter($filteredOfficials, function ($o) use ($sLower) {
                return str_contains(strtolower($o['name']), $sLower) ||
                       str_contains(strtolower($o['district_name'] ?? ''), $sLower) ||
                       str_contains(strtolower($o['state_name'] ?? ''), $sLower);
            }));
        }

        Response::success([
            'hierarchy_tree' => $tree,
            'officials_catalog' => $filteredOfficials,
            'user_role' => $role,
            'contacts_visible' => $canSeeContacts,
            'can_nudge' => ($role === 'PUBLIC'),
        ], 'Hierarchy retrieved successfully');
    }

    private static function sanitizeOfficial(array $u, bool $canSeeContacts): array {
        return [
            'id' => (int)$u['id'],
            'name' => $u['name'],
            'role' => $u['role'],
            'designation_label' => self::formatRoleLabel($u['role']),
            'state_id' => $u['state_id'] ? (int)$u['state_id'] : null,
            'state_name' => $u['state_name'] ?? null,
            'district_id' => $u['district_id'] ? (int)$u['district_id'] : null,
            'district_name' => $u['district_name'] ?? null,
            'phone' => $canSeeContacts ? ($u['phone'] ?? null) : null,
            'email' => $canSeeContacts ? ($u['email'] ?? null) : null,
        ];
    }

    private static function formatRoleLabel(string $role): string {
        return match ($role) {
            'STATE_OFFICER' => 'State Officer',
            'DISTRICT_OFFICER' => 'District Officer',
            'INSPECTOR' => 'Field Inspector',
            'MOSJE_ADMIN' => 'MoSJE Admin',
            'SENIOR_OFFICER' => 'Senior Officer',
            default => $role,
        };
    }

    private static function formatTimeRemaining(?string $scheduledEndDate, string $status): array {
        if ($status === 'COMPLETED' || $status === 'CLOSED') {
            return ['label' => 'Work Completed', 'is_overdue' => false];
        }
        if (empty($scheduledEndDate)) {
            return ['label' => 'No completion date set', 'is_overdue' => false];
        }
        $target = new DateTime($scheduledEndDate);
        $today = new DateTime(date('Y-m-d'));
        $diff = $today->diff($target);
        $days = (int)$diff->format('%r%a');
        if ($days > 0) {
            $months = floor($days / 30);
            $rem = $days % 30;
            $label = ($months > 0) ? "{$months}mo {$rem}d left" : "{$days} days remaining";
            return ['label' => $label, 'is_overdue' => false];
        } elseif ($days === 0) {
            return ['label' => 'Due Today', 'is_overdue' => false];
        } else {
            $overdue = abs($days);
            return ['label' => "Overdue by {$overdue} days", 'is_overdue' => true];
        }
    }
}

if (class_exists('Router')) {
    Router::add('GET', '/api/hierarchy', [HierarchyController::class, 'getHierarchy']);
}
