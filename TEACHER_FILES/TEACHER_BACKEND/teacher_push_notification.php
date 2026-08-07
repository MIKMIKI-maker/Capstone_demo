<?php
function pushTeacherNotification($teacher_conn, $teacher_id, $type, $title, $message = '', $data = null) {
    if (!$teacher_conn || !$teacher_id) return;
    $teacher_conn->query("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        notification_type VARCHAR(50) DEFAULT 'info',
        title VARCHAR(255) NOT NULL,
        message TEXT,
        is_read TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    // Migration: add data_json if missing (holds extra structured detail for the teacher's notification modal)
    $col = $teacher_conn->query("SHOW COLUMNS FROM notifications LIKE 'data_json'");
    if ($col && $col->num_rows == 0) {
        $teacher_conn->query("ALTER TABLE notifications ADD COLUMN data_json LONGTEXT DEFAULT NULL");
    }
    $dataJson = $data !== null ? (is_string($data) ? $data : json_encode($data)) : null;
    $stmt = $teacher_conn->prepare(
        "INSERT INTO notifications (teacher_id, notification_type, title, message, data_json) VALUES (?, ?, ?, ?, ?)"
    );
    if (!$stmt) return;
    $stmt->bind_param("issss", $teacher_id, $type, $title, $message, $dataJson);
    $stmt->execute();
    $stmt->close();
}
?>
