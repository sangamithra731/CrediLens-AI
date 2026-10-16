<?php
/**
 * CrediLens AI — Authentication API
 * Handles: Register, Login, Logout, Forgot Password, Profile Update
 */
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'register':
        handleRegister();
        break;
    case 'login':
        handleLogin();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'forgot_password':
        handleForgotPassword();
        break;
    case 'reset_password':
        handleResetPassword();
        break;
    case 'verify_otp':
        handleVerifyOTP();
        break;
    case 'update_profile':
        handleUpdateProfile();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function handleRegister() {
    $pdo = getDBConnection();
    
    $fullName = sanitize($_POST['full_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $roleId = (int)($_POST['role_id'] ?? 1);
    
    // Validation
    if (empty($fullName) || empty($email) || empty($password)) {
        jsonResponse(['success' => false, 'message' => 'All fields are required'], 400);
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'message' => 'Invalid email address'], 400);
    }
    
    if (strlen($password) < 8) {
        jsonResponse(['success' => false, 'message' => 'Password must be at least 8 characters'], 400);
    }
    
    if ($password !== $confirmPassword) {
        jsonResponse(['success' => false, 'message' => 'Passwords do not match'], 400);
    }
    
    // Check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Email is already registered'], 400);
    }
    
    // Restrict role registration (only applicants can self-register)
    if ($roleId !== 1) {
        // Check if an admin is creating the user
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
            $roleId = 1; // Force applicant role
        }
    }
    
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, password_hash, role_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$fullName, $email, $phone, $passwordHash, $roleId]);
    
    $userId = $pdo->lastInsertId();
    
    logAudit($userId, 'user_registered', 'user', $userId);
    
    // Send welcome notification & email
    sendNotification($userId, 'Welcome to CrediLens AI!', 'Your account has been created successfully. You can now apply for loans.', 'success', 'system');
    require_once __DIR__ . '/../includes/mailer.php';
    @emailWelcome($email, $fullName);
    
    jsonResponse(['success' => true, 'message' => 'Registration successful! Please login.']);
}

function handleLogin() {
    $pdo = getDBConnection();
    
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        jsonResponse(['success' => false, 'message' => 'Email and password are required'], 400);
    }
    
    $stmt = $pdo->prepare("
        SELECT u.*, r.role_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.email = ? AND u.is_active = 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        jsonResponse(['success' => false, 'message' => 'Invalid email or password'], 401);
    }
    
    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role_id'] = $user['role_id'];
    $_SESSION['user_role'] = $user['role_name'];
    $_SESSION['user_avatar'] = $user['avatar'];
    
    // Update last login
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    logAudit($user['id'], 'user_login', 'user', $user['id']);
    
    // Trigger login security email notification
    require_once __DIR__ . '/../includes/mailer.php';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    @emailUserLogin($user['email'], $user['full_name'], $ip, $ua);
    
    jsonResponse([
        'success' => true, 
        'message' => 'Login successful!',
        'user' => [
            'id' => $user['id'],
            'name' => $user['full_name'],
            'email' => $user['email'],
            'role' => $user['role_name']
        ]
    ]);
}

function handleLogout() {
    if (isset($_SESSION['user_id'])) {
        logAudit($_SESSION['user_id'], 'user_logout', 'user', $_SESSION['user_id']);
    }
    session_destroy();
    jsonResponse(['success' => true, 'message' => 'Logged out successfully']);
}

function handleForgotPassword() {
    $pdo = getDBConnection();
    $email = sanitize($_POST['email'] ?? '');
    
    if (empty($email)) {
        jsonResponse(['success' => false, 'message' => 'Email is required'], 400);
    }
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Don't reveal if email exists
        jsonResponse(['success' => true, 'message' => 'If the email exists, an OTP has been sent']);
    }
    
    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    $stmt = $pdo->prepare("UPDATE users SET otp_code = ?, otp_expiry = ? WHERE id = ?");
    $stmt->execute([$otp, $expiry, $user['id']]);
    
    // In production, send via email. For demo, show in response
    jsonResponse([
        'success' => true, 
        'message' => 'OTP has been generated. For demo purposes, your OTP is: ' . $otp,
        'otp_demo' => $otp
    ]);
}

function handleVerifyOTP() {
    $pdo = getDBConnection();
    $email = sanitize($_POST['email'] ?? '');
    $otp = sanitize($_POST['otp'] ?? '');
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND otp_code = ? AND otp_expiry > NOW()");
    $stmt->execute([$email, $otp]);
    
    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Invalid or expired OTP'], 400);
    }
    
    jsonResponse(['success' => true, 'message' => 'OTP verified successfully']);
}

function handleResetPassword() {
    $pdo = getDBConnection();
    $email = sanitize($_POST['email'] ?? '');
    $otp = sanitize($_POST['otp'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    
    if (strlen($newPassword) < 8) {
        jsonResponse(['success' => false, 'message' => 'Password must be at least 8 characters'], 400);
    }
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND otp_code = ? AND otp_expiry > NOW()");
    $stmt->execute([$email, $otp]);
    $user = $stmt->fetch();
    
    if (!$user) {
        jsonResponse(['success' => false, 'message' => 'Invalid or expired OTP'], 400);
    }
    
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, otp_code = NULL, otp_expiry = NULL WHERE id = ?");
    $stmt->execute([$passwordHash, $user['id']]);
    
    logAudit($user['id'], 'password_reset', 'user', $user['id']);
    
    jsonResponse(['success' => true, 'message' => 'Password reset successful! Please login.']);
}

function handleUpdateProfile() {
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(['success' => false, 'message' => 'Not authenticated'], 401);
    }
    
    $pdo = getDBConnection();
    $userId = $_SESSION['user_id'];
    
    $fullName = sanitize($_POST['full_name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    
    if (empty($fullName)) {
        jsonResponse(['success' => false, 'message' => 'Full name is required'], 400);
    }
    
    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
    $stmt->execute([$fullName, $phone, $userId]);
    
    $_SESSION['user_name'] = $fullName;
    
    logAudit($userId, 'profile_updated', 'user', $userId);
    
    jsonResponse(['success' => true, 'message' => 'Profile updated successfully']);
}
