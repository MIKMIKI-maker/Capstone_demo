<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/teacher_check_pending_reminders.php';

header('Content-Type: application/json');

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$teacher_id = isset($_REQUEST['teacher_id']) ? intval($_REQUEST['teacher_id']) : 1;

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

// Basic counts
$countQueries = [
    ['assigned_learners',    "SELECT COUNT(*) as c FROM students WHERE teacher_id=?",                                         'i'],
    ['active_learners',      "SELECT COUNT(*) as c FROM students WHERE teacher_id=? AND status='active'",                    'i'],
    ['total_activities',     "SELECT COUNT(*) as c FROM teacher_activities WHERE teacher_id=?",                               'i'],
    ['draft_activities',     "SELECT COUNT(*) as c FROM teacher_activities WHERE teacher_id=? AND status='draft'",            'i'],
    ['published_activities', "SELECT COUNT(*) as c FROM teacher_activities WHERE teacher_id=? AND status='published'",        'i'],
];

foreach ($countQueries as $q) {
    list($key, $sql, $types) = $q;
    $stmt = $conn->prepare($sql);
    if (!$stmt) continue;
    $stmt->bind_param($types, $teacher_id);
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
     FROM teacher_activities WHERE teacher_id=? ORDER BY created_at DESC LIMIT 20"
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
        (SELECT lp.score FROM learner_progress lp WHERE lp.student_id = s.id AND lp.teacher_id = ?
         ORDER BY lp.created_at DESC LIMIT 1) AS last_score,
        (SELECT COUNT(*) FROM learner_progress lp WHERE lp.student_id = s.id AND lp.teacher_id = ?) AS activity_count
     FROM students s
     WHERE s.teacher_id = ?
     ORDER BY s.student_name ASC
     LIMIT 12"
);
if ($stmt) {
    $stmt->bind_param("iii", $teacher_id, $teacher_id, $teacher_id);
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
