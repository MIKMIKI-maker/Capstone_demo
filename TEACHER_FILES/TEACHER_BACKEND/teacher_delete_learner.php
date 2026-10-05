<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/teacher_auth.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/school_year.php';
// A School Year that isn't Active is read-only.
requireActiveSchoolYearView();

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
// The logged-in teacher, from the session — never a teacher_id sent by the browser.
$teacher_id = requireTeacherId();

$data = json_decode(file_get_contents('php://input'), true);
$student_id = isset($data['id']) ? intval($data['id']) : 0;

if (!$student_id) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM students WHERE id = ? AND teacher_id = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database prepare failed']);
    $conn->close();
    exit;
}

$stmt->bind_param("ii", $student_id, $teacher_id);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Student deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete student']);
}

$stmt->close();
$conn->close();
?>
