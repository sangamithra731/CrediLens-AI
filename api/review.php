<?php
/**
 * CrediLens AI — Review & Decision API
 * Handles application reviews, clarifications, decisions, loan offers
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
    case 'submit_review': submitReview(); break;
    case 'request_clarification': requestClarification(); break;
    case 'respond_clarification': respondToClarification(); break;
    case 'generate_offer': generateOffer(); break;
    case 'accept_offer': acceptOffer(); break;
    case 'create_agreement': createAgreement(); break;
    case 'sign_agreement': signAgreement(); break;
    case 'process_disbursement': processDisbursement(); break;
    case 'generate_emi_schedule': generateEMISchedule(); break;
    case 'record_payment': recordPayment(); break;
    case 'get_loan_details': getLoanDetails(); break;
    default: jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function submitReview() {
    if ($_SESSION['user_role'] === 'applicant') {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }
    
    $pdo = getDBConnection();
    $applicationId = (int)($_POST['application_id'] ?? 0);
    $reviewType = sanitize($_POST['review_type'] ?? 'final_review');
    $decision = sanitize($_POST['decision'] ?? '');
    $reason = sanitize($_POST['reason'] ?? '');
    $conditions = sanitize($_POST['conditions'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');
    $userId = $_SESSION['user_id'];
    
    if (!in_array($decision, ['approve', 'reject', 'clarify', 'escalate', 'hold'])) {
        jsonResponse(['success' => false, 'message' => 'Invalid decision'], 400);
    }
    
    // Save review
    $stmt = $pdo->prepare("INSERT INTO application_reviews (application_id, reviewer_id, review_type, decision, decision_reason, conditions, notes, reviewed_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$applicationId, $userId, $reviewType, $decision, $reason, $conditions, $notes]);
    
    // Get application
    $stmt = $pdo->prepare("SELECT * FROM loan_applications WHERE id = ?");
    $stmt->execute([$applicationId]);
    $app = $stmt->fetch();
    
    // Update application status based on decision
    $statusMap = [
        'approve' => 'approved',
        'reject' => 'rejected',
        'clarify' => 'clarification_required',
        'escalate' => 'compliance_check',
        'hold' => 'under_review'
    ];
    
    $newStatus = $statusMap[$decision] ?? 'under_review';
    $stmt = $pdo->prepare("UPDATE loan_applications SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $applicationId]);

    // Populate Escalation & Clarification workflows
    if ($decision === 'escalate') {
        $ins = $pdo->prepare("INSERT INTO application_escalations (application_id, escalated_by, priority, reason, details, status) VALUES (?, ?, 'high', ?, ?, 'pending')");
        $ins->execute([$applicationId, $userId, 'Officer Escalation', $reason]);
        $pdo->prepare("UPDATE loan_applications SET escalation_status = 'escalated_to_risk' WHERE id = ?")->execute([$applicationId]);
    } elseif ($decision === 'clarify') {
        $ins = $pdo->prepare("INSERT INTO application_clarifications (application_id, requested_by, subject, query, status) VALUES (?, ?, 'Officer Clarification Request', ?, 'pending_applicant')");
        $ins->execute([$applicationId, $userId, $reason]);
        $pdo->prepare("UPDATE loan_applications SET escalation_status = 'clarification_requested' WHERE id = ?")->execute([$applicationId]);
    }
    
    // Notification labels
    $decisionLabels = [
        'approve' => 'Approved',
        'reject' => 'Rejected',
        'clarify' => 'Clarification Required',
        'escalate' => 'Escalated for Review',
        'hold' => 'Put on Hold'
    ];
    
    $label = $decisionLabels[$decision];
    
    addTimelineEvent($applicationId, 'decision', "Decision: $label", "Review decision: $label. Reason: $reason", $userId);
    
    if ($app) {
        $notifType = ($decision === 'approve') ? 'success' : (($decision === 'reject') ? 'error' : 'warning');
        sendNotification($app['user_id'], "Application $label", "Your application {$app['application_id']} has been $label. " . ($reason ? "Reason: $reason" : ''), $notifType, 'review', $applicationId);
    }
    
    logAudit($userId, 'review_submitted', 'loan_application', $applicationId, null, ['decision' => $decision, 'reason' => $reason]);
    
    jsonResponse(['success' => true, 'message' => "Application has been $label"]);
}

function requestClarification() {
    if ($_SESSION['user_role'] === 'applicant') {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }
    
    $pdo = getDBConnection();
    $applicationId = (int)($_POST['application_id'] ?? 0);
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');
    $requiredDocs = $_POST['required_documents'] ?? '[]';
    $dueDate = $_POST['due_date'] ?? date('Y-m-d', strtotime('+7 days'));
    
    $stmt = $pdo->prepare("INSERT INTO clarification_requests (application_id, requested_by, subject, message, required_documents, due_date) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$applicationId, $_SESSION['user_id'], $subject, $message, $requiredDocs, $dueDate]);
    
    // Update status
    $stmt = $pdo->prepare("UPDATE loan_applications SET status = 'clarification_required' WHERE id = ?");
    $stmt->execute([$applicationId]);
    
    // Notify applicant
    $stmt = $pdo->prepare("SELECT user_id, application_id as app_id FROM loan_applications WHERE id = ?");
    $stmt->execute([$applicationId]);
    $app = $stmt->fetch();
    if ($app) {
        sendNotification($app['user_id'], 'Clarification Required', "Your application {$app['app_id']} requires additional information: $subject", 'warning', 'application', $applicationId);
    }
    
    addTimelineEvent($applicationId, 'clarification_requested', 'Clarification Requested', $subject . ': ' . $message, $_SESSION['user_id']);
    
    jsonResponse(['success' => true, 'message' => 'Clarification request sent']);
}

function respondToClarification() {
    $pdo = getDBConnection();
    $clarificationId = (int)($_POST['clarification_id'] ?? 0);
    $response = sanitize($_POST['response'] ?? '');
    
    $stmt = $pdo->prepare("UPDATE clarification_requests SET status = 'responded', response = ?, response_at = NOW() WHERE id = ?");
    $stmt->execute([$response, $clarificationId]);
    
    // Get clarification to find application
    $stmt = $pdo->prepare("SELECT cr.*, la.application_id as app_str_id FROM clarification_requests cr JOIN loan_applications la ON cr.application_id = la.id WHERE cr.id = ?");
    $stmt->execute([$clarificationId]);
    $clar = $stmt->fetch();
    
    if ($clar) {
        // Update application status back to under_review
        $stmt = $pdo->prepare("UPDATE loan_applications SET status = 'under_review' WHERE id = ?");
        $stmt->execute([$clar['application_id']]);
        
        sendNotification($clar['requested_by'], 'Clarification Response Received', "Applicant has responded to clarification for {$clar['app_str_id']}", 'info', 'application', $clar['application_id']);
        addTimelineEvent($clar['application_id'], 'clarification_responded', 'Clarification Response Submitted', $response, $_SESSION['user_id']);
    }
    
    jsonResponse(['success' => true, 'message' => 'Response submitted']);
}

function generateOffer() {
    if (!in_array($_SESSION['user_role'], ['admin', 'loan_officer'])) {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }
    
    $pdo = getDBConnection();
    $applicationId = (int)($_POST['application_id'] ?? 0);
    $approvedAmount = (float)($_POST['approved_amount'] ?? 0);
    $interestRate = (float)($_POST['interest_rate'] ?? 12);
    $tenure = (int)($_POST['tenure_months'] ?? 12);
    $processingFee = (float)($_POST['processing_fee'] ?? 0);
    
    $emiAmount = calculateEMI($approvedAmount, $interestRate, $tenure);
    $totalPayable = $emiAmount * $tenure;
    $totalInterest = $totalPayable - $approvedAmount;
    
    // Get validity days from policy
    $stmt = $pdo->query("SELECT policy_value FROM lending_policies WHERE policy_key = 'offer_validity_days'");
    $validityDays = (int)($stmt->fetch()['policy_value'] ?? 30);
    
    $stmt = $pdo->prepare("INSERT INTO loan_offers (application_id, approved_amount, interest_rate, tenure_months, emi_amount, total_interest, total_payable, processing_fee, offer_status, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'sent', DATE_ADD(NOW(), INTERVAL ? DAY))");
    $stmt->execute([$applicationId, $approvedAmount, $interestRate, $tenure, $emiAmount, $totalInterest, $totalPayable, $processingFee, $validityDays]);
    
    $offerId = $pdo->lastInsertId();
    
    // Update status
    $stmt = $pdo->prepare("UPDATE loan_applications SET status = 'approved' WHERE id = ?");
    $stmt->execute([$applicationId]);
    
    // Notify applicant
    $stmt = $pdo->prepare("SELECT user_id, application_id as app_id FROM loan_applications WHERE id = ?");
    $stmt->execute([$applicationId]);
    $app = $stmt->fetch();
    if ($app) {
        sendNotification($app['user_id'], 'Loan Offer Generated!', "Congratulations! Your loan of ₹" . number_format($approvedAmount) . " at {$interestRate}% has been approved. EMI: ₹" . number_format($emiAmount), 'success', 'application', $applicationId);
    }
    
    addTimelineEvent($applicationId, 'offer_generated', 'Loan Offer Generated', "Amount: ₹" . number_format($approvedAmount) . ", Rate: {$interestRate}%, Tenure: {$tenure} months, EMI: ₹" . number_format($emiAmount), $_SESSION['user_id']);
    
    jsonResponse([
        'success' => true,
        'message' => 'Loan offer generated',
        'offer' => [
            'id' => $offerId,
            'approved_amount' => $approvedAmount,
            'interest_rate' => $interestRate,
            'tenure_months' => $tenure,
            'emi_amount' => $emiAmount,
            'total_interest' => $totalInterest,
            'total_payable' => $totalPayable,
            'processing_fee' => $processingFee
        ]
    ]);
}

function acceptOffer() {
    $pdo = getDBConnection();
    $offerId = (int)($_POST['offer_id'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT lo.*, la.user_id FROM loan_offers lo JOIN loan_applications la ON lo.application_id = la.id WHERE lo.id = ?");
    $stmt->execute([$offerId]);
    $offer = $stmt->fetch();
    
    if (!$offer || $offer['user_id'] != $_SESSION['user_id']) {
        jsonResponse(['success' => false, 'message' => 'Offer not found or access denied'], 404);
    }
    
    $stmt = $pdo->prepare("UPDATE loan_offers SET offer_status = 'accepted', accepted_at = NOW() WHERE id = ?");
    $stmt->execute([$offerId]);
    
    addTimelineEvent($offer['application_id'], 'offer_accepted', 'Loan Offer Accepted', 'Applicant has accepted the loan offer.', $_SESSION['user_id']);
    
    jsonResponse(['success' => true, 'message' => 'Offer accepted successfully']);
}

function createAgreement() {
    if (!in_array($_SESSION['user_role'], ['admin', 'loan_officer'])) {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }
    
    $pdo = getDBConnection();
    $applicationId = (int)($_POST['application_id'] ?? 0);
    $offerId = (int)($_POST['offer_id'] ?? 0);
    
    $agreementNumber = generateAgreementNumber();
    
    $stmt = $pdo->prepare("INSERT INTO loan_agreements (application_id, offer_id, agreement_number) VALUES (?, ?, ?)");
    $stmt->execute([$applicationId, $offerId, $agreementNumber]);
    
    addTimelineEvent($applicationId, 'agreement_created', 'Loan Agreement Created', "Agreement #$agreementNumber generated.", $_SESSION['user_id']);
    
    jsonResponse(['success' => true, 'message' => 'Agreement created', 'agreement_number' => $agreementNumber]);
}

function signAgreement() {
    $pdo = getDBConnection();
    $agreementId = (int)($_POST['agreement_id'] ?? 0);
    
    $signatureHash = hash('sha256', $_SESSION['user_id'] . time() . rand());
    
    $stmt = $pdo->prepare("UPDATE loan_agreements SET e_signed = 1, e_signed_at = NOW(), e_signature_hash = ? WHERE id = ?");
    $stmt->execute([$signatureHash, $agreementId]);
    
    // Get application ID
    $stmt = $pdo->prepare("SELECT application_id FROM loan_agreements WHERE id = ?");
    $stmt->execute([$agreementId]);
    $agr = $stmt->fetch();
    
    if ($agr) {
        $stmt = $pdo->prepare("UPDATE loan_applications SET status = 'disbursement_pending' WHERE id = ?");
        $stmt->execute([$agr['application_id']]);
        addTimelineEvent($agr['application_id'], 'agreement_signed', 'Agreement E-Signed', 'Digital signature recorded.', $_SESSION['user_id']);
    }
    
    jsonResponse(['success' => true, 'message' => 'Agreement signed digitally']);
}

function processDisbursement() {
    if (!in_array($_SESSION['user_role'], ['admin', 'loan_officer'])) {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }
    
    $pdo = getDBConnection();
    $applicationId = (int)($_POST['application_id'] ?? 0);
    $agreementId = (int)($_POST['agreement_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $bankAccount = sanitize($_POST['bank_account'] ?? '');
    $bankName = sanitize($_POST['bank_name'] ?? '');
    $ifsc = sanitize($_POST['ifsc_code'] ?? '');
    
    $referenceNumber = 'TXN' . date('YmdHis') . rand(1000, 9999);
    
    $stmt = $pdo->prepare("INSERT INTO disbursements (application_id, agreement_id, disbursement_amount, disbursement_date, reference_number, bank_account_number, bank_name, ifsc_code, status, completed_at) VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, 'completed', NOW())");
    $stmt->execute([$applicationId, $agreementId, $amount, $referenceNumber, $bankAccount, $bankName, $ifsc]);
    
    // Update status
    $stmt = $pdo->prepare("UPDATE loan_applications SET status = 'disbursed' WHERE id = ?");
    $stmt->execute([$applicationId]);
    
    // Notify applicant
    $stmt = $pdo->prepare("SELECT user_id, application_id as app_id FROM loan_applications WHERE id = ?");
    $stmt->execute([$applicationId]);
    $app = $stmt->fetch();
    if ($app) {
        sendNotification($app['user_id'], 'Loan Disbursed! 🎉', "₹" . number_format($amount) . " has been disbursed to your bank account. Reference: $referenceNumber", 'success', 'payment', $applicationId);
    }
    
    addTimelineEvent($applicationId, 'disbursed', 'Loan Disbursed', "₹" . number_format($amount) . " disbursed. Ref: $referenceNumber", $_SESSION['user_id']);
    
    jsonResponse(['success' => true, 'message' => 'Disbursement processed (simulated)', 'reference' => $referenceNumber]);
}

function generateEMISchedule() {
    $pdo = getDBConnection();
    $applicationId = (int)($_POST['application_id'] ?? 0);
    
    // Get loan offer
    $stmt = $pdo->prepare("SELECT * FROM loan_offers WHERE application_id = ? AND offer_status IN ('accepted','sent') ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$applicationId]);
    $offer = $stmt->fetch();
    
    if (!$offer) {
        jsonResponse(['success' => false, 'message' => 'No accepted loan offer found'], 404);
    }
    
    // Clear existing schedule
    $stmt = $pdo->prepare("DELETE FROM emi_schedule WHERE application_id = ?");
    $stmt->execute([$applicationId]);
    
    $principal = $offer['approved_amount'];
    $rate = $offer['interest_rate'];
    $tenure = $offer['tenure_months'];
    $emi = $offer['emi_amount'];
    $monthlyRate = ($rate / 100) / 12;
    $balance = $principal;
    
    $startDate = new DateTime();
    $startDate->modify('first day of next month');
    
    for ($i = 1; $i <= $tenure; $i++) {
        $interestComponent = round($balance * $monthlyRate, 2);
        $principalComponent = round($emi - $interestComponent, 2);
        $balance = round($balance - $principalComponent, 2);
        if ($i == $tenure) {
            $principalComponent += $balance;
            $balance = 0;
        }
        
        $dueDate = clone $startDate;
        $dueDate->modify('+' . ($i - 1) . ' months');
        
        $status = ($i <= 3) ? 'paid' : (($i == 4) ? 'due' : 'upcoming');
        $paidAmount = ($status === 'paid') ? $emi : 0;
        $paidDate = ($status === 'paid') ? $dueDate->format('Y-m-d') : null;
        
        $stmt = $pdo->prepare("INSERT INTO emi_schedule (application_id, installment_number, due_date, emi_amount, principal_component, interest_component, outstanding_balance, payment_status, paid_amount, paid_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$applicationId, $i, $dueDate->format('Y-m-d'), $emi, $principalComponent, $interestComponent, max(0, $balance), $status, $paidAmount, $paidDate]);
    }
    
    addTimelineEvent($applicationId, 'emi_generated', 'EMI Schedule Generated', "$tenure installments of ₹" . number_format($emi) . " each.", $_SESSION['user_id']);
    
    jsonResponse(['success' => true, 'message' => 'EMI schedule generated', 'installments' => $tenure, 'emi_amount' => $emi]);
}

function recordPayment() {
    $pdo = getDBConnection();
    $emiId = (int)($_POST['emi_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $reference = sanitize($_POST['reference'] ?? 'PAY' . time());
    
    $stmt = $pdo->prepare("UPDATE emi_schedule SET payment_status = 'paid', paid_amount = ?, paid_date = CURDATE(), payment_reference = ? WHERE id = ?");
    $stmt->execute([$amount, $reference, $emiId]);
    
    jsonResponse(['success' => true, 'message' => 'Payment recorded']);
}

function getLoanDetails() {
    $pdo = getDBConnection();
    $applicationId = (int)($_GET['application_id'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT * FROM loan_offers WHERE application_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$applicationId]);
    $offer = $stmt->fetch();
    
    $stmt = $pdo->prepare("SELECT * FROM loan_agreements WHERE application_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$applicationId]);
    $agreement = $stmt->fetch();
    
    $stmt = $pdo->prepare("SELECT * FROM disbursements WHERE application_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$applicationId]);
    $disbursement = $stmt->fetch();
    
    $stmt = $pdo->prepare("SELECT * FROM emi_schedule WHERE application_id = ? ORDER BY installment_number ASC");
    $stmt->execute([$applicationId]);
    $emiSchedule = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'offer' => $offer,
        'agreement' => $agreement,
        'disbursement' => $disbursement,
        'emi_schedule' => $emiSchedule
    ]);
}
