<?php
// Grades one student's work on an 'activity' uploaded material — the same
// grading as activities (teacher_finalize_submission.php): assistance
// level, a note, and a final score, saved once and then locked.
// POST material_id, assistance_level, teacher_note, final_score (0–100)
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/teacher_auth.php';
require_once __DIR__ . '/material_helpers.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/school_year.php';
require_once __DIR__ . '/../../MAILER/notify_parent.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}
requireActiveSchoolYearView($conn);
ensureMaterialSchema($conn);

$teacher_id  = requireTeacherId();
$material_id = (int)($_POST['material_id'] ?? 0);
$assistance  = trim($_POST['assistance_level'] ?? '');
$note        = trim($_POST['teacher_note'] ?? '');
$scoreRaw    = trim((string)($_POST['final_score'] ?? ''));

$LEVELS = ['Full Physical Prompt', 'Verbal Cueing', 'Minimal Assistance', 'Independent'];
if ($assistance !== '' && !in_array($assistance, $LEVELS, true)) $assistance = '';
if ($scoreRaw === '' || !ctype_digit($scoreRaw) || (int)$scoreRaw > 100) {
    echo json_encode(['success' => false, 'message' => 'Enter a final score from 0 to 100.']);
    exit;
}
$score = (int)$scoreRaw;
if (mb_strlen($note) > 2000) $note = mb_substr($note, 0, 2000);

$q = $conn->prepare("SELECT m.id, m.student_id, m.title, m.material_kind, m.is_graded, s.student_name, s.parent_name, s.parent_email
    FROM teacher_uploaded_materials m LEFT JOIN students s ON s.id = m.student_id
    WHERE m.id = ? AND m.teacher_id = ?");
$q->bind_param("ii", $material_id, $teacher_id);
$q->execute();
$m = $q->get_result()->fetch_assoc();
$q->close();
if (!$m || $m['material_kind'] !== 'activity') {
    echo json_encode(['success' => false, 'message' => 'Material not found']);
    exit;
}
if (!empty($m['is_graded'])) {
    echo json_encode(['success' => false, 'message' => 'This work was already graded.']);
    exit;
}
if (!listMaterialSubmissions($conn, $material_id, (int)$m['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'The student has not submitted anything yet.']);
    exit;
}

// is_graded = 0 in the WHERE keeps a double-click from grading twice.
$u = $conn->prepare("UPDATE teacher_uploaded_materials
    SET assistance_level = ?, teacher_note = ?, final_score = ?, is_graded = 1, graded_at = NOW()
    WHERE id = ? AND teacher_id = ? AND is_graded = 0");
$u->bind_param("ssiii", $assistance, $note, $score, $material_id, $teacher_id);
$u->execute();
$ok = $u->affected_rows > 0;
$u->close();
if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'This work was already graded.']);
    exit;
}

$title = 'Activity graded';
$msg   = 'Your teacher graded "' . $m['title'] . '" — score: ' . $score . '.';
$n = $conn->prepare("INSERT INTO student_notifications (teacher_id, student_id, title, message, notification_type) VALUES (?, ?, ?, ?, 'graded')");
if ($n) { $sid = (int)$m['student_id']; $n->bind_param("iiss", $teacher_id, $sid, $title, $msg); $n->execute(); $n->close(); }
notifyParentByEmail($m['parent_email'], $m['parent_name'], $m['student_name'], $title, $msg);

echo json_encode(['success' => true]);
