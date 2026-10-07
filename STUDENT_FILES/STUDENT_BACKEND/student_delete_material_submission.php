<?php
// A student removes one of their own submitted files (e.g. sent the wrong
// photo) from an 'activity' material.
// POST: submission_id
require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/material_helpers.php';
require_once __DIR__ . '/student_auth.php';
header('Content-Type: application/json');

$student_admin_id = requireStudentSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}
$submission_id = isset($_POST['submission_id']) ? (int)$_POST['submission_id'] : 0;
if (!$submission_id) {
    echo json_encode(['success' => false, 'message' => 'Missing submission']);
    exit;
}

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$rec = resolveStudentRecord($conn, $student_admin_id);
if (!$rec) {
    echo json_encode(['success' => false, 'message' => 'Not enrolled']);
    $conn->close();
    exit;
}
$student_id = (int)$rec['student_record_id'];
$teacher_id = (int)$rec['teacher_id'];
ensureMaterialSchema($conn);

// Only the student's own file, on a material from their current teacher.
$stmt = $conn->prepare("SELECT s.file_name FROM material_submissions s
    INNER JOIN teacher_uploaded_materials m ON m.id = s.material_id
    WHERE s.id = ? AND s.student_id = ? AND m.student_id = ? AND m.teacher_id = ?");
$stmt->bind_param("iiii", $submission_id, $student_id, $student_id, $teacher_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) {
    echo json_encode(['success' => false, 'message' => 'File not found']);
    $conn->close();
    exit;
}

$del = $conn->prepare("DELETE FROM material_submissions WHERE id = ? AND student_id = ?");
$del->bind_param("ii", $submission_id, $student_id);
$del->execute();
$del->close();
$conn->close();

deleteMaterialSubmissionFile($row['file_name']);

echo json_encode(['success' => true]);
