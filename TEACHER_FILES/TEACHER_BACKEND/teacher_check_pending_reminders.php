<?php
// Daily "still not answered" reminders for the teacher — one per
// (activity, student) per day, starting the day after publish and
// continuing through the deadline day itself, until the student submits.
//
// This app has no cron/scheduler (see docker/supervisord.conf — only
// apache2 and mysql run), so there's no true once-a-day trigger. Instead
// this runs opportunistically whenever the teacher's dashboard loads.
// That's safe to call more than once on the same day: pushTeacherNotification's
// notif_key encodes today's date, so a repeat call for something already
// sent today is a no-op (INSERT IGNORE against a unique key), not a
// duplicate. A day the teacher never opens the dashboard simply gets no
// reminder for that day — the "X days" count is computed from the real
// publish date each time, so it's still accurate whenever they next log in.
require_once __DIR__ . '/teacher_push_notification.php';

function checkPendingActivityReminders($conn, $teacher_id) {
    if (!$conn || !$teacher_id) return;

    $sql = "SELECT ta.id AS activity_id, ta.activity_title, ta.deadline,
                   DATEDIFF(CURDATE(), DATE(ta.created_at)) AS days_since,
                   s.id AS student_id, s.student_name
            FROM teacher_activities ta
            JOIN activity_assignments aa ON aa.activity_id = ta.id
            JOIN students s ON s.id = aa.student_id
            LEFT JOIN activity_submissions sub
                   ON sub.activity_id = ta.id AND sub.student_id = aa.student_id
            WHERE ta.teacher_id = ?
              AND ta.status = 'published'
              AND ta.deadline IS NOT NULL
              AND ta.deadline >= CURDATE()
              AND DATE(ta.created_at) < CURDATE()
              AND sub.id IS NULL";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return;
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $today = date('Y-m-d');
    while ($row = $res->fetch_assoc()) {
        $daysSince = (int)$row['days_since'];
        $dayWord = $daysSince === 1 ? 'day' : 'days';
        $message = trim($row['student_name']) . ' still hasn\'t answered "' . $row['activity_title'] . '" — '
            . $daysSince . ' ' . $dayWord . ' since it was published.';
        $notifKey = 'pending_' . $row['activity_id'] . '_' . $row['student_id'] . '_' . $today;

        pushTeacherNotification(
            $conn,
            $teacher_id,
            'pending_activity',
            'Activity not yet answered',
            $message,
            ['activity_id' => (int)$row['activity_id'], 'student_id' => (int)$row['student_id']],
            $notifKey
        );
    }
    $stmt->close();
}
