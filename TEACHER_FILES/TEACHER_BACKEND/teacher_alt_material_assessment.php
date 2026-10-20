<?php
/**
 * Learning Modality Assessment that unlocks the Additional Alternative
 * Learning Material (Video Learning / Audio Learning) — see
 * alt_material_helpers.php.
 * GET                                   → { assessments: { <students.id>: {...} } } for the teacher's learners, Active S.Y.
 * POST action=save, student_id, answers (JSON), notes → saves (or retakes) the learner's assessment
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/db.php';
require_once __DIR__ . '/teacher_auth.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/school_year.php';
require_once __DIR__ . '/alt_material_helpers.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}
getDatabaseConnection();

$teacher_id = requireTeacherId();
$sy = getActiveSchoolYearId($conn);

function altRosterIds(mysqli $conn, int $teacher_id, int $sy): array {
    $roster = teacherRosterCondition($conn, $teacher_id, $sy, 's');
    $ids = [];
    $res = $conn->query("SELECT s.id FROM students s WHERE $roster");
    while ($res && ($r = $res->fetch_assoc())) $ids[] = (int)$r['id'];
    return $ids;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $data = altMaterialAssessments($conn, $sy, altRosterIds($conn, $teacher_id, $sy));
    echo json_encode(['success' => true, 'school_year_id' => $sy, 'assessments' => (object)$data]);
    exit;
}

requireActiveSchoolYearView($conn);
$action = (string)($_POST['action'] ?? '');
if ($action !== 'save') {
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

$student_id = (int)($_POST['student_id'] ?? 0);
if (!in_array($student_id, altRosterIds($conn, $teacher_id, $sy), true)) {
    echo json_encode(['success' => false, 'message' => 'This learner is not in your class this School Year.']);
    exit;
}
$answers = json_decode((string)($_POST['answers'] ?? ''), true);
$scored = is_array($answers) ? scoreAltAssessment($answers) : null;
if (!$scored) {
    echo json_encode(['success' => false, 'message' => 'Please answer every question.']);
    exit;
}
$notes = mb_substr(trim((string)($_POST['notes'] ?? '')), 0, 500);

ensureAltMaterialTable($conn);
$stmt = $conn->prepare("INSERT INTO alt_material_assessments
        (school_year_id, student_id, answers_json, visual_pct, auditory_pct, readiness_pct, video_allowed, audio_allowed, recommended, notes, teacher_id)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE answers_json = VALUES(answers_json), visual_pct = VALUES(visual_pct),
        auditory_pct = VALUES(auditory_pct), readiness_pct = VALUES(readiness_pct),
        video_allowed = VALUES(video_allowed), audio_allowed = VALUES(audio_allowed),
        recommended = VALUES(recommended), notes = VALUES(notes), teacher_id = VALUES(teacher_id),
        assessed_at = CURRENT_TIMESTAMP");
$answersJson = json_encode($scored['answers']);
$video = $scored['video_allowed'] ? 1 : 0;
$audio = $scored['audio_allowed'] ? 1 : 0;
$stmt->bind_param("iisiiiiissi", $sy, $student_id, $answersJson, $scored['visual_pct'], $scored['auditory_pct'],
    $scored['readiness_pct'], $video, $audio, $scored['recommended'], $notes, $teacher_id);
$ok = $stmt->execute();
$stmt->close();

echo json_encode([
    'success' => $ok,
    'message' => $ok ? 'Assessment saved.' : 'Could not save the assessment.',
    'assessment' => $ok ? (altMaterialAssessments($conn, $sy, [$student_id])[$student_id] ?? null) : null,
]);
