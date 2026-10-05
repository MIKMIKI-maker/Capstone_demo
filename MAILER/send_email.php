<?php
require_once __DIR__ . '/mailer_config.php';

/**
 * Records every send attempt (success or failure) to email_log, since Brevo's
 * dashboard is a separate place from this app — this is the in-app record of
 * what was sent and why a particular send failed.  Never lets a logging
 * failure break the actual email send — swallows its own errors.
 */
function _logEmailAttempt(string $toEmail, string $subject, bool $success, ?string $errorMessage): void {
    try {
        require_once __DIR__ . '/../ADMIN_FILES/ADMIN_BACKEND/db.php';
        $conn = getDatabaseConnection();
        if (!$conn) return;
        $conn->query("CREATE TABLE IF NOT EXISTS email_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            recipient_email VARCHAR(255) NOT NULL,
            subject VARCHAR(500),
            success TINYINT(1) NOT NULL,
            error_message TEXT,
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_sent_at (sent_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $stmt = $conn->prepare("INSERT INTO email_log (recipient_email, subject, success, error_message) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $successInt = $success ? 1 : 0;
            $stmt->bind_param("ssis", $toEmail, $subject, $successInt, $errorMessage);
            $stmt->execute();
            $stmt->close();
        }
        $conn->close();
    } catch (\Throwable $e) {
        error_log('_logEmailAttempt failed: ' . $e->getMessage());
    }
}

/** Emails successfully sent in the last 24 hours (Brevo's limit window). */
function emailsSentLast24h(): int {
    try {
        require_once __DIR__ . '/../ADMIN_FILES/ADMIN_BACKEND/db.php';
        $conn = getDatabaseConnection();
        if (!$conn) return 0;
        $res = $conn->query("SELECT COUNT(*) AS cnt FROM email_log WHERE success = 1 AND sent_at >= (NOW() - INTERVAL 1 DAY)");
        $count = $res ? (int)$res->fetch_assoc()['cnt'] : 0;
        $conn->close();
        return $count;
    } catch (\Throwable $e) {
        return 0;
    }
}

/**
 * One Admin notification per 24 hours for a given title, so a busy day
 * doesn't flood the bell with the same "limit" warning on every send.
 */
function _notifyAdminOncePerDay(string $title, string $message): void {
    try {
        require_once __DIR__ . '/../ADMIN_FILES/ADMIN_BACKEND/db.php';
        require_once __DIR__ . '/../ADMIN_FILES/ADMIN_BACKEND/admin_push_notification.php';
        $conn = getDatabaseConnection();
        if (!$conn) return;
        $stmt = $conn->prepare("SELECT 1 FROM admin_notifications WHERE title = ? AND created_at >= (NOW() - INTERVAL 1 DAY) LIMIT 1");
        $already = false;
        if ($stmt) {
            $stmt->bind_param("s", $title);
            $stmt->execute();
            $already = (bool)$stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
        if (!$already) pushAdminNotification($conn, 'email', $title, $message);
        $conn->close();
    } catch (\Throwable $e) {
        error_log('_notifyAdminOncePerDay failed: ' . $e->getMessage());
    }
}

/**
 * Sends an HTML email via Brevo's transactional email HTTP API.
 * Returns true on success, false on failure — never throws, so a broken
 * mail provider can't take down the caller's own request (the notification/
 * enrollment still succeeds in the app even if the email fails to send).
 *
 * This replaced direct SMTP (PHPMailer) because Render's hosting blocks
 * outbound SMTP entirely — every send timed out there ("Could not connect
 * to SMTP host... Connection timed out"), regardless of how correct the
 * credentials were. Brevo's API is a plain HTTPS POST, unaffected by that
 * block, and worked identically in local Docker testing (which never had
 * the SMTP-blocking problem to begin with).
 */
function send_email(string $toEmail, string $toName, string $subject, string $htmlBody): bool {
    if (!BREVO_API_KEY || !SMTP_USER) {
        error_log('send_email: BREVO_API_KEY/SMTP_USER not configured — skipping email to ' . $toEmail);
        _logEmailAttempt($toEmail, $subject, false, 'Email provider not configured');
        return false;
    }
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        error_log('send_email: invalid recipient address: ' . $toEmail);
        _logEmailAttempt($toEmail, $subject, false, 'Invalid recipient address');
        return false;
    }

    // Stop at the daily limit ourselves rather than sending into a wall of
    // Brevo rejections; callers already handle false (e.g. Add Account shows
    // the temporary password to the Admin instead).
    $sentLast24h = emailsSentLast24h();
    if ($sentLast24h >= EMAIL_DAILY_LIMIT) {
        _logEmailAttempt($toEmail, $subject, false, 'Daily email limit reached (' . EMAIL_DAILY_LIMIT . ' per 24 hours) - not sent');
        _notifyAdminOncePerDay('Daily email limit reached',
            'SPED ALM has sent ' . EMAIL_DAILY_LIMIT . ' emails in the last 24 hours, so new emails are paused until older ones fall outside the 24-hour window. See Settings > Email Log.');
        return false;
    }

    $altBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)));

    $payload = [
        'sender'      => ['name' => SMTP_FROM_NAME, 'email' => SMTP_USER],
        'to'          => [['email' => $toEmail, 'name' => $toName]],
        'subject'     => $subject,
        'htmlContent' => $htmlBody,
        'textContent' => $altBody,
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'content-type: application/json',
            'api-key: ' . BREVO_API_KEY,
        ],
        CURLOPT_RETURNTRANSFER => true,
        // Bounds the worst case to a few seconds instead of PHP's default
        // socket timeout — every caller (e.g. admin_add_account.php) sends
        // inline before responding, so a slow/unreachable provider would
        // otherwise hang the whole request right along with it.
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $responseBody = curl_exec($ch);
    $curlError    = curl_error($ch);
    $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($responseBody === false) {
        error_log('send_email failed to ' . $toEmail . ': ' . $curlError);
        _logEmailAttempt($toEmail, $subject, false, $curlError ?: 'Network error contacting email provider');
        return false;
    }

    // Brevo returns 201 with a messageId on success; anything else is a
    // rejection (bad API key, unverified sender, invalid recipient, etc.)
    // with the reason in the response body.
    if ($httpCode >= 200 && $httpCode < 300) {
        _logEmailAttempt($toEmail, $subject, true, null);
        $warnAt = (int)ceil(EMAIL_DAILY_LIMIT * 0.8);
        if ($sentLast24h + 1 >= $warnAt) {
            _notifyAdminOncePerDay('Email limit almost reached',
                ($sentLast24h + 1) . ' of ' . EMAIL_DAILY_LIMIT . ' emails used in the last 24 hours. Sending pauses at ' . EMAIL_DAILY_LIMIT . '. See Settings > Email Log.');
        }
        return true;
    }

    $decoded = json_decode($responseBody, true);
    $errorMessage = is_array($decoded) && isset($decoded['message'])
        ? $decoded['message']
        : ('HTTP ' . $httpCode . ': ' . $responseBody);
    error_log('send_email failed to ' . $toEmail . ': ' . $errorMessage);
    _logEmailAttempt($toEmail, $subject, false, $errorMessage);
    return false;
}
