<?php
/**
 * CrediLens AI — Database Setup & Installer
 * Run this once to create the database and insert demo data
 * Access: http://localhost/Lending%20and%20Credit%20platform/setup.php
 */

// Only allow in development
$allowedIPs = ['127.0.0.1', '::1', 'localhost'];
if (!in_array($_SERVER['REMOTE_ADDR'], $allowedIPs)) {
    http_response_code(403);
    exit('Access denied. This script can only be run locally.');
}

$step = $_GET['step'] ?? '1';
$dbName = 'credilens_db';

function getConnection($withDb = false) {
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $dsn = 'mysql:host=' . $host . ($withDb ? ';dbname=' . 'credilens_db' : '') . ';charset=utf8mb4';
    return new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

$messages = [];
$success = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Step 1: Create DB safely if not exists
        $pdo = getConnection();
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $messages[] = ['type' => 'success', 'text' => '✅ Database verified/ready: ' . $dbName];

        // Step 2: Import schema
        $pdo = getConnection(true);
        $schemaFile = __DIR__ . '/database/schema.sql';
        if (!file_exists($schemaFile)) {
            throw new Exception('schema.sql not found at ' . $schemaFile);
        }


$sql = file_get_contents($schemaFile);

// Remove full-line SQL comments before splitting statements
$sql = preg_replace('/^\s*--.*$/m', '', $sql);

// Split and execute SQL statements
$statements = array_filter(
    array_map('trim', explode(';', $sql))
);

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

$count = 0;

foreach ($statements as $stmt) {
    if ($stmt === '') {
        continue;
    }

    try {
        $pdo->exec($stmt);
        $count++;
    } catch (PDOException $e) {
        throw new Exception(
            "SQL statement failed: " . $e->getMessage()
        );
    }
}

$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

$messages[] = [
    'type' => 'success',
    'text' => "Schema imported: $count statements executed"
];


        // Step 3: Insert demo data
        $pdo = getConnection(true);

        // Hash password
        $pass = password_hash('Demo@123', PASSWORD_DEFAULT);
        $adminPass = password_hash('Admin@123', PASSWORD_DEFAULT);

        // Insert roles if not exist
        $pdo->exec("INSERT IGNORE INTO roles (id, role_name, description) VALUES
            (1, 'Applicant', 'Loan applicants'),
            (2, 'Loan Officer', 'Reviews and decides applications'),
            (3, 'Risk Analyst', 'Performs risk assessments'),
            (4, 'Compliance Officer', 'Ensures regulatory compliance'),
            (5, 'Admin', 'Full system access')
        ");

        // Insert demo users
        $users = [
            ['Rohan Sharma', 'applicant@demo.com', 'applicant@demo.com', '9876543210', 1, $pass],
            ['Priya Mehta', 'officer@demo.com', 'officer@demo.com', '9876543211', 2, $pass],
            ['Amit Gupta', 'analyst@demo.com', 'analyst@demo.com', '9876543212', 3, $pass],
            ['Sunita Rao', 'compliance@demo.com', 'compliance@demo.com', '9876543213', 4, $pass],
            ['Admin User', 'admin@credilens.ai', 'admin@credilens.ai', '9876543214', 5, $adminPass],
        ];


$stmt = $pdo->prepare(
    "INSERT IGNORE INTO users
    (full_name, email, phone, role_id, password_hash,
     is_active, email_verified)
    VALUES (?, ?, ?, ?, ?, 1, 1)"
);

foreach ($users as $u) {
    $stmt->execute([
        $u[0], $u[1], $u[3], $u[4], $u[5]
    ]);
}

        $messages[] = ['type' => 'success', 'text' => '✅ Demo users created'];

        // Insert loan types
        
        // Insert loan types
        $pdo->exec("INSERT IGNORE INTO loan_types
            (id, name, description, min_amount, max_amount,
             min_tenure, max_tenure, interest_rate_min,
             interest_rate_max, is_active)
            VALUES
            (1, 'Home Loan', 'For purchase or construction of residential property', 500000, 100000000, 60, 360, 6.5, 9.5, 1),
            (2, 'Personal Loan', 'Unsecured loan for personal needs', 25000, 4000000, 12, 60, 10.5, 24.0, 1),
            (3, 'Business Loan', 'Loans for business expansion or working capital', 100000, 50000000, 12, 120, 12.0, 22.0, 1),
            (4, 'Education Loan', 'For higher education in India or abroad', 100000, 7500000, 12, 180, 7.0, 15.0, 1),
            (5, 'Vehicle Loan', 'For purchase of new or used vehicles', 50000, 10000000, 12, 84, 7.5, 14.0, 1)
        ");

        $messages[] = [
            'type' => 'success',
            'text' => 'Loan types seeded'
        ];

        // Insert document types
       
        // Insert document types
        $pdo->exec("INSERT IGNORE INTO document_types
            (id, name, category, description, is_mandatory)
            VALUES
            (1, 'PAN Card', 'identity', 'Government issued PAN card', 1),
            (2, 'Aadhaar Card', 'identity', 'UIDAI Aadhaar card (front and back)', 1),
            (3, 'Passport / Voter ID', 'identity', 'Alternate identity proof', 0),
            (4, 'Salary Slip (3 months)', 'income', 'Last 3 months salary slips', 1),
            (5, 'Form 16 / ITR', 'income', 'Income Tax Return or Form 16', 1),
            (6, 'Bank Statement (6 months)', 'bank_statement', 'Last 6 months bank account statement', 1),
            (7, 'Employment Letter', 'employment', 'Offer letter or employment confirmation', 0),
            (8, 'Business Registration', 'business', 'GST registration, business license etc.', 0),
            (9, 'Property Documents', 'collateral', 'Property ownership documents for home loans', 0),
            (10, 'Passport Photo', 'personal', 'Recent passport size photograph', 1)
        ");

        $messages[] = [
            'type' => 'success',
            'text' => 'Document types seeded'
        ];


        // Insert default lending policies
        $pdo->exec("INSERT IGNORE INTO lending_policies (policy_key, policy_name, policy_value, data_type, category, description) VALUES
            ('max_dti_ratio', 'Maximum Debt-to-Income Ratio', '50', 'number', 'eligibility', 'Maximum allowed DTI ratio (%). Applications above this fail eligibility.'),
            ('min_monthly_income', 'Minimum Monthly Income', '15000', 'number', 'eligibility', 'Minimum monthly income required (₹).'),
            ('min_credit_score', 'Minimum Credit Score', '600', 'number', 'eligibility', 'Minimum credit score required. Set 0 to ignore.'),
            ('max_loan_to_income', 'Max Loan-to-Income Ratio', '48', 'number', 'eligibility', 'Maximum loan amount as multiple of monthly income.'),
            ('min_work_experience', 'Minimum Work Experience', '1', 'number', 'eligibility', 'Minimum years of work experience required.'),
            ('max_existing_loans', 'Maximum Existing Active Loans', '3', 'number', 'eligibility', 'Maximum number of active loans allowed.'),
            ('auto_approve_score', 'Auto-Approve Risk Score', '30', 'number', 'process', 'Applications with risk score <= this are fast-tracked.'),
            ('auto_reject_score', 'Auto-Reject Risk Score', '80', 'number', 'process', 'Applications with risk score >= this are auto-rejected.'),
            ('sla_review_days', 'Review SLA (Days)', '7', 'number', 'process', 'Target number of days to complete application review.'),
            ('processing_fee_pct', 'Processing Fee (%)', '1.0', 'number', 'product', 'Processing fee as percentage of loan amount.')
        ");
        $messages[] = ['type' => 'success', 'text' => '✅ Lending policies seeded'];

        // Create uploads directory
        $uploadsDir = __DIR__ . '/uploads';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
            file_put_contents($uploadsDir . '/.htaccess', "Options -Indexes\nDeny from all\n<FilesMatch \"\.(pdf|jpg|jpeg|png|doc|docx)$\">\n  Allow from all\n</FilesMatch>");
        }
        $messages[] = ['type' => 'success', 'text' => '✅ Uploads directory created'];

        $messages[] = ['type' => 'success', 'text' => '🎉 Setup complete! You can now log in to CrediLens AI.'];

    } catch (Exception $e) {
        $success = false;
        $messages[] = ['type' => 'error', 'text' => '❌ Error: ' . $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CrediLens AI — Setup</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .setup-container { max-width: 700px; margin: 0 auto; padding: 3rem 2rem; }
        .setup-logo { text-align: center; margin-bottom: 2.5rem; }
        .setup-logo h1 { font-size: 2.5rem; font-weight: 900; background: var(--gradient-primary); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .setup-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; margin-bottom: 1.5rem; }
        .message { padding: 0.75rem 1rem; border-radius: var(--radius-sm); margin-bottom: 0.5rem; font-size: 0.875rem; font-family: monospace; }
        .message.success { background: var(--success-bg); color: var(--success-light); border: 1px solid rgba(16,185,129,0.2); }
        .message.warning { background: var(--warning-bg); color: var(--warning-light); border: 1px solid rgba(245,158,11,0.2); }
        .message.error { background: var(--error-bg); color: var(--error-light); border: 1px solid rgba(239,68,68,0.2); }
    </style>
</head>
<body>
<div class="setup-container">
    <div class="setup-logo">
        <div style="font-size: 3rem; margin-bottom: 0.5rem;">🏦</div>
        <h1>CrediLens AI</h1>
        <p style="color: var(--text-muted);">Database Setup & Installer</p>
    </div>

    <?php if (!empty($messages)): ?>
    <div class="setup-card">
        <h3 style="margin-bottom: 1rem;">Setup Results</h3>
        <?php foreach ($messages as $msg): ?>
        <div class="message <?= $msg['type'] ?>"><?= htmlspecialchars($msg['text']) ?></div>
        <?php endforeach; ?>

        <?php if ($success): ?>
        <div style="margin-top: 1.5rem; text-align: center;">
            <a href="index.php" class="btn btn-primary btn-lg">🚀 Launch CrediLens AI →</a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="setup-card">
        <h3 style="margin-bottom: 0.5rem;">⚙️ System Setup</h3>
        <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 1.5rem;">This will create the database, import the schema, and seed demo data.</p>

        <div style="background: var(--warning-bg); border: 1px solid rgba(245,158,11,0.2); border-radius: var(--radius-md); padding: 1rem; margin-bottom: 1.5rem;">
            <p style="font-size: 0.875rem; color: var(--warning-light);">⚠️ <strong>Prerequisites:</strong> XAMPP must be running with MySQL. Default config: host=localhost, user=root, no password.</p>
        </div>

        <div style="margin-bottom: 1.5rem; font-size: 0.875rem;">
            <div style="display:grid; grid-template-columns: 1fr 2fr; gap: 0.5rem; color: var(--text-secondary);">
                <strong>Database:</strong><span><?= $dbName ?></span>
                <strong>Host:</strong><span>localhost</span>
                <strong>Schema:</strong><span>database/schema.sql</span>
                <strong>Demo Users:</strong><span>5 accounts (pass: Demo@123 / Admin@123)</span>
            </div>
        </div>

        <form method="POST">
            <button type="submit" class="btn btn-primary btn-lg btn-block">
                🔧 Run Setup Now
            </button>
        </form>
    </div>

    <div class="setup-card">
        <h3 style="margin-bottom: 1rem;">👤 Demo Credentials</h3>
        <?php
        $demoUsers = [
            ['Applicant', 'applicant@demo.com', 'Demo@123', 'var(--accent)'],
            ['Loan Officer', 'officer@demo.com', 'Demo@123', 'var(--success)'],
            ['Risk Analyst', 'analyst@demo.com', 'Demo@123', 'var(--warning)'],
            ['Compliance Officer', 'compliance@demo.com', 'Demo@123', '#a78bfa'],
            ['Admin', 'admin@credilens.ai', 'Admin@123', 'var(--error)'],
        ];
        foreach ($demoUsers as $u):
        ?>
        <div style="display:flex; align-items:center; gap:1rem; padding:0.75rem 0; border-bottom:1px solid var(--border);">
            <span style="font-weight:700; color:<?= $u[3] ?>; width:160px;"><?= $u[0] ?></span>
            <span style="font-family:monospace; font-size:0.875rem; color:var(--text-secondary);"><?= $u[1] ?></span>
            <code style="margin-left:auto; background:var(--bg-glass); padding:0.25rem 0.75rem; border-radius:4px; font-size:0.8rem;"><?= $u[2] ?></code>
        </div>
        <?php endforeach; ?>
    </div>

    <p style="text-align:center; color: var(--text-dim); font-size:0.8rem; margin-top: 2rem;">
        ⚠️ Delete or rename <code>setup.php</code> after initial setup for security.
    </p>
</div>
</body>
</html>
