<?php
require_once __DIR__ . '/mailer_config.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Sends an HTML email via the configured SMTP account.
 * Returns true on success, false on failure — never throws, so a broken
 * mail server can't take down the caller's own request (the notification/
 * enrollment still succeeds in the app even if the email fails to send).
 */
function send_email(string $toEmail, string $toName, string $subject, string $htmlBody): bool {
    if (!SMTP_USER || !SMTP_PASSWORD) {
        error_log('send_email: SMTP_USER/SMTP_PASSWORD not configured — skipping email to ' . $toEmail);
        return false;
    }
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        error_log('send_email: invalid recipient address: ' . $toEmail);
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

        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody  = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)));

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('send_email failed to ' . $toEmail . ': ' . $mail->ErrorInfo);
        return false;
    }
}
