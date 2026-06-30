<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

session_start();
$teacher_id = isset($_SESSION['admin_id']) ? intval($_SESSION['admin_id']) : 1;

$student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
$student_name = isset($_POST['student_name']) ? trim($_POST['student_name']) : '';
$parent_name = isset($_POST['parent_name']) ? trim($_POST['parent_name']) : '';
$parent_email = isset($_POST['parent_email']) ? trim($_POST['parent_email']) : '';
$parent_phone = isset($_POST['parent_phone']) ? trim($_POST['parent_phone']) : '';
$disability_type = isset($_POST['disability_type']) ? trim($_POST['disability_type']) : '';
$age = isset($_POST['age']) ? intval($_POST['age']) : 0;

if (!$student_id || !$student_name) {
    echo json_encode(['success' => false, 'message' => 'Student ID and name are required']);
    exit;
}

$stmt = $conn->prepare("UPDATE students SET student_name = ?, parent_name = ?, parent_email = ?, parent_phone = ?, disability_type = ?, age = ? 
                        WHERE id = ? AND teacher_id = ?");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database prepare failed']);
    $conn->close();
    exit;
}

$stmt->bind_param("sssssdi", $student_name, $parent_name, $parent_email, $parent_phone, $disability_type, $age, $student_id, $teacher_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Student updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update student']);
}

$stmt->close();
$conn->close();
?>
