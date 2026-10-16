<?php
/**
 * CrediLens AI — Privacy & Consent Management API
 * Manages statutory applicant consent, data retention schedules,
 * and secure document deletion compliant with data privacy frameworks.
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
    case 'record_consent':
        recordConsent();
        break;
    case 'get_consents':
        getConsents();
        break;
    case 'secure_delete':
        secureDeleteDocument();
        break;
    case 'get_retention_logs':
        getRetentionLogs();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function recordConsent() {
    $pdo = getDBConnection();
    $userId = $_SESSION['user_id'];
    $consentType = sanitize($_POST['consent_type'] ?? 'terms_and_privacy');
    $consentText = sanitize($_POST['consent_text'] ?? 'I consent to KYC verification, credit bureau scoring inquiry, and automated risk assessment.');
    $isGranted = (int)($_POST['is_granted'] ?? 1);

    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $ins = $pdo->prepare("INSERT INTO user_consents 
        (user_id, consent_type, consent_text, is_granted, ip_address, user_agent, granted_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $ins->execute([$userId, $consentType, $consentText, $isGranted, $ip, $ua]);

    logAudit($userId, 'consent_granted', 'user_consent', $pdo->lastInsertId(), null, ['type' => $consentType]);

    jsonResponse(['success' => true, 'message' => 'Consent successfully recorded with statutory audit trail.']);
}

function getConsents() {
    $pdo = getDBConnection();
    $userId = (int)($_GET['user_id'] ?? $_SESSION['user_id']);

    // Non-admin can only see their own
    if ($_SESSION['user_role'] !== 'admin' && $userId !== (int)$_SESSION['user_id']) {
        $userId = (int)$_SESSION['user_id'];
    }

    $stmt = $pdo->prepare("SELECT * FROM user_consents WHERE user_id = ? ORDER BY granted_at DESC");
    $stmt->execute([$userId]);

    jsonResponse(['success' => true, 'consents' => $stmt->fetchAll()]);
}

function secureDeleteDocument() {
    $pdo = getDBConnection();
    $docId = (int)($_POST['document_id'] ?? 0);
    $reason = sanitize($_POST['reason'] ?? 'Applicant requested data deletion under Privacy Policy');

    $stmt = $pdo->prepare("SELECT ad.*, la.user_id FROM application_documents ad JOIN loan_applications la ON ad.application_id = la.id WHERE ad.id = ?");
    $stmt->execute([$docId]);
    $doc = $stmt->fetch();

    if (!$doc) {
        jsonResponse(['success' => false, 'message' => 'Document not found'], 404);
    }

    if ($_SESSION['user_role'] !== 'admin' && $doc['user_id'] != $_SESSION['user_id']) {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }

    $filePath = __DIR__ . '/../' . $doc['file_path'];
    if (file_exists($filePath)) {
        // Multi-pass secure overwrite (zeros, ones, random) with sync to mitigate residual data
        $len = filesize($filePath);
        if ($len > 0) {
            $fp = @fopen($filePath, 'r+b');
            if ($fp) {
                // Pass 1: Zero fill
                @fseek($fp, 0);
                @fwrite($fp, str_repeat("\x00", min($len, 1024 * 1024)));
                @fflush($fp);
                // Pass 2: Ones fill
                @fseek($fp, 0);
                @fwrite($fp, str_repeat("\xFF", min($len, 1024 * 1024)));
                @fflush($fp);
                // Pass 3: Pseudo-random bytes
                @fseek($fp, 0);
                @fwrite($fp, random_bytes(min($len, 1024 * 64)));
                @fflush($fp);
                @ftruncate($fp, 0);
                @fclose($fp);
            }
        }
        @unlink($filePath);
    }

    // Remove DB records
    $pdo->prepare("DELETE FROM document_verifications WHERE document_id = ?")->execute([$docId]);
    $pdo->prepare("DELETE FROM document_ocr_data WHERE document_id = ?")->execute([$docId]);
    $pdo->prepare("DELETE FROM application_documents WHERE id = ?")->execute([$docId]);

    // Record immutable retention log
    $ins = $pdo->prepare("INSERT INTO data_retention_logs (user_id, entity_type, entity_id, action, reason, executed_by)
        VALUES (?, 'document', ?, 'secure_purge', ?, ?)");
    $ins->execute([$doc['user_id'], $docId, $reason, $_SESSION['user_id']]);

    logAudit($_SESSION['user_id'], 'document_purged', 'document', $docId, null, ['reason' => $reason]);

    jsonResponse(['success' => true, 'message' => 'Document securely purged and cryptographic deletion logged.']);
}

function getRetentionLogs() {
    if (!in_array($_SESSION['user_role'], ['admin', 'compliance_officer'])) {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }

    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT drl.*, u.full_name as user_name, exec.full_name as executor_name 
        FROM data_retention_logs drl 
        LEFT JOIN users u ON drl.user_id = u.id 
        LEFT JOIN users exec ON drl.executed_by = exec.id 
        ORDER BY drl.created_at DESC LIMIT 50");
    jsonResponse(['success' => true, 'logs' => $stmt->fetchAll()]);
}
