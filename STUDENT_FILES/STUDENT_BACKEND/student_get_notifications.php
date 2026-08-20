<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Cache-Control: no-cache');

require_once __DIR__ . '/student_auth.php';
$student_admin_id = requireStudentSession();

// Direct lightweight connection — skip getTeacherDatabaseConnection() migration overhead
$conn = new mysqli('127.0.0.1', 'root', '', 'spedalm_db', 3306);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}
$conn->set_charset('utf8mb4');

$rec = resolveStudentRecord($conn, $student_admin_id);
if (!$rec) {
    echo json_encode(['success' => false, 'message' => 'Not enrolled']);
    $conn->close();
    exit;
}
$student_record_id = (int)$rec['student_record_id'];
$teacher_id         = (int)$rec['teacher_id'];

$notifications = [];

// 1. Direct notifications sent by teacher via teacher_notify_student.php
$stmt0 = $conn->prepare("
    SELECT sn.id, sn.title, sn.message, sn.notification_type, sn.is_read, sn.created_at,
           CONCAT(t.first_name, ' ', t.last_name) AS teacher_name
    FROM student_notifications sn
    LEFT JOIN teacher_accounts t ON t.id = sn.teacher_id
    WHERE sn.student_id = ?
    ORDER BY sn.created_at DESC
    LIMIT 30
");
if ($stmt0) {
    $stmt0->bind_param("i", $student_record_id);
    $stmt0->execute();
    $directs = $stmt0->get_result();
    if ($directs) {
        while ($row = $directs->fetch_assoc()) {
            $notifications[] = [
                'id'   => (int)$row['id'],
                'type' => $row['notification_type'] ?: 'message',
                'title'=> htmlspecialchars($row['title']),
                'text' => htmlspecialchars($row['message']),
                'time' => $row['created_at'],
                'read' => (bool)$row['is_read'],
            ];
        }
    }
    $stmt0->close();
}

// 2. Teacher notes as notifications
$stmt = $conn->prepare("
    SELECT n.id, n.note, n.created_at,
           CONCAT(t.first_name, ' ', t.last_name) AS teacher_name,
           r.id AS read_id
    FROM student_notes n
    LEFT JOIN teacher_accounts t ON t.id = n.teacher_id
    LEFT JOIN student_notification_reads r ON r.student_id = n.student_id AND r.notif_key = CONCAT('note_', n.id)
    WHERE n.teacher_id = ? AND n.student_id = ?
    ORDER BY n.created_at DESC
    LIMIT 15
");
if ($stmt) {
    $stmt->bind_param("ii", $teacher_id, $student_record_id);
    $stmt->execute();
    $notes = $stmt->get_result();
    if ($notes) {
        while ($row = $notes->fetch_assoc()) {
            $tname = htmlspecialchars($row['teacher_name'] ?: 'Your teacher');
            $notifications[] = [
                'id'    => 'note_' . $row['id'],
                'type'  => 'message',
                'title' => 'Note from ' . $tname,
                'text'  => htmlspecialchars($row['note']),
                'time'  => $row['created_at'],
                'read'  => $row['read_id'] !== null,
            ];
        }
    }
    $stmt->close();
}

// 3. Newly published activities (not yet completed) as notifications
$stmt2 = $conn->prepare("
    SELECT a.id AS activity_id, a.activity_title, a.activity_type, a.created_at,
           r.id AS read_id
    FROM teacher_activities a
        INNER JOIN activity_assignments aa ON aa.activity_id = a.id AND aa.student_id = ?
    LEFT JOIN learner_progress lp ON lp.activity_id = a.id AND lp.student_id = ?
    LEFT JOIN student_notification_reads r ON r.student_id = ? AND r.notif_key = CONCAT('activity_', a.id)
    WHERE a.teacher_id = ? AND a.status = 'published' AND lp.id IS NULL
    ORDER BY a.created_at DESC
    LIMIT 10
");
if ($stmt2) {
    $stmt2->bind_param("iiii", $student_record_id, $student_record_id, $student_record_id, $teacher_id);
    $stmt2->execute();
    $acts = $stmt2->get_result();
    if ($acts) {
        while ($row = $acts->fetch_assoc()) {
            $type_label = $row['activity_type'] ? ' (' . $row['activity_type'] . ')' : '';
            $notifications[] = [
                'id'    => 'activity_' . $row['activity_id'],
                'type'  => 'new_activity',
                'title' => 'New activity available',
                'text'  => 'Your teacher added "' . htmlspecialchars($row['activity_title']) . '"' . $type_label . ' to your materials.',
                'time'  => $row['created_at'],
                'read'  => $row['read_id'] !== null,
            ];
        }
    }
    $stmt2->close();
}
$conn->close();

// Sort all by time descending
usort($notifications, function($a, $b) { return strcmp($b['time'], $a['time']); });

$unread = count(array_filter($notifications, function($n) { return !$n['read']; }));

echo json_encode([
    'success'       => true,
    'unread'        => $unread,
    'notifications' => $notifications,
]);
?>
