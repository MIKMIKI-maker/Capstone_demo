<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/teacher_auth.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/school_year.php';
require_once __DIR__ . '/material_helpers.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$teacher_id = requireTeacherId();
if (!$teacher_id) {
    echo json_encode(['success' => false, 'message' => 'teacher_id required']);
    $conn->close();
    exit;
}

// The report covers the School Year being viewed: that year's class roster
// and only activities from that year.
$syId = getViewedSchoolYearId($conn);
$roster = teacherRosterCondition($conn, $teacher_id, $syId, 's');
$syActivities = "SELECT id FROM teacher_activities WHERE school_year_id = $syId";

$students     = [];
$total_scores = [];
$activities   = [];
$recent_log   = [];

try {

// Step 1: Get all active students (no learner_progress dependency)
$stmt = $conn->prepare("
    SELECT s.id AS student_id, s.student_name, s.grade_level, s.disability_type
    FROM students s
    WHERE $roster AND s.status = 'active'
    ORDER BY s.student_name ASC
");
if ($stmt) {
    $stmt->execute();
    $rows = $stmt->get_result();
    while ($r = $rows->fetch_assoc()) {
        $students[] = [
            'student_id'         => $r['student_id'],
            'student_name'       => $r['student_name'],
            'grade_level'        => $r['grade_level'],
            'disability_type'    => $r['disability_type'],
            'total_attempts'     => 0,
            'avg_score'          => 0,
            'highest_score'      => 0,
            'lowest_score'       => 0,
            'passed_count'       => 0,
            'last_activity_date' => null,
            'status'             => 'No Activity',
            'latest_assistance_level' => null,
            'high_support_count' => 0,
        ];
    }
    $stmt->close();
}

// Step 2: Get published activities (no learner_progress dependency)
$stmt2 = $conn->prepare("
    SELECT id, activity_title, activity_type, created_at
    FROM teacher_activities
    WHERE teacher_id = ? AND status = 'published' AND school_year_id = $syId
    ORDER BY created_at DESC
");
if ($stmt2) {
    $stmt2->bind_param("i", $teacher_id);
    $stmt2->execute();
    $act_rows = $stmt2->get_result();
    while ($ar = $act_rows->fetch_assoc()) {
        $activities[] = [
            'activity_id'    => (int)$ar['id'],
            'activity_title' => $ar['activity_title'],
            'activity_type'  => $ar['activity_type'],
            'submissions'    => 0,
            'avg_score'      => 0,
            'passed_count'   => 0,
            'created_at'     => $ar['created_at'],
        ];
    }
    $stmt2->close();
}

// Step 3: Enrich students and activities with progress data from learner_progress
// Use finalized_score from activity_submissions when the teacher has graded it,
// otherwise fall back to the raw learner_progress score.
$progStmt = $conn->prepare("
    SELECT lp.student_id, lp.activity_id,
           COALESCE(sub.finalized_score, lp.score) AS score
    FROM learner_progress lp
    LEFT JOIN activity_submissions sub
           ON sub.activity_id = lp.activity_id
          AND sub.student_id  = lp.student_id
          AND sub.teacher_id  = lp.teacher_id
          AND sub.is_finalized = 1
    WHERE lp.teacher_id = ? AND lp.activity_id IN ($syActivities)
");
$progressByStudent = [];
$progressByActivity = [];
if ($progStmt) {
    $progStmt->bind_param("i", $teacher_id);
    $progStmt->execute();
    $progRows = $progStmt->get_result();
    if ($progRows) {
        while ($pr = $progRows->fetch_assoc()) {
            $sid = (int)$pr['student_id'];
            $aid = (int)$pr['activity_id'];
            $sc  = (int)$pr['score'];
            if (!isset($progressByStudent[$sid])) $progressByStudent[$sid] = [];
            $progressByStudent[$sid][] = $sc;
            if (!isset($progressByActivity[$aid])) $progressByActivity[$aid] = [];
            $progressByActivity[$aid][] = $sc;
        }
    }
    $progStmt->close();
}

// Manual activities (uploaded 'activity' materials): a graded one counts
// like a finalized activity — in each learner's average, the activity list
// (one row per material title), skills (its Learning Domain), the trend and
// the recent log.
$matRows = materialActivityRows($conn, $teacher_id, $syId);
$matGroups = [];
foreach ($matRows as $m) {
    $key = $m['title'] . '|' . $m['domain'];
    if (!isset($matGroups[$key])) {
        $matGroups[$key] = ['activity_id' => 0, 'is_material' => true, 'activity_title' => $m['title'] . ' (Manual)', 'activity_type' => 'Manual Activity',
                            'submissions' => 0, 'avg_score' => 0, 'passed_count' => 0, 'created_at' => $m['uploaded_at'], 'scores' => []];
    }
    if ($m['graded']) {
        $matGroups[$key]['scores'][] = $m['score'];
        $progressByStudent[$m['student_id']][] = $m['score'];
    }
}
foreach ($matGroups as $g) {
    if ($g['scores']) {
        $g['submissions']  = count($g['scores']);
        $g['avg_score']    = round(array_sum($g['scores']) / count($g['scores']), 1);
        $g['passed_count'] = count(array_filter($g['scores'], function($sc){ return $sc >= 80; }));
    }
    unset($g['scores']);
    $activities[] = $g;
}

// Update student stats
foreach ($students as &$s) {
    $sid = $s['student_id'];
    if (!empty($progressByStudent[$sid])) {
        $scores = $progressByStudent[$sid];
        $avg = round(array_sum($scores) / count($scores), 1);
        $s['total_attempts']  = count($scores);
        $s['avg_score']       = $avg;
        $s['highest_score']   = max($scores);
        $s['lowest_score']    = min($scores);
        $s['passed_count']    = count(array_filter($scores, function($sc){ return $sc >= 80; }));
        $s['status']          = $avg >= 80 ? 'Passing' : ($avg >= 60 ? 'Needs Support' : 'At Risk');
        $total_scores[]       = $avg;
    }
}
unset($s);
// Update activity submission counts
foreach ($activities as &$a) {
    $aid = $a['activity_id'];
    if (!empty($progressByActivity[$aid])) {
        $scores = $progressByActivity[$aid];
        $a['submissions']  = count($scores);
        $a['avg_score']    = round(array_sum($scores) / count($scores), 1);
        $a['passed_count'] = count(array_filter($scores, function($sc){ return $sc >= 80; }));
    }
}
unset($a);

// Assistance-level history — per-student, so "Needs Attention" can factor in
// how much support a learner has needed lately, not just their score.
$assistStmt = $conn->prepare("
    SELECT student_id, assistance_level, finalized_at
    FROM activity_submissions
    WHERE teacher_id = ? AND is_finalized = 1 AND assistance_level IS NOT NULL AND activity_id IN ($syActivities)
    ORDER BY finalized_at ASC
");
if ($assistStmt) {
    $assistStmt->bind_param("i", $teacher_id);
    $assistStmt->execute();
    $assistRows = $assistStmt->get_result();
    $latestAssistByStudent = [];
    $highSupportCountByStudent = [];
    $highSupportLevels = ['Full Physical Prompt', 'Verbal Cueing'];
    while ($ar = $assistRows->fetch_assoc()) {
        $sid = (int)$ar['student_id'];
        $latestAssistByStudent[$sid] = $ar['assistance_level']; // ORDER BY ASC — last write is the most recent
        if (in_array($ar['assistance_level'], $highSupportLevels, true)) {
            $highSupportCountByStudent[$sid] = ($highSupportCountByStudent[$sid] ?? 0) + 1;
        }
    }
    foreach ($students as &$s) {
        $sid = $s['student_id'];
        $s['latest_assistance_level'] = $latestAssistByStudent[$sid] ?? null;
        $s['high_support_count']      = $highSupportCountByStudent[$sid] ?? 0;
    }
    unset($s);
    $assistStmt->close();
}

// Class overview
$class_avg = count($total_scores) > 0 ? round(array_sum($total_scores) / count($total_scores), 1) : 0;
$passing   = count(array_filter($students, function($s) { return $s['status'] === 'Passing'; }));
$at_risk   = count(array_filter($students, function($s) { return $s['status'] === 'At Risk'; }));
$no_act    = count(array_filter($students, function($s) { return $s['status'] === 'No Activity'; }));

// Skills breakdown by subject
$skills_breakdown = [
    'Cognitive'     => null,
    'Communication' => null,
    'Fine Motor'    => null,
    'Social Skills' => null,
    'Self Help'     => null,
    'Language Development' => null,
    'Aesthetic & Creative'  => null,
];
$skillStmt = $conn->prepare("
    SELECT COALESCE(ta.subject,'Other') AS subject,
           SUM(COALESCE(sub.finalized_score, lp.score)) AS total, COUNT(*) AS n
    FROM learner_progress lp
    LEFT JOIN teacher_activities ta ON ta.id = lp.activity_id
    LEFT JOIN activity_submissions sub
           ON sub.activity_id = lp.activity_id
          AND sub.student_id  = lp.student_id
          AND sub.teacher_id  = lp.teacher_id
          AND sub.is_finalized = 1
    WHERE lp.teacher_id = ? AND lp.activity_id IN ($syActivities)
    GROUP BY ta.subject
");
if ($skillStmt) {
    $skillStmt->bind_param("i", $teacher_id);
    $skillStmt->execute();
    $skillRows = $skillStmt->get_result();
    $skillMap = [
        'cognitive'     => 'Cognitive',
        'communication' => 'Communication',
        'motor'         => 'Fine Motor',
        'social'        => 'Social Skills',
        'self'          => 'Self Help',
        'language'      => 'Language Development',
        'aesthetic'     => 'Aesthetic & Creative',
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
    while ($sk = $skillRows->fetch_assoc()) $addSkill($sk['subject'], (float)$sk['total'], (int)$sk['n']);
    foreach ($matRows as $m) if ($m['graded']) $addSkill($m['domain'], $m['score'], 1);
    foreach ($skillSum as $skillName => $sum) $skills_breakdown[$skillName] = round($sum / $skillN[$skillName], 1);
    $skillStmt->close();
}

// Progress over time — class-wide average score per day, so a "Progress
// Over Time" trend line can show whether the class is improving, not just
// a single current snapshot.
$progress_over_time = [];
$potStmt = $conn->prepare("
    SELECT DATE(COALESCE(lp.assessment_date, lp.created_at)) AS day,
           SUM(COALESCE(sub.finalized_score, lp.score)) AS total, COUNT(*) AS n
    FROM learner_progress lp
    LEFT JOIN activity_submissions sub
           ON sub.activity_id = lp.activity_id
          AND sub.student_id  = lp.student_id
          AND sub.teacher_id  = lp.teacher_id
          AND sub.is_finalized = 1
    WHERE lp.teacher_id = ? AND lp.activity_id IN ($syActivities)
    GROUP BY day
    ORDER BY day ASC
");
if ($potStmt) {
    $potStmt->bind_param("i", $teacher_id);
    $potStmt->execute();
    $potRows = $potStmt->get_result();
    $daySum = []; $dayN = [];
    while ($pt = $potRows->fetch_assoc()) {
        $daySum[$pt['day']] = (float)$pt['total']; $dayN[$pt['day']] = (int)$pt['n'];
    }
    foreach ($matRows as $m) {
        if (!$m['graded'] || !$m['date']) continue;
        $day = substr($m['date'], 0, 10);
        $daySum[$day] = ($daySum[$day] ?? 0) + $m['score']; $dayN[$day] = ($dayN[$day] ?? 0) + 1;
    }
    ksort($daySum);
    foreach ($daySum as $day => $sum) $progress_over_time[] = ['date' => $day, 'avg_score' => round($sum / $dayN[$day], 1)];
    $potStmt->close();
}

// Recent activity log (last 30 days)
$stmt3 = $conn->prepare("
    SELECT lp.score, lp.assessment_date, lp.created_at,
           s.student_name, a.activity_title, a.activity_type
    FROM learner_progress lp
    JOIN students s          ON s.id = lp.student_id
    JOIN teacher_activities a ON a.id = lp.activity_id
    WHERE lp.teacher_id = ? AND lp.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND a.school_year_id = $syId
    ORDER BY lp.created_at DESC
    LIMIT 20
");
if ($stmt3) {
    $stmt3->bind_param("i", $teacher_id);
    $stmt3->execute();
    $log_rows = $stmt3->get_result();
    while ($lr = $log_rows->fetch_assoc()) {
        $recent_log[] = $lr;
    }
    $stmt3->close();
}
$namesById = [];
foreach ($students as $s) $namesById[(int)$s['student_id']] = $s['student_name'];
$cutoff = date('Y-m-d H:i:s', strtotime('-30 days'));
foreach ($matRows as $m) {
    if (!$m['graded'] || !$m['date'] || $m['date'] < $cutoff || !isset($namesById[$m['student_id']])) continue;
    $recent_log[] = ['score' => $m['score'], 'assessment_date' => $m['date'], 'created_at' => $m['date'],
                     'student_name' => $namesById[$m['student_id']], 'activity_title' => $m['title'], 'activity_type' => 'Manual Activity'];
}
usort($recent_log, function ($a, $b) { return strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''); });
$recent_log = array_slice($recent_log, 0, 20);
$conn->close();

echo json_encode([
    'success'      => true,
    'generated_at' => date('Y-m-d H:i:s'),
    'overview' => [
        'class_avg'       => $class_avg,
        'total_students'  => count($students),
        'passing'         => $passing,
        'at_risk'         => $at_risk,
        'no_activity'     => $no_act,
        'total_activities'=> count($activities),
    ],
    'students'         => $students,
    'activities'       => $activities,
    'recent_log'       => $recent_log,
    'skills_breakdown' => $skills_breakdown,
    'progress_over_time' => $progress_over_time,
]);

} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
