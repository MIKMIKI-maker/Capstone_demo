<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/teacher_push_notification.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$teacher_id     = isset($_POST['teacher_id'])     ? intval($_POST['teacher_id'])     : 0;
$student_id     = isset($_POST['student_id'])     ? intval($_POST['student_id'])     : 0;
$activity_id    = isset($_POST['activity_id'])    ? intval($_POST['activity_id'])    : 0;
$activity_title = isset($_POST['activity_title']) ? trim($_POST['activity_title'])   : 'Activity';
$slide_number   = isset($_POST['slide_number'])   ? intval($_POST['slide_number'])   : 1;
$difficulty     = isset($_POST['difficulty'])     ? trim($_POST['difficulty'])       : '';
$retake_count   = isset($_POST['retake_count'])   ? intval($_POST['retake_count'])   : 0;

if (!$teacher_id || !$student_id || !$activity_id) {
    echo json_encode(['success' => false, 'message' => 'Teacher ID, student ID, and activity ID are required']);
    exit;
}

$teacher_conn = getTeacherDatabaseConnection();
if (!$teacher_conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$student_name = 'A student';
$sq = $teacher_conn->prepare("SELECT student_name FROM students WHERE id=? AND teacher_id=?");
if ($sq) {
    $sq->bind_param("ii", $student_id, $teacher_id);
    $sq->execute();
    $r = $sq->get_result();
    if ($row = $r->fetch_assoc()) { $student_name = $row['student_name']; }
    $sq->close();
}

$diffTag = $difficulty !== '' ? " ({$difficulty})" : '';

pushTeacherNotification(
    $teacher_conn,
    $teacher_id,
    'retake_alert',
    '🔄 Student Still Retrying',
    "{$student_name} is on retake #{$retake_count} of Slide {$slide_number}{$diffTag} in \"{$activity_title}\" — they're working on it right now.",
    [
        'student_id'     => $student_id,
        'student_name'   => $student_name,
        'activity_id'    => $activity_id,
        'activity_title' => $activity_title,
        'slide_number'   => $slide_number,
        'difficulty'     => $difficulty,
        'retake_count'   => $retake_count,
        'live'           => true,
    ]
);

$teacher_conn->close();
echo json_encode(['success' => true]);
