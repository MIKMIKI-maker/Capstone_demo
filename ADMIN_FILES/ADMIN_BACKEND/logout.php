<?php
session_start();
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$admin_id = isset($_POST['admin_id']) ? intval($_POST['admin_id']) : 0;

if ($admin_id > 0) {
    $conn = getDatabaseConnection();
    if ($conn) {
        // Leave last_seen untouched — admin_stats uses last_seen for the 2-min online window,
        // so the user stays visible as "active" for up to 2 minutes after logout, then auto-expires.
        $stmt = $conn->prepare("UPDATE admin_accounts SET status = 'inactive' WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $admin_id);
            $stmt->execute();
            $stmt->close();
        }
        $conn->close();
    }
}

session_unset();
session_destroy();

echo json_encode(['status' => 'success']);
