<?php
// The files a student submitted for one of this teacher's 'activity'
// materials (Uploaded Files → Submissions).
// GET ?material_id=N (teacher_uploaded_materials.id)
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/teacher_auth.php';
require_once __DIR__ . '/material_helpers.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache');

// The logged-in teacher, from the session — never a teacher_id sent by the browser.
$teacher_id  = requireTeacherId();
$material_id = isset($_GET['material_id']) ? (int)$_GET['material_id'] : 0;
if (!$material_id) {
    echo json_encode(['success' => false, 'message' => 'Missing material']);
    exit;
}

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}
ensureMaterialSchema($conn);

$stmt = $conn->prepare("SELECT m.id, m.student_id, m.title, m.description, m.material_kind, m.uploaded_at, s.student_name
    FROM teacher_uploaded_materials m
    LEFT JOIN students s ON s.id = m.student_id
    WHERE m.id = ? AND m.teacher_id = ?");
$stmt->bind_param("ii", $material_id, $teacher_id);
$stmt->execute();
$material = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$material) {
    echo json_encode(['success' => false, 'message' => 'Material not found']);
    $conn->close();
    exit;
}

$submissions = listMaterialSubmissions($conn, $material_id, (int)$material['student_id']);
$conn->close();

echo json_encode(['success' => true, 'material' => $material, 'submissions' => $submissions]);
