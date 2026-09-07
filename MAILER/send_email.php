<?php
require_once __DIR__ . '/mailer_config.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Records every send attempt (success or failure) to email_log, since Gmail
 * gives no usage dashboard of its own for a regular SMTP account — this is
 * the only way to see how many of the 500/day quota have been used, or why
 * a particular send failed. Never lets a logging failure break the actual
 * email send — swallows its own errors.
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

/**
 * Sends an HTML email via the configured SMTP account.
 * Returns true on success, false on failure — never throws, so a broken
 * mail server can't take down the caller's own request (the notification/
 * enrollment still succeeds in the app even if the email fails to send).
 */
function send_email(string $toEmail, string $toName, string $subject, string $htmlBody): bool {
    if (!SMTP_USER || !SMTP_PASSWORD) {
        error_log('send_email: SMTP_USER/SMTP_PASSWORD not configured — skipping email to ' . $toEmail);
        _logEmailAttempt($toEmail, $subject, false, 'SMTP not configured');
        return false;
    }
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        error_log('send_email: invalid recipient address: ' . $toEmail);
        _logEmailAttempt($toEmail, $subject, false, 'Invalid recipient address');
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        // Without this, a blocked/unreachable SMTP port (common on hosting
        // free tiers) leaves this call hanging on PHPMailer's default socket
        // timeout for a couple minutes - and every caller (e.g.
        // admin_add_account.php) sends the email inline before responding,
        // so the whole "Add Account" request would hang right along with it.
        // Capping it here keeps every caller fast without touching each one.
        $mail->Timeout    = 10;

        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        // Gmail (and most mail clients) strip data: URI images out of HTML
        // email bodies as a security measure — a base64-embedded <img> just
        // renders broken. An inline CID attachment is the actual supported
        // way to embed an image that doesn't depend on the recipient
        // fetching it from a live server. Every current template references
        // it as <img src="cid:sped_logo">.
        $logoPath = __DIR__ . '/logo_email.png';
        if (is_file($logoPath)) {
            $mail->addEmbeddedImage($logoPath, 'sped_logo', 'logo.png');
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody  = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)));

        $mail->send();
        _logEmailAttempt($toEmail, $subject, true, null);
        return true;
    } catch (Exception $e) {
        error_log('send_email failed to ' . $toEmail . ': ' . $mail->ErrorInfo);
        _logEmailAttempt($toEmail, $subject, false, $mail->ErrorInfo);
        return false;
    }
}
