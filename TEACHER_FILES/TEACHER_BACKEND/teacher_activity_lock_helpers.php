<?php
// Shared by teacher_lock_activity.php (lock/unlock toggle from the activity
// list) and teacher_update_activity.php (unlock + notify on save). Content
// itself lives only in the publishing teacher's browser storage (see
// Sorting_Type_Template.html confirmPublish()) — these helpers just track
// the lock flag + notify affected students through the DB-backed inbox.

function setActivityLocked($conn, $teacher_id, $activity_id, $locked) {
    $col = $conn->query("SHOW COLUMNS FROM teacher_activities LIKE 'is_locked'");
    if ($col && $col->num_rows == 0) {
        $conn->query("ALTER TABLE teacher_activities ADD COLUMN is_locked TINYINT(1) DEFAULT 0");
    }
    $lockedInt = $locked ? 1 : 0;
    $stmt = $conn->prepare("UPDATE teacher_activities SET is_locked = ? WHERE id = ? AND teacher_id = ?");
    if (!$stmt) return;
    $stmt->bind_param("iii", $lockedInt, $activity_id, $teacher_id);
    $stmt->execute();
    $stmt->close();
}

function notifyActivityAssignees($conn, $teacher_id, $activity_id, $type, $title, $message) {
    $stmt = $conn->prepare("SELECT student_id FROM activity_assignments WHERE activity_id = ?");
    $notified = 0;
    if (!$stmt) return $notified;
    $stmt->bind_param("i", $activity_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $ins = $conn->prepare("INSERT INTO student_notifications (teacher_id, student_id, title, message, notification_type) VALUES (?, ?, ?, ?, ?)");
    while ($row = $res->fetch_assoc()) {
        $sid = (int)$row['student_id'];
        if ($ins) {
            $ins->bind_param("iisss", $teacher_id, $sid, $title, $message, $type);
            if ($ins->execute()) $notified++;
        }
    }
    if ($ins) $ins->close();
    $stmt->close();
    return $notified;
}
?>
