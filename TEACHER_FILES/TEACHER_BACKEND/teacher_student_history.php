<?php
/**
 * A learner's record across every School Year — which class and teacher
 * they had each year, and every activity assigned to them, whichever
 * teacher gave it. Lets a new teacher see what the learner did last year.
 * GET ?student_id=N (students.id)
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/teacher_auth.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/school_year.php';

header('Content-Type: application/json');

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$teacher_id = requireTeacherId();
$student_id = (int)($_GET['student_id'] ?? 0);
if (!$student_id || !teacherCanSeeStudent($conn, $teacher_id, $student_id)) {
    echo json_encode(['success' => false, 'message' => 'Student not found']);
    exit;
}

$activeId = getActiveSchoolYearId($conn);
$years = [];
$res = $conn->query("SELECT id, name FROM school_years ORDER BY start_date DESC");
while ($row = $res->fetch_assoc()) {
    $years[(int)$row['id']] = [
        'school_year_id' => (int)$row['id'],
        'name'           => $row['name'],
        'is_active'      => (int)$row['id'] === $activeId,
        'section'        => null,
        'teacher_name'   => null,
        'exit_reason'    => null,
        'activities'     => [],
    ];
}

// Class + teacher each year, from the enrollment record.
$stmt = $conn->prepare("SELECT ce.school_year_id, ce.exit_reason, c.section_name, CONCAT(a.first_name, ' ', a.last_name) AS teacher_name
    FROM students s
    INNER JOIN class_enrollments ce ON ce.student_admin_id = s.admin_account_id
    INNER JOIN classes c ON c.id = ce.class_id
    LEFT JOIN admin_accounts a ON a.id = c.teacher_admin_id
    WHERE s.id = ? AND s.admin_account_id > 0");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$r = $stmt->get_result();
while ($row = $r->fetch_assoc()) {
    $sy = (int)$row['school_year_id'];
    if (!isset($years[$sy])) continue;
    $years[$sy]['section'] = $row['section_name'];
    $years[$sy]['teacher_name'] = trim((string)$row['teacher_name']);
    $years[$sy]['exit_reason'] = $row['exit_reason'];
}
$stmt->close();

// Every assigned activity, with the latest score and the teacher's final assessment.
$stmt = $conn->prepare("SELECT ta.school_year_id, ta.activity_title, ta.activity_type, ta.subject,
        CONCAT(t.first_name, ' ', t.last_name) AS teacher_name,
        (SELECT lp.score FROM learner_progress lp WHERE lp.activity_id = ta.id AND lp.student_id = aa.student_id ORDER BY lp.id DESC LIMIT 1) AS score,
        (SELECT lp.assessment_date FROM learner_progress lp WHERE lp.activity_id = ta.id AND lp.student_id = aa.student_id ORDER BY lp.id DESC LIMIT 1) AS completed_on,
        sub.finalized_score, sub.is_finalized, sub.assistance_level
    FROM activity_assignments aa
    INNER JOIN teacher_activities ta ON ta.id = aa.activity_id
    LEFT JOIN teacher_accounts t ON t.id = ta.teacher_id
    LEFT JOIN activity_submissions sub ON sub.activity_id = ta.id AND sub.student_id = aa.student_id
    WHERE aa.student_id = ?
    ORDER BY ta.created_at DESC");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$r = $stmt->get_result();
while ($row = $r->fetch_assoc()) {
    $sy = (int)$row['school_year_id'];
    if (!isset($years[$sy])) continue;
    $final = (int)$row['is_finalized'] === 1 && $row['finalized_score'] !== null;
    $years[$sy]['activities'][] = [
        'title'            => $row['activity_title'],
        'type'             => $row['activity_type'],
        'subject'          => $row['subject'],
        'teacher_name'     => trim((string)$row['teacher_name']),
        'completed_on'     => $row['completed_on'],
        'score'            => $final ? (int)$row['finalized_score'] : ($row['score'] !== null ? (int)$row['score'] : null),
        'is_finalized'     => $final,
        'assistance_level' => $row['assistance_level'],
    ];
}
$stmt->close();
$conn->close();

// Summaries, and only the years the learner actually has something in.
$out = [];
foreach ($years as $y) {
    if (!$y['section'] && !$y['activities']) continue;
    $scores = array_filter(array_column($y['activities'], 'score'), function ($s) { return $s !== null; });
    $y['assigned']  = count($y['activities']);
    $y['completed'] = count(array_filter($y['activities'], function ($a) { return $a['completed_on'] !== null; }));
    $y['average']   = $scores ? (int)round(array_sum($scores) / count($scores)) : null;
    $out[] = $y;
}

echo json_encode(['success' => true, 'years' => $out]);
