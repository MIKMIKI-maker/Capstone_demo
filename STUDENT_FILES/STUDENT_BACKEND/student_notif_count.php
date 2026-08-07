<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$teacher_id = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : 0;

if (!$student_id || !$teacher_id) {
    echo json_encode(['unread' => 0]);
    exit;
}

$conn = new mysqli('127.0.0.1', 'root', '', 'spedalm_db', 3307);
if ($conn->connect_error) {
    echo json_encode(['unread' => 0]);
    exit;
}
$conn->set_charset('utf8mb4');

$unread = 0;

// Count unread direct notifications from teacher
$stmt = $conn->prepare("SELECT COUNT(*) FROM student_notifications WHERE student_id=? AND is_read=0");
if ($stmt) {
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $stmt->bind_result($cnt);
    $stmt->fetch();
    $stmt->close();
    $unread += (int)$cnt;
}

// Count published activities not yet completed by this student
$stmt2 = $conn->prepare("
    SELECT COUNT(*) FROM teacher_activities a
    LEFT JOIN learner_progress lp ON lp.activity_id = a.id AND lp.student_id = ?
    WHERE a.teacher_id = ? AND a.status = 'published' AND lp.id IS NULL
");
if ($stmt2) {
    $stmt2->bind_param("ii", $student_id, $teacher_id);
    $stmt2->execute();
    $stmt2->bind_result($acts);
    $stmt2->fetch();
    $stmt2->close();
    $unread += (int)$acts;
}

$conn->close();
echo json_encode(['unread' => $unread]);
?>
