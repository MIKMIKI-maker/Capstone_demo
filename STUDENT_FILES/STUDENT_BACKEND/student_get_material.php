<?php
// One uploaded material for the material page (Student_material.html), plus
// whatever the student has already submitted for it if it's an 'activity'.
// GET ?id=N (teacher_uploaded_materials.id)
require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/material_helpers.php';
require_once __DIR__ . '/student_auth.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache');

$student_admin_id = requireStudentSession();
$material_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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
$material = $material_id ? loadStudentMaterial($conn, $material_id, $student_id, $teacher_id) : null;
if (!$material) {
    echo json_encode(['success' => false, 'message' => 'Material not found']);
    $conn->close();
    exit;
}

$teacher_name = '';
$tq = $conn->prepare("SELECT first_name, last_name FROM teacher_accounts WHERE id = ?");
if ($tq) {
    $tq->bind_param("i", $teacher_id);
    $tq->execute();
    if ($t = $tq->get_result()->fetch_assoc()) $teacher_name = trim($t['first_name'] . ' ' . $t['last_name']);
    $tq->close();
}

$submissions = $material['material_kind'] === 'activity' ? listMaterialSubmissions($conn, $material_id, $student_id) : [];
$conn->close();

// Internal ids the page has no use for.
unset($material['teacher_id'], $material['student_id']);

echo json_encode([
    'success'      => true,
    'material'     => $material,
    'teacher_name' => $teacher_name,
    'submissions'  => $submissions,
    'limits'       => [
        'max_video_mb' => MATERIAL_SUBMISSION_MAX_VIDEO_BYTES / 1048576,
        'max_file_mb'  => MATERIAL_SUBMISSION_MAX_FILE_BYTES / 1048576,
        'max_files'    => MATERIAL_SUBMISSION_MAX_FILES,
    ],
]);
