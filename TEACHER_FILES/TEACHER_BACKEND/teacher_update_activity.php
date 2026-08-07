<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/teacher_activity_lock_helpers.php';
require_once __DIR__ . '/teacher_push_notification.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$teacher_id     = isset($_POST['teacher_id'])     ? intval($_POST['teacher_id'])     : 0;
$activity_id    = isset($_POST['activity_id'])    ? intval($_POST['activity_id'])    : 0;
$activity_title = isset($_POST['activity_title']) ? trim($_POST['activity_title'])   : '';
$subject        = isset($_POST['subject'])        ? trim($_POST['subject'])          : '';
$content_json   = isset($_POST['content_json'])   ? $_POST['content_json']           : null;
$deadline       = isset($_POST['deadline'])       ? trim($_POST['deadline'])         : '';
if ($deadline === '') $deadline = null;

if (!$teacher_id || !$activity_id || !$activity_title) {
    echo json_encode(['success' => false, 'message' => 'Teacher ID, activity ID, and title are required']);
    exit;
}

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$stmt = $conn->prepare("UPDATE teacher_activities SET activity_title = ?, subject = ?, content_json = ?, deadline = ? WHERE id = ? AND teacher_id = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Query preparation failed']);
    exit;
}
$stmt->bind_param("ssssii", $activity_title, $subject, $content_json, $deadline, $activity_id, $teacher_id);

if ($stmt->execute()) {
    setActivityLocked($conn, $teacher_id, $activity_id, false);
    notifyActivityAssignees(
        $conn, $teacher_id, $activity_id, 'locked',
        '✅ Activity Available Again',
        "\"{$activity_title}\" has been updated and is available again. You can continue working on it."
    );
    pushTeacherNotification($conn, $teacher_id, 'activity', 'Activity Updated', "You saved changes to \"{$activity_title}\" and it's live again for students.");
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update activity']);
}

$stmt->close();
$conn->close();
?>
