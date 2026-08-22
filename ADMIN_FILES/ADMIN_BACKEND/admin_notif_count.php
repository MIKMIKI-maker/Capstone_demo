<?php
require_once __DIR__ . '/db.php';
requireAdminSession();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$conn = getDatabaseConnection();
if (!$conn) { echo json_encode(['unread' => 0]); exit; }

// Table may not exist yet on first load — handle gracefully
$res = $conn->query("SELECT COUNT(*) AS cnt FROM admin_notifications WHERE is_read = 0");
$count = ($res && $row = $res->fetch_assoc()) ? (int)$row['cnt'] : 0;
$conn->close();

echo json_encode(['unread' => $count]);
