<?php
/**
 * CrediLens AI — Email Helper
 * Sends styled HTML emails for key loan lifecycle events.
 * Falls back to file logging when SMTP isn't configured (typical XAMPP).
 */

require_once __DIR__ . '/smtp.php';

// Email configuration
define('MAIL_LOG_DIR', __DIR__ . '/../logs/emails');

function getMailConfig() {
    $configFile = __DIR__ . '/../config/mail.php';
    if (file_exists($configFile)) {
        return require $configFile;
    }
    return [
        'driver'     => 'smtp',
        'host'       => 'smtp.gmail.com',
        'port'       => 587,
        'encryption' => 'tls',
        'username'   => '',
        'password'   => '',
        'from_email' => 'noreply@credilens.ai',
        'from_name'  => 'CrediLens AI',
        'timeout'    => 10,
    ];
}

/**
 * Send an HTML email.
 * Uses SimpleSMTP when credentials are provided, or falls back to native mail/file logging.
 */
function sendEmail($to, $subject, $bodyHtml, $plainText = '') {
    if (!is_dir(MAIL_LOG_DIR)) {
        @mkdir(MAIL_LOG_DIR, 0755, true);
    }

    $config = getMailConfig();
    $fullHtml = wrapEmailTemplate($subject, $bodyHtml);
    $sent = false;
    $driverUsed = 'log';
    $errorMsg = '';

    // Check if SMTP is configured with credentials
    if (($config['driver'] ?? 'smtp') === 'smtp' && !empty($config['username']) && !empty($config['password'])) {
        $driverUsed = 'smtp';
        $smtp = new SimpleSMTP($config);
        $fromEmail = !empty($config['from_email']) ? $config['from_email'] : $config['username'];
        $fromName  = $config['from_name'] ?? 'CrediLens AI';
        
        $sent = $smtp->send($to, $subject, $fullHtml, $fromEmail, $fromName, $plainText);
        if (!$sent) {
            $errorMsg = $smtp->getLastError();
        }
    } else {
        // Fallback to PHP native mail()
        $driverUsed = 'mail';
        $fromEmail = $config['from_email'] ?? 'noreply@credilens.ai';
        $fromName  = $config['from_name'] ?? 'CrediLens AI';
        $headers   = "From: " . $fromName . " <" . $fromEmail . ">\r\n";
        $headers  .= "Reply-To: " . $fromEmail . "\r\n";
        $headers  .= "MIME-Version: 1.0\r\n";
        $headers  .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers  .= "X-Mailer: CrediLens-AI/1.0\r\n";

        $sent = @mail($to, $subject, $fullHtml, $headers);
        if (!$sent) {
            $errorMsg = 'Native mail() unconfigured (logged to local audit trail)';
        }
    }

    // Always log for audit and UI preview
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'to'        => $to,
        'subject'   => $subject,
        'sent'      => $sent,
        'driver'    => $driverUsed,
        'error'     => $errorMsg,
        'body_html' => $fullHtml,
    ];
    $logFile = MAIL_LOG_DIR . '/email_' . date('Y-m-d') . '.log';
    @file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);

    return [
        'sent'   => $sent,
        'driver' => $driverUsed,
        'error'  => $errorMsg,
    ];
}

/**
 * Wrap body content in a premium styled email template
 */
function wrapEmailTemplate($subject, $bodyContent) {
    return '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;font-family:\'Inter\',\'Segoe UI\',Arial,sans-serif;background:#f1f5f9;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
    <!-- Header -->
    <tr><td style="background:linear-gradient(135deg,#6366f1,#8b5cf6);padding:28px 32px;text-align:center;">
        <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:800;letter-spacing:0.5px;">CrediLens AI</h1>
        <p style="margin:4px 0 0;color:rgba(255,255,255,0.8);font-size:12px;text-transform:uppercase;letter-spacing:2px;">Lending Platform</p>
    </td></tr>
    <!-- Body -->
    <tr><td style="padding:32px;">
        <h2 style="margin:0 0 20px;font-size:18px;font-weight:700;color:#0f172a;">' . htmlspecialchars($subject) . '</h2>
        ' . $bodyContent . '
    </td></tr>
    <!-- Footer -->
    <tr><td style="padding:20px 32px;background:#f8fafc;border-top:1px solid #e2e8f0;text-align:center;">
        <p style="margin:0;font-size:12px;color:#94a3b8;">This is an automated email from CrediLens AI. Do not reply to this email.</p>
        <p style="margin:8px 0 0;font-size:11px;color:#cbd5e1;">&copy; ' . date('Y') . ' CrediLens AI — Lending & Credit Platform</p>
    </td></tr>
</table>
</td></tr>
</table>
</body></html>';
}

// =========================================================
// Convenience methods for specific email events
// =========================================================

/**
 * Application submitted
 */
function emailApplicationSubmitted($userEmail, $userName, $applicationId, $loanType, $amount) {
    $body = '
        <p style="color:#475569;font-size:14px;line-height:1.7;">Dear <strong>' . htmlspecialchars($userName) . '</strong>,</p>
        <p style="color:#475569;font-size:14px;line-height:1.7;">Your loan application has been successfully submitted and is now under review.</p>
        <table width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
            <tr><td style="padding:12px 16px;background:#f8fafc;font-size:12px;color:#64748b;font-weight:600;text-transform:uppercase;border-bottom:1px solid #e2e8f0;">Application Details</td></tr>
            <tr><td style="padding:16px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr><td style="padding:6px 0;color:#94a3b8;font-size:13px;">Application ID</td><td style="padding:6px 0;color:#0f172a;font-weight:600;font-size:13px;text-align:right;">' . htmlspecialchars($applicationId) . '</td></tr>
                    <tr><td style="padding:6px 0;color:#94a3b8;font-size:13px;">Loan Type</td><td style="padding:6px 0;color:#0f172a;font-weight:600;font-size:13px;text-align:right;">' . htmlspecialchars($loanType) . '</td></tr>
                    <tr><td style="padding:6px 0;color:#94a3b8;font-size:13px;">Requested Amount</td><td style="padding:6px 0;color:#0f172a;font-weight:600;font-size:13px;text-align:right;">₹' . number_format($amount, 0) . '</td></tr>
                    <tr><td style="padding:6px 0;color:#94a3b8;font-size:13px;">Status</td><td style="padding:6px 0;font-size:13px;text-align:right;"><span style="background:#dbeafe;color:#2563eb;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;">Submitted</span></td></tr>
                </table>
            </td></tr>
        </table>
        <p style="color:#475569;font-size:14px;line-height:1.7;">You will receive updates as your application progresses through review.</p>';
    return sendEmail($userEmail, 'Application Submitted — ' . $applicationId, $body);
}

/**
 * Application status changed
 */
function emailStatusChange($userEmail, $userName, $applicationId, $oldStatus, $newStatus) {
    $statusColors = [
        'approved'      => ['#dcfce7','#16a34a'],
        'rejected'      => ['#fee2e2','#dc2626'],
        'under_review'  => ['#dbeafe','#2563eb'],
        'risk_assessment' => ['#fef3c7','#d97706'],
        'disbursed'     => ['#d1fae5','#059669'],
        'clarification_required' => ['#fff7ed','#ea580c'],
    ];
    $sc = $statusColors[$newStatus] ?? ['#f1f5f9','#475569'];
    $statusLabel = ucwords(str_replace('_', ' ', $newStatus));

    $body = '
        <p style="color:#475569;font-size:14px;line-height:1.7;">Dear <strong>' . htmlspecialchars($userName) . '</strong>,</p>
        <p style="color:#475569;font-size:14px;line-height:1.7;">The status of your loan application <strong>' . htmlspecialchars($applicationId) . '</strong> has been updated.</p>
        <div style="margin:20px 0;padding:20px;background:' . $sc[0] . ';border-radius:12px;text-align:center;">
            <p style="margin:0 0 4px;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:1px;">Current Status</p>
            <p style="margin:0;font-size:22px;font-weight:800;color:' . $sc[1] . ';">' . $statusLabel . '</p>
        </div>
        <p style="color:#475569;font-size:14px;line-height:1.7;">Log in to your CrediLens AI dashboard for more details.</p>';
    return sendEmail($userEmail, 'Status Update — ' . $applicationId, $body);
}

/**
 * Application approved
 */
function emailApplicationApproved($userEmail, $userName, $applicationId, $amount, $interestRate, $tenure) {
    $body = '
        <p style="color:#475569;font-size:14px;line-height:1.7;">Dear <strong>' . htmlspecialchars($userName) . '</strong>,</p>
        <div style="margin:16px 0;padding:24px;background:#dcfce7;border-radius:12px;text-align:center;">
            <p style="margin:0;font-size:28px;">&#10003;</p>
            <p style="margin:4px 0 0;font-size:18px;font-weight:800;color:#16a34a;">Congratulations! Your Loan is Approved</p>
        </div>
        <table width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
            <tr><td style="padding:12px 16px;background:#f8fafc;font-size:12px;color:#64748b;font-weight:600;text-transform:uppercase;border-bottom:1px solid #e2e8f0;">Approved Terms</td></tr>
            <tr><td style="padding:16px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr><td style="padding:8px 0;color:#94a3b8;font-size:13px;">Application ID</td><td style="padding:8px 0;color:#0f172a;font-weight:600;font-size:13px;text-align:right;">' . htmlspecialchars($applicationId) . '</td></tr>
                    <tr><td style="padding:8px 0;color:#94a3b8;font-size:13px;">Approved Amount</td><td style="padding:8px 0;color:#16a34a;font-weight:800;font-size:16px;text-align:right;">₹' . number_format($amount, 0) . '</td></tr>
                    <tr><td style="padding:8px 0;color:#94a3b8;font-size:13px;">Interest Rate</td><td style="padding:8px 0;color:#0f172a;font-weight:600;font-size:13px;text-align:right;">' . $interestRate . '% p.a.</td></tr>
                    <tr><td style="padding:8px 0;color:#94a3b8;font-size:13px;">Tenure</td><td style="padding:8px 0;color:#0f172a;font-weight:600;font-size:13px;text-align:right;">' . $tenure . ' months</td></tr>
                </table>
            </td></tr>
        </table>
        <p style="color:#475569;font-size:14px;line-height:1.7;">Please log in to review and accept your loan offer.</p>';
    return sendEmail($userEmail, 'Loan Approved! — ' . $applicationId, $body);
}

/**
 * Application rejected
 */
function emailApplicationRejected($userEmail, $userName, $applicationId, $reason) {
    $body = '
        <p style="color:#475569;font-size:14px;line-height:1.7;">Dear <strong>' . htmlspecialchars($userName) . '</strong>,</p>
        <p style="color:#475569;font-size:14px;line-height:1.7;">We regret to inform you that your loan application <strong>' . htmlspecialchars($applicationId) . '</strong> has not been approved at this time.</p>
        <div style="margin:20px 0;padding:20px;background:#fee2e2;border-radius:12px;">
            <p style="margin:0 0 8px;font-size:12px;color:#64748b;text-transform:uppercase;font-weight:600;">Reason</p>
            <p style="margin:0;font-size:14px;color:#991b1b;line-height:1.6;">' . htmlspecialchars($reason) . '</p>
        </div>
        <p style="color:#475569;font-size:14px;line-height:1.7;">You may re-apply after 90 days or contact support for more information.</p>';
    return sendEmail($userEmail, 'Application Decision — ' . $applicationId, $body);
}

/**
 * EMI reminder
 */
function emailEmiReminder($userEmail, $userName, $applicationId, $emiAmount, $dueDate) {
    $body = '
        <p style="color:#475569;font-size:14px;line-height:1.7;">Dear <strong>' . htmlspecialchars($userName) . '</strong>,</p>
        <p style="color:#475569;font-size:14px;line-height:1.7;">This is a reminder that your EMI payment is due soon.</p>
        <div style="margin:20px 0;padding:20px;background:#fef3c7;border-radius:12px;text-align:center;">
            <p style="margin:0 0 4px;font-size:12px;color:#92400e;text-transform:uppercase;font-weight:600;">EMI Due</p>
            <p style="margin:0;font-size:28px;font-weight:900;color:#d97706;">₹' . number_format($emiAmount, 0) . '</p>
            <p style="margin:8px 0 0;font-size:14px;color:#92400e;">Due Date: <strong>' . htmlspecialchars($dueDate) . '</strong></p>
        </div>
        <p style="color:#475569;font-size:14px;line-height:1.7;">Please ensure timely payment to avoid late fees.</p>';
    return sendEmail($userEmail, 'EMI Reminder — ' . $applicationId, $body);
}

/**
 * Overdue payment
 */
function emailOverdueNotice($userEmail, $userName, $applicationId, $overdueAmount, $lateFee) {
    $body = '
        <p style="color:#475569;font-size:14px;line-height:1.7;">Dear <strong>' . htmlspecialchars($userName) . '</strong>,</p>
        <p style="color:#475569;font-size:14px;line-height:1.7;">Your EMI payment for application <strong>' . htmlspecialchars($applicationId) . '</strong> is now <span style="color:#dc2626;font-weight:700;">overdue</span>.</p>
        <div style="margin:20px 0;padding:20px;background:#fee2e2;border-radius:12px;text-align:center;">
            <p style="margin:0 0 4px;font-size:12px;color:#991b1b;text-transform:uppercase;font-weight:600;">Overdue Amount</p>
            <p style="margin:0;font-size:28px;font-weight:900;color:#dc2626;">₹' . number_format($overdueAmount, 0) . '</p>
            <p style="margin:8px 0 0;font-size:13px;color:#991b1b;">Late Fee: ₹' . number_format($lateFee, 0) . '</p>
        </div>
        <p style="color:#475569;font-size:14px;line-height:1.7;">Please make payment immediately to avoid additional penalties and impact on your credit history.</p>';
    return sendEmail($userEmail, 'OVERDUE — Immediate Payment Required', $body);
}

/**
 * Login alert email
 */
function emailUserLogin($userEmail, $userName, $ipAddress = '', $userAgent = '') {
    $time = date('Y-m-d H:i:s T');
    $device = 'Web Browser';
    if (strpos($userAgent, 'Mobile') !== false) {
        $device = 'Mobile Device';
    } elseif (strpos($userAgent, 'Windows') !== false) {
        $device = 'Windows PC';
    } elseif (strpos($userAgent, 'Macintosh') !== false) {
        $device = 'Mac';
    }

    $body = '
        <p style="color:#475569;font-size:14px;line-height:1.7;">Hello <strong>' . htmlspecialchars($userName) . '</strong>,</p>
        <p style="color:#475569;font-size:14px;line-height:1.7;">A new login was detected for your CrediLens AI account.</p>
        <table width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
            <tr><td style="padding:12px 16px;background:#f8fafc;font-size:12px;color:#64748b;font-weight:600;text-transform:uppercase;border-bottom:1px solid #e2e8f0;">Session Details</td></tr>
            <tr><td style="padding:16px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr><td style="padding:6px 0;color:#94a3b8;font-size:13px;">Date & Time</td><td style="padding:6px 0;color:#0f172a;font-weight:600;font-size:13px;text-align:right;">' . $time . '</td></tr>
                    <tr><td style="padding:6px 0;color:#94a3b8;font-size:13px;">Device / System</td><td style="padding:6px 0;color:#0f172a;font-weight:600;font-size:13px;text-align:right;">' . htmlspecialchars($device) . '</td></tr>
                    <tr><td style="padding:6px 0;color:#94a3b8;font-size:13px;">IP Address</td><td style="padding:6px 0;color:#0f172a;font-weight:600;font-size:13px;text-align:right;">' . htmlspecialchars($ipAddress ?: '127.0.0.1') . '</td></tr>
                    <tr><td style="padding:6px 0;color:#94a3b8;font-size:13px;">Status</td><td style="padding:6px 0;color:#16a34a;font-weight:700;font-size:13px;text-align:right;">✓ Successful</td></tr>
                </table>
            </td></tr>
        </table>
        <p style="color:#64748b;font-size:13px;line-height:1.6;">If this was you, you can safely ignore this email. If you did not log in, please reset your password immediately or contact security support.</p>';
    return sendEmail($userEmail, 'Security Alert: New Login Detected — CrediLens AI', $body);
}

/**
 * Welcome registration email
 */
function emailWelcome($userEmail, $userName) {
    $body = '
        <p style="color:#475569;font-size:14px;line-height:1.7;">Dear <strong>' . htmlspecialchars($userName) . '</strong>,</p>
        <div style="margin:16px 0;padding:24px;background:#eef2ff;border-radius:12px;text-align:center;">
            <p style="margin:0;font-size:28px;">🎉</p>
            <p style="margin:6px 0 0;font-size:18px;font-weight:800;color:#4f46e5;">Welcome to CrediLens AI!</p>
            <p style="margin:4px 0 0;font-size:13px;color:#6366f1;">Your Digital Lending & Credit Platform</p>
        </div>
        <p style="color:#475569;font-size:14px;line-height:1.7;">Your account has been successfully created. You can now:</p>
        <ul style="color:#475569;font-size:14px;line-height:1.8;padding-left:20px;">
            <li>Apply for Instant Personal, Business, and Home loans</li>
            <li>Upload documents for real-time automated AI verification</li>
            <li>Track application stages, credit assessment, and approval status live</li>
        </ul>
        <p style="color:#475569;font-size:14px;line-height:1.7;">We are glad to have you on board!</p>';
    return sendEmail($userEmail, 'Welcome to CrediLens AI — Account Created', $body);
}

/**
 * Send notification + email together
 * Creates an in-app notification AND sends an email
 */
function notifyAndEmail($pdo, $userId, $applicationId, $type, $category, $title, $message, $emailFunc = null) {
    // In-app notification
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, application_id, type, category, title, message) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $applicationId, $type, $category, $title, $message]);

    // Email if function provided
    if ($emailFunc && is_callable($emailFunc)) {
        $emailFunc();
    }
}
