<?php
// $notif_key: pass this to make the notification idempotent — a second call
// with the same (teacher_id, notif_key) is silently ignored instead of
// inserting a duplicate row. Used for anything that might get pushed more
// than once for the same real-world event (e.g. a daily "still not
// answered" reminder, which this app re-checks on every dashboard load
// rather than on a real once-a-day cron — see
// teacher_check_pending_reminders.php). Leave it null for normal
// one-shot notifications (e.g. retake alerts), which were never deduped
// and should keep inserting freely.
function pushTeacherNotification($teacher_conn, $teacher_id, $type, $title, $message = '', $data = null, $notif_key = null) {
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
    // Migration: add notif_key + a unique (teacher_id, notif_key) pair so a
    // notification can opt into "insert at most once" semantics. NULL never
    // conflicts with NULL in a unique index, so existing non-deduped calls
    // (notif_key omitted) are unaffected.
    $col2 = $teacher_conn->query("SHOW COLUMNS FROM notifications LIKE 'notif_key'");
    if ($col2 && $col2->num_rows == 0) {
        $teacher_conn->query("ALTER TABLE notifications ADD COLUMN notif_key VARCHAR(150) DEFAULT NULL");
        $teacher_conn->query("ALTER TABLE notifications ADD UNIQUE KEY unique_teacher_notif (teacher_id, notif_key)");
    }

    $dataJson = $data !== null ? (is_string($data) ? $data : json_encode($data)) : null;

    if ($notif_key !== null) {
        $stmt = $teacher_conn->prepare(
            "INSERT IGNORE INTO notifications (teacher_id, notification_type, title, message, data_json, notif_key) VALUES (?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) return;
        $stmt->bind_param("isssss", $teacher_id, $type, $title, $message, $dataJson, $notif_key);
    } else {
        $stmt = $teacher_conn->prepare(
            "INSERT INTO notifications (teacher_id, notification_type, title, message, data_json) VALUES (?, ?, ?, ?, ?)"
        );
        if (!$stmt) return;
        $stmt->bind_param("issss", $teacher_id, $type, $title, $message, $dataJson);
    }
    $stmt->execute();
    $stmt->close();
}
?>
