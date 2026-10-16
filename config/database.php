<?php
/**
 * CrediLens AI — Database Configuration
 * Manages MySQL connection using PDO
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'credilens_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// App Configuration
define('APP_NAME', 'CrediLens AI');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/Lending%20and%20Credit%20platform');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx']);

// Gemini API Configuration
define('GEMINI_API_KEY', ''); // Set your Gemini API key here
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent');

// Session Configuration
define('SESSION_LIFETIME', 3600); // 1 hour

/**
 * Get PDO Database Connection
 */
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE  => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES    => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    
    return $pdo;
}

/**
 * Generate unique Application ID
 */
function generateApplicationId() {
    $pdo = getDBConnection();
    $year = date('y');
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM loan_applications WHERE YEAR(created_at) = YEAR(CURDATE())");
    $count = $stmt->fetch()['count'] + 1;
    return 'LN-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
}

/**
 * Generate unique Agreement Number
 */
function generateAgreementNumber() {
    return 'AGR-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
}

/**
 * Log audit trail
 */
function logAudit($userId, $action, $entityType = null, $entityId = null, $oldValue = null, $newValue = null) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_value, new_value, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $action,
            $entityType,
            $entityId,
            $oldValue ? json_encode($oldValue) : null,
            $newValue ? json_encode($newValue) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Audit log failed: " . $e->getMessage());
    }
}

/**
 * Add timeline event
 */
function addTimelineEvent($applicationId, $eventType, $title, $description = null, $performedBy = null, $data = null) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO application_timeline (application_id, event_type, event_title, event_description, event_data, performed_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $applicationId,
            $eventType,
            $title,
            $description,
            $data ? json_encode($data) : null,
            $performedBy
        ]);
    } catch (Exception $e) {
        error_log("Timeline event failed: " . $e->getMessage());
    }
}

/**
 * Send notification
 */
function sendNotification($userId, $title, $message, $type = 'info', $category = 'system', $applicationId = null, $actionUrl = null) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, application_id, type, category, title, message, action_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $applicationId, $type, $category, $title, $message, $actionUrl]);
    } catch (Exception $e) {
        error_log("Notification failed: " . $e->getMessage());
    }
}

/**
 * Calculate EMI
 */
function calculateEMI($principal, $annualRate, $tenureMonths) {
    $monthlyRate = ($annualRate / 100) / 12;
    if ($monthlyRate == 0) {
        return $principal / $tenureMonths;
    }
    $emi = ($principal * $monthlyRate * pow(1 + $monthlyRate, $tenureMonths)) / (pow(1 + $monthlyRate, $tenureMonths) - 1);
    return round($emi, 2);
}

/**
 * Sanitize input
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * JSON response helper
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
