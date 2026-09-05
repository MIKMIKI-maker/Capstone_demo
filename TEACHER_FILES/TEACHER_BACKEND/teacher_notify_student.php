<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/teacher_auth.php';
require_once __DIR__ . '/../../MAILER/send_email.php';

function notifyParentByEmail($parent_email, $parent_name, $student_name, $title, $message) {
    if (!$parent_email) return;
    $safeStudent = htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8');
    $safeTitle   = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    $html = "<p>Hello " . htmlspecialchars($parent_name ?: 'Parent/Guardian', ENT_QUOTES, 'UTF-8') . ",</p>"
        . "<p>Your child's teacher sent a new notification regarding <b>{$safeStudent}</b>:</p>"
        . "<div style=\"background:#f1f5f9;border-left:4px solid #1e3a8a;padding:12px 16px;margin:12px 0;\">"
        . "<p style=\"margin:0 0 6px;font-weight:700;color:#1e3a8a;\">{$safeTitle}</p>"
        . "<p style=\"margin:0;\">{$safeMessage}</p>"
        . "</div>"
        . "<p style=\"color:#64748b;font-size:12px;\">This is an automated message from SPED ALM. Please do not reply directly to this email.</p>";
    send_email($parent_email, $parent_name ?: 'Parent/Guardian', "New notification for {$student_name}: {$title}", $html);
}

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
$teacher_id = requireTeacherId();

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
            // Look up student name + parent contact for the teacher's sent-log
            // entry and the parent email notification below.
            $snq = $conn->prepare("SELECT student_name, parent_name, parent_email FROM students WHERE id = ? AND teacher_id = ?");
            $student_label = 'a student';
            if ($snq) {
                $snq->bind_param("ii", $student_id, $teacher_id);
                $snq->execute();
                if ($snrow = $snq->get_result()->fetch_assoc()) {
                    $student_label = $snrow['student_name'];
                    notifyParentByEmail($snrow['parent_email'], $snrow['parent_name'], $student_label, $title, $message);
                }
                $snq->close();
            }
            pushTeacherNotification($conn, $teacher_id, 'message', 'Notification Sent', 'To ' . $student_label . ': "' . $title . '"');
            echo json_encode(['success' => true, 'message' => 'Notification sent']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send notification']);
        }
        $stmt->close();
    } else {
        // Broadcast to all students of this teacher
        $bq = $conn->prepare("SELECT id, student_name, parent_name, parent_email FROM students WHERE teacher_id = ?");
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
                    if ($ins->execute()) {
                        $count++;
                        notifyParentByEmail($s['parent_email'], $s['parent_name'], $s['student_name'], $title, $message);
                    }
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
