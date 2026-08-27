<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
require_once __DIR__ . '/student_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]);
    exit;
}

$student_admin_id = requireStudentSession();

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false]);
    exit;
}
$conn->set_charset('utf8mb4');

$rec = resolveStudentRecord($conn, $student_admin_id);
if (!$rec) {
    echo json_encode(['success' => false]);
    $conn->close();
    exit;
}
$student_record_id = (int)$rec['student_record_id'];

$stmt = $conn->prepare("DELETE FROM student_notifications WHERE student_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $student_record_id);
    $stmt->execute();
    $stmt->close();
}
$conn->close();

echo json_encode(['success' => true]);
?>
