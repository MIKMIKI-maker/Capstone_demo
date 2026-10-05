<?php
require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
require_once __DIR__ . '/student_auth.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/school_year.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$student_admin_id = requireStudentSession();

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$rec = resolveStudentRecord($conn, $student_admin_id);
if (!$rec) {
    echo json_encode(['success' => false, 'message' => 'Not enrolled']);
    $conn->close();
    exit;
}
$student_record_id = (int)$rec['student_record_id'];
$teacher_id         = (int)$rec['teacher_id'];
// Students only ever see the Active School Year.
$syId = getActiveSchoolYearId($conn);
// Overall stats — only from activities actually assigned to this student
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total_submitted,
           AVG(lp.score) AS avg_score
    FROM learner_progress lp
    INNER JOIN activity_assignments aa ON aa.activity_id = lp.activity_id
                                      AND aa.student_id  = lp.student_id
    INNER JOIN teacher_activities ta ON ta.id = lp.activity_id
    WHERE lp.teacher_id = ? AND lp.student_id = ? AND ta.school_year_id = $syId
");
$stmt->bind_param("ii", $teacher_id, $student_record_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Total activities assigned to THIS student only (denominator)
$stmt2 = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM activity_assignments aa
    INNER JOIN teacher_activities ta ON ta.id = aa.activity_id
    WHERE aa.student_id = ? AND ta.teacher_id = ? AND ta.school_year_id = $syId
");
$stmt2->bind_param("ii", $student_record_id, $teacher_id);
$stmt2->execute();
$total_row = $stmt2->get_result()->fetch_assoc();
$stmt2->close();

// Activity performance list (with finalized score + retake count)
$stmt3 = $conn->prepare("
    SELECT a.id AS activity_id, a.activity_title, a.activity_type, a.subject, a.content_json, lp.score, lp.assessment_date,
           CASE WHEN sub.is_finalized = 1 THEN 'Completed' WHEN lp.score >= 80 THEN 'Completed' ELSE 'In Progress' END AS status_label,
           sub.retake_count, sub.finalized_score, sub.is_finalized, sub.assistance_level, sub.answers_json, sub.struggled_items_json
    FROM learner_progress lp
    JOIN teacher_activities a ON a.id = lp.activity_id
    LEFT JOIN activity_submissions sub ON sub.student_id = lp.student_id AND sub.activity_id = lp.activity_id AND sub.teacher_id = lp.teacher_id
    WHERE lp.teacher_id = ? AND lp.student_id = ? AND a.school_year_id = $syId
    ORDER BY lp.assessment_date DESC
");
$stmt3->bind_param("ii", $teacher_id, $student_record_id);
$stmt3->execute();
$perf_result = $stmt3->get_result();

// Per-template "answer mode" labels (e.g. Matching's Pair Matching vs Memory
// Match) — these live inside each activity's own content_json (the builder's
// saved slide state), not as a DB column, since a teacher picks the mode per
// slide inside the template itself.
$modeLabelsByType = [
    'Matching'        => ['pair' => 'Pair Matching', 'memory' => 'Memory Match', 'grid' => 'Grid Match'],
    'Sorting'         => ['bins' => 'Sort into Bins', 'oddoneout' => 'Odd One Out', 'yesno' => 'Yes/No Sort'],
    'MathMixed'       => ['mc' => 'Multiple Choice', 'tenframe' => 'Ten-Frame Count', 'order' => 'Put in Order', 'equation' => 'Solve the Equation'],
    'WrittenResponse' => ['written' => 'Written Response', 'scramble' => 'Word Scramble', 'sentence' => 'Build the Sentence'],
    'PictureLabeling' => ['grid' => 'Label Each Picture', 'diagram' => 'Label the Picture', 'type' => 'Type the Label'],
    'Tracing'         => ['letters' => 'Letter & Number Tracing', 'shapes' => 'Shapes & Lines (print-only)'],
];

$activities = [];
while ($r = $perf_result->fetch_assoc()) {
    $modeLabel = null;
    if (!empty($r['content_json'])) {
        $cj = json_decode($r['content_json'], true);
        $firstMode = $cj['activities'][0]['mode'] ?? null;
        if ($firstMode && isset($modeLabelsByType[$r['activity_type']][$firstMode])) {
            $modeLabel = $modeLabelsByType[$r['activity_type']][$firstMode];
        }
    }
    unset($r['content_json']); // never send the raw builder state (has base64 thumbnails) to the client
    $r['mode_label'] = $modeLabel;
    $r['struggled_items'] = $r['struggled_items_json'] ? json_decode($r['struggled_items_json'], true) : [];
    unset($r['struggled_items_json']);
    $activities[] = $r;
}
$stmt3->close();

// General teacher notes (student_notes table) — note_type/note_id let the
// student portal target the right row (and the right delete query) when
// removing one, since these and the activity-linked notes below live in
// two completely different tables.
$stmt4 = $conn->prepare("SELECT id AS note_id, 'general' AS note_type, note, created_at, NULL AS activity_title FROM student_notes WHERE teacher_id = ? AND student_id = ? ORDER BY created_at DESC LIMIT 20");
$stmt4->bind_param("ii", $teacher_id, $student_record_id);
$stmt4->execute();
$notes_result = $stmt4->get_result();
$notes = [];
while ($n = $notes_result->fetch_assoc()) $notes[] = $n;
$stmt4->close();

// Activity-specific notes from finalized submissions
$stmt5 = $conn->prepare("
    SELECT sub.id AS note_id, 'activity' AS note_type, sub.teacher_note AS note, sub.finalized_at AS created_at, ta.activity_title, ta.subject
    FROM activity_submissions sub
    JOIN teacher_activities ta ON ta.id = sub.activity_id
    WHERE sub.student_id = ? AND sub.teacher_id = ? AND sub.is_finalized = 1 AND sub.teacher_note IS NOT NULL AND sub.teacher_note != ''
    ORDER BY sub.finalized_at DESC
    LIMIT 20
");
$stmt5->bind_param("ii", $student_record_id, $teacher_id);
$stmt5->execute();
$act_notes_result = $stmt5->get_result();
while ($n = $act_notes_result->fetch_assoc()) $notes[] = $n;
$stmt5->close();

// Sort all notes by created_at descending
usort($notes, function($a, $b) {
    return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
});

$conn->close();

$avg_score        = $stats['avg_score'] ? round(floatval($stats['avg_score'])) : 0;
$total_assigned   = intval($total_row['total']);      // activities assigned to this student
$total_completed  = intval($stats['total_submitted']); // of those, how many the student submitted

// Skills breakdown — average score per Learning Domain, keyed off each
// activity's own `subject` (set by the teacher at publish time). Reuses
// $activities (already fetched above) instead of a separate query, and
// prefers the finalized score the same way the rest of this endpoint does.
$skillMap = [
    'cognitive'     => 'Cognitive',
    'communication' => 'Communication',
    'motor'         => 'Fine Motor',
    'social'        => 'Social Skills',
    'self'          => 'Self Help',
    'language'      => 'Language Development',
    'aesthetic'     => 'Aesthetic & Creative'
];
$skillTotals = [];
$skillCounts = [];
foreach ($activities as $act) {
    $subj = strtolower($act['subject'] ?? '');
    foreach ($skillMap as $key => $skillName) {
        if (strpos($subj, $key) !== false) {
            $sc = ($act['is_finalized'] && $act['finalized_score'] !== null) ? $act['finalized_score'] : $act['score'];
            if ($sc !== null) {
                $skillTotals[$skillName] = ($skillTotals[$skillName] ?? 0) + floatval($sc);
                $skillCounts[$skillName] = ($skillCounts[$skillName] ?? 0) + 1;
            }
            break;
        }
    }
}
$skills = [];
foreach ($skillMap as $skillName) {
    $skills[$skillName] = isset($skillCounts[$skillName]) ? round($skillTotals[$skillName] / $skillCounts[$skillName]) : 0;
}

echo json_encode([
    'success'          => true,
    'avg_score'        => $avg_score,
    'completed'        => $total_completed,
    'total_activities' => $total_assigned,
    'activities'       => $activities,
    'notes'            => $notes,
    'skills'           => $skills
]);
?>
