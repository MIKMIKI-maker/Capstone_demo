<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/teacher_auth.php';
require_once __DIR__ . '/teacher_check_pending_reminders.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/school_year.php';

header('Content-Type: application/json');

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// The logged-in teacher, from the session — never a teacher_id sent by the browser.
$teacher_id = requireTeacherId();

// See teacher_check_pending_reminders.php — this is the closest thing this
// app has to a daily cron, piggybacked on the one page every teacher is
// virtually guaranteed to load.
checkPendingActivityReminders($conn, $teacher_id);

$stats = [
    'assigned_learners'    => 0,
    'active_learners'      => 0,
    'total_activities'     => 0,
    'draft_activities'     => 0,
    'published_activities' => 0,
    'recent_activities'    => [],
    'today_tasks'          => [],
    'student_progress'     => []
];

// Activity counts/lists only cover the School Year being viewed.
$syId = getViewedSchoolYearId($conn);
$roster = teacherRosterCondition($conn, $teacher_id, $syId, 's');

// Basic counts
$countQueries = [
    ['assigned_learners',    "SELECT COUNT(*) as c FROM students s WHERE $roster",                                           ''],
    ['active_learners',      "SELECT COUNT(*) as c FROM students s WHERE $roster AND s.status='active'",                     ''],
    ['total_activities',     "SELECT COUNT(*) as c FROM teacher_activities WHERE teacher_id=? AND school_year_id=$syId",                        'i'],
    ['draft_activities',     "SELECT COUNT(*) as c FROM teacher_activities WHERE teacher_id=? AND school_year_id=$syId AND status='draft'",     'i'],
    ['published_activities', "SELECT COUNT(*) as c FROM teacher_activities WHERE teacher_id=? AND school_year_id=$syId AND status='published'", 'i'],
];

foreach ($countQueries as $q) {
    list($key, $sql, $types) = $q;
    $stmt = $conn->prepare($sql);
    if (!$stmt) continue;
    if ($types) $stmt->bind_param($types, $teacher_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stats[$key] = (int)($row['c'] ?? 0);
    $stmt->close();
}

// Recent activities — Published/Draft activities only (not enrollments or
// scores, which used to also feed this panel).
$recent = [];

$stmt = $conn->prepare(
    "SELECT activity_title as title, status as sub, created_at as date
     FROM teacher_activities WHERE teacher_id=? AND school_year_id=$syId ORDER BY created_at DESC LIMIT 20"
);
if ($stmt) {
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $recent[] = [
            'title' => ($row['sub'] === 'published' ? 'Published: ' : 'Draft: ') . $row['title'],
            'type'  => $row['sub'] === 'published' ? 'publish' : 'draft',
            'date'  => $row['date']
        ];
    }
    $stmt->close();
}

$stats['recent_activities'] = $recent;

// Per-student progress
$stmt = $conn->prepare(
    "SELECT s.id, s.student_name, s.disability_type, s.status,
        (SELECT ROUND(AVG(CASE WHEN sub.is_finalized = 1 AND sub.finalized_score IS NOT NULL THEN sub.finalized_score ELSE lp.score END))
         FROM learner_progress lp
         JOIN teacher_activities ta ON ta.id = lp.activity_id AND ta.teacher_id = lp.teacher_id
         LEFT JOIN activity_submissions sub ON sub.student_id = lp.student_id AND sub.activity_id = lp.activity_id AND sub.teacher_id = lp.teacher_id
         WHERE lp.student_id = s.id AND lp.teacher_id = ? AND ta.school_year_id = $syId) AS last_score,
        (SELECT COUNT(*) FROM learner_progress lp
         JOIN teacher_activities ta ON ta.id = lp.activity_id AND ta.teacher_id = lp.teacher_id
         WHERE lp.student_id = s.id AND lp.teacher_id = ? AND ta.school_year_id = $syId) AS activity_count
     FROM students s
     WHERE $roster
     ORDER BY s.student_name ASC
     LIMIT 12"
);
if ($stmt) {
    $stmt->bind_param("ii", $teacher_id, $teacher_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $name = trim($row['student_name']);
        $parts = preg_split('/\s+/', $name, 2);
        $initials = strtoupper(
            substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1)
        );
        $stats['student_progress'][] = [
            'id'             => (int)$row['id'],
            'name'           => $name,
            'initials'       => $initials ?: '?',
            'condition'      => $row['disability_type'] ?: '',
            'last_score'     => $row['last_score'] !== null ? (int)$row['last_score'] : null,
            'activity_count' => (int)$row['activity_count'],
            'status'         => $row['status']
        ];
    }
    $stmt->close();
}

// Today's pending tasks (preview for dashboard panel)
$stmt = $conn->prepare(
    "SELECT id, task_text, is_done FROM teacher_tasks WHERE teacher_id=? ORDER BY is_done ASC, created_at DESC LIMIT 6"
);
if ($stmt) {
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $stats['today_tasks'][] = [
            'id'      => (int)$row['id'],
            'title'   => $row['task_text'],
            'is_done' => (int)$row['is_done']
        ];
    }
    $stmt->close();
}

echo json_encode($stats);
$conn->close();
