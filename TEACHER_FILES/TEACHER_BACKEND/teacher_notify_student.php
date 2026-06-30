<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$action     = isset($_POST['action'])     ? trim($_POST['action'])     : 'send';
$teacher_id = isset($_POST['teacher_id']) ? intval($_POST['teacher_id']) : 0;

if (!$teacher_id) {
    echo json_encode(['success' => false, 'message' => 'Teacher ID required']);
    $conn->close();
    exit;
}

if ($action === 'send') {
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    $title      = isset($_POST['title'])      ? trim($_POST['title'])      : '';
    $message    = isset($_POST['message'])    ? trim($_POST['message'])    : '';
    $type       = isset($_POST['type'])       ? trim($_POST['type'])       : 'message';

    if (!$title || !$message) {
        echo json_encode(['success' => false, 'message' => 'Title and message are required']);
        $conn->close();
        exit;
    }

    if ($student_id) {
        // Send to one specific student
        $stmt = $conn->prepare("INSERT INTO student_notifications (teacher_id, student_id, title, message, notification_type) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisss", $teacher_id, $student_id, $title, $message, $type);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Notification sent']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send notification']);
        }
        $stmt->close();
    } else {
        // Broadcast to all students of this teacher
        $students_res = $conn->query("SELECT id FROM students WHERE teacher_id = $teacher_id AND status = 'active'");
        $count = 0;
        while ($s = $students_res->fetch_assoc()) {
            $sid = $s['id'];
            $stmt = $conn->prepare("INSERT INTO student_notifications (teacher_id, student_id, title, message, notification_type) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisss", $teacher_id, $sid, $title, $message, $type);
            if ($stmt->execute()) $count++;
            $stmt->close();
        }
        echo json_encode(['success' => true, 'message' => "Notification sent to $count student(s)", 'count' => $count]);
    }

} elseif ($action === 'mark_read') {
    $notif_id   = isset($_POST['notif_id'])   ? intval($_POST['notif_id'])   : 0;
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;

    if ($notif_id) {
        $stmt = $conn->prepare("UPDATE student_notifications SET is_read = 1 WHERE id = ? AND student_id = ?");
        $stmt->bind_param("ii", $notif_id, $student_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
    } elseif ($student_id) {
        // Mark all read for this student
        $stmt = $conn->prepare("UPDATE student_notifications SET is_read = 1 WHERE student_id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'notif_id or student_id required']);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
}

$conn->close();
?>
