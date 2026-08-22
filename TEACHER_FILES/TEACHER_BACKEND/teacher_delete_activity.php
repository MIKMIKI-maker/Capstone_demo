<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/teacher_auth.php';
session_start();
$_SESSION['teacher_id'] = requireTeacherId();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$teacher_conn = getTeacherDatabaseConnection();
if (!$teacher_conn) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit;
}

$teacher_id  = requireTeacherId();
$activity_id = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : 0;

if (!$teacher_id || !$activity_id) {
    echo json_encode(['success' => false, 'message' => 'Missing params']);
    $teacher_conn->close();
    exit;
}

// Remove student assignments for this activity row first
$del_assign = $teacher_conn->prepare("DELETE FROM activity_assignments WHERE activity_id = ?");
if ($del_assign) {
    $del_assign->bind_param("i", $activity_id);
    $del_assign->execute();
    $del_assign->close();
}

// Delete the activity row (scoped to the requesting teacher for safety)
$del = $teacher_conn->prepare("DELETE FROM teacher_activities WHERE id = ? AND teacher_id = ?");
if (!$del) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $teacher_conn->error]);
    $teacher_conn->close();
    exit;
}
$del->bind_param("ii", $activity_id, $teacher_id);
$del->execute();
$affected = $del->affected_rows;
$del->close();
$teacher_conn->close();

echo json_encode(['success' => $affected > 0]);
?>
