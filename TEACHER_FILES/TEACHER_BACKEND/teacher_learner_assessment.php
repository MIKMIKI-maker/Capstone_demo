<?php
/**
 * End-of-year system assessment of the teacher's learners
 * (see learnerAssessments in school_year.php).
 * GET  [student_id]                → { assessments: { <students.id>: {...} }, rules } for the S.Y. being viewed
 * POST action=start,  student_id   → start the one reassessment of this S.Y.
 *                                     (keeps the initial result; lists what to redo)
 * POST action=finish, student_id   → record the result after the reassessment
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/db.php';
require_once __DIR__ . '/teacher_auth.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/school_year.php';

header('Content-Type: application/json');

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}
getDatabaseConnection();

$teacher_id = requireTeacherId();
$rules = [
    'pass_score' => ASSESS_PASS_SCORE,
    'pass_completion' => ASSESS_PASS_COMPLETION,
    'pass_average' => ASSESS_PASS_AVERAGE,
    'reassess_average' => ASSESS_REASSESS_AVERAGE,
];

// The teacher's learners in a S.Y. (students.id list).
function rosterIds(mysqli $conn, int $teacher_id, int $sy): array {
    $roster = teacherRosterCondition($conn, $teacher_id, $sy, 's');
    $ids = [];
    $res = $conn->query("SELECT s.id FROM students s WHERE $roster");
    while ($res && ($r = $res->fetch_assoc())) $ids[] = (int)$r['id'];
    return $ids;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $sy = getViewedSchoolYearId($conn);
    $ids = rosterIds($conn, $teacher_id, $sy);
    $one = (int)($_GET['student_id'] ?? 0);
    if ($one) {
        // A learner's progress page: theirs only, if the teacher can see them.
        if (!teacherCanSeeStudent($conn, $teacher_id, $one)) {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            exit;
        }
        $ids = [$one];
    }
    $data = $ids ? learnerAssessments($conn, $sy, $ids) : [];
    echo json_encode(['success' => true, 'school_year_id' => $sy, 'is_active' => $sy === getActiveSchoolYearId($conn),
        'assessments' => (object)$data, 'rules' => $rules]);
    exit;
}

// Changes are only for the Active S.Y. — a past one is read-only.
requireActiveSchoolYearView($conn);
$sy = getActiveSchoolYearId($conn);
$action = (string)($_POST['action'] ?? '');
$student_id = (int)($_POST['student_id'] ?? 0);
if (!in_array($student_id, rosterIds($conn, $teacher_id, $sy), true)) {
    echo json_encode(['success' => false, 'message' => 'This learner is not in your class this School Year.']);
    exit;
}
$a = learnerAssessments($conn, $sy, [$student_id])[$student_id];
$teacher_admin_id = teacherAdminIdFor($conn, $teacher_id);

if ($action === 'start') {
    if ($a['stage'] !== 'initial') {
        echo json_encode(['success' => false, 'message' => 'This learner already had a reassessment this School Year.']);
        exit;
    }
    if (!in_array($a['now']['result'], ['reassess', 'practice'], true)) {
        echo json_encode(['success' => false, 'message' => $a['now']['result'] === 'passed'
            ? 'This learner already passed — no reassessment needed.'
            : 'There is nothing scored yet to reassess.']);
        exit;
    }
    $initial = $a['now'];
    $items = $initial['redo'];
    unset($initial['redo'], $initial['result']);
    $stmt = $conn->prepare("INSERT INTO learner_assessments (school_year_id, student_id, initial_result, initial_json, reassess_items, reassess_started_at, teacher_admin_id)
        VALUES (?, ?, ?, ?, ?, NOW(), ?)");
    $initialJson = json_encode($initial);
    $itemsJson = json_encode($items);
    $stmt->bind_param("iisssi", $sy, $student_id, $a['now']['result'], $initialJson, $itemsJson, $teacher_admin_id);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => $ok, 'message' => $ok ? 'Reassessment started.' : 'Could not start the reassessment.']);
    exit;
}

if ($action === 'finish') {
    if ($a['stage'] !== 'reassess') {
        echo json_encode(['success' => false, 'message' => 'There is no reassessment in progress for this learner.']);
        exit;
    }
    $final = $a['now'];
    $finalResult = $final['result'];
    unset($final['result']);
    $stmt = $conn->prepare("UPDATE learner_assessments SET final_result = ?, final_json = ?, finished_at = NOW(), teacher_admin_id = ?
        WHERE school_year_id = ? AND student_id = ?");
    $finalJson = json_encode($final);
    $stmt->bind_param("ssiii", $finalResult, $finalJson, $teacher_admin_id, $sy, $student_id);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => $ok, 'result' => $finalResult, 'message' => $ok ? 'Reassessment finished.' : 'Could not finish the reassessment.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
