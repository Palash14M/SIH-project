-- Schema for Smart Inspection MoSJE Platform (ANSI SQL: SQLite & MySQL Compatible)

CREATE TABLE IF NOT EXISTS states (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    code VARCHAR(10) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS districts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    state_id INTEGER NOT NULL,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(10) NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (state_id) REFERENCES states(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(50) UNIQUE,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE,
    phone VARCHAR(20) UNIQUE,
    password_hash VARCHAR(255),
    role VARCHAR(50) NOT NULL, -- MASTER_ADMIN, MOSJE_ADMIN, STATE_OFFICER, DISTRICT_OFFICER, INSPECTOR, NGO, PUBLIC, SENIOR_OFFICER
    state_id INTEGER,
    district_id INTEGER,
    gov_id VARCHAR(50),
    lgd_code VARCHAR(50),
    must_change_password INTEGER DEFAULT 0,
    remember_device_token VARCHAR(100),
    status VARCHAR(20) DEFAULT 'ACTIVE', -- ACTIVE, INACTIVE, SUSPENDED
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (state_id) REFERENCES states(id) ON DELETE SET NULL,
    FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS contractors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(200) NOT NULL,
    registration_number VARCHAR(100) NOT NULL UNIQUE,
    contact_person VARCHAR(150),
    phone VARCHAR(20),
    email VARCHAR(150),
    address TEXT,
    created_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS tender_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS tenders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tender_number VARCHAR(100) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    category_id INTEGER NOT NULL,
    issuing_department VARCHAR(200) NOT NULL,
    state_id INTEGER NOT NULL,
    district_id INTEGER NOT NULL,
    latitude REAL NOT NULL,
    longitude REAL NOT NULL,
    location_note TEXT,
    contractor_id INTEGER NOT NULL,
    sanctioned_amount REAL NOT NULL DEFAULT 0.0,
    actual_spent REAL NOT NULL DEFAULT 0.0,
    award_date DATE NOT NULL,
    start_date DATE NOT NULL,
    scheduled_end_date DATE NOT NULL,
    status VARCHAR(30) DEFAULT 'AWARDED', -- AWARDED, IN_PROGRESS, DELAYED, COMPLETED, CLOSED
    responsible_senior_id INTEGER NOT NULL,
    state_officer_id INTEGER,
    district_officer_id INTEGER,
    progress_percentage REAL DEFAULT 0.0,
    spent_percentage REAL DEFAULT 0.0,
    variance REAL DEFAULT 0.0,
    variance_flag INTEGER DEFAULT 0,
    delay_flag INTEGER DEFAULT 0,
    is_red_marked INTEGER DEFAULT 0,
    red_mark_reason TEXT,
    red_mark_package TEXT,
    red_marked_by INTEGER,
    red_marked_at DATETIME,
    created_by INTEGER,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (category_id) REFERENCES tender_categories(id),
    FOREIGN KEY (state_id) REFERENCES states(id),
    FOREIGN KEY (district_id) REFERENCES districts(id),
    FOREIGN KEY (contractor_id) REFERENCES contractors(id),
    FOREIGN KEY (responsible_senior_id) REFERENCES users(id),
    FOREIGN KEY (state_officer_id) REFERENCES users(id),
    FOREIGN KEY (district_officer_id) REFERENCES users(id),
    FOREIGN KEY (red_marked_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS tender_inspectors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tender_id INTEGER NOT NULL,
    inspector_id INTEGER NOT NULL,
    assigned_at DATETIME NOT NULL,
    UNIQUE(tender_id, inspector_id),
    FOREIGN KEY (tender_id) REFERENCES tenders(id) ON DELETE CASCADE,
    FOREIGN KEY (inspector_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tender_ngos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tender_id INTEGER NOT NULL,
    ngo_id INTEGER NOT NULL,
    assigned_by INTEGER NOT NULL,
    assigned_at DATETIME NOT NULL,
    UNIQUE(tender_id, ngo_id),
    FOREIGN KEY (tender_id) REFERENCES tenders(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS milestones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tender_id INTEGER NOT NULL,
    name VARCHAR(200) NOT NULL,
    planned_date DATE NOT NULL,
    planned_weight REAL NOT NULL, -- weight in percentage (sum = 100)
    actual_progress REAL DEFAULT 0.0,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (tender_id) REFERENCES tenders(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS quality_checklist_templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id INTEGER NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    description TEXT,
    is_critical INTEGER DEFAULT 0, -- 1 if failure auto-raises an issue
    created_at DATETIME NOT NULL,
    FOREIGN KEY (category_id) REFERENCES tender_categories(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS inspections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tender_id INTEGER NOT NULL,
    inspector_id INTEGER NOT NULL,
    status VARCHAR(30) DEFAULT 'DRAFT', -- DRAFT, SUBMITTED, VERIFIED, ISSUE_RAISED, NOTIFIED, IN_RESOLUTION, REINSPECTION_PENDING, CLOSED
    overall_progress REAL DEFAULT 0.0,
    actual_spent_recorded REAL DEFAULT 0.0,
    spent_basis VARCHAR(255),
    remarks TEXT,
    issue_found INTEGER DEFAULT 0,
    severity VARCHAR(20) DEFAULT 'NONE', -- NONE, LOW, MEDIUM, HIGH, CRITICAL
    officer_remarks TEXT,
    verified_by INTEGER,
    verified_at DATETIME,
    is_reinspection INTEGER DEFAULT 0,
    parent_inspection_id INTEGER,
    created_at DATETIME NOT NULL,
    submitted_at DATETIME,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (tender_id) REFERENCES tenders(id) ON DELETE CASCADE,
    FOREIGN KEY (inspector_id) REFERENCES users(id),
    FOREIGN KEY (verified_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS quality_checks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    inspection_id INTEGER NOT NULL,
    template_id INTEGER NOT NULL,
    status VARCHAR(20) DEFAULT 'PASS', -- PASS, FAIL, NA
    remarks TEXT,
    evidence_photo_path VARCHAR(255),
    created_at DATETIME NOT NULL,
    FOREIGN KEY (inspection_id) REFERENCES inspections(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES quality_checklist_templates(id)
);

CREATE TABLE IF NOT EXISTS milestone_progress (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    milestone_id INTEGER NOT NULL,
    inspection_id INTEGER NOT NULL,
    progress_percentage REAL NOT NULL,
    recorded_at DATETIME NOT NULL,
    FOREIGN KEY (milestone_id) REFERENCES milestones(id) ON DELETE CASCADE,
    FOREIGN KEY (inspection_id) REFERENCES inspections(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS budget_entries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tender_id INTEGER NOT NULL,
    inspection_id INTEGER NOT NULL,
    amount REAL NOT NULL,
    basis VARCHAR(255) NOT NULL,
    document_photo_path VARCHAR(255),
    status VARCHAR(20) DEFAULT 'ACCEPTED', -- ACCEPTED, DISPUTED
    dispute_reason TEXT,
    recorded_by INTEGER NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (tender_id) REFERENCES tenders(id) ON DELETE CASCADE,
    FOREIGN KEY (inspection_id) REFERENCES inspections(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS inspection_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    inspection_id INTEGER NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(20) NOT NULL, -- PHOTO, VIDEO
    latitude REAL NOT NULL,
    longitude REAL NOT NULL,
    accuracy REAL NOT NULL,
    device_capture_time DATETIME NOT NULL,
    server_time DATETIME NOT NULL,
    mock_flag INTEGER DEFAULT 0,
    time_mismatch INTEGER DEFAULT 0,
    file_size INTEGER DEFAULT 0,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (inspection_id) REFERENCES inspections(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS issues (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    inspection_id INTEGER NOT NULL,
    tender_id INTEGER NOT NULL,
    quality_check_id INTEGER,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    severity VARCHAR(20) DEFAULT 'MEDIUM',
    status VARCHAR(30) DEFAULT 'OPEN', -- OPEN, NOTIFIED, IN_RESOLUTION, RESOLVED, CLOSED
    raised_by INTEGER NOT NULL,
    created_at DATETIME NOT NULL,
    resolved_at DATETIME,
    FOREIGN KEY (inspection_id) REFERENCES inspections(id) ON DELETE CASCADE,
    FOREIGN KEY (tender_id) REFERENCES tenders(id) ON DELETE CASCADE,
    FOREIGN KEY (raised_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS reinspections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    issue_id INTEGER NOT NULL,
    original_inspection_id INTEGER NOT NULL,
    reinspection_id INTEGER,
    scheduled_date DATE NOT NULL,
    assigned_inspector_id INTEGER NOT NULL,
    status VARCHAR(30) DEFAULT 'SCHEDULED', -- SCHEDULED, PASSED, FAILED
    outcome_remarks TEXT,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE,
    FOREIGN KEY (original_inspection_id) REFERENCES inspections(id) ON DELETE CASCADE,
    FOREIGN KEY (reinspection_id) REFERENCES inspections(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_inspector_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS ngos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL UNIQUE,
    organization_name VARCHAR(200) NOT NULL,
    registration_number VARCHAR(100) NOT NULL UNIQUE,
    contact_person VARCHAR(150) NOT NULL,
    mobile VARCHAR(20) NOT NULL,
    email VARCHAR(150) NOT NULL,
    address TEXT NOT NULL,
    district_id INTEGER NOT NULL,
    status VARCHAR(20) DEFAULT 'PENDING', -- PENDING, APPROVED, REJECTED
    decision_reason TEXT,
    decided_by INTEGER,
    decided_at DATETIME,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (district_id) REFERENCES districts(id),
    FOREIGN KEY (decided_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS ngo_documents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ngo_id INTEGER NOT NULL,
    document_type VARCHAR(50) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_at DATETIME NOT NULL,
    FOREIGN KEY (ngo_id) REFERENCES ngos(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS complaints (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tender_id INTEGER NOT NULL,
    ngo_id INTEGER NOT NULL,
    issue_id INTEGER,
    subject VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    current_level VARCHAR(30) DEFAULT 'SENIOR_OFFICER', -- SENIOR_OFFICER, DISTRICT_OFFICER, STATE_OFFICER, MOSJE_ADMIN
    current_assignee_id INTEGER NOT NULL,
    status VARCHAR(30) DEFAULT 'PENDING', -- PENDING, IN_REVIEW, RESOLVED, REJECTED, ESCALATED
    resolution_remarks TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (tender_id) REFERENCES tenders(id) ON DELETE CASCADE,
    FOREIGN KEY (ngo_id) REFERENCES ngos(id) ON DELETE CASCADE,
    FOREIGN KEY (current_assignee_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id VARCHAR(100) NOT NULL UNIQUE,
    user_id INTEGER NOT NULL,
    amount REAL NOT NULL,
    gst_amount REAL NOT NULL,
    total_amount REAL NOT NULL,
    currency VARCHAR(10) DEFAULT 'INR',
    status VARCHAR(30) DEFAULT 'CREATED', -- CREATED, SUCCESS, FAILED
    method VARCHAR(50),
    transaction_id VARCHAR(100),
    metadata TEXT,
    created_at DATETIME NOT NULL,
    verified_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS escalations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    complaint_id INTEGER NOT NULL,
    from_level VARCHAR(30) NOT NULL,
    to_level VARCHAR(30) NOT NULL,
    payment_id INTEGER NOT NULL,
    fee_amount REAL NOT NULL,
    gst_amount REAL NOT NULL,
    total_amount REAL NOT NULL,
    reason TEXT NOT NULL,
    status VARCHAR(30) DEFAULT 'IN_REVIEW', -- IN_REVIEW, UPHELD, REJECTED
    decided_by INTEGER,
    decision_remarks TEXT,
    created_at DATETIME NOT NULL,
    resolved_at DATETIME,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES payments(id),
    FOREIGN KEY (decided_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS refunds (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    payment_id INTEGER NOT NULL,
    amount REAL NOT NULL,
    status VARCHAR(30) DEFAULT 'PROCESSED', -- PROCESSED, FAILED
    reason TEXT,
    refund_reference VARCHAR(100) NOT NULL,
    processed_at DATETIME NOT NULL,
    FOREIGN KEY (payment_id) REFERENCES payments(id)
);

CREATE TABLE IF NOT EXISTS nudges (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    ngo_id INTEGER NOT NULL,
    tender_id INTEGER NOT NULL,
    reason TEXT NOT NULL,
    status VARCHAR(30) NOT NULL, -- DELIVERED, REJECTED_INVALID_REASON
    nudge_date DATE NOT NULL, -- Format YYYY-MM-DD for enforcing 1 per day rule
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (ngo_id) REFERENCES ngos(id) ON DELETE CASCADE,
    FOREIGN KEY (tender_id) REFERENCES tenders(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'INFO',
    entity_type VARCHAR(50),
    entity_id INTEGER,
    is_read INTEGER DEFAULT 0,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    role VARCHAR(50),
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INTEGER,
    old_state VARCHAR(50),
    new_state VARCHAR(50),
    reason TEXT,
    metadata TEXT,
    created_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS otp_codes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    phone VARCHAR(20) NOT NULL,
    code VARCHAR(10) NOT NULL,
    attempts INTEGER DEFAULT 0,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    verified_at DATETIME
);

CREATE TABLE IF NOT EXISTS tender_imports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    filename VARCHAR(255) NOT NULL,
    total_rows INTEGER NOT NULL,
    created_count INTEGER NOT NULL,
    updated_count INTEGER NOT NULL,
    rejected_count INTEGER NOT NULL,
    report_json TEXT,
    imported_by INTEGER NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (imported_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS red_marks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tender_id INTEGER NOT NULL,
    inspection_id INTEGER,
    raised_by INTEGER NOT NULL,
    reason TEXT NOT NULL,
    days_overdue INTEGER NOT NULL,
    scheduled_end_date DATE,
    sanctioned_amount REAL NOT NULL,
    actual_spent REAL NOT NULL,
    variance_percentage REAL NOT NULL,
    overage_amount REAL NOT NULL,
    status VARCHAR(30) DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL,
    FOREIGN KEY (tender_id) REFERENCES tenders(id) ON DELETE CASCADE,
    FOREIGN KEY (raised_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS inspection_codes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code VARCHAR(100) UNIQUE NOT NULL,
    tender_id INTEGER NOT NULL,
    inspector_id INTEGER NOT NULL,
    generated_by INTEGER NOT NULL,
    is_redeemed INTEGER DEFAULT 0,
    redeemed_at DATETIME,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (tender_id) REFERENCES tenders(id) ON DELETE CASCADE,
    FOREIGN KEY (inspector_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS gov_sync_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    phone VARCHAR(20) NOT NULL UNIQUE,
    gov_id VARCHAR(50),
    lgd_code VARCHAR(50),
    email VARCHAR(150),
    official_name VARCHAR(150),
    jurisdiction VARCHAR(100),
    role VARCHAR(50),
    created_at DATETIME NOT NULL
);

-- Essential Performance Indexes
CREATE INDEX IF NOT EXISTS idx_tenders_district ON tenders(district_id);
CREATE INDEX IF NOT EXISTS idx_tenders_status ON tenders(status);
CREATE INDEX IF NOT EXISTS idx_tenders_category ON tenders(category_id);
CREATE INDEX IF NOT EXISTS idx_inspections_tender ON inspections(tender_id);
CREATE INDEX IF NOT EXISTS idx_inspections_status ON inspections(status);
CREATE INDEX IF NOT EXISTS idx_inspections_inspector ON inspections(inspector_id);
CREATE INDEX IF NOT EXISTS idx_nudges_user_date ON nudges(user_id, nudge_date);
CREATE INDEX IF NOT EXISTS idx_audit_entity ON audit_log(entity_type, entity_id);
CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id, is_read);
