<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/teacher_auth.php';
require_once __DIR__ . '/teacher_activity_lock_helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action         = isset($_POST['action'])         ? trim($_POST['action'])         : '';
$teacher_id     = requireTeacherId();
$activity_id    = isset($_POST['activity_id'])    ? intval($_POST['activity_id'])   : 0;
$activity_title = isset($_POST['activity_title']) ? trim($_POST['activity_title'])  : 'Activity';

if (!$teacher_id || !$activity_id || !in_array($action, ['lock', 'unlock'], true)) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid parameters']);
    exit;
}

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

setActivityLocked($conn, $teacher_id, $activity_id, $action === 'lock');

if ($action === 'lock') {
    $title   = '⏸️ Activity Temporarily Unavailable';
    $message = "Your teacher is updating \"{$activity_title}\". Please wait a bit before continuing — you'll be notified when it's ready.";
} else {
    $title   = '✅ Activity Available Again';
    $message = "\"{$activity_title}\" has been updated and is available again. You can continue working on it.";
}
$notified = notifyActivityAssignees($conn, $teacher_id, $activity_id, 'locked', $title, $message);

$conn->close();
echo json_encode(['success' => true, 'notified' => $notified]);
?>
