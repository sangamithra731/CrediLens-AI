<?php
/**
 * CrediLens AI — Authentication Middleware
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require authentication — redirect to login if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        if (!headers_sent()) {
            header('Location: index.php?page=login');
        }
        exit;
    }
}

/**
 * Require specific role
 */
function requireRole($allowedRoles) {
    requireLogin();
    if (!in_array($_SESSION['user_role'], (array)$allowedRoles)) {
        http_response_code(403);
        die('<h1>403 — Access Denied</h1><p>You do not have permission to access this page.</p>');
    }
}

/**
 * Get current user data (fetches from DB for full profile details)
 */
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT u.*, r.slug as role FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user) {
            return $user;
        }
    } catch (Exception $e) {
        // Fallback to session data
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'full_name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role_id' => $_SESSION['user_role_id'],
        'role' => $_SESSION['user_role'],
        'phone' => '',
        'created_at' => date('Y-m-d'),
        'last_login' => null,
        'avatar' => $_SESSION['user_avatar'] ?? null
    ];
}

/**
 * Check if user has a specific permission
 */
function hasPermission($permission) {
    if (!isLoggedIn()) return false;
    
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as cnt FROM role_permissions rp
        JOIN permissions p ON rp.permission_id = p.id
        WHERE rp.role_id = ? AND p.permission_name = ?
    ");
    $stmt->execute([$_SESSION['user_role_id'], $permission]);
    return $stmt->fetch()['cnt'] > 0;
}

/**
 * CSRF Protection
 */
function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token = null) {
    $submitted = $token ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || empty($submitted)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $submitted);
}

/**
 * Secure document authorization check
 * Ensures applicants can only see their own files, while staff can view assigned/all
 */
function canAccessDocument($documentId, $userId = null, $userRole = null) {
    $userId = $userId ?? $_SESSION['user_id'] ?? 0;
    $userRole = $userRole ?? $_SESSION['user_role'] ?? '';

    if (in_array($userRole, ['admin', 'compliance_officer', 'loan_officer', 'risk_analyst'])) {
        return true;
    }

    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT la.user_id FROM application_documents ad 
                           JOIN loan_applications la ON ad.application_id = la.id 
                           WHERE ad.id = ?");
    $stmt->execute([$documentId]);
    $ownerId = $stmt->fetchColumn();

    return $ownerId && (int)$ownerId === (int)$userId;
}

/**
 * Get unread notification count
 */
function getUnreadNotificationCount() {
    if (!isLoggedIn()) return 0;
    
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch()['cnt'];
}

