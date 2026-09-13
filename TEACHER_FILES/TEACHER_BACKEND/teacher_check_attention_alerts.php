<?php
// Notifies the teacher the first time a learner's overall average crosses
// into the same "Needs Support" (60-79%) / "At Risk" (<60%) bands the
// Needs Attention panel on Class Reports already flags — so a teacher who
// hasn't opened that page still finds out.
//
// Piggybacked on the dashboard load like teacher_check_pending_reminders.php
// (see that file — this app has no cron). notif_key is scoped to
// (student_id, band), not to today's date, so this fires once per learner
// per band rather than once a day: dropping from Passing into Needs Support
// notifies once, and dropping further into At Risk notifies again (a
// different band = a different key), but staying in the same band on every
// later check is a no-op via INSERT IGNORE.
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
            GROUP BY s.id, s.student_name
            HAVING avg_score < 80";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return;
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $avg  = (float)$row['avg_score'];
        $name = trim($row['student_name']);
        $atRisk = $avg < 60;
        $band = $atRisk ? 'at_risk' : 'needs_support';
        $message = $atRisk
            ? $name . ' is At Risk — average score is ' . $avg . '%. Needs immediate attention.'
            : $name . ' Needs Support — average score is ' . $avg . '%. Consider providing extra practice.';

        pushTeacherNotification(
            $conn,
            $teacher_id,
            'needs_attention',
            $name . ' needs attention',
            $message,
            ['student_id' => (int)$row['student_id'], 'avg_score' => $avg, 'band' => $band],
            'attention_' . $row['student_id'] . '_' . $band
        );
    }
    $stmt->close();
}
