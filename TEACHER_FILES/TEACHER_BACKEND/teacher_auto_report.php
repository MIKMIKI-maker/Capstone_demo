<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/teacher_auth.php';

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

$students     = [];
$total_scores = [];
$activities   = [];
$recent_log   = [];

try {

// Step 1: Get all active students (no learner_progress dependency)
$stmt = $conn->prepare("
    SELECT id AS student_id, student_name, grade_level, disability_type
    FROM students
    WHERE teacher_id = ? AND status = 'active'
    ORDER BY student_name ASC
");
if ($stmt) {
    $stmt->bind_param("i", $teacher_id);
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
        ];
    }
    $stmt->close();
}

// Step 2: Get published activities (no learner_progress dependency)
$stmt2 = $conn->prepare("
    SELECT id, activity_title, activity_type, created_at
    FROM teacher_activities
    WHERE teacher_id = ? AND status = 'published'
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
    WHERE lp.teacher_id = ?
");
if ($progStmt) {
    $progStmt->bind_param("i", $teacher_id);
    $progStmt->execute();
    $progRows = $progStmt->get_result();
    if ($progRows) {
        $progressByStudent = [];
        $progressByActivity = [];
        while ($pr = $progRows->fetch_assoc()) {
            $sid = (int)$pr['student_id'];
            $aid = (int)$pr['activity_id'];
            $sc  = (int)$pr['score'];
            if (!isset($progressByStudent[$sid])) $progressByStudent[$sid] = [];
            $progressByStudent[$sid][] = $sc;
            if (!isset($progressByActivity[$aid])) $progressByActivity[$aid] = [];
            $progressByActivity[$aid][] = $sc;
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
    }
    $progStmt->close();
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
];
$skillStmt = $conn->prepare("
    SELECT COALESCE(ta.subject,'Other') AS subject,
           ROUND(AVG(COALESCE(sub.finalized_score, lp.score)),1) AS avg_score
    FROM learner_progress lp
    LEFT JOIN teacher_activities ta ON ta.id = lp.activity_id
    LEFT JOIN activity_submissions sub
           ON sub.activity_id = lp.activity_id
          AND sub.student_id  = lp.student_id
          AND sub.teacher_id  = lp.teacher_id
          AND sub.is_finalized = 1
    WHERE lp.teacher_id = ?
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
    ];
    while ($sk = $skillRows->fetch_assoc()) {
        $subj = strtolower($sk['subject']);
        foreach ($skillMap as $key => $skillName) {
            if (strpos($subj, $key) !== false) {
                $skills_breakdown[$skillName] = (float)$sk['avg_score'];
                break;
            }
        }
    }
    $skillStmt->close();
}

// Recent activity log (last 30 days)
$stmt3 = $conn->prepare("
    SELECT lp.score, lp.assessment_date, lp.created_at,
           s.student_name, a.activity_title, a.activity_type
    FROM learner_progress lp
    JOIN students s          ON s.id = lp.student_id
    JOIN teacher_activities a ON a.id = lp.activity_id
    WHERE lp.teacher_id = ? AND lp.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
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
]);

} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
