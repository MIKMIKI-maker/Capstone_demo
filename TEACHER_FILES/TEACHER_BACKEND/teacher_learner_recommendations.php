<?php
/**
 * The teacher's recommendation for each learner's next School Year
 * (My Learners): "continue" (stay), "move" (another class/teacher) or
 * "graduate". The Admin sees it — with the learner's progress — in the
 * Set as Active steps and makes the final choice there.
 * GET  → { recommendations: { <students.id>: { recommendation, note } } } for the S.Y. being viewed
 * POST student_id, recommendation ('' clears it), note → saves it for the Active S.Y.
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
$teacher_admin_id = teacherAdminIdFor($conn, $teacher_id);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $sy = getViewedSchoolYearId($conn);
    $recs = learnerRecommendations($conn, $sy);
    $roster = teacherRosterCondition($conn, $teacher_id, $sy, 's');
    $out = [];
    $res = $conn->query("SELECT s.id, s.admin_account_id FROM students s WHERE $roster");
    while ($res && ($row = $res->fetch_assoc())) {
        $sid = (int)$row['admin_account_id'];
        if ($sid && isset($recs[$sid])) {
            $out[(int)$row['id']] = ['recommendation' => $recs[$sid]['recommendation'], 'note' => $recs[$sid]['note']];
        }
    }
    echo json_encode(['success' => true, 'recommendations' => (object)$out]);
    exit;
}

// Saving is only for the Active S.Y. — a past one is read-only.
requireActiveSchoolYearView($conn);

$student_id = (int)($_POST['student_id'] ?? 0);
$recommendation = trim((string)($_POST['recommendation'] ?? ''));
$note = trim((string)($_POST['note'] ?? ''));
if ($recommendation !== '' && !in_array($recommendation, LEARNER_RECOMMENDATIONS, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid recommendation']);
    exit;
}
if (mb_strlen($note) > 500) $note = mb_substr($note, 0, 500);

// Only for a learner in this teacher's class this S.Y.
$sy = getActiveSchoolYearId($conn);
$roster = teacherRosterCondition($conn, $teacher_id, $sy, 's');
$stmt = $conn->prepare("SELECT s.admin_account_id FROM students s WHERE s.id = ? AND $roster");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$student_admin_id = (int)($row['admin_account_id'] ?? 0);
if (!$student_admin_id || !$teacher_admin_id) {
    echo json_encode(['success' => false, 'message' => 'This learner is not in your class this School Year.']);
    exit;
}

ensureLearnerRecommendationSchema($conn);
if ($recommendation === '') {
    $del = $conn->prepare("DELETE FROM learner_recommendations WHERE school_year_id = ? AND student_admin_id = ?");
    $del->bind_param("ii", $sy, $student_admin_id);
    $del->execute();
    $del->close();
} else {
    $up = $conn->prepare("INSERT INTO learner_recommendations (school_year_id, student_admin_id, recommendation, note, teacher_admin_id)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE recommendation = VALUES(recommendation), note = VALUES(note), teacher_admin_id = VALUES(teacher_admin_id)");
    $up->bind_param("iissi", $sy, $student_admin_id, $recommendation, $note, $teacher_admin_id);
    $up->execute();
    $up->close();
}
echo json_encode(['success' => true]);
