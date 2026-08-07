<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success' => false]); exit; }

$notif_id   = isset($_POST['notif_id'])   ? intval($_POST['notif_id'])   : 0;
$student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;

if (!$notif_id || !$student_id) { echo json_encode(['success' => false]); exit; }

$conn = new mysqli('127.0.0.1', 'root', '', 'spedalm_db', 3307);
if ($conn->connect_error) { echo json_encode(['success' => false]); exit; }
$conn->set_charset('utf8mb4');

$stmt = $conn->prepare("DELETE FROM student_notifications WHERE id = ? AND student_id = ?");
if ($stmt) {
    $stmt->bind_param("ii", $notif_id, $student_id);
    $stmt->execute();
    $stmt->close();
}
$conn->close();
echo json_encode(['success' => true]);
?>
