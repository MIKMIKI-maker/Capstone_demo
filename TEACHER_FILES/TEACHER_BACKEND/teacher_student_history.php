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

// Uploaded "Activity" materials (work the learner hands in as a file),
// counted the same way once the teacher grades them. Older databases
// without the materials' school_year_id column simply skip this.
$col = $conn->query("SHOW COLUMNS FROM teacher_uploaded_materials LIKE 'school_year_id'");
if ($col && $col->num_rows > 0) {
    $stmt = $conn->prepare("SELECT m.school_year_id, m.title, m.domain, m.final_score, m.is_graded, m.assistance_level,
            DATE(COALESCE(m.graded_at, (SELECT MAX(ms.submitted_at) FROM material_submissions ms WHERE ms.material_id = m.id AND ms.student_id = m.student_id))) AS completed_on,
            CONCAT(t.first_name, ' ', t.last_name) AS teacher_name
        FROM teacher_uploaded_materials m
        LEFT JOIN teacher_accounts t ON t.id = m.teacher_id
        WHERE m.student_id = ? AND m.material_kind = 'activity'
        ORDER BY m.uploaded_at DESC");
    if ($stmt) {
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) {
            $sy = (int)$row['school_year_id'];
            if (!isset($years[$sy])) continue;
            $graded = (int)$row['is_graded'] === 1 && $row['final_score'] !== null;
            $years[$sy]['activities'][] = [
                'title'            => $row['title'],
                'type'             => 'Upload',
                'subject'          => $row['domain'],
                'teacher_name'     => trim((string)$row['teacher_name']),
                'completed_on'     => $row['completed_on'],
                'score'            => $graded ? (int)$row['final_score'] : null,
                'is_finalized'     => $graded,
                'assistance_level' => $row['assistance_level'],
                'is_upload'        => true,
            ];
        }
        $stmt->close();
    }
}
// Newest first, online and uploaded activities together.
foreach ($years as &$y) {
    usort($y['activities'], function ($a, $b) { return strcmp((string)$b['completed_on'], (string)$a['completed_on']); });
}
unset($y);

// Summaries, and only the years the learner actually has something in.
$out = [];
foreach ($years as $y) {
    if (!$y['section'] && !$y['activities']) continue;
    $scores = array_filter(array_column($y['activities'], 'score'), function ($s) { return $s !== null; });
    $y['assigned']  = count($y['activities']);
    $y['completed'] = count(array_filter($y['activities'], function ($a) { return $a['completed_on'] !== null; }));
    $y['average']   = $scores ? (int)round(array_sum($scores) / count($scores)) : null;
    // The system's end-of-year assessment for that S.Y. (final result if reassessed).
    $as = learnerAssessments($conn, (int)$y['school_year_id'], [$student_id])[$student_id] ?? null;
    $y['assessment'] = $as ? ['result' => $as['result'], 'stage' => $as['stage']] : null;
    $out[] = $y;
}

$conn->close();
echo json_encode(['success' => true, 'years' => $out]);
