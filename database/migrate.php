<?php
/**
 * CrediLens AI — Safe Database Migrations
 * Idempotent schema upgrades without dropping existing data.
 */

require_once __DIR__ . '/../config/database.php';

function runMigrations() {
    $pdo = getDBConnection();
    $messages = [];

    // Enable error reporting
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Create table helper
    $execute = function($sql, $desc) use ($pdo, &$messages) {
        try {
            $pdo->exec($sql);
            $messages[] = ['type' => 'success', 'message' => "✓ $desc"];
        } catch (PDOException $e) {
            $messages[] = ['type' => 'error', 'message' => "✗ $desc: " . $e->getMessage()];
        }
    };

    // Helper: Add column safely if not exists
    $addColumnIfMissing = function($table, $column, $definition) use ($pdo, &$messages) {
        try {
            $check = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
            $check->execute([$table, $column]);
            if ($check->fetchColumn() == 0) {
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
                $messages[] = ['type' => 'success', 'message' => "✓ Added column $table.$column"];
            }
        } catch (PDOException $e) {
            $messages[] = ['type' => 'error', 'message' => "✗ Adding $table.$column failed: " . $e->getMessage()];
        }
    };

    // --- Core Enhancements to existing tables ---
    $addColumnIfMissing('loan_applications', 'credit_score', 'INT DEFAULT 720');
    $addColumnIfMissing('loan_applications', 'risk_score', 'DECIMAL(5,2) DEFAULT NULL');
    $addColumnIfMissing('loan_applications', 'risk_category', "ENUM('low','medium','high') DEFAULT 'medium'");
    $addColumnIfMissing('loan_applications', 'escalation_status', "ENUM('none','clarification_requested','escalated_to_risk','escalated_to_head','rejected_escalated') DEFAULT 'none'");
    $addColumnIfMissing('loan_applications', 'rejection_reason', 'TEXT DEFAULT NULL');
    $addColumnIfMissing('loan_applications', 'approved_amount', 'DECIMAL(15,2) DEFAULT NULL');
    $addColumnIfMissing('loan_applications', 'interest_rate', 'DECIMAL(5,2) DEFAULT NULL');
    $addColumnIfMissing('loan_applications', 'tenure_months', 'INT DEFAULT NULL');
    $addColumnIfMissing('loan_applications', 'disbursed_at', 'DATETIME DEFAULT NULL');
    $addColumnIfMissing('loan_applications', 'disbursed_by', 'INT DEFAULT NULL');
    $addColumnIfMissing('loan_applications', 'disbursement_reference', 'VARCHAR(100) DEFAULT NULL');

    $addColumnIfMissing('application_documents', 'file_hash', 'VARCHAR(64) DEFAULT NULL');
    $addColumnIfMissing('application_documents', 'ocr_status', "ENUM('pending','processing','extracted','completed','failed','skipped') DEFAULT 'pending'");
    $addColumnIfMissing('application_documents', 'ocr_data_json', 'LONGTEXT DEFAULT NULL');
    $addColumnIfMissing('application_documents', 'ocr_match_score', 'DECIMAL(5,2) DEFAULT NULL');

    // --- 1. Privacy & Consent Management ---
    $execute("CREATE TABLE IF NOT EXISTS user_consents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        consent_type VARCHAR(100) NOT NULL,
        consent_text TEXT NOT NULL,
        is_granted TINYINT(1) DEFAULT 1,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent TEXT DEFAULT NULL,
        granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        revoked_at DATETIME DEFAULT NULL,
        INDEX idx_user_consent (user_id, consent_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Create user_consents table");

    $execute("CREATE TABLE IF NOT EXISTS data_retention_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        entity_type VARCHAR(50) NOT NULL,
        entity_id INT NOT NULL,
        action VARCHAR(50) NOT NULL,
        reason TEXT,
        executed_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Create data_retention_logs table");

    // --- 2. Advanced Fraud Detection ---
    $execute("CREATE TABLE IF NOT EXISTS fraud_checks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NOT NULL,
        check_type VARCHAR(100) NOT NULL,
        severity ENUM('low','medium','high','critical') DEFAULT 'medium',
        score DECIMAL(5,2) DEFAULT 0,
        flag_status ENUM('passed','flagged','cleared','confirmed_fraud') DEFAULT 'flagged',
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        matched_details JSON DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        cleared_by INT DEFAULT NULL,
        cleared_at DATETIME DEFAULT NULL,
        cleared_notes TEXT DEFAULT NULL,
        INDEX idx_app_fraud (application_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Create fraud_checks table");

    // --- 3. OCR Document Intelligence ---
    $execute("CREATE TABLE IF NOT EXISTS document_ocr_data (
        id INT AUTO_INCREMENT PRIMARY KEY,
        document_id INT NOT NULL,
        application_id INT NOT NULL,
        document_type VARCHAR(50) NOT NULL,
        extracted_name VARCHAR(150) DEFAULT NULL,
        extracted_id_number VARCHAR(100) DEFAULT NULL,
        extracted_dob DATE DEFAULT NULL,
        extracted_income DECIMAL(15,2) DEFAULT NULL,
        match_confidence DECIMAL(5,2) DEFAULT 0.00,
        name_match TINYINT(1) DEFAULT 0,
        id_match TINYINT(1) DEFAULT 0,
        income_match TINYINT(1) DEFAULT 0,
        discrepancies JSON DEFAULT NULL,
        raw_text LONGTEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_doc_ocr (document_id),
        INDEX idx_app_ocr (application_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Create document_ocr_data table");

    // --- 4. Explainable AI Risk Assessment ---
    $execute("CREATE TABLE IF NOT EXISTS risk_factor_explanations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NOT NULL,
        factor_code VARCHAR(50) NOT NULL,
        factor_name VARCHAR(150) NOT NULL,
        category ENUM('affordability','credit_history','employment','kyc_compliance','market_risk') NOT NULL,
        weight DECIMAL(5,2) NOT NULL,
        score DECIMAL(5,2) NOT NULL,
        impact ENUM('positive','negative','neutral') DEFAULT 'neutral',
        explanation TEXT NOT NULL,
        recommendation TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_app_risk (application_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Create risk_factor_explanations table");

    // --- 5. Application Escalations & Clarifications ---
    $execute("CREATE TABLE IF NOT EXISTS application_escalations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NOT NULL,
        escalated_by INT NOT NULL,
        assigned_to INT DEFAULT NULL,
        priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
        reason VARCHAR(255) NOT NULL,
        details TEXT NOT NULL,
        resolution_notes TEXT DEFAULT NULL,
        status ENUM('pending','in_review','resolved','dismissed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        resolved_at DATETIME DEFAULT NULL,
        INDEX idx_app_esc (application_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Create application_escalations table");

    $execute("CREATE TABLE IF NOT EXISTS application_clarifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NOT NULL,
        requested_by INT NOT NULL,
        subject VARCHAR(200) NOT NULL,
        query TEXT NOT NULL,
        applicant_response TEXT DEFAULT NULL,
        status ENUM('pending_applicant','responded','closed') DEFAULT 'pending_applicant',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        responded_at DATETIME DEFAULT NULL,
        INDEX idx_app_clar (application_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Create application_clarifications table");

    // --- 6. Policy Versioning & Audit ---
    $execute("CREATE TABLE IF NOT EXISTS lending_policy_versions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        policy_id INT NOT NULL,
        policy_name VARCHAR(100) NOT NULL,
        category VARCHAR(50) NOT NULL,
        version INT NOT NULL DEFAULT 1,
        old_value VARCHAR(255) DEFAULT NULL,
        new_value VARCHAR(255) NOT NULL,
        rationale TEXT NOT NULL,
        changed_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_policy_ver (policy_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Create lending_policy_versions table");

    // --- 7. Loan Accounting Ledger & Repayments ---
    $execute("CREATE TABLE IF NOT EXISTS loan_accounting_ledger (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NOT NULL,
        entry_type ENUM('disbursement','emi_principal','emi_interest','fee','penalty','waiver','part_payment','full_closure') NOT NULL,
        debit DECIMAL(15,2) DEFAULT 0.00,
        credit DECIMAL(15,2) DEFAULT 0.00,
        balance_after DECIMAL(15,2) NOT NULL,
        reference_no VARCHAR(100) NOT NULL,
        description TEXT NOT NULL,
        recorded_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_loan_acc (application_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Create loan_accounting_ledger table");

    $execute("CREATE TABLE IF NOT EXISTS payment_reconciliations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NOT NULL,
        transaction_reference VARCHAR(100) NOT NULL UNIQUE,
        payment_gateway VARCHAR(50) DEFAULT 'manual_bank_transfer',
        expected_amount DECIMAL(15,2) NOT NULL,
        actual_amount DECIMAL(15,2) NOT NULL,
        status ENUM('matched','mismatched','pending','refunded') DEFAULT 'pending',
        gateway_payload JSON DEFAULT NULL,
        reconciled_by INT DEFAULT NULL,
        reconciled_at DATETIME DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Create payment_reconciliations table");

    // --- 8. AI Assistant Conversations & Multi-Turn Memory ---
    $execute("CREATE TABLE IF NOT EXISTS ai_conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        session_id VARCHAR(100) NOT NULL,
        title VARCHAR(200) DEFAULT 'Loan Guidance Session',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_ai_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Create ai_conversations table");

    $execute("CREATE TABLE IF NOT EXISTS ai_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        role ENUM('user','assistant','system') NOT NULL,
        content TEXT NOT NULL,
        tool_calls JSON DEFAULT NULL,
        tool_results JSON DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ai_conv (conversation_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Create ai_messages table");

    // --- 9. Database Backup Records ---
    $execute("CREATE TABLE IF NOT EXISTS system_backups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL,
        filesize_bytes BIGINT NOT NULL,
        tables_count INT NOT NULL,
        records_count INT NOT NULL,
        created_by INT NOT NULL,
        status ENUM('success','failed') DEFAULT 'success',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Create system_backups table");

    return $messages;
}

// Allow CLI execution or direct inclusion
if (php_sapi_name() === 'cli' || basename($_SERVER['PHP_SELF']) === 'migrate.php') {
    echo "Running CrediLens AI Migrations...\n";
    $results = runMigrations();
    foreach ($results as $r) {
        echo "[{$r['type']}] {$r['message']}\n";
    }
    echo "Migration completed.\n";
}
