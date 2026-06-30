<?php
require_once __DIR__ . '/db.php';

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
$teacher_id = isset($_REQUEST['teacher_id']) ? intval($_REQUEST['teacher_id']) : 1;

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
?>
