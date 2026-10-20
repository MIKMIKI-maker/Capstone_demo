<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/teacher_auth.php';
require_once __DIR__ . '/student_level.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/school_year.php';
require_once __DIR__ . '/material_helpers.php';

header('Content-Type: application/json');

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$teacher_id = requireTeacherId();
// Activity-based stats only cover the School Year being viewed.
$syId = getViewedSchoolYearId($conn);
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
    'grade_level'          => '',
    'disability_type'      => '',
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
        'Self Help'     => 0,
        'Language Development' => 0,
        'Aesthetic & Creative'  => 0
    ],
    'notes'     => [],
    'iep_goals' => []
];

// ── Student info ─────────────────────────────────────────────────────────────
// Current learners, and past ones from this teacher's earlier classes.
if (!teacherCanSeeStudent($conn, $teacher_id, $student_id)) {
    echo json_encode(['success' => false, 'message' => 'Student not found (id=' . $student_id . ', teacher=' . $teacher_id . ')']);
    exit;
}
$stmt = safeQuery($conn,
    "SELECT student_name, grade_level, disability_type FROM students WHERE id = ?",
    "i", $student_id
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
$studentInfo = $res->fetch_assoc();
$progress['student_name'] = $studentInfo['student_name'];
$progress['grade_level'] = $studentInfo['grade_level'] ?? '';
$progress['disability_type'] = $studentInfo['disability_type'] ?? '';
$stmt->close();

// ── Total activities assigned to this student ─────────────────────────────────
$stmt = safeQuery($conn,
    "SELECT COUNT(*) as cnt
     FROM activity_assignments aa
     INNER JOIN teacher_activities ta ON ta.id = aa.activity_id
    WHERE aa.student_id = ? AND ta.teacher_id = ? AND ta.school_year_id = $syId",
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
     INNER JOIN teacher_activities ta ON ta.id = lp.activity_id
    WHERE lp.student_id = ? AND lp.teacher_id = ? AND ta.school_year_id = $syId",
    "ii", $student_id, $teacher_id
);
if ($stmt) {
    $statsRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $progress['activities_completed'] = (int)($statsRow['cnt'] ?? 0);
    $progress['average_score']        = !empty($statsRow['avg_score']) ? round(floatval($statsRow['avg_score'])) : 0;
}
// Manual activities (uploaded 'activity' materials) count too: given = one
// more to do, turned in = done, and a graded one adds its score.
$matRows = materialActivityRows($conn, $teacher_id, $syId, $student_id);
$matScores = [];
foreach ($matRows as $m) if ($m['graded']) $matScores[] = $m['score'];
if ($matRows) {
    $actDone = $progress['activities_completed'];
    $sum = $progress['average_score'] * $actDone + array_sum($matScores);
    $n = $actDone + count($matScores);
    $progress['total_activities']     += count($matRows);
    $progress['activities_completed'] += count(array_filter($matRows, function ($m) { return $m['submitted']; }));
    $progress['average_score']         = $n ? round($sum / $n) : 0;
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
         sub.assistance_level, sub.finalized_score, sub.is_finalized, sub.scaffold_used, sub.struggled_items_json, sub.retake_count, sub.attempt_history_json
     FROM activity_assignments aa
     INNER JOIN teacher_activities ta ON ta.id = aa.activity_id
    LEFT  JOIN learner_progress lp   ON ta.id = lp.activity_id AND lp.student_id = ?
     LEFT  JOIN activity_submissions sub ON sub.activity_id = ta.id AND sub.student_id = ? AND sub.teacher_id = ?
    WHERE aa.student_id = ? AND ta.teacher_id = ? AND ta.school_year_id = $syId
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
            'retake_count'    => (int)($row['retake_count'] ?? 0),
            'attempt_history' => $row['attempt_history_json'] ? (json_decode($row['attempt_history_json'], true) ?: []) : []
        ];
    }
    $stmt->close();
}

// Every manual activity (uploaded 'activity' material) in the history with
// the rest, newest first: graded = completed with its score, turned in =
// waiting for the teacher's grade, else not started yet.
foreach ($matRows as $m) {
    $sc = $m['score'];
    $status = $m['graded'] ? 'completed' : ($m['submitted'] ? 'to-grade' : 'not-started');
    $progress['activities'][] = [
        'id' => 'm' . $m['id'], 'name' => $m['title'], 'subject' => $m['domain'],
        'date' => $m['graded'] ? $m['date'] : ($m['submitted_at'] ?: null),
        'score' => $sc, 'score_color' => $sc === null ? '#CBD5E1' : ($sc >= 80 ? '#10b981' : ($sc >= 60 ? '#f59e0b' : ($sc > 0 ? '#ef4444' : '#CBD5E1'))),
        'status' => $status, 'assistance_level' => $m['assistance_level'], 'finalized_score' => $sc, 'is_finalized' => $m['graded'],
        'scaffold_used' => 0, 'struggled_items' => [], 'retake_count' => 0, 'attempt_history' => [], 'is_material' => true,
        'activity_type' => 'Manual Activity',
    ];
}
if ($matRows) {
    usort($progress['activities'], function ($a, $b) { return strcmp($b['date'] ?? '', $a['date'] ?? ''); });
    $progress['activities'] = array_slice($progress['activities'], 0, 15);
}

// ── Skills breakdown ──────────────────────────────────────────────────────────
$stmt = safeQuery($conn,
    "SELECT COALESCE(ta.subject,'Other') AS subject, SUM(lp.score) AS total, COUNT(*) AS n
     FROM learner_progress lp
     INNER JOIN teacher_activities ta ON lp.activity_id = ta.id
    WHERE lp.student_id = ? AND ta.school_year_id = $syId
     GROUP BY ta.subject",
    "i", $student_id
);
if ($stmt) {
    $skillMap = [
        'cognitive'     => 'Cognitive',
        'communication' => 'Communication',
        'motor'         => 'Fine Motor',
        'social'        => 'Social Skills',
        'self'          => 'Self Help',
        'language'      => 'Language Development',
        'aesthetic'     => 'Aesthetic & Creative'
    ];
    $skillSum = []; $skillN = [];
    $addSkill = function ($subject, $total, $n) use ($skillMap, &$skillSum, &$skillN) {
        $subj = strtolower((string)$subject);
        foreach ($skillMap as $key => $skillName) {
            if (strpos($subj, $key) !== false) {
                $skillSum[$skillName] = ($skillSum[$skillName] ?? 0) + $total;
                $skillN[$skillName]   = ($skillN[$skillName] ?? 0) + $n;
                break;
            }
        }
    };
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) $addSkill($row['subject'], (float)$row['total'], (int)$row['n']);
    foreach ($matRows as $m) if ($m['graded']) $addSkill($m['domain'], $m['score'], 1);
    foreach ($skillSum as $skillName => $sum) $progress['skills'][$skillName] = round($sum / $skillN[$skillName]);
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
// Two sources feed the notes panel: general notes typed via "+ Add Note",
// and per-activity comments left while finalizing a submission. Each is
// tagged with `source` (+ `activity_title` for the latter) so the UI can
// label them apart and target the right delete endpoint for each.
$notes = [];

$stmt = safeQuery($conn,
    "SELECT id, note, created_at FROM student_notes WHERE teacher_id = ? AND student_id = ? ORDER BY created_at DESC LIMIT 20",
    "ii", $teacher_id, $student_id
);
if ($stmt) {
    $r = $stmt->get_result();
    while ($n = $r->fetch_assoc()) {
        $notes[] = [
            'source'         => 'general',
            'id'             => (int)$n['id'],
            'note'           => $n['note'],
            'created_at'     => $n['created_at'],
            'activity_title' => null,
        ];
    }
    $stmt->close();
}

$stmt = safeQuery($conn,
    "SELECT sub.id AS submission_id, sub.teacher_note AS note, sub.finalized_at AS created_at, ta.activity_title, ta.subject
     FROM activity_submissions sub
     JOIN teacher_activities ta ON ta.id = sub.activity_id
     WHERE sub.student_id = ? AND sub.teacher_id = ? AND sub.is_finalized = 1 AND ta.school_year_id = $syId
       AND sub.teacher_note IS NOT NULL AND sub.teacher_note != ''
     ORDER BY sub.finalized_at DESC LIMIT 20",
    "ii", $student_id, $teacher_id
);
if ($stmt) {
    $r = $stmt->get_result();
    while ($n = $r->fetch_assoc()) {
        $notes[] = [
            'source'         => 'activity',
            'id'             => (int)$n['submission_id'],
            'note'           => $n['note'],
            'created_at'     => $n['created_at'],
            'activity_title' => $n['activity_title'],
            'subject'        => $n['subject'],
        ];
    }
    $stmt->close();
}

usort($notes, function ($a, $b) { return strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''); });
$progress['notes'] = $notes;

// The learner's current level for "🎲 Per student" activities (from their
// last few scores — see student_level.php).
$progress['learning_level'] = studentLevelInfo($conn, $student_id);

echo json_encode($progress);
$conn->close();
