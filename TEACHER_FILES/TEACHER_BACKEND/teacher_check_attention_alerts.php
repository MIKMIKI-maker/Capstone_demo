<?php
// Notifies the teacher the first time a learner's overall average drops into
// "Need Assistance" (below 75%) — the same bottom tier the Needs Attention
// panel on Class Reports flags (Excellent/Very Good/Good/Need Support sit
// above it and are never flagged) — so a teacher who hasn't opened that page
// still finds out.
//
// Piggybacked on the dashboard load like teacher_check_pending_reminders.php
// (see that file — this app has no cron). notif_key is scoped to student_id,
// not to today's date, so staying below 75% on every later check is a no-op
// via INSERT IGNORE rather than a fresh notification each time. Once a
// learner recovers to 75%+ their notification row is deleted, which frees
// the key so a later relapse notifies again instead of staying silent
// forever after the first drop.
require_once __DIR__ . '/teacher_push_notification.php';

function checkNeedsAttentionAlerts($conn, $teacher_id) {
    if (!$conn || !$teacher_id) return;

    $sql = "SELECT s.id AS student_id, s.student_name,
                   ROUND(AVG(COALESCE(sub.finalized_score, lp.score)), 1) AS avg_score
            FROM students s
            JOIN learner_progress lp ON lp.student_id = s.id AND lp.teacher_id = s.teacher_id
            LEFT JOIN activity_submissions sub
                   ON sub.activity_id = lp.activity_id
                  AND sub.student_id  = lp.student_id
                  AND sub.teacher_id  = lp.teacher_id
                  AND sub.is_finalized = 1
            WHERE s.teacher_id = ? AND s.status = 'active'
            GROUP BY s.id, s.student_name";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return;
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $delStmt = $conn->prepare("DELETE FROM notifications WHERE teacher_id = ? AND notif_key = ?");

    while ($row = $res->fetch_assoc()) {
        $avg      = (float)$row['avg_score'];
        $name     = trim($row['student_name']);
        $notifKey = 'attention_' . $row['student_id'] . '_need_assistance';

        if ($avg < 75) {
            $message = $name . ' needs assistance — average score is ' . $avg . '%. Needs immediate support.';
            pushTeacherNotification(
                $conn,
                $teacher_id,
                'needs_attention',
                $name . ' needs assistance',
                $message,
                ['student_id' => (int)$row['student_id'], 'avg_score' => $avg],
                $notifKey
            );
        } elseif ($delStmt) {
            $delStmt->bind_param("is", $teacher_id, $notifKey);
            $delStmt->execute();
        }
    }
    if ($delStmt) $delStmt->close();
    $stmt->close();
}
