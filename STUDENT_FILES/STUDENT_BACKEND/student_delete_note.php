<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
require_once __DIR__ . '/student_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success' => false]); exit; }

$student_admin_id = requireStudentSession();
$note_id   = isset($_POST['note_id'])   ? intval($_POST['note_id']) : 0;
$note_type = isset($_POST['note_type']) ? $_POST['note_type']       : '';

if (!$note_id || !in_array($note_type, ['general', 'activity'], true)) {
    echo json_encode(['success' => false]); exit;
}

$conn = getTeacherDatabaseConnection();
if (!$conn) { echo json_encode(['success' => false]); exit; }
$conn->set_charset('utf8mb4');

$rec = resolveStudentRecord($conn, $student_admin_id);
if (!$rec) { echo json_encode(['success' => false]); $conn->close(); exit; }
$student_id = (int)$rec['student_record_id'];
$teacher_id = (int)$rec['teacher_id'];

if ($note_type === 'general') {
    // A standalone row in student_notes — safe to delete outright.
    $stmt = $conn->prepare("DELETE FROM student_notes WHERE id = ? AND teacher_id = ? AND student_id = ?");
    if ($stmt) {
        $stmt->bind_param("iii", $note_id, $teacher_id, $student_id);
        $stmt->execute();
        $stmt->close();
    }
} else {
    // Activity-linked notes are a column on the submission itself, not a
    // separate row — "deleting" one just clears that column, leaving the
    // rest of the (already finalized) submission untouched.
    $stmt = $conn->prepare("UPDATE activity_submissions SET teacher_note = NULL WHERE id = ? AND teacher_id = ? AND student_id = ?");
    if ($stmt) {
        $stmt->bind_param("iii", $note_id, $teacher_id, $student_id);
        $stmt->execute();
        $stmt->close();
    }
}

$conn->close();
echo json_encode(['success' => true]);
?>
