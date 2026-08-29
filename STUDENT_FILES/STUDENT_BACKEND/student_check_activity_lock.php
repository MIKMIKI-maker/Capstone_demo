<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
require_once __DIR__ . '/student_auth.php';
requireStudentSession();

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

$stmt = $conn->prepare("SELECT is_locked FROM teacher_activities WHERE id = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Query failed']);
    $conn->close();
    exit;
}
$stmt->bind_param("i", $activity_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Activity not found']);
    exit;
}

echo json_encode(['success' => true, 'is_locked' => (bool)$row['is_locked']]);
