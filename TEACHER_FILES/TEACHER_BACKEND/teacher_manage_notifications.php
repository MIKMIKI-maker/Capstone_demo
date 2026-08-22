<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/teacher_auth.php';
 $teacher_id = requireTeacherId();

header('Content-Type: application/json');

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';

switch($action) {
    case 'create':
        createNotification($conn, $teacher_id);
        break;
    case 'list':
        listNotifications($conn, $teacher_id);
        break;
    case 'mark_read':
        markNotificationRead($conn, $teacher_id);
        break;
    case 'delete':
        deleteNotification($conn, $teacher_id);
        break;
    case 'get_unread_count':
        getUnreadCount($conn, $teacher_id);
        break;
    case 'mark_all_read':
        markAllNotificationsRead($conn, $teacher_id);
        break;
    case 'clear_all':
        clearAllNotifications($conn, $teacher_id);
        break;
    case 'seed_history':
        seedHistoryFromProgress($conn, $teacher_id);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}

$conn->close();

function createNotification($conn, $teacher_id) {
    $notification_type = isset($_POST['notification_type']) ? trim($_POST['notification_type']) : 'info';
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    
    if (!$title || !$message) {
        echo json_encode(['success' => false, 'message' => 'Title and message are required']);
        return;
    }
    
    $sql = "INSERT INTO notifications (teacher_id, notification_type, title, message, is_read)
            VALUES (?, ?, ?, ?, FALSE)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $teacher_id, $notification_type, $title, $message);
    
    if ($stmt->execute()) {
        $notification_id = $stmt->insert_id;
        echo json_encode(['success' => true, 'message' => 'Notification created', 'notification_id' => $notification_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create notification']);
    }
    $stmt->close();
}

function listNotifications($conn, $teacher_id) {
    $limit = isset($_REQUEST['limit']) ? intval($_REQUEST['limit']) : 20;
    $offset = isset($_REQUEST['offset']) ? intval($_REQUEST['offset']) : 0;
    
    $sql = "SELECT * FROM notifications WHERE teacher_id=? ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $teacher_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    // Get total count
    $countSQL = "SELECT COUNT(*) as total FROM notifications WHERE teacher_id=?";
    $countStmt = $conn->prepare($countSQL);
    $countStmt->bind_param("i", $teacher_id);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $countRow = $countResult->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'count' => count($notifications),
        'total' => $countRow['total']
    ]);
    $stmt->close();
    $countStmt->close();
}

function markNotificationRead($conn, $teacher_id) {
    $notification_id = isset($_POST['notification_id']) ? intval($_POST['notification_id']) : 0;
    
    if (!$notification_id) {
        echo json_encode(['success' => false, 'message' => 'Notification ID is required']);
        return;
    }
    
    $sql = "UPDATE notifications SET is_read=TRUE WHERE id=? AND teacher_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $notification_id, $teacher_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update notification']);
    }
    $stmt->close();
}

function deleteNotification($conn, $teacher_id) {
    $notification_id = isset($_POST['notification_id']) ? intval($_POST['notification_id']) : 0;
    
    if (!$notification_id) {
        echo json_encode(['success' => false, 'message' => 'Notification ID is required']);
        return;
    }
    
    $sql = "DELETE FROM notifications WHERE id=? AND teacher_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $notification_id, $teacher_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Notification deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete notification']);
    }
    $stmt->close();
}

function getUnreadCount($conn, $teacher_id) {
    $sql = "SELECT COUNT(*) as unread_count FROM notifications WHERE teacher_id=? AND is_read=FALSE";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    echo json_encode(['success' => true, 'unread_count' => $row['unread_count']]);
    $stmt->close();
}

function markAllNotificationsRead($conn, $teacher_id) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE teacher_id=?");
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    echo json_encode(['success' => true]);
    $stmt->close();
}

function clearAllNotifications($conn, $teacher_id) {
    $stmt = $conn->prepare("DELETE FROM notifications WHERE teacher_id=?");
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    echo json_encode(['success' => true]);
    $stmt->close();
}

function seedHistoryFromProgress($conn, $teacher_id) {
    // Only seed when the teacher has zero notifications (avoid duplicates)
    $chk = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE teacher_id=?");
    if (!$chk) { echo json_encode(['success' => true, 'seeded' => 0]); return; }
    $chk->bind_param("i", $teacher_id);
    $chk->execute();
    $cnt = (int)$chk->get_result()->fetch_assoc()['cnt'];
    $chk->close();
    if ($cnt > 0) { echo json_encode(['success' => true, 'seeded' => 0]); return; }

    $ins = $conn->prepare(
        "INSERT INTO notifications (teacher_id, notification_type, title, message, is_read, created_at)
         VALUES (?, ?, ?, ?, 1, ?)"
    );
    if (!$ins) { echo json_encode(['success' => true, 'seeded' => 0]); return; }
    $seeded = 0;

    // 1. Seed past published activities ("Activity Published")
    $actStmt = $conn->prepare("
        SELECT ta.activity_title, ta.activity_type, ta.created_at,
               COUNT(aa.student_id) AS assigned_count
        FROM teacher_activities ta
        LEFT JOIN activity_assignments aa ON aa.activity_id = ta.id
        WHERE ta.teacher_id = ? AND ta.status = 'published'
        GROUP BY ta.id
        ORDER BY ta.created_at ASC
    ");
    if ($actStmt) {
        $actStmt->bind_param("i", $teacher_id);
        $actStmt->execute();
        $actRows = $actStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $actStmt->close();
        foreach ($actRows as $row) {
            $msg  = 'You published "' . $row['activity_title'] . '"';
            if ((int)$row['assigned_count'] > 0) {
                $msg .= ' — assigned to ' . $row['assigned_count'] . ' student(s).';
            }
            $ts   = $row['created_at'] ?: date('Y-m-d H:i:s');
            $type = 'activity';
            $ttl  = 'Activity Published';
            $ins->bind_param("issss", $teacher_id, $type, $ttl, $msg, $ts);
            $ins->execute();
            $seeded++;
        }
    }

    // 2. Seed past student completions ("Student Activity Completed")
    $lpStmt = $conn->prepare("
        SELECT lp.score, lp.assessment_date, s.student_name, ta.activity_title
        FROM learner_progress lp
        JOIN students s  ON s.id  = lp.student_id  AND s.teacher_id = ?
        JOIN teacher_activities ta ON ta.id = lp.activity_id AND ta.teacher_id = ?
        ORDER BY lp.assessment_date ASC
    ");
    if ($lpStmt) {
        $lpStmt->bind_param("ii", $teacher_id, $teacher_id);
        $lpStmt->execute();
        $lpRows = $lpStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $lpStmt->close();
        foreach ($lpRows as $row) {
            $sc   = $row['score'];
            $msg  = $row['student_name'] . ' completed "' . $row['activity_title'] . '"'
                  . ($sc !== null ? ' with a score of ' . intval($sc) . '%.' : '.');
            $ts   = $row['assessment_date'] ?: date('Y-m-d H:i:s');
            $type = 'activity';
            $ttl  = 'Student Activity Completed';
            $ins->bind_param("issss", $teacher_id, $type, $ttl, $msg, $ts);
            $ins->execute();
            $seeded++;
        }
    }

    $ins->close();
    echo json_encode(['success' => true, 'seeded' => $seeded]);
}
?>
