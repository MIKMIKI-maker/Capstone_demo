<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../../MAILER/send_email.php'; // ensures email_log table exists even if no email has been sent yet
requireAdminSession();

header('Content-Type: application/json');

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Brevo's free tier caps at 300 sends per rolling 24h window (visible on
// Brevo's own "Usage and plan" page too, but this is the in-app view).
$todayRes = $conn->query("SELECT COUNT(*) AS cnt FROM email_log WHERE success = 1 AND sent_at >= (NOW() - INTERVAL 1 DAY)");
$sentLast24h = $todayRes ? (int)$todayRes->fetch_assoc()['cnt'] : 0;

// At the limit, sending resumes once enough of the oldest sends in the window
// are more than 24 hours old to bring the count back under it.
$resumesAt = null;
if ($sentLast24h >= EMAIL_DAILY_LIMIT) {
    $offset = $sentLast24h - EMAIL_DAILY_LIMIT;
    $resRes = $conn->query("SELECT sent_at + INTERVAL 1 DAY AS resumes FROM email_log
        WHERE success = 1 AND sent_at >= (NOW() - INTERVAL 1 DAY) ORDER BY sent_at ASC LIMIT 1 OFFSET $offset");
    $resumesAt = $resRes && ($r = $resRes->fetch_assoc()) ? $r['resumes'] : null;
}

$failedRes = $conn->query("SELECT COUNT(*) AS cnt FROM email_log WHERE success = 0 AND sent_at >= (NOW() - INTERVAL 1 DAY)");
$failedLast24h = $failedRes ? (int)$failedRes->fetch_assoc()['cnt'] : 0;

$rows = [];
$listRes = $conn->query("SELECT recipient_email, subject, success, error_message, sent_at FROM email_log ORDER BY sent_at DESC LIMIT 30");
if ($listRes) {
    while ($r = $listRes->fetch_assoc()) {
        $rows[] = [
            'recipient_email' => $r['recipient_email'],
            'subject'         => $r['subject'],
            'success'         => (bool)$r['success'],
            'error_message'   => $r['error_message'],
            'sent_at'         => $r['sent_at'],
        ];
    }
}

echo json_encode([
    'success' => true,
    'sent_last_24h' => $sentLast24h,
    'failed_last_24h' => $failedLast24h,
    'daily_limit' => EMAIL_DAILY_LIMIT,
    'resumes_at' => $resumesAt,
    'rows' => $rows,
]);

$conn->close();
