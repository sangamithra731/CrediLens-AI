-- ============================================
-- CrediLens AI — Complete Database Schema
-- PS-001: Lending & Credit FinTech Platform
-- ============================================

CREATE DATABASE IF NOT EXISTS credilens_db;
USE credilens_db;

-- ============================================
-- MODULE 1: User & Access Management
-- ============================================

CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO roles (role_name, description) VALUES
('applicant', 'Apply for loans and track status'),
('loan_officer', 'Check applications and documents'),
('risk_analyst', 'Evaluate financial risk'),
('compliance_officer', 'Check regulatory and policy compliance'),
('admin', 'Manage users, rules and system settings');

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL DEFAULT 1,
    avatar VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    email_verified TINYINT(1) DEFAULT 0,
    otp_code VARCHAR(10) DEFAULT NULL,
    otp_expiry DATETIME DEFAULT NULL,
    last_login DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

CREATE TABLE permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    permission_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT
);

CREATE TABLE role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (permission_id) REFERENCES permissions(id)
);

INSERT INTO permissions (permission_name, description) VALUES
('view_own_applications', 'View own loan applications'),
('create_application', 'Create new loan application'),
('upload_documents', 'Upload documents for application'),
('view_all_applications', 'View all loan applications'),
('review_applications', 'Review and make decisions on applications'),
('assess_risk', 'Perform risk assessments'),
('manage_compliance', 'Manage compliance and policies'),
('manage_users', 'Manage user accounts and roles'),
('view_reports', 'View reports and analytics'),
('manage_settings', 'Manage system settings'),
('view_audit_logs', 'View audit trail'),
('manage_disbursements', 'Manage loan disbursements');

INSERT INTO role_permissions (role_id, permission_id) VALUES
(1,1),(1,2),(1,3),
(2,1),(2,3),(2,4),(2,5),(2,9),(2,12),
(3,4),(3,6),(3,9),
(4,4),(4,7),(4,9),(4,11),
(5,1),(5,4),(5,5),(5,6),(5,7),(5,8),(5,9),(5,10),(5,11),(5,12);

-- ============================================
-- MODULE 2: Loan Application Management
-- ============================================

CREATE TABLE loan_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    min_amount DECIMAL(15,2) DEFAULT 10000,
    max_amount DECIMAL(15,2) DEFAULT 5000000,
    min_tenure INT DEFAULT 6,
    max_tenure INT DEFAULT 360,
    interest_rate_min DECIMAL(5,2) DEFAULT 8.50,
    interest_rate_max DECIMAL(5,2) DEFAULT 18.00,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO loan_types (name, description, min_amount, max_amount, min_tenure, max_tenure, interest_rate_min, interest_rate_max) VALUES
('Personal Loan', 'Unsecured personal loan for various needs', 50000, 2500000, 6, 60, 10.50, 18.00),
('Home Loan', 'Loan for purchasing or constructing a home', 500000, 50000000, 60, 360, 8.50, 12.00),
('Education Loan', 'Loan for higher education expenses', 100000, 5000000, 12, 180, 9.00, 14.00),
('Vehicle Loan', 'Loan for purchasing a vehicle', 100000, 5000000, 12, 84, 9.50, 15.00),
('Business Loan', 'Loan for business expansion or working capital', 200000, 10000000, 12, 120, 11.00, 20.00);

CREATE TABLE loan_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id VARCHAR(20) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    loan_type_id INT NOT NULL,
    
    -- Personal Details
    full_name VARCHAR(150) NOT NULL,
    date_of_birth DATE,
    gender ENUM('male','female','other'),
    marital_status ENUM('single','married','divorced','widowed'),
    address_line1 VARCHAR(255),
    address_line2 VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(100),
    pincode VARCHAR(10),
    pan_number VARCHAR(20),
    aadhaar_number VARCHAR(20),
    
    -- Employment Details
    employment_type ENUM('salaried','self_employed','business','retired','student','other'),
    employer_name VARCHAR(200),
    occupation VARCHAR(150),
    years_of_experience INT DEFAULT 0,
    
    -- Financial Details
    monthly_income DECIMAL(15,2) DEFAULT 0,
    additional_income DECIMAL(15,2) DEFAULT 0,
    monthly_expenses DECIMAL(15,2) DEFAULT 0,
    existing_emi DECIMAL(15,2) DEFAULT 0,
    existing_loans_count INT DEFAULT 0,
    
    -- Loan Details
    requested_amount DECIMAL(15,2) NOT NULL,
    requested_tenure INT NOT NULL,
    loan_purpose TEXT,
    
    -- Status Tracking
    status ENUM('draft','submitted','under_review','document_verification','clarification_required','risk_assessment','compliance_check','approved','rejected','disbursement_pending','disbursed','closed','cancelled') DEFAULT 'draft',
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    
    -- Assignment
    assigned_officer_id INT DEFAULT NULL,
    assigned_analyst_id INT DEFAULT NULL,
    
    submitted_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (loan_type_id) REFERENCES loan_types(id),
    FOREIGN KEY (assigned_officer_id) REFERENCES users(id),
    FOREIGN KEY (assigned_analyst_id) REFERENCES users(id)
);

-- ============================================
-- MODULE 3: AI Document Processing
-- ============================================

CREATE TABLE document_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category ENUM('identity','income','address','bank_statement','other') NOT NULL,
    is_mandatory TINYINT(1) DEFAULT 1,
    description TEXT
);

INSERT INTO document_types (name, category, is_mandatory, description) VALUES
('PAN Card', 'identity', 1, 'Permanent Account Number card for tax identification'),
('Aadhaar Card', 'identity', 1, 'Government-issued identity document'),
('Salary Slip', 'income', 1, 'Latest 3 months salary slips'),
('Bank Statement', 'bank_statement', 1, 'Last 6 months bank statements'),
('Form 16', 'income', 0, 'Annual tax deduction certificate'),
('ITR', 'income', 0, 'Income Tax Return filing'),
('Address Proof', 'address', 1, 'Utility bill or rental agreement'),
('Employment Letter', 'income', 0, 'Employment confirmation letter'),
('Passport', 'identity', 0, 'Valid passport'),
('Voter ID', 'identity', 0, 'Voter identification card');

CREATE TABLE application_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    document_type_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT DEFAULT 0,
    mime_type VARCHAR(100),
    
    -- AI Processing
    ocr_status ENUM('pending','processing','completed','failed') DEFAULT 'pending',
    ocr_text LONGTEXT,
    extracted_data JSON,
    ai_classification VARCHAR(100),
    ai_confidence DECIMAL(5,2),
    
    -- Validation
    validation_status ENUM('pending','valid','invalid','mismatch','incomplete') DEFAULT 'pending',
    validation_errors JSON,
    
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME DEFAULT NULL,
    
    FOREIGN KEY (application_id) REFERENCES loan_applications(id) ON DELETE CASCADE,
    FOREIGN KEY (document_type_id) REFERENCES document_types(id)
);

CREATE TABLE document_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    document_id INT NOT NULL,
    field_name VARCHAR(100),
    declared_value VARCHAR(255),
    extracted_value VARCHAR(255),
    match_status ENUM('match','mismatch','not_found','partial') DEFAULT 'not_found',
    confidence DECIMAL(5,2),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (application_id) REFERENCES loan_applications(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES application_documents(id) ON DELETE CASCADE
);

-- ============================================
-- MODULE 4: Eligibility & Credit Risk Assessment
-- ============================================

CREATE TABLE risk_assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    assessed_by INT DEFAULT NULL,
    
    -- Eligibility
    age_eligible TINYINT(1) DEFAULT NULL,
    income_eligible TINYINT(1) DEFAULT NULL,
    employment_eligible TINYINT(1) DEFAULT NULL,
    document_complete TINYINT(1) DEFAULT NULL,
    overall_eligible TINYINT(1) DEFAULT NULL,
    
    -- Financial Indicators
    verified_monthly_income DECIMAL(15,2),
    total_monthly_obligations DECIMAL(15,2),
    dti_ratio DECIMAL(5,2),
    disposable_income DECIMAL(15,2),
    loan_to_income_ratio DECIMAL(5,2),
    
    -- Credit Indicators
    credit_score INT DEFAULT NULL,
    credit_score_source VARCHAR(100),
    
    -- Risk Scoring
    risk_score DECIMAL(5,2),
    risk_category ENUM('low','medium','high','very_high') DEFAULT 'medium',
    
    -- AI Analysis
    ai_risk_score DECIMAL(5,2),
    ai_recommendation ENUM('approve','review','reject') DEFAULT 'review',
    ai_explanation JSON,
    ai_flags JSON,
    
    -- Policy Compliance
    policy_checks JSON,
    compliance_status ENUM('compliant','non_compliant','review_needed') DEFAULT 'review_needed',
    
    -- Fairness
    fairness_check_passed TINYINT(1) DEFAULT NULL,
    
    assessed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (application_id) REFERENCES loan_applications(id) ON DELETE CASCADE,
    FOREIGN KEY (assessed_by) REFERENCES users(id)
);

CREATE TABLE risk_factors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    factor_name VARCHAR(100) NOT NULL,
    factor_value VARCHAR(255),
    impact ENUM('positive','negative','neutral') DEFAULT 'neutral',
    weight DECIMAL(5,2) DEFAULT 1.00,
    explanation TEXT,
    evidence_source VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (assessment_id) REFERENCES risk_assessments(id) ON DELETE CASCADE
);

-- ============================================
-- MODULE 5: Credit Review & Decision
-- ============================================

CREATE TABLE application_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    review_type ENUM('document_review','risk_review','compliance_review','final_review') NOT NULL,
    
    decision ENUM('approve','reject','clarify','escalate','hold') DEFAULT NULL,
    decision_reason TEXT,
    conditions TEXT,
    
    notes TEXT,
    reviewed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (application_id) REFERENCES loan_applications(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id)
);

CREATE TABLE clarification_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    requested_by INT NOT NULL,
    
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    required_documents JSON,
    
    status ENUM('pending','responded','resolved','expired') DEFAULT 'pending',
    response TEXT,
    response_at DATETIME DEFAULT NULL,
    
    due_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (application_id) REFERENCES loan_applications(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id)
);

-- ============================================
-- MODULE 6: Loan Disbursement & Repayment
-- ============================================

CREATE TABLE loan_offers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    
    approved_amount DECIMAL(15,2) NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL,
    tenure_months INT NOT NULL,
    emi_amount DECIMAL(15,2) NOT NULL,
    total_interest DECIMAL(15,2),
    total_payable DECIMAL(15,2),
    processing_fee DECIMAL(15,2) DEFAULT 0,
    
    offer_status ENUM('generated','sent','accepted','rejected','expired') DEFAULT 'generated',
    accepted_at DATETIME DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (application_id) REFERENCES loan_applications(id) ON DELETE CASCADE
);

CREATE TABLE loan_agreements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    offer_id INT NOT NULL,
    
    agreement_number VARCHAR(50) NOT NULL UNIQUE,
    agreement_document VARCHAR(500),
    
    e_signed TINYINT(1) DEFAULT 0,
    e_signed_at DATETIME DEFAULT NULL,
    e_signature_hash VARCHAR(255),
    
    final_verification_status ENUM('pending','passed','failed') DEFAULT 'pending',
    final_verified_by INT DEFAULT NULL,
    final_verified_at DATETIME DEFAULT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (application_id) REFERENCES loan_applications(id) ON DELETE CASCADE,
    FOREIGN KEY (offer_id) REFERENCES loan_offers(id),
    FOREIGN KEY (final_verified_by) REFERENCES users(id)
);

CREATE TABLE disbursements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    agreement_id INT NOT NULL,
    
    disbursement_amount DECIMAL(15,2) NOT NULL,
    disbursement_date DATE NOT NULL,
    disbursement_mode ENUM('bank_transfer','cheque','demand_draft') DEFAULT 'bank_transfer',
    reference_number VARCHAR(100),
    bank_account_number VARCHAR(50),
    bank_name VARCHAR(150),
    ifsc_code VARCHAR(20),
    
    status ENUM('pending','processing','completed','failed','reversed') DEFAULT 'pending',
    completed_at DATETIME DEFAULT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (application_id) REFERENCES loan_applications(id) ON DELETE CASCADE,
    FOREIGN KEY (agreement_id) REFERENCES loan_agreements(id)
);

CREATE TABLE emi_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    
    installment_number INT NOT NULL,
    due_date DATE NOT NULL,
    emi_amount DECIMAL(15,2) NOT NULL,
    principal_component DECIMAL(15,2),
    interest_component DECIMAL(15,2),
    outstanding_balance DECIMAL(15,2),
    
    payment_status ENUM('upcoming','due','paid','overdue','partial') DEFAULT 'upcoming',
    paid_amount DECIMAL(15,2) DEFAULT 0,
    paid_date DATE DEFAULT NULL,
    payment_reference VARCHAR(100),
    
    late_fee DECIMAL(10,2) DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (application_id) REFERENCES loan_applications(id) ON DELETE CASCADE
);

-- ============================================
-- MODULE 7: Notifications & Tracking
-- ============================================

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    application_id INT DEFAULT NULL,
    
    type ENUM('info','success','warning','error','reminder') DEFAULT 'info',
    category ENUM('application','document','review','payment','system') DEFAULT 'system',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    action_url VARCHAR(500),
    
    is_read TINYINT(1) DEFAULT 0,
    read_at DATETIME DEFAULT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (application_id) REFERENCES loan_applications(id) ON DELETE SET NULL
);

CREATE TABLE application_timeline (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    
    event_type VARCHAR(100) NOT NULL,
    event_title VARCHAR(255) NOT NULL,
    event_description TEXT,
    event_data JSON,
    
    performed_by INT DEFAULT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (application_id) REFERENCES loan_applications(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id)
);

-- ============================================
-- MODULE 8-9: Admin, Compliance & Audit
-- ============================================

CREATE TABLE lending_policies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    policy_name VARCHAR(150) NOT NULL,
    policy_key VARCHAR(100) NOT NULL UNIQUE,
    policy_value TEXT NOT NULL,
    data_type ENUM('number','string','boolean','json') DEFAULT 'string',
    category VARCHAR(100),
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    updated_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

INSERT INTO lending_policies (policy_name, policy_key, policy_value, data_type, category, description) VALUES
('Maximum DTI Ratio', 'max_dti_ratio', '50', 'number', 'eligibility', 'Maximum allowed debt-to-income ratio (%)'),
('Minimum Age', 'min_age', '21', 'number', 'eligibility', 'Minimum applicant age in years'),
('Maximum Age', 'max_age', '65', 'number', 'eligibility', 'Maximum applicant age in years'),
('Minimum Monthly Income', 'min_monthly_income', '15000', 'number', 'eligibility', 'Minimum monthly income required'),
('Minimum Employment Tenure', 'min_employment_years', '1', 'number', 'eligibility', 'Minimum years of employment'),
('High Risk DTI Threshold', 'high_risk_dti', '40', 'number', 'risk', 'DTI ratio above which risk is flagged high'),
('Auto Reject DTI Threshold', 'auto_reject_dti', '60', 'number', 'risk', 'DTI ratio above which application is auto-flagged for rejection'),
('Risk Score Low Threshold', 'risk_low_threshold', '30', 'number', 'risk', 'Risk score below this is low risk'),
('Risk Score High Threshold', 'risk_high_threshold', '70', 'number', 'risk', 'Risk score above this is high risk'),
('Loan Offer Validity Days', 'offer_validity_days', '30', 'number', 'process', 'Number of days a loan offer remains valid');

CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT DEFAULT NULL,
    
    old_value JSON,
    new_value JSON,
    
    ip_address VARCHAR(45),
    user_agent TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE compliance_checks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    check_type VARCHAR(100) NOT NULL,
    check_name VARCHAR(200) NOT NULL,
    status ENUM('passed','failed','warning','pending') DEFAULT 'pending',
    details TEXT,
    checked_by INT DEFAULT NULL,
    checked_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (application_id) REFERENCES loan_applications(id) ON DELETE CASCADE,
    FOREIGN KEY (checked_by) REFERENCES users(id)
);

-- ============================================
-- MODULE 10: External Integrations (Mock)
-- ============================================

CREATE TABLE external_api_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT DEFAULT NULL,
    service_name VARCHAR(100) NOT NULL,
    endpoint VARCHAR(500),
    request_data JSON,
    response_data JSON,
    status_code INT,
    is_mock TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (application_id) REFERENCES loan_applications(id) ON DELETE SET NULL
);

-- ============================================
-- Indexes for Performance
-- ============================================

CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role_id);
CREATE INDEX idx_applications_user ON loan_applications(user_id);
CREATE INDEX idx_applications_status ON loan_applications(status);
CREATE INDEX idx_applications_appid ON loan_applications(application_id);
CREATE INDEX idx_documents_app ON application_documents(application_id);
CREATE INDEX idx_risk_app ON risk_assessments(application_id);
CREATE INDEX idx_reviews_app ON application_reviews(application_id);
CREATE INDEX idx_notifications_user ON notifications(user_id);
CREATE INDEX idx_notifications_read ON notifications(user_id, is_read);
CREATE INDEX idx_timeline_app ON application_timeline(application_id);
CREATE INDEX idx_audit_user ON audit_logs(user_id);
CREATE INDEX idx_audit_entity ON audit_logs(entity_type, entity_id);
CREATE INDEX idx_emi_app ON emi_schedule(application_id);
CREATE INDEX idx_emi_status ON emi_schedule(payment_status);

-- ============================================
-- Default Admin User (password: Admin@123)
-- ============================================

INSERT INTO users (full_name, email, phone, password_hash, role_id, is_active, email_verified) VALUES
('System Administrator', 'admin@credilens.ai', '9999999999', '$2y$10$placeholder', 5, 1, 1);
