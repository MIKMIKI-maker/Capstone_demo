<?php
// Helper: insert one row into admin_notifications.
// Creates the table automatically if it doesn't exist yet.
function pushAdminNotification($conn, $type, $title, $message = '', $related_id = null) {
    if (!$conn) return;

    $conn->query("CREATE TABLE IF NOT EXISTS admin_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        notification_type VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT,
        related_id INT,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (is_read),
        INDEX (created_at)
    )");

    $stmt = $conn->prepare(
        "INSERT INTO admin_notifications (notification_type, title, message, related_id)
         VALUES (?, ?, ?, ?)"
    );
    if (!$stmt) return;
    $stmt->bind_param("sssi", $type, $title, $message, $related_id);
    $stmt->execute();
    $stmt->close();
}
?>
