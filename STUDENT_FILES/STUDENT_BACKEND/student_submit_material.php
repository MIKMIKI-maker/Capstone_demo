<?php
// A student submits one file (photo, video, audio or document) for an
// 'activity' material their teacher uploaded. One request per file.
// POST multipart: material_id, file, caption (optional message to the teacher)
require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/material_helpers.php';
require_once __DIR__ . '/student_auth.php';
header('Content-Type: application/json');

$student_admin_id = requireStudentSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$maxVideoMb = MATERIAL_SUBMISSION_MAX_VIDEO_BYTES / 1048576;
$maxFileMb  = MATERIAL_SUBMISSION_MAX_FILE_BYTES / 1048576;
$tooBig     = 'Masyadong malaki ang file. Video: hanggang ' . $maxVideoMb . ' MB, ibang file: hanggang ' . $maxFileMb . ' MB.';

// Past post_max_size PHP silently drops the whole body ($_POST and $_FILES
// both come back empty), so that case has to be recognised on its own.
if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    echo json_encode(['success' => false, 'message' => $tooBig]);
    exit;
}

$material_id = isset($_POST['material_id']) ? (int)$_POST['material_id'] : 0;
$caption     = isset($_POST['caption']) ? trim((string)$_POST['caption']) : '';
$caption     = function_exists('mb_substr') ? mb_substr($caption, 0, 500) : substr($caption, 0, 500);

if (!$material_id) {
    echo json_encode(['success' => false, 'message' => 'Missing material']);
    exit;
}
$fileErr = isset($_FILES['file']) ? $_FILES['file']['error'] : UPLOAD_ERR_NO_FILE;
if ($fileErr === UPLOAD_ERR_INI_SIZE || $fileErr === UPLOAD_ERR_FORM_SIZE) {
    echo json_encode(['success' => false, 'message' => $tooBig]);
    exit;
}
if ($fileErr !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => $fileErr === UPLOAD_ERR_NO_FILE ? 'Pumili muna ng file.' : 'Hindi na-upload ang file. Subukan ulit.']);
    exit;
}

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
$student_id = (int)$rec['student_record_id'];
$teacher_id = (int)$rec['teacher_id'];

ensureMaterialSchema($conn);
$material = loadStudentMaterial($conn, $material_id, $student_id, $teacher_id);
if (!$material) {
    echo json_encode(['success' => false, 'message' => 'Material not found']);
    $conn->close();
    exit;
}
if ($material['material_kind'] !== 'activity') {
    echo json_encode(['success' => false, 'message' => 'This material does not take submissions.']);
    $conn->close();
    exit;
}

$cnt = $conn->prepare("SELECT COUNT(*) AS n FROM material_submissions WHERE material_id = ? AND student_id = ?");
$cnt->bind_param("ii", $material_id, $student_id);
$cnt->execute();
$already = (int)$cnt->get_result()->fetch_assoc()['n'];
$cnt->close();
if ($already >= MATERIAL_SUBMISSION_MAX_FILES) {
    echo json_encode(['success' => false, 'message' => 'Hanggang ' . MATERIAL_SUBMISSION_MAX_FILES . ' file lang ang puwedeng ipasa. Alisin muna ang isang file.']);
    $conn->close();
    exit;
}

// The real content decides the type, never the file name the browser sent.
$tmp  = $_FILES['file']['tmp_name'];
$mime = mime_content_type($tmp);
$allowed = materialSubmissionMimeExt();
if (!isset($allowed[$mime])) {
    echo json_encode(['success' => false, 'message' => 'Hindi puwede ang ganitong file. Puwede: larawan, video, audio, PDF, Word o PowerPoint.']);
    $conn->close();
    exit;
}
$size  = (int)$_FILES['file']['size'];
$limit = strpos($mime, 'video/') === 0 ? MATERIAL_SUBMISSION_MAX_VIDEO_BYTES : MATERIAL_SUBMISSION_MAX_FILE_BYTES;
if ($size > $limit) {
    echo json_encode(['success' => false, 'message' => $tooBig]);
    $conn->close();
    exit;
}

$origName = basename((string)$_FILES['file']['name']);
// Fits file_original_name VARCHAR(255) — some phones produce very long names.
$origName = function_exists('mb_substr') ? mb_substr($origName, -200) : substr($origName, -200);
$stored = saveMaterialSubmissionFile($tmp, $mime, $origName);
if ($stored === null) {
    echo json_encode(['success' => false, 'message' => 'Hindi na-save ang file. Subukan ulit.']);
    $conn->close();
    exit;
}

$ins = $conn->prepare("INSERT INTO material_submissions (material_id, teacher_id, student_id, file_name, file_original_name, file_type, file_size, caption)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$ins->bind_param("iiisssis", $material_id, $teacher_id, $student_id, $stored, $origName, $mime, $size, $caption);
if (!$ins->execute()) {
    $ins->close();
    $conn->close();
    deleteMaterialSubmissionFile($stored);
    echo json_encode(['success' => false, 'message' => 'Hindi na-save ang file. Subukan ulit.']);
    exit;
}
$newId = $ins->insert_id;
$ins->close();

$row = null;
$rq = $conn->prepare("SELECT id, file_name, file_original_name, file_type, file_size, caption, submitted_at FROM material_submissions WHERE id = ?");
if ($rq) { $rq->bind_param("i", $newId); $rq->execute(); $row = $rq->get_result()->fetch_assoc(); $rq->close(); }

// Let the teacher know — at most one notification per material per hour, so
// a student sending several photos in a row doesn't flood the bell.
require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/teacher_push_notification.php';
$studentName = trim((string)($rec['student_name'] ?? '')) ?: 'A student';
pushTeacherNotification(
    $conn,
    $teacher_id,
    'material_submission',
    '📥 New Submission',
    $studentName . ' submitted work for "' . $material['title'] . '".',
    ['student_name' => $studentName, 'activity_title' => $material['title'], 'material_id' => $material_id],
    'matsub_' . $material_id . '_' . gmdate('YmdH')
);
$conn->close();

echo json_encode(['success' => true, 'submission' => $row]);
