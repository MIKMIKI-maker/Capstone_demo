<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
require_once __DIR__ . '/student_auth.php';

$student_admin_id = requireStudentSession();

$activity_id = isset($_GET['activity_id']) ? intval($_GET['activity_id']) : 0;
if (!$activity_id) {
    echo json_encode(['success' => false, 'message' => 'activity_id required']);
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
$student_record_id = (int)$rec['student_record_id'];

// Only serve content for activities actually assigned to this student
$stmt = $conn->prepare("
    SELECT a.id, a.teacher_id, a.activity_title, a.activity_type, a.subject, a.deadline, a.content_json, a.is_locked
    FROM teacher_activities a
    INNER JOIN activity_assignments aa ON aa.activity_id = a.id AND aa.student_id = ?
    WHERE a.id = ? AND a.status = 'published'
");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Query failed']);
    exit;
}
$stmt->bind_param("ii", $student_record_id, $activity_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();
$conn->close();

if (!$row || !$row['content_json']) {
    echo json_encode(['success' => false, 'message' => 'No saved content found for this activity']);
    exit;
}

$content = json_decode($row['content_json'], true);
if ($content === null) {
    echo json_encode(['success' => false, 'message' => 'Saved content is corrupted']);
    exit;
}

echo json_encode([
    'success'     => true,
    'activity_id' => (int)$row['id'],
    'teacher_id'  => (int)$row['teacher_id'],
    'title'       => $row['activity_title'],
    'type'        => $row['activity_type'],
    'condition'   => $row['subject'],
    'deadline'    => $row['deadline'],
    'is_locked'   => (bool)$row['is_locked'],
    'content'     => $content,
]);
