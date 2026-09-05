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

// Gmail's free-tier SMTP relay caps at 500 sends per rolling 24h window, with
// no usage dashboard of its own — this is the only place that count is visible.
$todayRes = $conn->query("SELECT COUNT(*) AS cnt FROM email_log WHERE success = 1 AND sent_at >= (NOW() - INTERVAL 1 DAY)");
$sentLast24h = $todayRes ? (int)$todayRes->fetch_assoc()['cnt'] : 0;

$failedRes = $conn->query("SELECT COUNT(*) AS cnt FROM email_log WHERE success = 0 AND sent_at >= (NOW() - INTERVAL 1 DAY)");
$failedLast24h = $failedRes ? (int)$failedRes->fetch_assoc()['cnt'] : 0;

$rows = [];
$listRes = $conn->query("SELECT recipient_email, subject, success, error_message, sent_at FROM email_log ORDER BY sent_at DESC LIMIT 100");
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
    'daily_limit' => 500,
    'rows' => $rows,
]);

$conn->close();
