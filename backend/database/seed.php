<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

echo "=== SEEDING DATABASE WITH REALISTIC DATA ===\n";

$pdo = Database::getConnection();

// Hash helper
function hashPass(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT);
}

$pdo->beginTransaction();

try {
    // 1. STATES
    $stmtState = $pdo->prepare("INSERT INTO states (name, code, created_at) VALUES (:name, :code, :created_at)");
    $statesData = [
        ['Maharashtra', 'MH'],
        ['Uttar Pradesh', 'UP'],
    ];
    $stateIds = [];
    foreach ($statesData as $s) {
        $stmtState->execute([':name' => $s[0], ':code' => $s[1], ':created_at' => date('Y-m-d H:i:s')]);
        $stateIds[$s[1]] = (int)$pdo->lastInsertId();
    }
    echo " -> Seeded 2 States\n";

    // 2. DISTRICTS
    $stmtDist = $pdo->prepare("INSERT INTO districts (state_id, name, code, created_at) VALUES (:state_id, :name, :code, :created_at)");
    $districtsData = [
        [$stateIds['MH'], 'Nagpur', 'NGP'],
        [$stateIds['MH'], 'Pune', 'PUN'],
        [$stateIds['UP'], 'Lucknow', 'LKO'],
        [$stateIds['UP'], 'Varanasi', 'VNS'],
    ];
    $distIds = [];
    foreach ($districtsData as $d) {
        $stmtDist->execute([':state_id' => $d[0], ':name' => $d[1], ':code' => $d[2], ':created_at' => date('Y-m-d H:i:s')]);
        $distIds[$d[1]] = (int)$pdo->lastInsertId();
    }
    echo " -> Seeded 4 Districts\n";

    // 3. USERS
    $stmtUser = $pdo->prepare("
        INSERT INTO users (username, name, email, phone, password_hash, role, state_id, district_id, gov_id, lgd_code, must_change_password, status, created_at, updated_at)
        VALUES (:username, :name, :email, :phone, :password_hash, :role, :state_id, :district_id, :gov_id, :lgd_code, :must_change_password, :status, :created_at, :updated_at)
    ");

    $now = date('Y-m-d H:i:s');
    $usersData = [
        // MASTER ADMIN (Task 9 & 12) - username: admin, password: admin, force change on first login
        ['admin', 'Master Supreme Administrator', 'admin@master.gov.in', '9500000000', hashPass('admin'), 'MASTER_ADMIN', null, null, 'MASTER-ROOT-001', null, 1, 'ACTIVE'],

        // MoSJE Admin (Task 12) - mobile: 9543210876, email: mosje.admin@gov.in, password: Demo@123
        [null, 'MoSJE Central Administrator', 'mosje.admin@gov.in', '9543210876', hashPass('Demo@123'), 'MOSJE_ADMIN', null, null, 'GOV-CENTRAL-001', null, 0, 'ACTIVE'],
        // Legacy admin for existing tests
        [null, 'MoSJE National Administrator', 'admin@mosje.gov.in', '9100000001', hashPass('Admin@12345'), 'MOSJE_ADMIN', null, null, 'GOV-ADMIN-001', null, 0, 'ACTIVE'],

        // State Officers (Task 12 State Officer: mobile 9654321087, state LGD ST-LGD-024, pass Demo@123)
        [null, 'Shri Sanjay Verma (State Officer MH)', 'state.demo@mosje.gov.in', '9654321087', hashPass('Demo@123'), 'STATE_OFFICER', $stateIds['MH'], null, null, 'ST-LGD-024', 0, 'ACTIVE'],
        [null, 'Dr. Arvind Patil (State Officer MH)', 'state.mh@mosje.gov.in', '9100000002', hashPass('Officer@12345'), 'STATE_OFFICER', $stateIds['MH'], null, null, 'ST-LGD-024', 0, 'ACTIVE'],
        [null, 'Shri Rajeshwar Singh (State Officer UP)', 'state.up@mosje.gov.in', '9100000003', hashPass('Officer@12345'), 'STATE_OFFICER', $stateIds['UP'], null, null, 'ST-LGD-009', 0, 'ACTIVE'],

        // District Officers (Task 12 District Officer: mobile 9765432190, district LGD DL-LGD-478, pass Demo@123)
        [null, 'District Officer Nagpur', 'district.demo@mosje.gov.in', '9765432190', hashPass('Demo@123'), 'DISTRICT_OFFICER', $stateIds['MH'], $distIds['Nagpur'], null, 'DL-LGD-478', 0, 'ACTIVE'],
        [null, 'Virendra Deshmukh (DO Nagpur)', 'district.nagpur@mosje.gov.in', '9100000004', hashPass('Officer@12345'), 'DISTRICT_OFFICER', $stateIds['MH'], $distIds['Nagpur'], null, 'DL-LGD-478', 0, 'ACTIVE'],
        [null, 'Sunita Kulkarni (DO Pune)', 'district.pune@mosje.gov.in', '9100000005', hashPass('Officer@12345'), 'DISTRICT_OFFICER', $stateIds['MH'], $distIds['Pune'], null, 'DL-LGD-479', 0, 'ACTIVE'],
        [null, 'Ramesh Chandra (DO Lucknow)', 'district.lucknow@mosje.gov.in', '9100000006', hashPass('Officer@12345'), 'DISTRICT_OFFICER', $stateIds['UP'], $distIds['Lucknow'], null, 'DL-LGD-301', 0, 'ACTIVE'],

        // Senior Officers
        [null, 'Chief Engineer Sharma', 'senior.sharma@mosje.gov.in', '9100000007', hashPass('Senior@12345'), 'SENIOR_OFFICER', $stateIds['MH'], $distIds['Nagpur'], null, null, 0, 'ACTIVE'],
        [null, 'Superintending Engineer Verma', 'senior.verma@mosje.gov.in', '9100000008', hashPass('Senior@12345'), 'SENIOR_OFFICER', $stateIds['UP'], $distIds['Lucknow'], null, null, 0, 'ACTIVE'],

        // Field Inspectors (Task 12: mobile 9900112233 auto-generated registration)
        [null, 'Field Inspector (Auto-Reg)', 'inspector.demo@mosje.gov.in', '9900112233', hashPass('Demo@123'), 'INSPECTOR', $stateIds['MH'], $distIds['Nagpur'], null, null, 0, 'ACTIVE'],
        [null, 'Rajesh M. (Inspector Nagpur)', 'inspector.rajesh@mosje.gov.in', '9100000011', hashPass('Inspect@12345'), 'INSPECTOR', $stateIds['MH'], $distIds['Nagpur'], null, null, 0, 'ACTIVE'],
        [null, 'Priya Joshi (Inspector Pune)', 'inspector.priya@mosje.gov.in', '9100000012', hashPass('Inspect@12345'), 'INSPECTOR', $stateIds['MH'], $distIds['Pune'], null, null, 0, 'ACTIVE'],
        [null, 'Amit Srivastava (Inspector Lucknow)', 'inspector.amit@mosje.gov.in', '9100000013', hashPass('Inspect@12345'), 'INSPECTOR', $stateIds['UP'], $distIds['Lucknow'], null, null, 0, 'ACTIVE'],
        [null, 'Sunita Maurya (Inspector Varanasi)', 'inspector.sunita@mosje.gov.in', '9100000014', hashPass('Inspect@12345'), 'INSPECTOR', $stateIds['UP'], $distIds['Varanasi'], null, null, 0, 'ACTIVE'],
        [null, 'Vikram B. (Inspector Nagpur)', 'inspector.vikram@mosje.gov.in', '9100000015', hashPass('Inspect@12345'), 'INSPECTOR', $stateIds['MH'], $distIds['Nagpur'], null, null, 0, 'ACTIVE'],

        // NGOs (Task 12: mobile 9873321045, email demo.ngo@example.org, pass Demo@123)
        [null, 'Demo NGO Social Auditor', 'demo.ngo@example.org', '9873321045', hashPass('Demo@123'), 'NGO', $stateIds['MH'], $distIds['Nagpur'], null, null, 0, 'ACTIVE'],
        [null, 'Sewa Bharati Representative', 'ngo.sewa@org.in', '9100000021', hashPass('Ngo@12345'), 'NGO', $stateIds['MH'], $distIds['Nagpur'], null, null, 0, 'ACTIVE'],
        [null, 'Samaj Vikas Representative', 'ngo.samaj@org.in', '9100000022', hashPass('Ngo@12345'), 'NGO', $stateIds['UP'], $distIds['Lucknow'], null, null, 0, 'ACTIVE'],
        [null, 'Gramin Kalyan Representative', 'ngo.gramin@org.in', '9100000023', hashPass('Ngo@12345'), 'NGO', $stateIds['MH'], $distIds['Pune'], null, null, 0, 'ACTIVE'],

        // Public Citizen Users (Task 12: mobile 9821004567, OTP-based login)
        [null, 'Citizen (Demo 9821004567)', 'demo.citizen@example.org', '9821004567', null, 'PUBLIC', null, null, null, null, 0, 'ACTIVE'],
        [null, 'Ramesh Kumar (Citizen)', 'ramesh.citizen@gmail.com', '9876543210', null, 'PUBLIC', null, null, null, null, 0, 'ACTIVE'],
        [null, 'Ananya Sen (Citizen)', 'ananya.sen@gmail.com', '9876543211', null, 'PUBLIC', null, null, null, null, 0, 'ACTIVE'],
        [null, 'Sunil Yadav (Citizen)', 'sunil.yadav@gmail.com', '9876543212', null, 'PUBLIC', null, null, null, null, 0, 'ACTIVE'],
    ];

    $userIds = [];
    foreach ($usersData as $u) {
        $stmtUser->execute([
            ':username' => $u[0],
            ':name' => $u[1],
            ':email' => $u[2],
            ':phone' => $u[3],
            ':password_hash' => $u[4],
            ':role' => $u[5],
            ':state_id' => $u[6],
            ':district_id' => $u[7],
            ':gov_id' => $u[8],
            ':lgd_code' => $u[9],
            ':must_change_password' => $u[10],
            ':status' => $u[11],
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $uid = (int)$pdo->lastInsertId();
        $userIds[$u[2]] = $uid;
        if (!empty($u[0])) {
            $userIds[$u[0]] = $uid;
        }
    }
    echo " -> Seeded " . count($usersData) . " Users (Master Admin, MoSJE Admin, State, District, Inspectors, NGOs, Public)\n";

    // 4. NGOS
    $stmtNgo = $pdo->prepare("
        INSERT INTO ngos (user_id, organization_name, registration_number, contact_person, mobile, email, address, district_id, status, decision_reason, decided_by, decided_at, created_at)
        VALUES (:user_id, :organization_name, :registration_number, :contact_person, :mobile, :email, :address, :district_id, :status, :decision_reason, :decided_by, :decided_at, :created_at)
    ");
    $ngosData = [
        [$userIds['demo.ngo@example.org'], 'Jan Jagriti Social Foundation', 'NGO-MH-2023-8821', 'Ramesh Varma', '9873321045', 'demo.ngo@example.org', 'Civil Lines, Nagpur, MH', $distIds['Nagpur'], 'APPROVED', 'Verified Government Darpan & 80G compliant.', $userIds['district.nagpur@mosje.gov.in'], $now],
        [$userIds['ngo.sewa@org.in'], 'Sewa Bharati Trust', 'NGO-MH-2021-9981', 'Gopal Rao', '9100000021', 'ngo.sewa@org.in', 'Plot 45, Sitabuldi, Nagpur, MH', $distIds['Nagpur'], 'APPROVED', 'Verified 80G and Darpan registration valid.', $userIds['district.nagpur@mosje.gov.in'], $now],
        [$userIds['ngo.samaj@org.in'], 'Samaj Vikas Sanstha', 'NGO-UP-2022-7712', 'Vandana Shukla', '9100000022', 'ngo.samaj@org.in', '12 Gomti Nagar, Lucknow, UP', $distIds['Lucknow'], 'APPROVED', 'Credible track record in social audits.', $userIds['district.lucknow@mosje.gov.in'], $now],
        [$userIds['ngo.gramin@org.in'], 'Gramin Kalyan Samiti', 'NGO-MH-2024-1104', 'Mahesh Kale', '9100000023', 'ngo.gramin@org.in', 'Kothrud, Pune, MH', $distIds['Pune'], 'PENDING', null, null, null],
    ];
    $ngoIds = [];
    foreach ($ngosData as $n) {
        $stmtNgo->execute([
            ':user_id' => $n[0],
            ':organization_name' => $n[1],
            ':registration_number' => $n[2],
            ':contact_person' => $n[3],
            ':mobile' => $n[4],
            ':email' => $n[5],
            ':address' => $n[6],
            ':district_id' => $n[7],
            ':status' => $n[8],
            ':decision_reason' => $n[9],
            ':decided_by' => $n[10],
            ':decided_at' => $n[11],
            ':created_at' => $now,
        ]);
        $ngoIds[$n[1]] = (int)$pdo->lastInsertId();
    }
    echo " -> Seeded 3 NGOs (2 Approved, 1 PENDING)\n";

    // 5. CONTRACTORS
    $stmtContractor = $pdo->prepare("
        INSERT INTO contractors (name, registration_number, contact_person, phone, email, address, created_at)
        VALUES (:name, :registration_number, :contact_person, :phone, :email, :address, :created_at)
    ");
    $contractorsData = [
        ['Larsen & Infra Projects Ltd', 'REG-MH-2024-001', 'Rajesh Patel', '9822012345', 'rajesh@larseninfra.com', 'MIDC Hingna, Nagpur', $now],
        ['Bharat Road Builders Pvt Ltd', 'REG-MH-2023-882', 'Mohan Deshmukh', '9822054321', 'contact@bharatroads.in', 'Shivaji Nagar, Pune', $now],
        ['Ganga Construction Consortium', 'REG-UP-2022-441', 'Alok Srivastava', '9415011223', 'alok@gangaconstruct.org', 'Hazratganj, Lucknow', $now],
        ['National Welfare Projects Corp', 'REG-FED-2021-990', 'Anita Verma', '9415099887', 'info@nwpc.gov.in', 'Cantonment, Varanasi', $now],
    ];
    $contractorIds = [];
    foreach ($contractorsData as $c) {
        $stmtContractor->execute([
            ':name' => $c[0],
            ':registration_number' => $c[1],
            ':contact_person' => $c[2],
            ':phone' => $c[3],
            ':email' => $c[4],
            ':address' => $c[5],
            ':created_at' => $c[6],
        ]);
        $contractorIds[$c[0]] = (int)$pdo->lastInsertId();
    }
    echo " -> Seeded 4 Contractors\n";

    // 6. TENDER CATEGORIES
    $stmtCat = $pdo->prepare("INSERT INTO tender_categories (name, description, created_at) VALUES (:name, :desc, :created_at)");
    $cats = [
        ['Roads', 'Highways, arterial roads, and rural connectivity infrastructure'],
        ['Flyovers/Bridges', 'Elevated corridors, river bridges, and grade separators'],
        ['Buildings', 'Public administrative complexes, hostels, and residential schools'],
        ['Water supply/Sanitation', 'Piped drinking water, sewer treatment, and drainage networks'],
        ['Welfare schemes', 'MoSJE specialized equipment centers and skill workshops'],
        ['Other', 'Miscellaneous civic amenities and emergency civil works'],
    ];
    $catIds = [];
    foreach ($cats as $cat) {
        $stmtCat->execute([':name' => $cat[0], ':desc' => $cat[1], ':created_at' => $now]);
        $catIds[$cat[0]] = (int)$pdo->lastInsertId();
    }
    echo " -> Seeded 6 Tender Categories\n";

    // 7. CHECKLIST TEMPLATES
    $stmtTpl = $pdo->prepare("
        INSERT INTO quality_checklist_templates (category_id, item_name, description, is_critical, created_at)
        VALUES (:cat_id, :item, :desc, :is_critical, :created_at)
    ");
    $templatesData = [
        [$catIds['Roads'], 'Subgrade Soil Compaction Test', 'Moisture density and Proctor compaction verification', 1],
        [$catIds['Roads'], 'Bitumen Mix Temperature & Thickness', 'Core sampling check for specified bituminous layer', 1],
        [$catIds['Roads'], 'Side Drainage Alignment', 'Slope gradient and masonry joint check', 0],
        [$catIds['Roads'], 'Retro-reflective Signage & Kerb Painting', 'Visibility and spacing compliance', 0],

        [$catIds['Flyovers/Bridges'], 'Pier Cap Steel Reinforcement', 'Rebar spacing and rust treatment verification', 1],
        [$catIds['Flyovers/Bridges'], 'Prestressed Girder Load Test', 'Deflection under static and dynamic loading', 1],
        [$catIds['Flyovers/Bridges'], 'Expansion Joint Water Tightness', 'Elastomeric sealing inspection', 0],

        [$catIds['Buildings'], 'Concrete Cube Compressive Strength', '28-day laboratory curing certificate check', 1],
        [$catIds['Buildings'], 'Structural Column Plumbness', 'Vertical alignment tolerance test', 1],
        [$catIds['Buildings'], 'Conduit Safety & Earthing', 'Resistance measurement for power lines', 0],

        [$catIds['Water supply/Sanitation'], 'Hydrostatic Pressure Leak Test', 'Hold at 1.5x working pressure for 2 hours', 1],
        [$catIds['Water supply/Sanitation'], 'Treated Water Chlorination Unit', 'Dosing calibration and residual testing', 1],

        [$catIds['Welfare schemes'], 'Direct Beneficiary Ledger Audit', 'Aadhaar biometric authentication match rate', 1],
        [$catIds['Welfare schemes'], 'Accessibility Ramp & Tactile Paving', 'Compliance with Harmonised Guidelines 2021', 0],

        [$catIds['Other'], 'Material Manufacturer Test Certificate', 'Verification of batch test reports', 1],
    ];
    $templateIds = [];
    foreach ($templatesData as $tpl) {
        $stmtTpl->execute([
            ':cat_id' => $tpl[0],
            ':item' => $tpl[1],
            ':desc' => $tpl[2],
            ':is_critical' => $tpl[3],
            ':created_at' => $now,
        ]);
        $templateIds[$tpl[1]] = (int)$pdo->lastInsertId();
    }
    echo " -> Seeded " . count($templatesData) . " Quality Checklist Templates\n";

    // 8. TENDERS (8 tenders, at least 1 variance flagged, at least 1 delayed)
    $stmtTender = $pdo->prepare("
        INSERT INTO tenders (
            tender_number, title, category_id, issuing_department, state_id, district_id,
            latitude, longitude, location_note, contractor_id, sanctioned_amount, actual_spent,
            award_date, start_date, scheduled_end_date, status, responsible_senior_id,
            state_officer_id, district_officer_id,
            progress_percentage, spent_percentage, variance, variance_flag, delay_flag,
            created_by, created_at, updated_at
        ) VALUES (
            :tender_number, :title, :category_id, :issuing_department, :state_id, :district_id,
            :latitude, :longitude, :location_note, :contractor_id, :sanctioned_amount, :actual_spent,
            :award_date, :start_date, :scheduled_end_date, :status, :responsible_senior_id,
            :state_officer_id, :district_officer_id,
            :progress_percentage, :spent_percentage, :variance, :variance_flag, :delay_flag,
            :created_by, :created_at, :updated_at
        )
    ");

    $tendersData = [
        // Tender 1: Roads (Nagpur) - In Progress (45%)
        [
            'TND-2026-RD-01', 'Four-Laning of Hingna Arterial Corridor', $catIds['Roads'], 'PWD Infrastructure Division',
            $stateIds['MH'], $distIds['Nagpur'], 21.0922, 79.0012, 'Between Hingna MIDC and Outer Ring Rd',
            $contractorIds['Larsen & Infra Projects Ltd'], 45000000.0, 19800000.0,
            '2026-01-10', '2026-02-01', '2026-12-31', 'IN_PROGRESS', $userIds['senior.sharma@mosje.gov.in'],
            45.0, 44.0, -1.0, 0, 0, $userIds['district.nagpur@mosje.gov.in']
        ],
        // Tender 2: Flyovers (Pune) - In Progress (60%)
        [
            'TND-2026-FL-02', 'Shivaji Nagar Elevated Grade Separator', $catIds['Flyovers/Bridges'], 'Urban Development Dept',
            $stateIds['MH'], $distIds['Pune'], 18.5314, 73.8446, 'Intersection over Railway Junction',
            $contractorIds['Bharat Road Builders Pvt Ltd'], 82000000.0, 49200000.0,
            '2025-11-15', '2025-12-01', '2026-11-30', 'IN_PROGRESS', $userIds['senior.sharma@mosje.gov.in'],
            60.0, 60.0, 0.0, 0, 0, $userIds['district.pune@mosje.gov.in']
        ],
        // Tender 3: Bridge (Varanasi) - DELAYED (End date passed, progress only 40%, delay_flag = 1)
        [
            'TND-2026-BR-03', 'Varuna River Submersible Bridge', $catIds['Flyovers/Bridges'], 'Bridge Corporation UP',
            $stateIds['UP'], $distIds['Varanasi'], 25.3216, 82.9876, 'Connecting Rajghat to Northern Bypass',
            $contractorIds['Ganga Construction Consortium'], 36000000.0, 15840000.0,
            '2025-06-01', '2025-07-01', '2026-08-31', 'DELAYED', $userIds['senior.verma@mosje.gov.in'],
            40.0, 44.0, 4.0, 0, 1, $userIds['admin@mosje.gov.in']
        ],
        // Tender 4: Building (Lucknow) - VARIANCE FLAGGED (Sanctioned 1.5 Cr, Spent 1.05 Cr (70%), Progress 40% -> Variance = +30% > 10% threshold!)
        [
            'TND-2026-BL-04', 'MoSJE Composite Rehabilitation Centre', $catIds['Buildings'], 'MoSJE Engineering Wing',
            $stateIds['UP'], $distIds['Lucknow'], 26.8522, 80.9462, 'Sector 7, Gomti Nagar Extension',
            $contractorIds['Ganga Construction Consortium'], 15000000.0, 10500000.0,
            '2026-01-05', '2026-01-20', '2026-10-31', 'IN_PROGRESS', $userIds['senior.verma@mosje.gov.in'],
            40.0, 70.0, 30.0, 1, 0, $userIds['district.lucknow@mosje.gov.in']
        ],
        // Tender 5: Water Supply (Nagpur) - Awarded
        [
            'TND-2026-WS-05', 'Amravati Road Overhead Sump & Pipeline', $catIds['Water supply/Sanitation'], 'Public Health Eng Dept',
            $stateIds['MH'], $distIds['Nagpur'], 21.1458, 79.0882, 'Zone IV Pumping Station',
            $contractorIds['Larsen & Infra Projects Ltd'], 28000000.0, 2800000.0,
            '2026-08-01', '2026-08-15', '2027-04-30', 'AWARDED', $userIds['senior.sharma@mosje.gov.in'],
            10.0, 10.0, 0.0, 0, 0, $userIds['district.nagpur@mosje.gov.in']
        ],
        // Tender 6: Welfare Schemes (Pune) - Awarded
        [
            'TND-2026-WF-06', 'PM-DAKSH Divyang Skill Training Facility', $catIds['Welfare schemes'], 'Social Welfare Dept',
            $stateIds['MH'], $distIds['Pune'], 18.5204, 73.8567, 'Central Training Block, Yerwada',
            $contractorIds['National Welfare Projects Corp'], 12000000.0, 0.0,
            '2026-09-01', '2026-09-15', '2027-03-31', 'AWARDED', $userIds['senior.sharma@mosje.gov.in'],
            0.0, 0.0, 0.0, 0, 0, $userIds['district.pune@mosje.gov.in']
        ],
        // Tender 7: Roads (Lucknow) - COMPLETED
        [
            'TND-2026-RD-07', 'Shaheed Path Link Road Upgrade', $catIds['Roads'], 'Lucknow Development Authority',
            $stateIds['UP'], $distIds['Lucknow'], 26.7891, 80.9923, 'Km 12 to Km 18 Link',
            $contractorIds['Ganga Construction Consortium'], 22000000.0, 21800000.0,
            '2025-04-10', '2025-05-01', '2026-06-30', 'COMPLETED', $userIds['senior.verma@mosje.gov.in'],
            100.0, 99.09, -0.91, 0, 0, $userIds['district.lucknow@mosje.gov.in']
        ],
        // Tender 8: Buildings (Varanasi) - Awarded
        [
            'TND-2026-BL-08', 'Dr. Ambedkar Hostel Expansion Wing', $catIds['Buildings'], 'Higher Education Infrastructure',
            $stateIds['UP'], $distIds['Varanasi'], 25.3176, 82.9739, 'BHU South Campus Perimeter',
            $contractorIds['National Welfare Projects Corp'], 18000000.0, 0.0,
            '2026-09-10', '2026-10-01', '2027-05-31', 'AWARDED', $userIds['senior.verma@mosje.gov.in'],
            0.0, 0.0, 0.0, 0, 0, $userIds['admin@mosje.gov.in']
        ],
    ];

    $tenderIds = [];
    foreach ($tendersData as $td) {
        $stId = $td[4];
        $dtId = $td[5];
        $soId = ($stId === $stateIds['MH']) ? $userIds['state.mh@mosje.gov.in'] : $userIds['state.up@mosje.gov.in'];
        $doId = ($dtId === $distIds['Nagpur']) ? $userIds['district.nagpur@mosje.gov.in'] :
                (($dtId === $distIds['Pune']) ? $userIds['district.pune@mosje.gov.in'] : $userIds['district.lucknow@mosje.gov.in']);

        $stmtTender->execute([
            ':tender_number' => $td[0],
            ':title' => $td[1],
            ':category_id' => $td[2],
            ':issuing_department' => $td[3],
            ':state_id' => $stId,
            ':district_id' => $dtId,
            ':latitude' => $td[6],
            ':longitude' => $td[7],
            ':location_note' => $td[8],
            ':contractor_id' => $td[9],
            ':sanctioned_amount' => $td[10],
            ':actual_spent' => $td[11],
            ':award_date' => $td[12],
            ':start_date' => $td[13],
            ':scheduled_end_date' => $td[14],
            ':status' => $td[15],
            ':responsible_senior_id' => $td[16],
            ':state_officer_id' => $soId,
            ':district_officer_id' => $doId,
            ':progress_percentage' => $td[17],
            ':spent_percentage' => $td[18],
            ':variance' => $td[19],
            ':variance_flag' => $td[20],
            ':delay_flag' => $td[21],
            ':created_by' => $td[22],
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $tenderIds[$td[0]] = (int)$pdo->lastInsertId();
    }
    echo " -> Seeded 8 Tenders (including 1 variance-flagged tender and 1 delayed tender)\n";

    // 9. ATTACH INSPECTORS & NGOS TO TENDERS
    $stmtAttachInsp = $pdo->prepare("INSERT INTO tender_inspectors (tender_id, inspector_id, assigned_at) VALUES (:tid, :iid, :now)");
    $stmtAttachNgo = $pdo->prepare("INSERT INTO tender_ngos (tender_id, ngo_id, assigned_by, assigned_at) VALUES (:tid, :nid, :by, :now)");

    // Attach Rajesh & Vikram to Tender 1 (Nagpur)
    $stmtAttachInsp->execute([':tid' => $tenderIds['TND-2026-RD-01'], ':iid' => $userIds['inspector.rajesh@mosje.gov.in'], ':now' => $now]);
    $stmtAttachInsp->execute([':tid' => $tenderIds['TND-2026-RD-01'], ':iid' => $userIds['inspector.vikram@mosje.gov.in'], ':now' => $now]);
    // Attach Priya to Tender 2 (Pune)
    $stmtAttachInsp->execute([':tid' => $tenderIds['TND-2026-FL-02'], ':iid' => $userIds['inspector.priya@mosje.gov.in'], ':now' => $now]);
    // Attach Amit to Tender 4 (Lucknow)
    $stmtAttachInsp->execute([':tid' => $tenderIds['TND-2026-BL-04'], ':iid' => $userIds['inspector.amit@mosje.gov.in'], ':now' => $now]);
    // Attach Sunita to Tender 3 (Varanasi)
    $stmtAttachInsp->execute([':tid' => $tenderIds['TND-2026-BR-03'], ':iid' => $userIds['inspector.sunita@mosje.gov.in'], ':now' => $now]);

    // Attach Approved NGOs
    $stmtAttachNgo->execute([':tid' => $tenderIds['TND-2026-RD-01'], ':nid' => $ngoIds['Sewa Bharati Trust'], ':by' => $userIds['district.nagpur@mosje.gov.in'], ':now' => $now]);
    $stmtAttachNgo->execute([':tid' => $tenderIds['TND-2026-BL-04'], ':nid' => $ngoIds['Samaj Vikas Sanstha'], ':by' => $userIds['district.lucknow@mosje.gov.in'], ':now' => $now]);
    echo " -> Attached Inspectors and NGOs to Tenders\n";

    // 10. MILESTONES FOR TENDERS
    $stmtMilestone = $pdo->prepare("
        INSERT INTO milestones (tender_id, name, planned_date, planned_weight, actual_progress, created_at)
        VALUES (:tid, :name, :pdate, :weight, :prog, :created_at)
    ");
    $milestonesData = [
        // Tender 1 (Nagpur Roads)
        [$tenderIds['TND-2026-RD-01'], 'Earthwork and Subgrade Embankment', '2026-04-30', 25.0, 100.0],
        [$tenderIds['TND-2026-RD-01'], 'Granular Sub-Base & Wet Mix Macadam', '2026-07-31', 30.0, 66.67], // 30% * 0.6667 = 20%
        [$tenderIds['TND-2026-RD-01'], 'Dense Bituminous Macadam Surface', '2026-10-31', 35.0, 0.0],
        [$tenderIds['TND-2026-RD-01'], 'Road Furniture, Markings & Drainage', '2026-12-15', 10.0, 0.0],

        // Tender 4 (Lucknow Building)
        [$tenderIds['TND-2026-BL-04'], 'Excavation & Raft Foundation', '2026-03-31', 20.0, 100.0],
        [$tenderIds['TND-2026-BL-04'], 'RCC Superstructure up to 3rd Floor', '2026-06-30', 30.0, 66.67], // 20%
        [$tenderIds['TND-2026-BL-04'], 'Brickwork, Plaster & MEP Rough-in', '2026-08-31', 25.0, 0.0],
        [$tenderIds['TND-2026-BL-04'], 'Flooring, Painting & Handover', '2026-10-15', 25.0, 0.0],
    ];
    foreach ($milestonesData as $m) {
        $stmtMilestone->execute([
            ':tid' => $m[0],
            ':name' => $m[1],
            ':pdate' => $m[2],
            ':weight' => $m[3],
            ':prog' => $m[4],
            ':created_at' => $now,
        ]);
    }
    echo " -> Seeded Milestones for Tenders\n";

    // 11. 12 INSPECTIONS ACROSS VARIED STATUSES
    $stmtInsp = $pdo->prepare("
        INSERT INTO inspections (
            tender_id, inspector_id, status, overall_progress, actual_spent_recorded,
            spent_basis, remarks, issue_found, severity, officer_remarks, verified_by,
            verified_at, is_reinspection, parent_inspection_id, created_at, submitted_at, updated_at
        ) VALUES (
            :tid, :iid, :status, :progress, :spent,
            :basis, :remarks, :issue, :severity, :off_remarks, :vby,
            :vat, :is_re, :parent, :created_at, :sub_at, :updated_at
        )
    ");

    $inspectionsData = [
        // 1. DRAFT
        [$tenderIds['TND-2026-RD-01'], $userIds['inspector.rajesh@mosje.gov.in'], 'DRAFT', 45.0, 19800000.0, 'Measurement Book Vol 3, p.42', 'Initial draft measurements taken on chainage 4+200', 0, 'NONE', null, null, null, 0, null],
        // 2. SUBMITTED
        [$tenderIds['TND-2026-RD-01'], $userIds['inspector.vikram@mosje.gov.in'], 'SUBMITTED', 45.0, 19800000.0, 'MB Book #89', 'Side drainage cross-section verified.', 0, 'NONE', null, null, null, 0, null],
        // 3. VERIFIED
        [$tenderIds['TND-2026-RD-01'], $userIds['inspector.rajesh@mosje.gov.in'], 'VERIFIED', 35.0, 15000000.0, 'Interim Bill #2', 'Earthwork inspected and verified.', 0, 'NONE', 'Satisfactory compaction report.', $userIds['district.nagpur@mosje.gov.in'], $now, 0, null],
        // 4. ISSUE_RAISED (On Tender 4)
        [$tenderIds['TND-2026-BL-04'], $userIds['inspector.amit@mosje.gov.in'], 'ISSUE_RAISED', 40.0, 10500000.0, 'Bill #4', 'Severe honeycomb voids found on Grid C column.', 1, 'HIGH', 'Urgent structural grouting required.', $userIds['district.lucknow@mosje.gov.in'], $now, 0, null],
        // 5. NOTIFIED
        [$tenderIds['TND-2026-BL-04'], $userIds['inspector.amit@mosje.gov.in'], 'NOTIFIED', 40.0, 10500000.0, 'Bill #4', 'Formal defect notice dispatched to contractor.', 1, 'HIGH', 'Notice served to Ganga Construction.', $userIds['district.lucknow@mosje.gov.in'], $now, 0, null],
        // 6. IN_RESOLUTION
        [$tenderIds['TND-2026-BL-04'], $userIds['inspector.amit@mosje.gov.in'], 'IN_RESOLUTION', 40.0, 10500000.0, 'Bill #4', 'Contractor deployed micro-concrete grouting team.', 1, 'HIGH', 'Resolution underway on site.', $userIds['district.lucknow@mosje.gov.in'], $now, 0, null],
        // 7. REINSPECTION_PENDING
        [$tenderIds['TND-2026-BL-04'], $userIds['inspector.amit@mosje.gov.in'], 'REINSPECTION_PENDING', 40.0, 10500000.0, 'Bill #4', 'Grouting complete. Scheduled for ultrasound pulse velocity check.', 1, 'HIGH', 'Inspector assigned for reinspection.', $userIds['district.lucknow@mosje.gov.in'], $now, 0, null],
        // 8. CLOSED (Reinspection passed)
        [$tenderIds['TND-2026-RD-07'], $userIds['inspector.amit@mosje.gov.in'], 'CLOSED', 100.0, 21800000.0, 'Final Bill & Completion Cert', 'All punch list defects cured. Final signoff.', 0, 'NONE', 'Approved for final payment.', $userIds['district.lucknow@mosje.gov.in'], $now, 1, 4],
        // 9. CLOSED (Clean initial inspection)
        [$tenderIds['TND-2026-RD-07'], $userIds['inspector.amit@mosje.gov.in'], 'CLOSED', 90.0, 19000000.0, 'Bill #7', 'Kerb painting and retroreflective studs verified.', 0, 'NONE', 'Inspection verified and closed.', $userIds['district.lucknow@mosje.gov.in'], $now, 0, null],
        // 10. SUBMITTED (Pune Flyover)
        [$tenderIds['TND-2026-FL-02'], $userIds['inspector.priya@mosje.gov.in'], 'SUBMITTED', 60.0, 49200000.0, 'MB #14', 'Girder segment 12 placed and prestressed.', 0, 'NONE', null, null, null, 0, null],
        // 11. VERIFIED (Pune Flyover)
        [$tenderIds['TND-2026-FL-02'], $userIds['inspector.priya@mosje.gov.in'], 'VERIFIED', 50.0, 41000000.0, 'Interim Bill #5', 'Pier 8 to Pier 11 bearings verified.', 0, 'NONE', 'Verified on site.', $userIds['district.pune@mosje.gov.in'], $now, 0, null],
        // 12. ISSUE_RAISED (Varanasi Bridge)
        [$tenderIds['TND-2026-BR-03'], $userIds['inspector.sunita@mosje.gov.in'], 'ISSUE_RAISED', 40.0, 15840000.0, 'MB #22', 'Approach embankment erosion during monsoon.', 1, 'CRITICAL', 'Work halted due to unstable approach.', $userIds['state.up@mosje.gov.in'], $now, 0, null],
    ];

    $inspectionIds = [];
    foreach ($inspectionsData as $idx => $ins) {
        $stmtInsp->execute([
            ':tid' => $ins[0],
            ':iid' => $ins[1],
            ':status' => $ins[2],
            ':progress' => $ins[3],
            ':spent' => $ins[4],
            ':basis' => $ins[5],
            ':remarks' => $ins[6],
            ':issue' => $ins[7],
            ':severity' => $ins[8],
            ':off_remarks' => $ins[9],
            ':vby' => $ins[10],
            ':vat' => $ins[11],
            ':is_re' => $ins[12],
            ':parent' => $ins[13],
            ':created_at' => $now,
            ':sub_at' => in_array($ins[2], ['DRAFT']) ? null : $now,
            ':updated_at' => $now,
        ]);
        $inspectionIds[] = (int)$pdo->lastInsertId();
    }
    echo " -> Seeded 12 Inspections across all lifecycle states\n";

    // 12. EVIDENCE ITEMS (with GPS, watermark metadata, timestamps)
    $stmtItem = $pdo->prepare("
        INSERT INTO inspection_items (
            inspection_id, file_path, file_type, latitude, longitude, accuracy,
            device_capture_time, server_time, mock_flag, time_mismatch, file_size, created_at
        ) VALUES (
            :iid, :path, :type, :lat, :lng, :acc,
            :dtime, :stime, :mock, :mismatch, :size, :created_at
        )
    ");
    // Seed evidence items for inspection #4 (Issue raised) and #3 (Verified)
    $stmtItem->execute([
        ':iid' => $inspectionIds[3], // Inspection #4
        ':path' => 'storage/media/evidence_tnd4_column_defect.jpg',
        ':type' => 'PHOTO',
        ':lat' => 26.8522,
        ':lng' => 80.9462,
        ':acc' => 12.5,
        ':dtime' => $now,
        ':stime' => $now,
        ':mock' => 0,
        ':mismatch' => 0,
        ':size' => 184520,
        ':created_at' => $now,
    ]);

    $stmtItem->execute([
        ':iid' => $inspectionIds[2], // Inspection #3
        ':path' => 'storage/media/evidence_tnd1_earthwork.jpg',
        ':type' => 'PHOTO',
        ':lat' => 21.0922,
        ':lng' => 79.0012,
        ':acc' => 8.2,
        ':dtime' => $now,
        ':stime' => $now,
        ':mock' => 0,
        ':mismatch' => 0,
        ':size' => 245010,
        ':created_at' => $now,
    ]);
    echo " -> Seeded Inspection Evidence Items\n";

    // 13. ISSUE RECORD
    $stmtIssue = $pdo->prepare("
        INSERT INTO issues (inspection_id, tender_id, quality_check_id, title, description, severity, status, raised_by, created_at)
        VALUES (:iid, :tid, :qid, :title, :desc, :sev, :status, :by, :created_at)
    ");
    $stmtIssue->execute([
        ':iid' => $inspectionIds[3],
        ':tid' => $tenderIds['TND-2026-BL-04'],
        ':qid' => null,
        ':title' => 'Structural Honeycombing on Grid C Column',
        ':desc' => 'Extensive voiding exposing rebar; requires non-shrink grout injection and 7-day cube testing.',
        ':sev' => 'HIGH',
        ':status' => 'IN_RESOLUTION',
        ':by' => $userIds['inspector.amit@mosje.gov.in'],
        ':created_at' => $now,
    ]);
    $seededIssueId = (int)$pdo->lastInsertId();
    echo " -> Seeded Issue Record\n";

    // 14. COMPLAINT & ESCALATION WITH PAYMENT RECORD
    $stmtComplaint = $pdo->prepare("
        INSERT INTO complaints (tender_id, ngo_id, issue_id, subject, description, current_level, current_assignee_id, status, resolution_remarks, created_at, updated_at)
        VALUES (:tid, :nid, :iid, :subj, :desc, :lvl, :assignee, :status, :rem, :created_at, :updated_at)
    ");
    $stmtComplaint->execute([
        ':tid' => $tenderIds['TND-2026-BL-04'],
        ':nid' => $ngoIds['Samaj Vikas Sanstha'],
        ':iid' => $seededIssueId,
        ':subj' => 'Substandard Concrete Quality at CRC Building',
        ':desc' => 'Severe honeycombing poses safety hazard to disabled visitors. Contractor failed to cure properly.',
        ':lvl' => 'DISTRICT_OFFICER',
        ':assignee' => $userIds['district.lucknow@mosje.gov.in'],
        ':status' => 'ESCALATED',
        ':rem' => 'Contractor senior rejected complaint. NGO escalated to District Officer with fee payment.',
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
    $complaintId = (int)$pdo->lastInsertId();

    // Payment for Escalation: ₹400 + 18% GST (₹72) = ₹472
    $stmtPay = $pdo->prepare("
        INSERT INTO payments (order_id, user_id, amount, gst_amount, total_amount, currency, status, method, transaction_id, metadata, created_at, verified_at)
        VALUES (:oid, :uid, :amount, :gst, :total, :curr, :status, :method, :txnid, :meta, :created_at, :vat)
    ");
    $orderId = 'DEMO_ORD_' . strtoupper(bin2hex(random_bytes(6)));
    $stmtPay->execute([
        ':oid' => $orderId,
        ':uid' => $userIds['ngo.samaj@org.in'],
        ':amount' => 400.00,
        ':gst' => 72.00,
        ':total' => 472.00,
        ':curr' => 'INR',
        ':status' => 'SUCCESS',
        ':method' => 'UPI',
        ':txnid' => 'DEMO_TXN_998124',
        ':meta' => json_encode(['complaint_id' => $complaintId, 'target_level' => 'DISTRICT_OFFICER']),
        ':created_at' => $now,
        ':vat' => $now,
    ]);
    $paymentId = (int)$pdo->lastInsertId();

    $stmtEsc = $pdo->prepare("
        INSERT INTO escalations (complaint_id, from_level, to_level, payment_id, fee_amount, gst_amount, total_amount, reason, status, created_at)
        VALUES (:cid, :from_lvl, :to_lvl, :pid, :fee, :gst, :total, :reason, :status, :created_at)
    ");
    $stmtEsc->execute([
        ':cid' => $complaintId,
        ':from_lvl' => 'SENIOR_OFFICER',
        ':to_lvl' => 'DISTRICT_OFFICER',
        ':pid' => $paymentId,
        ':fee' => 400.00,
        ':gst' => 72.00,
        ':total' => 472.00,
        ':reason' => 'Contractor response was dismissive. Immediate third-party ultrasound testing is essential.',
        ':status' => 'IN_REVIEW',
        ':created_at' => $now,
    ]);
    echo " -> Seeded Complaint, Payment (₹472), and Escalation\n";

    // 15. NOTIFICATIONS
    $stmtNotif = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, type, entity_type, entity_id, is_read, created_at)
        VALUES (:uid, :title, :msg, :type, :etype, :eid, :is_read, :created_at)
    ");
    $stmtNotif->execute([
        ':uid' => $userIds['district.lucknow@mosje.gov.in'],
        ':title' => 'Escalated Complaint Received',
        ':msg' => 'Samaj Vikas Sanstha has escalated complaint #1 with paid fee for CRC Building project.',
        ':type' => 'COMPLAINT_ESCALATED',
        ':etype' => 'complaint',
        ':eid' => $complaintId,
        ':is_read' => 0,
        ':created_at' => $now,
    ]);

    $stmtNotif->execute([
        ':uid' => $userIds['ngo.samaj@org.in'],
        ':title' => 'Escalation Payment Confirmed',
        ':msg' => 'Payment of ₹472.00 received. Order ' . $orderId . ' forwarded to District Officer.',
        ':type' => 'PAYMENT_RECEIPT',
        ':etype' => 'payment',
        ':eid' => $paymentId,
        ':is_read' => 0,
        ':created_at' => $now,
    ]);
    echo " -> Seeded Notifications\n";

    // 16. GOV SYNC RECORDS (TASK 10 & 12)
    $stmtSync = $pdo->prepare("
        INSERT INTO gov_sync_records (phone, gov_id, lgd_code, email, official_name, jurisdiction, role, created_at)
        VALUES (:phone, :gov_id, :lgd_code, :email, :official_name, :jurisdiction, :role, :created_at)
    ");
    $syncData = [
        ['9543210876', 'GOV-CENTRAL-001', null, 'mosje.admin@gov.in', 'MoSJE Central Administrator', 'National Central MoSJE', 'MOSJE_ADMIN'],
        ['9654321087', null, 'ST-LGD-024', 'state.demo@mosje.gov.in', 'State Officer (ST-LGD-024)', 'Maharashtra State', 'STATE_OFFICER'],
        ['9765432190', null, 'DL-LGD-478', 'district.demo@mosje.gov.in', 'District Officer (DL-LGD-478)', 'Nagpur District', 'DISTRICT_OFFICER'],
        ['9900112233', null, null, 'inspector.demo@mosje.gov.in', 'Field Inspector (Auto-Reg)', 'Nagpur Division', 'INSPECTOR'],
    ];
    foreach ($syncData as $sd) {
        $stmtSync->execute([
            ':phone' => $sd[0],
            ':gov_id' => $sd[1],
            ':lgd_code' => $sd[2],
            ':email' => $sd[3],
            ':official_name' => $sd[4],
            ':jurisdiction' => $sd[5],
            ':role' => $sd[6],
            ':created_at' => $now,
        ]);
    }
    echo " -> Seeded " . count($syncData) . " Gov ID & LGD Sync Records\n";

    // 17. INSPECTION CODES (TASK 11)
    // Format: [first 4 letters of inspector's first name][generation year, 4 digits][generation month, 3-letter uppercase][numeric project code]
    $stmtCode = $pdo->prepare("
        INSERT INTO inspection_codes (code, tender_id, inspector_id, generated_by, is_redeemed, redeemed_at, created_at)
        VALUES (:code, :tender_id, :inspector_id, :generated_by, :is_redeemed, :redeemed_at, :created_at)
    ");

    $genYear = date('Y');
    $genMonth = strtoupper(date('M'));
    $codeRajesh = "RAJE{$genYear}{$genMonth}9725481605";
    $codeAuto = "FIEL{$genYear}{$genMonth}9725481605";

    $stmtCode->execute([
        ':code' => $codeRajesh,
        ':tender_id' => 1,
        ':inspector_id' => $userIds['inspector.rajesh@mosje.gov.in'],
        ':generated_by' => $userIds['district.nagpur@mosje.gov.in'],
        ':is_redeemed' => 0,
        ':redeemed_at' => null,
        ':created_at' => $now,
    ]);

    $stmtCode->execute([
        ':code' => $codeAuto,
        ':tender_id' => 1,
        ':inspector_id' => $userIds['inspector.demo@mosje.gov.in'],
        ':generated_by' => $userIds['district.nagpur@mosje.gov.in'],
        ':is_redeemed' => 0,
        ':redeemed_at' => null,
        ':created_at' => $now,
    ]);
    echo " -> Seeded Inspection Codes ({$codeRajesh}, {$codeAuto})\n";

    // 18. RED MARK EVIDENCE PACKAGE (TASK 5)
    // Manual Red Mark raised on Tender 4: Over Sanctioned Amount / Out of Time
    $pkgTender4 = json_encode([
        'tender_id' => 4,
        'tender_number' => 'TND-2026-BL-04',
        'title' => 'MoSJE Composite Rehabilitation Centre',
        'sanctioned_amount' => 15000000.0,
        'actual_spent' => 10500000.0,
        'spent_percentage' => 70.0,
        'progress_percentage' => 40.0,
        'variance_percentage' => 30.0,
        'days_overdue' => 0,
        'overage_amount' => 0.0,
        'flag_type' => 'OUT_OF_TIME_OVER_SANCTIONED_AMOUNT',
        'flagged_by_role' => 'DISTRICT_OFFICER',
        'reason' => 'Expenditure variance exceeds 30% against slow 40% physical progress. Evidentiary audit package compiled for disciplinary escalation.',
        'timestamp' => $now,
    ]);

    $stmtRed = $pdo->prepare("
        INSERT INTO red_marks (tender_id, inspection_id, raised_by, reason, days_overdue, scheduled_end_date, sanctioned_amount, actual_spent, variance_percentage, overage_amount, status, created_at)
        VALUES (:tender_id, :inspection_id, :raised_by, :reason, :days_overdue, :scheduled_end_date, :sanctioned_amount, :actual_spent, :variance_percentage, :overage_amount, 'ACTIVE', :created_at)
    ");
    $stmtRed->execute([
        ':tender_id' => 4,
        ':inspection_id' => null,
        ':raised_by' => $userIds['district.lucknow@mosje.gov.in'],
        ':reason' => 'Expenditure variance exceeds 30% against slow 40% physical progress. Evidentiary audit package compiled for disciplinary escalation.',
        ':days_overdue' => 0,
        ':scheduled_end_date' => '2026-10-31',
        ':sanctioned_amount' => 15000000.0,
        ':actual_spent' => 10500000.0,
        ':variance_percentage' => 30.0,
        ':overage_amount' => 0.0,
        ':created_at' => $now,
    ]);

    $pdo->prepare("
        UPDATE tenders SET is_red_marked = 1, red_mark_reason = :reason, red_mark_package = :pkg, red_marked_by = :uid, red_marked_at = :now
        WHERE id = 4
    ")->execute([
        ':reason' => 'Expenditure variance exceeds 30% against slow 40% physical progress. Evidentiary audit package compiled for disciplinary escalation.',
        ':pkg' => $pkgTender4,
        ':uid' => $userIds['district.lucknow@mosje.gov.in'],
        ':now' => $now,
    ]);
    echo " -> Seeded Red Mark Evidentiary Dossier on Tender 4\n";

    $pdo->commit();
    echo "\n[SUCCESS] Seed completed cleanly and successfully!\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    echo "\n[ERROR] Seeding failed: " . $e->getMessage() . "\n";
    exit(1);
}
