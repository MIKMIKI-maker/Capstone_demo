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

    require_once __DIR__ . '/teacher_push_notification.php';

    if ($student_id) {
        // Send to one specific student
        $stmt = $conn->prepare("INSERT INTO student_notifications (teacher_id, student_id, title, message, notification_type) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisss", $teacher_id, $student_id, $title, $message, $type);
        if ($stmt->execute()) {
            // Look up student name for the teacher's sent-log entry
            $snq = $conn->prepare("SELECT student_name FROM students WHERE id = ? AND teacher_id = ?");
            $student_label = 'a student';
            if ($snq) { $snq->bind_param("ii", $student_id, $teacher_id); $snq->execute(); if ($snrow = $snq->get_result()->fetch_assoc()) { $student_label = $snrow['student_name']; } $snq->close(); }
            pushTeacherNotification($conn, $teacher_id, 'message', 'Notification Sent', 'To ' . $student_label . ': "' . $title . '"');
            echo json_encode(['success' => true, 'message' => 'Notification sent']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send notification']);
        }
        $stmt->close();
    } else {
        // Broadcast to all students of this teacher
        $bq = $conn->prepare("SELECT id FROM students WHERE teacher_id = ?");
        $count = 0;
        if ($bq) {
            $bq->bind_param("i", $teacher_id);
            $bq->execute();
            $students_res = $bq->get_result();
            $bq->close();
            $ins = $conn->prepare("INSERT INTO student_notifications (teacher_id, student_id, title, message, notification_type) VALUES (?, ?, ?, ?, ?)");
            if ($ins && $students_res) {
                while ($s = $students_res->fetch_assoc()) {
                    $sid = (int)$s['id'];
                    $ins->bind_param("iisss", $teacher_id, $sid, $title, $message, $type);
                    if ($ins->execute()) $count++;
                }
                $ins->close();
            }
        }
        if ($count > 0) {
            pushTeacherNotification($conn, $teacher_id, 'message', 'Broadcast Sent', '"' . $title . '" sent to ' . $count . ' student(s).');
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
