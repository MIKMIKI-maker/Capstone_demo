<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$conn = getTeacherDatabaseConnection();
if (!$conn) { echo json_encode(['success' => false, 'message' => 'DB connection failed']); exit; }

$action     = isset($_REQUEST['action'])     ? trim($_REQUEST['action'])     : 'save';
$teacher_id = isset($_REQUEST['teacher_id']) ? intval($_REQUEST['teacher_id']) : 0;
$student_id = isset($_REQUEST['student_id']) ? intval($_REQUEST['student_id']) : 0;

if ($action === 'save') {
    $note = isset($_POST['note']) ? trim($_POST['note']) : '';
    if (!$note) { echo json_encode(['success' => false, 'message' => 'Note is empty']); exit; }

    $stmt = $conn->prepare("INSERT INTO student_notes (teacher_id, student_id, note) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $teacher_id, $student_id, $note);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Note saved', 'id' => $conn->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save note']);
    }
    $stmt->close();

} elseif ($action === 'list') {
    $stmt = $conn->prepare("SELECT id, note, created_at FROM student_notes WHERE teacher_id=? AND student_id=? ORDER BY created_at DESC");
    $stmt->bind_param("ii", $teacher_id, $student_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    echo json_encode(['success' => true, 'notes' => $rows]);

} elseif ($action === 'delete') {
    $note_id = isset($_REQUEST['note_id']) ? intval($_REQUEST['note_id']) : 0;
    $stmt = $conn->prepare("DELETE FROM student_notes WHERE id=? AND teacher_id=?");
    $stmt->bind_param("ii", $note_id, $teacher_id);
    $stmt->execute();
    echo json_encode(['success' => true]);
    $stmt->close();
}

$conn->close();
?>
