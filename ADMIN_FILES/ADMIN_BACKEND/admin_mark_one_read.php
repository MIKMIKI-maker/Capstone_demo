<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success' => false]); exit; }

$notif_id = isset($_POST['notif_id']) ? intval($_POST['notif_id']) : 0;
if (!$notif_id) { echo json_encode(['success' => false]); exit; }

$conn = new mysqli('127.0.0.1', 'root', '', 'spedalm_db', 3306);
if ($conn->connect_error) { echo json_encode(['success' => false]); exit; }
$conn->set_charset('utf8mb4');

$stmt = $conn->prepare("UPDATE admin_notifications SET is_read = 1 WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $notif_id);
    $stmt->execute();
    $stmt->close();
}
$conn->close();
echo json_encode(['success' => true]);
?>
