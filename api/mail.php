<?php
/**
 * CrediLens AI — Mail Trigger & Audit API
 * Allows triggering test emails, loan lifecycle emails, configuring SMTP, and viewing sent email logs.
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
    case 'trigger_test':
        triggerTestEmail();
        break;
    case 'trigger_for_application':
        triggerForApplication();
        break;
    case 'get_logs':
        getMailLogs();
        break;
    case 'get_smtp_config':
        getSmtpConfig();
        break;
    case 'save_smtp_config':
        saveSmtpConfig();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function triggerTestEmail() {
    $pdo = getDBConnection();
    $userId = $_SESSION['user_id'];
    $currentUser = getCurrentUser();
    
    $to = trim($_POST['to'] ?? $currentUser['email']);
    $type = $_POST['type'] ?? 'approved';
    $name = trim($_POST['name'] ?? $currentUser['full_name']);
    $appId = trim($_POST['application_id'] ?? 'APP-' . date('Ymd') . '-001');

    if (empty($to)) {
        jsonResponse(['success' => false, 'message' => 'Recipient email is required']);
    }

    $result = null;
    $subject = '';

    switch ($type) {
        case 'submitted':
            $result = emailApplicationSubmitted($to, $name, $appId, 'Personal Loan (Express)', 250000);
            $subject = 'Application Submitted — ' . $appId;
            break;
        case 'approved':
            $result = emailApplicationApproved($to, $name, $appId, 300000, 10.5, 24);
            $subject = 'Loan Approved! — ' . $appId;
            break;
        case 'rejected':
            $result = emailApplicationRejected($to, $name, $appId, 'Debt-to-income ratio exceeds policy threshold (45%). Credit score below minimum requirement.');
            $subject = 'Application Decision — ' . $appId;
            break;
        case 'emi_reminder':
            $result = emailEmiReminder($to, $name, $appId, 13915, date('Y-m-d', strtotime('+3 days')));
            $subject = 'EMI Reminder — ' . $appId;
            break;
        case 'overdue':
            $result = emailOverdueNotice($to, $name, $appId, 13915, 750);
            $subject = 'OVERDUE — Immediate Payment Required';
            break;
        case 'status_change':
        default:
            $result = emailStatusChange($to, $name, $appId, 'under_review', 'risk_assessment');
            $subject = 'Status Update — ' . $appId;
            break;
    }

    $sent = is_array($result) ? ($result['sent'] ?? false) : (bool)$result;
    $driver = is_array($result) ? ($result['driver'] ?? 'log') : 'mail';
    $error = is_array($result) ? ($result['error'] ?? '') : '';

    $statusText = $sent ? ($driver === 'smtp' ? 'Delivered via live SMTP' : 'Delivered via mail()') : 'Logged locally';
    sendNotification($userId, 'Email Triggered: ' . $subject, 'Sent to ' . $to . ' (' . $statusText . ')', $sent ? 'success' : 'info', 'system');

    $msg = $sent 
        ? "Email sent successfully to $to via " . strtoupper($driver) . "!" 
        : "Email generated & logged to audit trail (SMTP: " . ($error ?: 'not configured') . ")";

    jsonResponse([
        'success' => true,
        'message' => $msg,
        'details' => [
            'to' => $to,
            'type' => $type,
            'subject' => $subject,
            'sent' => $sent,
            'driver' => $driver,
            'error' => $error,
            'logged_to_disk' => true,
            'log_path' => 'logs/emails/email_' . date('Y-m-d') . '.log'
        ]
    ]);
}

function triggerForApplication() {
    $pdo = getDBConnection();
    $appId = (int)($_POST['application_id'] ?? 0);
    $type = $_POST['type'] ?? 'status_change';

    $stmt = $pdo->prepare("SELECT la.*, u.email, u.full_name, lt.name as loan_type_name 
                           FROM loan_applications la 
                           JOIN users u ON la.user_id = u.id 
                           LEFT JOIN loan_types lt ON la.loan_type_id = lt.id 
                           WHERE la.id = ?");
    $stmt->execute([$appId]);
    $app = $stmt->fetch();

    if (!$app) {
        jsonResponse(['success' => false, 'message' => 'Application not found'], 404);
    }

    $to = $app['email'];
    $name = $app['full_name'];
    $code = $app['application_id'];

    switch ($type) {
        case 'submitted':
            $result = emailApplicationSubmitted($to, $name, $code, $app['loan_type_name'] ?? 'Personal Loan', $app['requested_amount']);
            break;
        case 'approved':
            $result = emailApplicationApproved($to, $name, $code, $app['approved_amount'] ?: $app['requested_amount'], $app['interest_rate'] ?: 10.5, $app['tenure_months'] ?: 12);
            break;
        case 'rejected':
            $result = emailApplicationRejected($to, $name, $code, $_POST['reason'] ?? 'Credit score threshold not met.');
            break;
        case 'status_change':
        default:
            $result = emailStatusChange($to, $name, $code, 'submitted', $app['status']);
            break;
    }

    jsonResponse([
        'success' => true,
        'message' => 'Email triggered for ' . $code . ' to ' . $to
    ]);
}

function getMailLogs() {
    $logDir = __DIR__ . '/../logs/emails';
    $logs = [];

    if (is_dir($logDir)) {
        $files = glob($logDir . '/*.log');
        rsort($files);

        foreach (array_slice($files, 0, 5) as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines) {
                foreach (array_reverse($lines) as $line) {
                    $entry = json_decode($line, true);
                    if ($entry) {
                        $logs[] = $entry;
                        if (count($logs) >= 30) break 2;
                    }
                }
            }
        }
    }

    jsonResponse(['success' => true, 'logs' => $logs]);
}

function getSmtpConfig() {
    $config = getMailConfig();
    unset($config['password']); // don't expose raw password
    $hasPassword = !empty(getMailConfig()['password']);
    $config['has_password'] = $hasPassword;

    jsonResponse(['success' => true, 'config' => $config]);
}

function saveSmtpConfig() {
    if ($_SESSION['user_role'] !== 'admin') {
        jsonResponse(['success' => false, 'message' => 'Only administrators can update SMTP settings'], 403);
    }

    $current = getMailConfig();

    $host       = trim($_POST['host'] ?? 'smtp.gmail.com');
    $port       = (int)($_POST['port'] ?? 587);
    $encryption = trim($_POST['encryption'] ?? 'tls');
    $username   = trim($_POST['username'] ?? '');
    $password   = trim($_POST['password'] ?? '');
    $fromEmail  = trim($_POST['from_email'] ?? 'noreply@credilens.ai');
    $fromName   = trim($_POST['from_name'] ?? 'CrediLens AI');

    // Keep existing password if not updated
    if (empty($password)) {
        $password = $current['password'] ?? '';
    }

    $code = "<?php\n/**\n * CrediLens AI — Email & SMTP Configuration\n */\n\nreturn [\n"
        . "    'driver'     => 'smtp',\n"
        . "    'host'       => " . var_export($host, true) . ",\n"
        . "    'port'       => " . $port . ",\n"
        . "    'encryption' => " . var_export($encryption, true) . ",\n"
        . "    'username'   => " . var_export($username, true) . ",\n"
        . "    'password'   => " . var_export($password, true) . ",\n"
        . "    'from_email' => " . var_export($fromEmail, true) . ",\n"
        . "    'from_name'  => " . var_export($fromName, true) . ",\n"
        . "    'timeout'    => 10,\n"
        . "];\n";

    $configFile = __DIR__ . '/../config/mail.php';
    if (@file_put_contents($configFile, $code) === false) {
        jsonResponse(['success' => false, 'message' => 'Failed to save SMTP configuration file.']);
    }

    logAudit($_SESSION['user_id'], 'smtp_configured', 'system_setting', 0);

    jsonResponse(['success' => true, 'message' => 'SMTP configuration saved successfully!']);
}
