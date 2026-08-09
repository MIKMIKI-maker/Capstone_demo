<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

session_start();
$teacher_id = isset($_REQUEST['teacher_id']) ? intval($_REQUEST['teacher_id'])
            : (isset($_SESSION['admin_id']) ? intval($_SESSION['admin_id']) : 1);
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

if (!$student_id) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required']);
    exit;
}

// Helper: safe prepare — returns false with a stored error instead of crashing
function safeQuery($conn, $sql, $types, ...$params) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

$progress = [
    'success'              => true,
    'student_name'         => '',
    'overall_progress'     => 0,
    'average_score'        => 0,
    'activities_completed' => 0,
    'total_activities'     => 0,
    'activity_scores'      => [0, 0, 0, 0, 0, 0],
    'activities'           => [],
    'skills'               => [
        'Cognitive'     => 0,
        'Communication' => 0,
        'Fine Motor'    => 0,
        'Social Skills' => 0,
        'Self Help'     => 0
    ],
    'notes'     => [],
    'iep_goals' => []
];

// ── Student info ─────────────────────────────────────────────────────────────
$stmt = safeQuery($conn,
    "SELECT student_name FROM students WHERE id = ? AND teacher_id = ?",
    "ii", $student_id, $teacher_id
);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'DB error: students query — ' . $conn->error]);
    exit;
}
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Student not found (id=' . $student_id . ', teacher=' . $teacher_id . ')']);
    exit;
}
$progress['student_name'] = $res->fetch_assoc()['student_name'];
$stmt->close();

// ── Total activities assigned to this student ─────────────────────────────────
$stmt = safeQuery($conn,
    "SELECT COUNT(*) as cnt
     FROM activity_assignments aa
     INNER JOIN teacher_activities ta ON ta.id = aa.activity_id
     WHERE aa.student_id = ? AND ta.teacher_id = ? AND ta.status = 'published'",
    "ii", $student_id, $teacher_id
);
if ($stmt) {
    $row = $stmt->get_result()->fetch_assoc();
    $progress['total_activities'] = (int)($row['cnt'] ?? 0);
    $stmt->close();
}

// ── Completed count + average score ──────────────────────────────────────────
$stmt = safeQuery($conn,
    "SELECT AVG(lp.score) AS avg_score, COUNT(*) AS cnt
     FROM learner_progress lp
     INNER JOIN activity_assignments aa ON aa.activity_id = lp.activity_id
                                       AND aa.student_id  = lp.student_id
     WHERE lp.student_id = ? AND lp.teacher_id = ?",
    "ii", $student_id, $teacher_id
);
if ($stmt) {
    $statsRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $progress['activities_completed'] = (int)($statsRow['cnt'] ?? 0);
    $progress['average_score']        = !empty($statsRow['avg_score']) ? round(floatval($statsRow['avg_score'])) : 0;
}
$progress['overall_progress'] = $progress['total_activities'] > 0
    ? round(($progress['activities_completed'] / $progress['total_activities']) * 100)
    : 0;

// ── Weekly bar chart scores ───────────────────────────────────────────────────
$stmt = safeQuery($conn,
    "SELECT WEEK(lp.assessment_date) as wk, AVG(lp.score) as avg_score
     FROM learner_progress lp
     WHERE lp.student_id = ? AND lp.assessment_date >= DATE_SUB(NOW(), INTERVAL 6 WEEK)
     GROUP BY WEEK(lp.assessment_date) ORDER BY wk DESC LIMIT 6",
    "i", $student_id
);
if ($stmt) {
    $weekScores = [];
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) { $weekScores[] = round($row['avg_score']); }
    $stmt->close();
    $weekScores = array_reverse($weekScores);
    while (count($weekScores) < 6) { array_unshift($weekScores, 0); }
    $progress['activity_scores'] = array_slice($weekScores, -6);
}

// ── Activity history — only assigned activities ───────────────────────────────
$stmt = safeQuery($conn,
    "SELECT ta.id, ta.activity_title, ta.subject, lp.score, lp.assessment_date,
         CASE WHEN lp.assessment_date IS NOT NULL THEN 'completed' ELSE 'not-started' END AS status,
         sub.assistance_level, sub.finalized_score, sub.is_finalized, sub.scaffold_used, sub.struggled_items_json, sub.retake_count
     FROM activity_assignments aa
     INNER JOIN teacher_activities ta ON ta.id = aa.activity_id
     LEFT  JOIN learner_progress lp   ON ta.id = lp.activity_id AND lp.student_id = ?
     LEFT  JOIN activity_submissions sub ON sub.activity_id = ta.id AND sub.student_id = ? AND sub.teacher_id = ?
     WHERE aa.student_id = ? AND ta.teacher_id = ?
     ORDER BY COALESCE(lp.assessment_date, ta.created_at) DESC
     LIMIT 15",
    "iiiii", $student_id, $student_id, $teacher_id, $student_id, $teacher_id
);
if ($stmt) {
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) {
        $score = $row['score'];
        $col   = '#CBD5E1';
        if ($score !== null && $score >= 80) $col = '#10b981';
        elseif ($score !== null && $score >= 60) $col = '#f59e0b';
        elseif ($score !== null && $score >  0)  $col = '#ef4444';
        $progress['activities'][] = [
            'id'              => $row['id'],
            'name'            => $row['activity_title'],
            'subject'         => $row['subject'] ?? '',
            'date'            => $row['assessment_date'] ?: null,
            'score'           => $score !== null ? (int)$score : null,
            'score_color'     => $col,
            'status'          => $row['status'],
            'assistance_level'=> $row['assistance_level'] ?? null,
            'finalized_score' => $row['finalized_score'] !== null ? (int)$row['finalized_score'] : null,
            'is_finalized'    => (bool)($row['is_finalized'] ?? false),
            'scaffold_used'   => (int)($row['scaffold_used'] ?? 0),
            'struggled_items' => $row['struggled_items_json'] ? json_decode($row['struggled_items_json'], true) : [],
            'retake_count'    => (int)($row['retake_count'] ?? 0)
        ];
    }
    $stmt->close();
}

// ── Skills breakdown ──────────────────────────────────────────────────────────
$stmt = safeQuery($conn,
    "SELECT COALESCE(ta.subject,'Other') AS subject, AVG(lp.score) AS avg_score
     FROM learner_progress lp
     LEFT JOIN teacher_activities ta ON lp.activity_id = ta.id
     WHERE lp.student_id = ?
     GROUP BY ta.subject",
    "i", $student_id
);
if ($stmt) {
    $skillMap = [
        'cognitive'     => 'Cognitive',
        'communication' => 'Communication',
        'motor'         => 'Fine Motor',
        'social'        => 'Social Skills',
        'self'          => 'Self Help'
    ];
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) {
        $subj = strtolower($row['subject']);
        foreach ($skillMap as $key => $skillName) {
            if (strpos($subj, $key) !== false) {
                $progress['skills'][$skillName] = round($row['avg_score']);
                break;
            }
        }
    }
    $stmt->close();
}

// ── IEP goals ────────────────────────────────────────────────────────────────
$stmt = safeQuery($conn,
    "SELECT iep_goal FROM iep_materials WHERE student_id = ? AND teacher_id = ?",
    "ii", $student_id, $teacher_id
);
if ($stmt) {
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) { $progress['iep_goals'][] = $row['iep_goal']; }
    $stmt->close();
}

// ── Teacher notes ─────────────────────────────────────────────────────────────
$stmt = safeQuery($conn,
    "SELECT id, note, created_at FROM student_notes WHERE teacher_id = ? AND student_id = ? ORDER BY created_at DESC LIMIT 20",
    "ii", $teacher_id, $student_id
);
if ($stmt) {
    $r = $stmt->get_result();
    while ($n = $r->fetch_assoc()) { $progress['notes'][] = $n; }
    $stmt->close();
}

echo json_encode($progress);
$conn->close();
