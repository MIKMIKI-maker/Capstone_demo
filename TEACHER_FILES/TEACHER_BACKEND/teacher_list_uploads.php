<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/teacher_auth.php';
require_once __DIR__ . '/material_helpers.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache');

// The logged-in teacher, from the session — never a teacher_id sent by the browser.
$teacher_id = requireTeacherId();
if (!$teacher_id) {
    echo json_encode(['success' => false, 'uploads' => []]);
    exit;
}

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'uploads' => []]);
    exit;
}

ensureMaterialSchema($conn);

// 'activity' materials also report how many files the student has
// submitted, so the list can show a "Submissions (n)" button.
$stmt = $conn->prepare("
    SELECT m.id, m.student_id, m.grading_period, m.title, m.description,
           m.file_name, m.file_original_name, m.file_type, m.file_size, m.uploaded_at,
           m.link_url, m.material_kind,
           s.student_name,
           (SELECT COUNT(*) FROM material_submissions ms WHERE ms.material_id = m.id AND ms.student_id = m.student_id) AS submission_count,
           (SELECT MAX(ms.submitted_at) FROM material_submissions ms WHERE ms.material_id = m.id AND ms.student_id = m.student_id) AS last_submitted_at
    FROM teacher_uploaded_materials m
    LEFT JOIN students s ON s.id = m.student_id
    WHERE m.teacher_id = ?
    ORDER BY m.uploaded_at DESC
");
if (!$stmt) {
    echo json_encode(['success' => false, 'uploads' => []]);
    $conn->close();
    exit;
}

$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$uploads = [];
while ($row = $result->fetch_assoc()) {
    $uploads[] = $row;
}
$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'uploads' => $uploads]);
?>
