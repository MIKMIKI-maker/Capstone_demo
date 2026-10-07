<?php
require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/material_helpers.php';
require_once __DIR__ . '/student_auth.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache');

$student_admin_id = requireStudentSession();

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'files' => []]);
    exit;
}

$rec = resolveStudentRecord($conn, $student_admin_id);
if (!$rec) {
    echo json_encode(['success' => false, 'files' => []]);
    $conn->close();
    exit;
}
$student_id = (int)$rec['student_record_id'];
$current_teacher_id = (int)$rec['teacher_id'];

ensureMaterialSchema($conn);

// submission_count lets the list show "📤 Magpasa" vs "✅ Naipasa" for
// 'activity' materials without opening each one.
$stmt = $conn->prepare(
    "SELECT m.id, m.grading_period, m.title, m.description, m.file_name, m.file_original_name, m.file_type, m.file_size,
            m.link_url, m.material_kind, m.uploaded_at,
            (SELECT COUNT(*) FROM material_submissions ms WHERE ms.material_id = m.id AND ms.student_id = m.student_id) AS submission_count
     FROM teacher_uploaded_materials m
     WHERE m.student_id = ? AND m.teacher_id = ?
     ORDER BY m.grading_period ASC, m.uploaded_at DESC"
);
$stmt->bind_param("ii", $student_id, $current_teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$files = [];
while ($row = $result->fetch_assoc()) {
    $files[] = $row;
}
$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'files' => $files]);
?>
