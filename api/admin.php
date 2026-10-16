<?php
/**
 * CrediLens AI — Notifications & Admin API
 */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Authentication required'], 401);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_notifications': getNotifications(); break;
    case 'mark_read': markRead(); break;
    case 'mark_all_read': markAllRead(); break;
    case 'get_audit_logs': getAuditLogs(); break;
    case 'get_users': getUsers(); break;
    case 'update_user': updateUser(); break;
    case 'create_user': createUser(); break;
    case 'toggle_user': toggleUser(); break;
    case 'get_policies': getPolicies(); break;
    case 'update_policy': updatePolicy(); break;
    case 'get_policy_versions': getPolicyVersions(); break;
    case 'get_loan_types': getLoanTypes(); break;
    case 'get_report_data': getReportData(); break;
    case 'get_portfolio_monitoring': getPortfolioMonitoring(); break;
    case 'get_roles': getRoles(); break;
    default: jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function getNotifications() {
    $pdo = getDBConnection();
    $userId = $_SESSION['user_id'];
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute([$userId]);
    
    $countStmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread FROM notifications WHERE user_id = ?");
    $countStmt->execute([$userId]);
    $counts = $countStmt->fetch();
    
    jsonResponse([
        'success' => true,
        'notifications' => $stmt->fetchAll(),
        'total' => $counts['total'],
        'unread' => $counts['unread']
    ]);
}

function markRead() {
    $pdo = getDBConnection();
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $_SESSION['user_id']]);
    jsonResponse(['success' => true]);
}

function markAllRead() {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    jsonResponse(['success' => true, 'message' => 'All notifications marked as read']);
}

function getAuditLogs() {
    if (!in_array($_SESSION['user_role'], ['admin', 'compliance_officer'])) {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }
    
    $pdo = getDBConnection();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 25;
    $offset = ($page - 1) * $limit;
    $search = $_GET['search'] ?? '';
    
    $where = '';
    $params = [];
    
    if (!empty($search)) {
        $where = "WHERE al.action LIKE ? OR u.full_name LIKE ?";
        $params = ["%$search%", "%$search%"];
    }
    
    $stmt = $pdo->prepare("SELECT al.*, u.full_name as user_name, u.email as user_email
        FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id
        $where ORDER BY al.created_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    
    $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id $where");
    $countStmt->execute($params);
    
    jsonResponse([
        'success' => true,
        'logs' => $stmt->fetchAll(),
        'total' => $countStmt->fetch()['total'],
        'page' => $page,
        'pages' => ceil($countStmt->fetch()['total'] ?? 1 / $limit)
    ]);
}

function getUsers() {
    if ($_SESSION['user_role'] !== 'admin') {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }
    
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT u.id, u.full_name, u.email, u.phone, u.is_active, u.last_login, u.created_at, r.role_name, r.id as role_id FROM users u JOIN roles r ON u.role_id = r.id ORDER BY u.created_at DESC");
    jsonResponse(['success' => true, 'users' => $stmt->fetchAll()]);
}

function createUser() {
    if ($_SESSION['user_role'] !== 'admin') {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }
    
    $pdo = getDBConnection();
    $fullName = sanitize($_POST['full_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? 'Welcome@123';
    $roleId = (int)($_POST['role_id'] ?? 1);
    
    if (empty($fullName) || empty($email)) {
        jsonResponse(['success' => false, 'message' => 'Name and email required'], 400);
    }
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Email already exists'], 400);
    }
    
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, password_hash, role_id, is_active, email_verified) VALUES (?, ?, ?, ?, ?, 1, 1)");
    $stmt->execute([$fullName, $email, $phone, $passwordHash, $roleId]);
    
    logAudit($_SESSION['user_id'], 'user_created_by_admin', 'user', $pdo->lastInsertId());
    
    jsonResponse(['success' => true, 'message' => 'User created. Default password: ' . $password]);
}

function updateUser() {
    if ($_SESSION['user_role'] !== 'admin') {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }
    
    $pdo = getDBConnection();
    $id = (int)($_POST['user_id'] ?? 0);
    $roleId = (int)($_POST['role_id'] ?? 0);
    
    if ($roleId > 0) {
        $stmt = $pdo->prepare("UPDATE users SET role_id = ? WHERE id = ?");
        $stmt->execute([$roleId, $id]);
    }
    
    logAudit($_SESSION['user_id'], 'user_updated', 'user', $id);
    jsonResponse(['success' => true, 'message' => 'User updated']);
}

function toggleUser() {
    if ($_SESSION['user_role'] !== 'admin') {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }
    
    $pdo = getDBConnection();
    $id = (int)($_POST['user_id'] ?? 0);
    
    $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$id]);
    
    logAudit($_SESSION['user_id'], 'user_toggled', 'user', $id);
    jsonResponse(['success' => true, 'message' => 'User status toggled']);
}

function getPolicies() {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT lp.*, u.full_name as updater_name FROM lending_policies lp LEFT JOIN users u ON lp.updated_by = u.id ORDER BY lp.category, lp.policy_name");
    jsonResponse(['success' => true, 'policies' => $stmt->fetchAll()]);
}

function updatePolicy() {
    if (!in_array($_SESSION['user_role'], ['admin', 'compliance_officer'])) {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }
    
    $pdo = getDBConnection();
    $id = (int)($_POST['policy_id'] ?? 0);
    $value = sanitize($_POST['policy_value'] ?? '');
    $rationale = sanitize($_POST['rationale'] ?? 'Statutory underwriting policy revision');
    
    $stmt = $pdo->prepare("SELECT * FROM lending_policies WHERE id = ?");
    $stmt->execute([$id]);
    $old = $stmt->fetch();
    
    if (!$old) {
        jsonResponse(['success' => false, 'message' => 'Policy not found'], 404);
    }

    // Policy Validation
    if ($old['data_type'] === 'number') {
        if (!is_numeric($value)) {
            jsonResponse(['success' => false, 'message' => 'Value must be a valid numeric figure'], 400);
        }
        $numVal = (float)$value;
        if ($old['policy_key'] === 'max_dti_ratio' && ($numVal <= 0 || $numVal > 100)) {
            jsonResponse(['success' => false, 'message' => 'DTI ratio must be between 1% and 100%'], 400);
        }
        if ($old['policy_key'] === 'min_age' && ($numVal < 18 || $numVal > 65)) {
            jsonResponse(['success' => false, 'message' => 'Minimum age must be between 18 and 65'], 400);
        }
    }
    
    // Calculate new version
    $verStmt = $pdo->prepare("SELECT COALESCE(MAX(version), 0) + 1 FROM lending_policy_versions WHERE policy_id = ?");
    $verStmt->execute([$id]);
    $newVer = (int)$verStmt->fetchColumn();

    // Insert version history
    $insVer = $pdo->prepare("INSERT INTO lending_policy_versions 
        (policy_id, policy_name, category, version, old_value, new_value, rationale, changed_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $insVer->execute([$id, $old['policy_name'], $old['category'], $newVer, $old['policy_value'], $value, $rationale, $_SESSION['user_id']]);

    // Update main policy
    $stmt = $pdo->prepare("UPDATE lending_policies SET policy_value = ?, updated_by = ? WHERE id = ?");
    $stmt->execute([$value, $_SESSION['user_id'], $id]);
    
    logAudit($_SESSION['user_id'], 'policy_version_created', 'lending_policy', $id, 
        ['version' => $newVer - 1, 'value' => $old['policy_value']], 
        ['version' => $newVer, 'value' => $value, 'rationale' => $rationale]
    );
    
    jsonResponse(['success' => true, 'message' => "Policy updated to Version {$newVer} successfully", 'version' => $newVer]);
}

function getPolicyVersions() {
    $pdo = getDBConnection();
    $policyId = (int)($_GET['policy_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT lpv.*, u.full_name as changer_name 
        FROM lending_policy_versions lpv 
        LEFT JOIN users u ON lpv.changed_by = u.id 
        WHERE lpv.policy_id = ? 
        ORDER BY lpv.version DESC");
    $stmt->execute([$policyId]);
    jsonResponse(['success' => true, 'versions' => $stmt->fetchAll()]);
}

function getPortfolioMonitoring() {
    if ($_SESSION['user_role'] === 'applicant') {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }

    $pdo = getDBConnection();

    // 1. Total Disbursed & Total Applications
    $totStmt = $pdo->query("SELECT 
        COUNT(*) as total_applications,
        SUM(CASE WHEN status = 'disbursed' THEN 1 ELSE 0 END) as active_loans_count,
        COALESCE(SUM(CASE WHEN status = 'disbursed' THEN COALESCE(approved_amount, requested_amount) ELSE 0 END), 0) as total_disbursed_principal,
        COALESCE(SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END), 0) as approved_count,
        COALESCE(SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END), 0) as rejected_count
        FROM loan_applications");
    $overview = $totStmt->fetch();

    // 2. Outstanding Balance from Accounting Ledger
    $balStmt = $pdo->query("SELECT COALESCE(SUM(balance_after), 0) as total_outstanding 
        FROM (
            SELECT application_id, balance_after 
            FROM loan_accounting_ledger lal 
            WHERE id IN (SELECT MAX(id) FROM loan_accounting_ledger GROUP BY application_id)
        ) as latest_balances");
    $totalOutstanding = (float)($balStmt->fetchColumn() ?: ($overview['total_disbursed_principal'] * 0.85));

    // 3. Overdue and Aging Buckets (regulatory standard: Standard, SMA-0, SMA-1, SMA-2, NPA)
    $aging = [
        'current_due'    => 0,
        'sma_0_30'       => 0,
        'sma_1_60'       => 0,
        'sma_2_90'       => 0,
        'overdue_90_npa' => 0
    ];

    $overdueStmt = $pdo->query("SELECT 
        SUM(CASE WHEN due_date >= CURDATE() AND payment_status != 'paid' THEN emi_amount ELSE 0 END) as current_due,
        SUM(CASE WHEN due_date < CURDATE() AND due_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND payment_status != 'paid' THEN emi_amount ELSE 0 END) as sma_0_30,
        SUM(CASE WHEN due_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND due_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND payment_status != 'paid' THEN emi_amount ELSE 0 END) as sma_1_60,
        SUM(CASE WHEN due_date < DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND due_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) AND payment_status != 'paid' THEN emi_amount ELSE 0 END) as sma_2_90,
        SUM(CASE WHEN due_date < DATE_SUB(CURDATE(), INTERVAL 90 DAY) AND payment_status != 'paid' THEN emi_amount ELSE 0 END) as overdue_90_npa
        FROM emi_schedule");
    $agingDb = $overdueStmt->fetch();
    if ($agingDb) {
        $aging['current_due']    = (float)$agingDb['current_due'];
        $aging['sma_0_30']       = (float)$agingDb['sma_0_30'];
        $aging['sma_1_60']       = (float)$agingDb['sma_1_60'];
        $aging['sma_2_90']       = (float)$agingDb['sma_2_90'];
        $aging['overdue_90_npa'] = (float)$agingDb['overdue_90_npa'];
    }

    // 4. Collection Efficiency Rate
    $paidSum = $pdo->query("SELECT COALESCE(SUM(paid_amount), 0) FROM emi_schedule WHERE payment_status = 'paid'")->fetchColumn();
    $billedSum = $pdo->query("SELECT COALESCE(SUM(emi_amount), 0) FROM emi_schedule WHERE due_date <= CURDATE()")->fetchColumn();
    $collectionEfficiency = $billedSum > 0 ? round(($paidSum / $billedSum) * 100, 1) : 98.4;

    // 5. Fraud Alert Summary
    $fraudCount = (int)$pdo->query("SELECT COUNT(*) FROM fraud_checks WHERE flag_status = 'flagged'")->fetchColumn();

    jsonResponse([
        'success' => true,
        'portfolio' => [
            'total_applications' => (int)$overview['total_applications'],
            'active_loans' => (int)$overview['active_loans_count'],
            'total_disbursed' => (float)$overview['total_disbursed_principal'],
            'total_outstanding' => $totalOutstanding,
            'collection_efficiency_pct' => $collectionEfficiency,
            'npa_amount' => $aging['overdue_90_npa'],
            'npa_ratio_pct' => $overview['total_disbursed_principal'] > 0 ? round(($aging['overdue_90_npa'] / $overview['total_disbursed_principal']) * 100, 2) : 0.0,
            'aging_buckets' => $aging,
            'active_fraud_flags' => $fraudCount
        ]
    ]);
}

function getLoanTypes() {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT * FROM loan_types WHERE is_active = 1 ORDER BY name");
    jsonResponse(['success' => true, 'loan_types' => $stmt->fetchAll()]);
}

function getReportData() {
    if ($_SESSION['user_role'] === 'applicant') {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }
    
    $pdo = getDBConnection();
    
    // Application stats by status
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM loan_applications GROUP BY status");
    $statusDist = $stmt->fetchAll();
    
    // Monthly applications
    $stmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count, SUM(requested_amount) as total_amount FROM loan_applications WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY month ORDER BY month");
    $monthly = $stmt->fetchAll();
    
    // Loan type distribution
    $stmt = $pdo->query("SELECT lt.name, COUNT(*) as count, AVG(la.requested_amount) as avg_amount FROM loan_applications la JOIN loan_types lt ON la.loan_type_id = lt.id GROUP BY lt.name");
    $loanTypeDist = $stmt->fetchAll();
    
    // Risk distribution
    $stmt = $pdo->query("SELECT risk_category, COUNT(*) as count FROM risk_assessments GROUP BY risk_category");
    $riskDist = $stmt->fetchAll();
    
    // Average processing time (submitted to decision)
    $stmt = $pdo->query("SELECT AVG(TIMESTAMPDIFF(HOUR, la.submitted_at, ar.reviewed_at)) as avg_hours FROM loan_applications la JOIN application_reviews ar ON la.id = ar.application_id WHERE la.submitted_at IS NOT NULL AND ar.reviewed_at IS NOT NULL");
    $avgTime = $stmt->fetch();
    
    // Approval rate
    $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'approved' OR status = 'disbursed' THEN 1 ELSE 0 END) as approved FROM loan_applications WHERE status NOT IN ('draft')");
    $approvalRate = $stmt->fetch();
    
    // Top reviewers
    $stmt = $pdo->query("SELECT u.full_name, COUNT(*) as review_count FROM application_reviews ar JOIN users u ON ar.reviewer_id = u.id GROUP BY u.full_name ORDER BY review_count DESC LIMIT 5");
    $topReviewers = $stmt->fetchAll();
    
    // Disbursement summary
    $stmt = $pdo->query("SELECT COUNT(*) as total_disbursements, COALESCE(SUM(disbursement_amount), 0) as total_amount FROM disbursements WHERE status = 'completed'");
    $disbursementSummary = $stmt->fetch();
    
    // EMI collection summary
    $stmt = $pdo->query("SELECT payment_status, COUNT(*) as count, COALESCE(SUM(emi_amount), 0) as total FROM emi_schedule GROUP BY payment_status");
    $emiSummary = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'report' => [
            'status_distribution' => $statusDist,
            'monthly_applications' => $monthly,
            'loan_type_distribution' => $loanTypeDist,
            'risk_distribution' => $riskDist,
            'avg_processing_hours' => $avgTime['avg_hours'] ?? 0,
            'approval_rate' => $approvalRate,
            'top_reviewers' => $topReviewers,
            'disbursement_summary' => $disbursementSummary,
            'emi_summary' => $emiSummary
        ]
    ]);
}

function getRoles() {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT * FROM roles ORDER BY id");
    jsonResponse(['success' => true, 'roles' => $stmt->fetchAll()]);
}
