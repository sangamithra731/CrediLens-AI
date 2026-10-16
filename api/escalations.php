<?php
/**
 * CrediLens AI — Application Escalation & Clarification API
 * Handles high-risk escalations, clarification requests, and exception reviews.
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Authentication required'], 401);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'create_escalation':
        createEscalation();
        break;
    case 'list_escalations':
        listEscalations();
        break;
    case 'resolve_escalation':
        resolveEscalation();
        break;
    case 'request_clarification':
        requestClarification();
        break;
    case 'list_clarifications':
        listClarifications();
        break;
    case 'reply_clarification':
        replyClarification();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function createEscalation() {
    if ($_SESSION['user_role'] === 'applicant') {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }

    $pdo = getDBConnection();
    $applicationId = (int)($_POST['application_id'] ?? 0);
    $priority = $_POST['priority'] ?? 'high';
    $reason = sanitize($_POST['reason'] ?? 'High risk / Policy deviation identified');
    $details = sanitize($_POST['details'] ?? '');
    $assignedTo = (int)($_POST['assigned_to'] ?? 0) ?: null;

    $ins = $pdo->prepare("INSERT INTO application_escalations 
        (application_id, escalated_by, assigned_to, priority, reason, details, status)
        VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    $ins->execute([$applicationId, $_SESSION['user_id'], $assignedTo, $priority, $reason, $details]);
    $escId = $pdo->lastInsertId();

    // Update application escalation status
    $pdo->prepare("UPDATE loan_applications SET escalation_status = 'escalated_to_risk' WHERE id = ?")->execute([$applicationId]);

    addTimelineEvent($applicationId, 'escalation', "Case Escalated ($priority Priority)", $reason, $_SESSION['user_id']);
    logAudit($_SESSION['user_id'], 'case_escalated', 'application', $applicationId);

    jsonResponse(['success' => true, 'message' => 'Application escalated successfully for senior risk review.', 'id' => $escId]);
}

function listEscalations() {
    $pdo = getDBConnection();
    $role = $_SESSION['user_role'];
    $userId = $_SESSION['user_id'];

    $where = [];
    $params = [];

    if ($role === 'applicant') {
        jsonResponse(['success' => true, 'escalations' => []]);
    }

    $stmt = $pdo->query("SELECT ae.*, la.application_id as app_code, la.full_name as applicant_name, 
        la.requested_amount, u.full_name as escalater_name
        FROM application_escalations ae
        JOIN loan_applications la ON ae.application_id = la.id
        LEFT JOIN users u ON ae.escalated_by = u.id
        ORDER BY ae.created_at DESC");

    jsonResponse(['success' => true, 'escalations' => $stmt->fetchAll()]);
}

function resolveEscalation() {
    if (!in_array($_SESSION['user_role'], ['admin', 'risk_analyst', 'compliance_officer'])) {
        jsonResponse(['success' => false, 'message' => 'Only risk analysts or admins can resolve escalations'], 403);
    }

    $pdo = getDBConnection();
    $escId = (int)($_POST['escalation_id'] ?? 0);
    $notes = sanitize($_POST['resolution_notes'] ?? 'Reviewed and cleared under exception authority.');
    $newStatus = $_POST['status'] ?? 'resolved';

    $stmt = $pdo->prepare("UPDATE application_escalations SET status = ?, resolution_notes = ?, resolved_at = NOW() WHERE id = ?");
    $stmt->execute([$newStatus, $notes, $escId]);

    // Clear escalation status on application
    $esc = $pdo->prepare("SELECT application_id FROM application_escalations WHERE id = ?");
    $esc->execute([$escId]);
    $appId = $esc->fetchColumn();
    if ($appId) {
        $pdo->prepare("UPDATE loan_applications SET escalation_status = 'none' WHERE id = ?")->execute([$appId]);
        addTimelineEvent($appId, 'escalation_resolved', 'Escalation Cleared', $notes, $_SESSION['user_id']);
    }

    jsonResponse(['success' => true, 'message' => 'Escalation resolved successfully.']);
}

function requestClarification() {
    if ($_SESSION['user_role'] === 'applicant') {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }

    $pdo = getDBConnection();
    $applicationId = (int)($_POST['application_id'] ?? 0);
    $subject = sanitize($_POST['subject'] ?? 'Information / Document Clarification Required');
    $query = sanitize($_POST['query'] ?? '');

    $ins = $pdo->prepare("INSERT INTO application_clarifications 
        (application_id, requested_by, subject, query, status)
        VALUES (?, ?, ?, ?, 'pending_applicant')");
    $ins->execute([$applicationId, $_SESSION['user_id'], $subject, $query]);

    // Update status
    $pdo->prepare("UPDATE loan_applications SET status = 'clarification_required', escalation_status = 'clarification_requested' WHERE id = ?")->execute([$applicationId]);

    // Notify applicant
    $app = $pdo->prepare("SELECT la.user_id, la.application_id, u.email, u.full_name 
        FROM loan_applications la JOIN users u ON la.user_id = u.id WHERE la.id = ?");
    $app->execute([$applicationId]);
    $appData = $app->fetch();

    if ($appData) {
        sendNotification($appData['user_id'], 'Clarification Requested: ' . $subject, $query, 'warning', 'application', $applicationId);
        @emailStatusChange($appData['email'], $appData['full_name'], $appData['application_id'], 'under_review', 'clarification_required');
    }

    addTimelineEvent($applicationId, 'clarification_requested', 'Clarification Requested: ' . $subject, $query, $_SESSION['user_id']);

    jsonResponse(['success' => true, 'message' => 'Clarification request sent to applicant.']);
}

function listClarifications() {
    $pdo = getDBConnection();
    $applicationId = (int)($_GET['application_id'] ?? 0);

    $sql = "SELECT ac.*, u.full_name as requester_name 
        FROM application_clarifications ac 
        LEFT JOIN users u ON ac.requested_by = u.id";
    
    if ($applicationId > 0) {
        $stmt = $pdo->prepare($sql . " WHERE ac.application_id = ? ORDER BY ac.created_at DESC");
        $stmt->execute([$applicationId]);
    } else {
        $stmt = $pdo->query($sql . " ORDER BY ac.created_at DESC LIMIT 50");
    }

    jsonResponse(['success' => true, 'clarifications' => $stmt->fetchAll()]);
}

function replyClarification() {
    $pdo = getDBConnection();
    $clarId = (int)($_POST['clarification_id'] ?? 0);
    $response = sanitize($_POST['response'] ?? '');

    $stmt = $pdo->prepare("UPDATE application_clarifications SET applicant_response = ?, status = 'responded', responded_at = NOW() WHERE id = ?");
    $stmt->execute([$response, $clarId]);

    // Get application
    $clar = $pdo->prepare("SELECT application_id FROM application_clarifications WHERE id = ?");
    $clar->execute([$clarId]);
    $appId = $clar->fetchColumn();

    if ($appId) {
        $pdo->prepare("UPDATE loan_applications SET status = 'under_review', escalation_status = 'none' WHERE id = ?")->execute([$appId]);
        addTimelineEvent($appId, 'clarification_responded', 'Applicant Provided Clarification', $response, $_SESSION['user_id']);
    }

    jsonResponse(['success' => true, 'message' => 'Response submitted successfully. Application returned to review queue.']);
}
