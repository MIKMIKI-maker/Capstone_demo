<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache');

$teacher_id = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : 0;
if (!$teacher_id) {
    echo json_encode(['success' => false, 'uploads' => []]);
    exit;
}

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'uploads' => []]);
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS teacher_uploaded_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    student_id INT NOT NULL,
    grading_period VARCHAR(20) NOT NULL DEFAULT 'First',
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_name VARCHAR(255) NOT NULL,
    file_original_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(100),
    file_size INT,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$stmt = $conn->prepare("
    SELECT m.id, m.student_id, m.grading_period, m.title, m.description,
           m.file_name, m.file_original_name, m.file_type, m.file_size, m.uploaded_at,
           s.student_name
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
