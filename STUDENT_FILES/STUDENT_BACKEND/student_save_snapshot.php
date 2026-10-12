<?php
// Saves one picture of an activity taken right after the student tapped
// "Check answers" (see ASSETS/attempt_history.js). Only for the logged-in
// student's own runs; JPEG only, checked by content.
require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/snapshot_helpers.php';
require_once __DIR__ . '/student_auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$student_admin_id = requireStudentSession();
$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}
$rec = resolveStudentRecord($conn, $student_admin_id);
if (!$rec) {
    echo json_encode(['success' => false, 'message' => 'Not enrolled']);
    exit;
}
$student_id = (int)$rec['student_record_id'];

$run     = isset($_POST['run_id']) ? (string)$_POST['run_id'] : '';
$slide   = isset($_POST['slide'])   ? (int)$_POST['slide']   : 0;
$attempt = isset($_POST['attempt']) ? (int)$_POST['attempt'] : -1;
$image   = isset($_POST['image'])   ? (string)$_POST['image'] : '';
if (!isValidSnapshotRunId($run) || $slide < 1 || $slide > 20 || $attempt < 0 || $attempt > 49) {
    echo json_encode(['success' => false, 'message' => 'Invalid snapshot']);
    exit;
}

$prefix = 'data:image/jpeg;base64,';
$bytes = strpos($image, $prefix) === 0 ? base64_decode(substr($image, strlen($prefix)), true) : false;
if ($bytes === false || $bytes === '' || strlen($bytes) > SNAPSHOT_MAX_BYTES) {
    echo json_encode(['success' => false, 'message' => 'Invalid image']);
    exit;
}
$finfo = new finfo(FILEINFO_MIME_TYPE);
if ($finfo->buffer($bytes) !== 'image/jpeg') {
    echo json_encode(['success' => false, 'message' => 'Invalid image']);
    exit;
}

ensureSnapshotSchema($conn);

// Caps so one student (or a page stuck in a loop) can't fill the storage:
// per run, and per hour across all runs.
$cnt = $conn->prepare("SELECT
        SUM(run_id = ?) AS in_run,
        SUM(created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)) AS last_hour
    FROM attempt_snapshots WHERE student_id = ?");
$cnt->bind_param("si", $run, $student_id);
$cnt->execute();
$counts = $cnt->get_result()->fetch_assoc();
$cnt->close();
if ((int)$counts['in_run'] >= SNAPSHOT_MAX_PER_RUN || (int)$counts['last_hour'] >= SNAPSHOT_MAX_PER_HOUR) {
    echo json_encode(['success' => false, 'message' => 'Too many snapshots']);
    exit;
}

$url = saveSnapshotImage($bytes);
if (!$url) {
    echo json_encode(['success' => false, 'message' => 'Could not save the picture']);
    exit;
}

// A re-check of the same try replaces its picture — drop the old file.
$old = $conn->prepare("SELECT image_url FROM attempt_snapshots WHERE student_id = ? AND run_id = ? AND slide = ? AND attempt = ?");
$old->bind_param("isii", $student_id, $run, $slide, $attempt);
$old->execute();
$oldRow = $old->get_result()->fetch_assoc();
$old->close();

// Kept right away if the student already submitted this run (the picture
// can finish uploading a moment after they tapped Submit).
$kept = snapshotRunIsSubmitted($conn, $student_id, $run) ? 1 : 0;
$ins = $conn->prepare("INSERT INTO attempt_snapshots (student_id, run_id, slide, attempt, image_url, kept) VALUES (?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE image_url = VALUES(image_url), kept = GREATEST(kept, VALUES(kept)), created_at = NOW()");
$ins->bind_param("isiisi", $student_id, $run, $slide, $attempt, $url, $kept);
$ok = $ins->execute();
$ins->close();
if ($ok && $oldRow && $oldRow['image_url'] !== $url) deleteSnapshotImage($oldRow['image_url']);

// Now and then, tidy away pictures no submission ended up using.
if (mt_rand(1, 20) === 1) cleanupSnapshots($conn);

echo json_encode(['success' => (bool)$ok]);
