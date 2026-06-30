<?php
require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$student_record_id = isset($_GET['student_record_id']) ? intval($_GET['student_record_id']) : 0;
$teacher_id        = isset($_GET['teacher_id'])        ? intval($_GET['teacher_id'])        : 0;

if (!$student_record_id || !$teacher_id) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

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
$stmt0->bind_param("i", $student_record_id);
$stmt0->execute();
$directs = $stmt0->get_result();
while ($row = $directs->fetch_assoc()) {
    $notifications[] = [
        'id'         => (int)$row['id'],
        'type'       => $row['notification_type'] ?: 'message',
        'title'      => htmlspecialchars($row['title']),
        'text'       => htmlspecialchars($row['message']),
        'time'       => $row['created_at'],
        'read'       => (bool)$row['is_read'],
    ];
}
$stmt0->close();

// 2. Teacher notes as notifications
$stmt = $conn->prepare("
    SELECT n.note, n.created_at,
           CONCAT(t.first_name, ' ', t.last_name) AS teacher_name
    FROM student_notes n
    LEFT JOIN teacher_accounts t ON t.id = n.teacher_id
    WHERE n.teacher_id = ? AND n.student_id = ?
    ORDER BY n.created_at DESC
    LIMIT 15
");
$stmt->bind_param("ii", $teacher_id, $student_record_id);
$stmt->execute();
$notes = $stmt->get_result();
while ($row = $notes->fetch_assoc()) {
    $notifications[] = [
        'id'    => null,
        'type'  => 'message',
        'title' => 'Note from ' . htmlspecialchars($row['teacher_name']),
        'text'  => htmlspecialchars($row['note']),
        'time'  => $row['created_at'],
        'read'  => false,
    ];
}
$stmt->close();

// 3. Newly published activities (not yet completed) as notifications
$stmt2 = $conn->prepare("
    SELECT a.id AS activity_id, a.activity_title, a.activity_type, a.created_at
    FROM teacher_activities a
    LEFT JOIN learner_progress lp ON lp.activity_id = a.id AND lp.student_id = ?
    WHERE a.teacher_id = ? AND a.status = 'published' AND lp.id IS NULL
    ORDER BY a.created_at DESC
    LIMIT 10
");
$stmt2->bind_param("ii", $student_record_id, $teacher_id);
$stmt2->execute();
$acts = $stmt2->get_result();
while ($row = $acts->fetch_assoc()) {
    $type_label = $row['activity_type'] ? ' (' . $row['activity_type'] . ')' : '';
    $notifications[] = [
        'id'    => null,
        'type'  => 'new_activity',
        'title' => 'New activity available',
        'text'  => 'Your teacher added "' . htmlspecialchars($row['activity_title']) . '"' . $type_label . ' to your materials.',
        'time'  => $row['created_at'],
        'read'  => false,
    ];
}
$stmt2->close();
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
