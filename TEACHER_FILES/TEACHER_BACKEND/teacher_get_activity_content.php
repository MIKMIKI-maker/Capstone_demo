<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Cache-Control: no-cache');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/teacher_auth.php';

$teacher_id = requireTeacherId();

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

// A teacher may only load the raw saved content of their own activity (for editing)
$stmt = $conn->prepare("
    SELECT id, teacher_id, activity_title, activity_type, subject, deadline, content_json, is_locked
    FROM teacher_activities
    WHERE id = ? AND teacher_id = ?
");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Query failed']);
    $conn->close();
    exit;
}
$stmt->bind_param("ii", $activity_id, $teacher_id);
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
