<?php
/**
 * CrediLens AI — Loan Applications API
 * Handles CRUD for loan applications
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
    case 'create':
        createApplication();
        break;
    case 'update':
        updateApplication();
        break;
    case 'submit':
        submitApplication();
        break;
    case 'list':
        listApplications();
        break;
    case 'get':
        getApplication();
        break;
    case 'get_stats':
        getStats();
        break;
    case 'update_status':
        updateStatus();
        break;
    case 'assign':
        assignApplication();
        break;
    case 'timeline':
        getTimeline();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function createApplication() {
    $pdo = getDBConnection();
    $userId = $_SESSION['user_id'];
    
    $applicationId = generateApplicationId();
    $loanTypeId = (int)($_POST['loan_type_id'] ?? 1);
    
    $stmt = $pdo->prepare("INSERT INTO loan_applications (
        application_id, user_id, loan_type_id, full_name, date_of_birth, gender, marital_status,
        address_line1, address_line2, city, state, pincode, pan_number, aadhaar_number,
        employment_type, employer_name, occupation, years_of_experience,
        monthly_income, additional_income, monthly_expenses, existing_emi, existing_loans_count,
        requested_amount, requested_tenure, loan_purpose, status
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft'
    )");
    
    $stmt->execute([
        $applicationId, $userId, $loanTypeId,
        sanitize($_POST['full_name'] ?? ''),
        $_POST['date_of_birth'] ?? null,
        $_POST['gender'] ?? null,
        $_POST['marital_status'] ?? null,
        sanitize($_POST['address_line1'] ?? ''),
        sanitize($_POST['address_line2'] ?? ''),
        sanitize($_POST['city'] ?? ''),
        sanitize($_POST['state'] ?? ''),
        sanitize($_POST['pincode'] ?? ''),
        sanitize($_POST['pan_number'] ?? ''),
        sanitize($_POST['aadhaar_number'] ?? ''),
        $_POST['employment_type'] ?? null,
        sanitize($_POST['employer_name'] ?? ''),
        sanitize($_POST['occupation'] ?? ''),
        (int)($_POST['years_of_experience'] ?? 0),
        (float)($_POST['monthly_income'] ?? 0),
        (float)($_POST['additional_income'] ?? 0),
        (float)($_POST['monthly_expenses'] ?? 0),
        (float)($_POST['existing_emi'] ?? 0),
        (int)($_POST['existing_loans_count'] ?? 0),
        (float)($_POST['requested_amount'] ?? 0),
        (int)($_POST['requested_tenure'] ?? 12),
        sanitize($_POST['loan_purpose'] ?? '')
    ]);
    
    $id = $pdo->lastInsertId();
    
    addTimelineEvent($id, 'created', 'Application Created', 'Loan application ' . $applicationId . ' has been created.', $userId);
    logAudit($userId, 'application_created', 'loan_application', $id);
    
    jsonResponse(['success' => true, 'message' => 'Application created', 'application_id' => $applicationId, 'id' => $id]);
}

function updateApplication() {
    $pdo = getDBConnection();
    $userId = $_SESSION['user_id'];
    $id = (int)($_POST['id'] ?? 0);
    
    // Verify ownership or permission
    $stmt = $pdo->prepare("SELECT * FROM loan_applications WHERE id = ?");
    $stmt->execute([$id]);
    $app = $stmt->fetch();
    
    if (!$app) {
        jsonResponse(['success' => false, 'message' => 'Application not found'], 404);
    }
    
    if ($app['user_id'] != $userId && $_SESSION['user_role'] === 'applicant') {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }
    
    if (!in_array($app['status'], ['draft', 'clarification_required'])) {
        jsonResponse(['success' => false, 'message' => 'Application cannot be edited in current status'], 400);
    }
    
    $stmt = $pdo->prepare("UPDATE loan_applications SET
        full_name = ?, date_of_birth = ?, gender = ?, marital_status = ?,
        address_line1 = ?, address_line2 = ?, city = ?, state = ?, pincode = ?,
        pan_number = ?, aadhaar_number = ?,
        employment_type = ?, employer_name = ?, occupation = ?, years_of_experience = ?,
        monthly_income = ?, additional_income = ?, monthly_expenses = ?, existing_emi = ?, existing_loans_count = ?,
        requested_amount = ?, requested_tenure = ?, loan_purpose = ?, loan_type_id = ?
        WHERE id = ?");
    
    $stmt->execute([
        sanitize($_POST['full_name'] ?? $app['full_name']),
        $_POST['date_of_birth'] ?? $app['date_of_birth'],
        $_POST['gender'] ?? $app['gender'],
        $_POST['marital_status'] ?? $app['marital_status'],
        sanitize($_POST['address_line1'] ?? $app['address_line1']),
        sanitize($_POST['address_line2'] ?? $app['address_line2']),
        sanitize($_POST['city'] ?? $app['city']),
        sanitize($_POST['state'] ?? $app['state']),
        sanitize($_POST['pincode'] ?? $app['pincode']),
        sanitize($_POST['pan_number'] ?? $app['pan_number']),
        sanitize($_POST['aadhaar_number'] ?? $app['aadhaar_number']),
        $_POST['employment_type'] ?? $app['employment_type'],
        sanitize($_POST['employer_name'] ?? $app['employer_name']),
        sanitize($_POST['occupation'] ?? $app['occupation']),
        (int)($_POST['years_of_experience'] ?? $app['years_of_experience']),
        (float)($_POST['monthly_income'] ?? $app['monthly_income']),
        (float)($_POST['additional_income'] ?? $app['additional_income']),
        (float)($_POST['monthly_expenses'] ?? $app['monthly_expenses']),
        (float)($_POST['existing_emi'] ?? $app['existing_emi']),
        (int)($_POST['existing_loans_count'] ?? $app['existing_loans_count']),
        (float)($_POST['requested_amount'] ?? $app['requested_amount']),
        (int)($_POST['requested_tenure'] ?? $app['requested_tenure']),
        sanitize($_POST['loan_purpose'] ?? $app['loan_purpose']),
        (int)($_POST['loan_type_id'] ?? $app['loan_type_id']),
        $id
    ]);
    
    addTimelineEvent($id, 'updated', 'Application Updated', 'Application details have been updated.', $userId);
    logAudit($userId, 'application_updated', 'loan_application', $id);
    
    jsonResponse(['success' => true, 'message' => 'Application updated successfully']);
}

function submitApplication() {
    $pdo = getDBConnection();
    $userId = $_SESSION['user_id'];
    $id = (int)($_POST['id'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT * FROM loan_applications WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    $app = $stmt->fetch();
    
    if (!$app) {
        jsonResponse(['success' => false, 'message' => 'Application not found'], 404);
    }
    
    if (!in_array($app['status'], ['draft', 'clarification_required'])) {
        jsonResponse(['success' => false, 'message' => 'Application cannot be submitted in current status'], 400);
    }
    
    $stmt = $pdo->prepare("UPDATE loan_applications SET status = 'submitted', submitted_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);
    
    addTimelineEvent($id, 'submitted', 'Application Submitted', 'Application has been submitted for review.', $userId);
    logAudit($userId, 'application_submitted', 'loan_application', $id);
    
    // Notify loan officers
    $officers = $pdo->query("SELECT id FROM users WHERE role_id = 2 AND is_active = 1")->fetchAll();
    foreach ($officers as $officer) {
        sendNotification($officer['id'], 'New Application Submitted', 'Application ' . $app['application_id'] . ' requires review.', 'info', 'application', $id);
    }
    
    sendNotification($userId, 'Application Submitted', 'Your application ' . $app['application_id'] . ' has been submitted successfully.', 'success', 'application', $id);
    
    // Trigger submission email
    require_once __DIR__ . '/../includes/mailer.php';
    $uStmt = $pdo->prepare("SELECT email, full_name FROM users WHERE id = ?");
    $uStmt->execute([$userId]);
    $u = $uStmt->fetch();
    $ltStmt = $pdo->prepare("SELECT name FROM loan_types WHERE id = ?");
    $ltStmt->execute([$app['loan_type_id']]);
    $lt = $ltStmt->fetch();
    if ($u && !empty($u['email'])) {
        @emailApplicationSubmitted($u['email'], $u['full_name'], $app['application_id'], $lt['name'] ?? 'Loan', (float)$app['requested_amount']);
    }
    
    jsonResponse(['success' => true, 'message' => 'Application submitted successfully']);
}

function listApplications() {
    $pdo = getDBConnection();
    $userId = $_SESSION['user_id'];
    $role = $_SESSION['user_role'];
    
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $where = [];
    $params = [];
    
    // Role-based filtering
    if ($role === 'applicant') {
        $where[] = "la.user_id = ?";
        $params[] = $userId;
    }
    
    if (!empty($status)) {
        $where[] = "la.status = ?";
        $params[] = $status;
    }
    
    if (!empty($search)) {
        $where[] = "(la.application_id LIKE ? OR la.full_name LIKE ? OR la.pan_number LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Count total
    $countSql = "SELECT COUNT(*) as total FROM loan_applications la $whereClause";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    // Fetch applications
    $sql = "SELECT la.*, lt.name as loan_type_name, u.full_name as applicant_name,
            officer.full_name as officer_name, analyst.full_name as analyst_name
            FROM loan_applications la
            JOIN loan_types lt ON la.loan_type_id = lt.id
            JOIN users u ON la.user_id = u.id
            LEFT JOIN users officer ON la.assigned_officer_id = officer.id
            LEFT JOIN users analyst ON la.assigned_analyst_id = analyst.id
            $whereClause
            ORDER BY la.created_at DESC
            LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $applications = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'applications' => $applications,
        'total' => $total,
        'page' => $page,
        'pages' => ceil($total / $limit)
    ]);
}

function getApplication() {
    $pdo = getDBConnection();
    $userId = $_SESSION['user_id'];
    $role = $_SESSION['user_role'];
    $id = (int)($_GET['id'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT la.*, lt.name as loan_type_name, lt.interest_rate_min, lt.interest_rate_max,
        u.full_name as applicant_name, u.email as applicant_email, u.phone as applicant_phone
        FROM loan_applications la
        JOIN loan_types lt ON la.loan_type_id = lt.id
        JOIN users u ON la.user_id = u.id
        WHERE la.id = ?");
    $stmt->execute([$id]);
    $app = $stmt->fetch();
    
    if (!$app) {
        jsonResponse(['success' => false, 'message' => 'Application not found'], 404);
    }
    
    if ($role === 'applicant' && $app['user_id'] != $userId) {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }
    
    // Get documents
    $stmt = $pdo->prepare("SELECT ad.*, dt.name as document_type_name, dt.category 
        FROM application_documents ad 
        JOIN document_types dt ON ad.document_type_id = dt.id
        WHERE ad.application_id = ?");
    $stmt->execute([$id]);
    $documents = $stmt->fetchAll();
    
    // Get risk assessment
    $stmt = $pdo->prepare("SELECT * FROM risk_assessments WHERE application_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$id]);
    $riskAssessment = $stmt->fetch();
    
    // Get risk factors
    $riskFactors = [];
    if ($riskAssessment) {
        $stmt = $pdo->prepare("SELECT * FROM risk_factors WHERE assessment_id = ?");
        $stmt->execute([$riskAssessment['id']]);
        $riskFactors = $stmt->fetchAll();
    }
    
    // Get reviews
    $stmt = $pdo->prepare("SELECT ar.*, u.full_name as reviewer_name, r.role_name as reviewer_role
        FROM application_reviews ar
        JOIN users u ON ar.reviewer_id = u.id
        JOIN roles r ON u.role_id = r.id
        WHERE ar.application_id = ?
        ORDER BY ar.created_at DESC");
    $stmt->execute([$id]);
    $reviews = $stmt->fetchAll();
    
    // Get clarification requests
    $stmt = $pdo->prepare("SELECT cr.*, u.full_name as requester_name
        FROM clarification_requests cr
        JOIN users u ON cr.requested_by = u.id
        WHERE cr.application_id = ?
        ORDER BY cr.created_at DESC");
    $stmt->execute([$id]);
    $clarifications = $stmt->fetchAll();
    
    // Get loan offer
    $stmt = $pdo->prepare("SELECT * FROM loan_offers WHERE application_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$id]);
    $loanOffer = $stmt->fetch();
    
    // Get timeline
    $stmt = $pdo->prepare("SELECT at.*, u.full_name as performer_name
        FROM application_timeline at
        LEFT JOIN users u ON at.performed_by = u.id
        WHERE at.application_id = ?
        ORDER BY at.created_at DESC LIMIT 20");
    $stmt->execute([$id]);
    $timeline = $stmt->fetchAll();
    
    // Get compliance checks
    $stmt = $pdo->prepare("SELECT * FROM compliance_checks WHERE application_id = ? ORDER BY created_at DESC");
    $stmt->execute([$id]);
    $complianceChecks = $stmt->fetchAll();
    
    // Get EMI schedule
    $stmt = $pdo->prepare("SELECT * FROM emi_schedule WHERE application_id = ? ORDER BY installment_number ASC");
    $stmt->execute([$id]);
    $emiSchedule = $stmt->fetchAll();
    
    // Get verification results
    $stmt = $pdo->prepare("SELECT * FROM document_verifications WHERE application_id = ?");
    $stmt->execute([$id]);
    $verifications = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'application' => $app,
        'documents' => $documents,
        'risk_assessment' => $riskAssessment,
        'risk_factors' => $riskFactors,
        'reviews' => $reviews,
        'clarifications' => $clarifications,
        'loan_offer' => $loanOffer,
        'timeline' => $timeline,
        'compliance_checks' => $complianceChecks,
        'emi_schedule' => $emiSchedule,
        'verifications' => $verifications
    ]);
}

function getStats() {
    $pdo = getDBConnection();
    $userId = $_SESSION['user_id'];
    $role = $_SESSION['user_role'];
    
    $stats = [];
    
    if ($role === 'applicant') {
        $stmt = $pdo->prepare("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as drafts,
            SUM(CASE WHEN status IN ('submitted','under_review','document_verification','risk_assessment','compliance_check') THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'disbursed' THEN 1 ELSE 0 END) as disbursed,
            SUM(CASE WHEN status = 'clarification_required' THEN 1 ELSE 0 END) as clarification,
            COALESCE(SUM(requested_amount), 0) as total_requested,
            COALESCE(AVG(requested_amount), 0) as avg_amount
            FROM loan_applications WHERE user_id = ?");
        $stmt->execute([$userId]);
    } else {
        $stmt = $pdo->query("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
            SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review,
            SUM(CASE WHEN status = 'document_verification' THEN 1 ELSE 0 END) as doc_verification,
            SUM(CASE WHEN status = 'risk_assessment' THEN 1 ELSE 0 END) as risk_assessment,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'disbursed' THEN 1 ELSE 0 END) as disbursed,
            SUM(CASE WHEN status = 'clarification_required' THEN 1 ELSE 0 END) as clarification,
            COALESCE(SUM(requested_amount), 0) as total_requested,
            COALESCE(AVG(requested_amount), 0) as avg_amount
            FROM loan_applications");
    }
    
    $stats = $stmt->fetch();
    
    // Get recent applications
    if ($role === 'applicant') {
        $stmt = $pdo->prepare("SELECT la.application_id, la.status, la.requested_amount, la.created_at, lt.name as loan_type
            FROM loan_applications la JOIN loan_types lt ON la.loan_type_id = lt.id
            WHERE la.user_id = ? ORDER BY la.created_at DESC LIMIT 5");
        $stmt->execute([$userId]);
    } else {
        $stmt = $pdo->query("SELECT la.application_id, la.full_name, la.status, la.requested_amount, la.created_at, lt.name as loan_type
            FROM loan_applications la JOIN loan_types lt ON la.loan_type_id = lt.id
            ORDER BY la.created_at DESC LIMIT 10");
    }
    $stats['recent'] = $stmt->fetchAll();
    
    // Monthly trend (last 6 months)
    $stmt = $pdo->query("SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as count,
        SUM(requested_amount) as total_amount
        FROM loan_applications 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC");
    $stats['monthly_trend'] = $stmt->fetchAll();
    
    // Loan type distribution
    $stmt = $pdo->query("SELECT lt.name, COUNT(*) as count 
        FROM loan_applications la JOIN loan_types lt ON la.loan_type_id = lt.id 
        GROUP BY lt.name ORDER BY count DESC");
    $stats['loan_type_dist'] = $stmt->fetchAll();
    
    jsonResponse(['success' => true, 'stats' => $stats]);
}

function updateStatus() {
    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'applicant') {
        jsonResponse(['success' => false, 'message' => 'Applicants cannot modify review status'], 403);
    }
    
    $pdo = getDBConnection();
    $id = (int)($_POST['id'] ?? 0);
    $newStatus = sanitize($_POST['status'] ?? '');
    $userId = $_SESSION['user_id'];
    
    $validStatuses = ['under_review','document_verification','clarification_required','risk_assessment','compliance_check','approved','rejected','disbursement_pending','disbursed','closed','cancelled'];
    
    if (!in_array($newStatus, $validStatuses)) {
        jsonResponse(['success' => false, 'message' => 'Invalid status identifier'], 400);
    }

    // Fetch existing application
    $stmt = $pdo->prepare("SELECT * FROM loan_applications WHERE id = ?");
    $stmt->execute([$id]);
    $app = $stmt->fetch();

    if (!$app) {
        jsonResponse(['success' => false, 'message' => 'Application not found'], 404);
    }

    $currentStatus = $app['status'];

    // Strict State Machine Transitions
    $allowedTransitions = [
        'draft'                  => ['submitted', 'cancelled'],
        'submitted'              => ['under_review', 'document_verification', 'cancelled'],
        'under_review'           => ['document_verification', 'clarification_required', 'risk_assessment', 'rejected'],
        'document_verification'  => ['risk_assessment', 'clarification_required', 'rejected'],
        'clarification_required' => ['under_review', 'document_verification', 'rejected'],
        'risk_assessment'        => ['compliance_check', 'approved', 'rejected', 'clarification_required'],
        'compliance_check'       => ['approved', 'rejected', 'clarification_required'],
        'approved'               => ['disbursement_pending', 'disbursed', 'cancelled'],
        'disbursement_pending'   => ['disbursed', 'cancelled'],
        'disbursed'              => ['closed'],
        'closed'                 => [],
        'rejected'               => [],
        'cancelled'              => []
    ];

    if ($role !== 'admin' && isset($allowedTransitions[$currentStatus]) && !in_array($newStatus, $allowedTransitions[$currentStatus])) {
        $allowed = implode(', ', $allowedTransitions[$currentStatus]);
        jsonResponse([
            'success' => false, 
            'message' => "Invalid transition: Cannot move from '{$currentStatus}' to '{$newStatus}'. Allowed: [{$allowed}]"
        ], 400);
    }

    // Role-specific authorization checks
    if ($newStatus === 'approved' && !in_array($role, ['admin', 'loan_officer'])) {
        jsonResponse(['success' => false, 'message' => 'Only Loan Officers and Administrators have approval authority.'], 403);
    }
    if ($newStatus === 'disbursed' && !in_array($role, ['admin', 'loan_officer'])) {
        jsonResponse(['success' => false, 'message' => 'Disbursement requires officer or administrator credentials.'], 403);
    }

    // Additional params on approval (approved amount, interest rate, tenure)
    $approvedAmount = !empty($_POST['approved_amount']) ? (float)$_POST['approved_amount'] : ($app['approved_amount'] ?: $app['requested_amount']);
    $interestRate = !empty($_POST['interest_rate']) ? (float)$_POST['interest_rate'] : ($app['interest_rate'] ?: 10.50);
    $tenureMonths = !empty($_POST['tenure_months']) ? (int)$_POST['tenure_months'] : ($app['tenure_months'] ?: $app['requested_tenure']);
    $rejectionReason = !empty($_POST['reason']) ? sanitize($_POST['reason']) : $app['rejection_reason'];

    $updSql = "UPDATE loan_applications SET 
        status = ?, 
        approved_amount = ?, 
        interest_rate = ?, 
        tenure_months = ?, 
        rejection_reason = ? 
        WHERE id = ?";
    $pdo->prepare($updSql)->execute([$newStatus, $approvedAmount, $interestRate, $tenureMonths, $rejectionReason, $id]);

    // Automated trigger: Run Fraud & Explainable Risk engines when transitioning
    if (in_array($newStatus, ['risk_assessment', 'under_review'])) {
        require_once __DIR__ . '/../includes/fraud_detector.php';
        require_once __DIR__ . '/../includes/credit_risk_engine.php';
        $fd = new FraudDetector($pdo);
        $fd->analyzeApplication($id);
        $cre = new CreditRiskEngine($pdo);
        $cre->evaluateApplication($id);
    }

    // Automated trigger: Record ledger entry on disbursement
    if ($newStatus === 'disbursed') {
        require_once __DIR__ . '/../includes/loan_accounting.php';
        $acct = new LoanAccounting($pdo);
        $disbRef = 'DISB-' . date('Ymd') . '-' . $app['application_id'];
        $acct->recordDisbursement($id, $approvedAmount, $disbRef, $userId);

        $pdo->prepare("UPDATE loan_applications SET disbursed_at = NOW(), disbursed_by = ?, disbursement_reference = ? WHERE id = ?")
            ->execute([$userId, $disbRef, $id]);

        // Generate EMI schedule if missing
        $checkSchedule = $pdo->prepare("SELECT COUNT(*) FROM emi_schedule WHERE application_id = ?");
        $checkSchedule->execute([$id]);
        if ($checkSchedule->fetchColumn() == 0) {
            $monthlyRate = ($interestRate / 12) / 100;
            $emi = ($approvedAmount * $monthlyRate * pow(1 + $monthlyRate, $tenureMonths)) / (pow(1 + $monthlyRate, $tenureMonths) - 1);
            $bal = $approvedAmount;
            $insEmi = $pdo->prepare("INSERT INTO emi_schedule (application_id, installment_number, due_date, emi_amount, principal_component, interest_component, outstanding_balance, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'upcoming')");

            for ($i = 1; $i <= $tenureMonths; $i++) {
                $intPart = $bal * $monthlyRate;
                $prinPart = $emi - $intPart;
                $bal = max(0, $bal - $prinPart);
                $dueDate = date('Y-m-d', strtotime("+$i month"));
                $insEmi->execute([$id, $i, $dueDate, round($emi, 2), round($prinPart, 2), round($intPart, 2), round($bal, 2)]);
            }
        }
    }
    
    $statusLabels = [
        'under_review' => 'Under Review',
        'document_verification' => 'Document Verification',
        'clarification_required' => 'Clarification Required',
        'risk_assessment' => 'Risk Assessment',
        'compliance_check' => 'Compliance Check',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'disbursement_pending' => 'Disbursement Pending',
        'disbursed' => 'Disbursed',
        'closed' => 'Closed',
        'cancelled' => 'Cancelled'
    ];
    
    $label = $statusLabels[$newStatus] ?? $newStatus;
    
    addTimelineEvent($id, 'status_change', 'Status Updated to ' . $label, 'Application status has been changed to ' . $label . '.', $userId);
    sendNotification($app['user_id'], 'Application Status Updated', 'Your application ' . $app['application_id'] . ' status has been updated to: ' . $label, 'info', 'application', $id);
    
    // Lifecycle Email Dispatch
    require_once __DIR__ . '/../includes/mailer.php';
    $uStmt = $pdo->prepare("SELECT email, full_name FROM users WHERE id = ?");
    $uStmt->execute([$app['user_id']]);
    $applicant = $uStmt->fetch();
    if ($applicant && !empty($applicant['email'])) {
        if ($newStatus === 'approved') {
            @emailApplicationApproved($applicant['email'], $applicant['full_name'], $app['application_id'], $approvedAmount, $interestRate, $tenureMonths);
        } elseif ($newStatus === 'rejected') {
            @emailApplicationRejected($applicant['email'], $applicant['full_name'], $app['application_id'], $rejectionReason ?: 'Policy criteria not met.');
        } else {
            @emailStatusChange($applicant['email'], $applicant['full_name'], $app['application_id'], $currentStatus, $newStatus);
        }
    }
    
    logAudit($userId, 'status_updated', 'loan_application', $id, ['from' => $currentStatus], ['to' => $newStatus, 'amount' => $approvedAmount]);
    
    jsonResponse(['success' => true, 'message' => 'Status updated to ' . $label]);
}

function assignApplication() {
    if (!in_array($_SESSION['user_role'], ['admin', 'loan_officer'])) {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }
    
    $pdo = getDBConnection();
    $id = (int)($_POST['id'] ?? 0);
    $officerId = (int)($_POST['officer_id'] ?? 0) ?: null;
    $analystId = (int)($_POST['analyst_id'] ?? 0) ?: null;
    
    $stmt = $pdo->prepare("UPDATE loan_applications SET assigned_officer_id = ?, assigned_analyst_id = ? WHERE id = ?");
    $stmt->execute([$officerId, $analystId, $id]);
    
    logAudit($_SESSION['user_id'], 'application_assigned', 'loan_application', $id);
    
    jsonResponse(['success' => true, 'message' => 'Application assigned successfully']);
}

function getTimeline() {
    $pdo = getDBConnection();
    $id = (int)($_GET['id'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT at.*, u.full_name as performer_name
        FROM application_timeline at
        LEFT JOIN users u ON at.performed_by = u.id
        WHERE at.application_id = ?
        ORDER BY at.created_at ASC");
    $stmt->execute([$id]);
    
    jsonResponse(['success' => true, 'timeline' => $stmt->fetchAll()]);
}
