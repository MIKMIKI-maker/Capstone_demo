<?php
require_once __DIR__ . '/db.php';
requireAdminSession();

header('Content-Type: application/json');

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Check if notifications table exists, create if not
$tableCheck = $conn->query("SHOW TABLES LIKE 'admin_notifications'");
if (!$tableCheck || $tableCheck->num_rows == 0) {
    $createTableSql = "CREATE TABLE IF NOT EXISTS admin_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        notification_type VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT,
        related_id INT,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (is_read),
        INDEX (created_at)
    )";
    $conn->query($createTableSql);
}

// Get notifications, most recent first
$sql = "SELECT id, notification_type, title, message, related_id, is_read, created_at 
        FROM admin_notifications 
        ORDER BY created_at DESC 
        LIMIT 100";

$result = $conn->query($sql);
$notifications = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => $row['id'],
            'type' => $row['notification_type'],
            'title' => $row['title'],
            'message' => $row['message'],
            'related_id' => $row['related_id'],
            'read' => $row['is_read'] == 1,
            'timestamp' => $row['created_at']
        ];
    }
}

echo json_encode($notifications);
$conn->close();
?>
