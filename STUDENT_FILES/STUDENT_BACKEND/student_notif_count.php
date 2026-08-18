<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Cache-Control: no-cache');

require_once __DIR__ . '/student_auth.php';
$student_admin_id = requireStudentSession();

$conn = new mysqli('127.0.0.1', 'root', '', 'spedalm_db', 3306);
if ($conn->connect_error) {
    echo json_encode(['unread' => 0]);
    exit;
}
$conn->set_charset('utf8mb4');

$rec = resolveStudentRecord($conn, $student_admin_id);
if (!$rec) {
    echo json_encode(['unread' => 0]);
    $conn->close();
    exit;
}
$student_id = (int)$rec['student_record_id'];
$teacher_id = (int)$rec['teacher_id'];

$unread = 0;

// 1. Unread direct notifications from teacher
$stmt = $conn->prepare("SELECT COUNT(*) FROM student_notifications WHERE student_id=? AND is_read=0");
if ($stmt) {
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $stmt->bind_result($cnt);
    $stmt->fetch();
    $stmt->close();
    $unread += (int)$cnt;
}

// 2. Teacher notes not yet marked read, capped at 15 most recent
$stmt2 = $conn->prepare("
    SELECT COUNT(*) FROM (
        SELECT n.id
        FROM student_notes n
        LEFT JOIN student_notification_reads r ON r.student_id = n.student_id AND r.notif_key = CONCAT('note_', n.id)
        WHERE n.teacher_id=? AND n.student_id=? AND r.id IS NULL
        ORDER BY n.created_at DESC
        LIMIT 15
    ) t
");
if ($stmt2) {
    $stmt2->bind_param("ii", $teacher_id, $student_id);
    $stmt2->execute();
    $stmt2->bind_result($cnt2);
    $stmt2->fetch();
    $stmt2->close();
    $unread += (int)$cnt2;
}

// 3. Newly published activities not yet completed or marked read, capped at 10 most recent
$stmt3 = $conn->prepare("
    SELECT COUNT(*) FROM (
        SELECT a.id
        FROM teacher_activities a
        LEFT JOIN learner_progress lp ON lp.activity_id = a.id AND lp.student_id = ?
        LEFT JOIN student_notification_reads r ON r.student_id = ? AND r.notif_key = CONCAT('activity_', a.id)
        WHERE a.teacher_id = ? AND a.status = 'published' AND lp.id IS NULL AND r.id IS NULL
        ORDER BY a.created_at DESC
        LIMIT 10
    ) t
");
if ($stmt3) {
    $stmt3->bind_param("iii", $student_id, $student_id, $teacher_id);
    $stmt3->execute();
    $stmt3->bind_result($cnt3);
    $stmt3->fetch();
    $stmt3->close();
    $unread += (int)$cnt3;
}

$conn->close();
echo json_encode(['unread' => $unread]);
?>
