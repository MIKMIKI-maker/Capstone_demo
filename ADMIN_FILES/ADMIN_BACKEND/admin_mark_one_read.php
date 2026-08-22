<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/db.php';
requireAdminSession();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success' => false]); exit; }

$notif_id = isset($_POST['notif_id']) ? intval($_POST['notif_id']) : 0;
if (!$notif_id) { echo json_encode(['success' => false]); exit; }

$conn = getDatabaseConnection();
if (!$conn) { echo json_encode(['success' => false, 'message' => 'Database connection failed']); exit; }

$stmt = $conn->prepare("UPDATE admin_notifications SET is_read = 1 WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $notif_id);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();
}
$conn->close();
echo json_encode(['success' => !empty($ok), 'message' => !empty($ok) ? 'Notification marked as read' : 'Notification not found or already read']);
?>
