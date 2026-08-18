<?php
require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
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

$conn->query("CREATE TABLE IF NOT EXISTS teacher_uploaded_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    student_id INT NOT NULL,
    grading_period VARCHAR(20) NOT NULL DEFAULT 'First',
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_name VARCHAR(255) NOT NULL DEFAULT '',
    file_original_name VARCHAR(255) NOT NULL DEFAULT '',
    file_type VARCHAR(100),
    file_size INT,
    link_url VARCHAR(500) DEFAULT NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

@$conn->query("ALTER TABLE teacher_uploaded_materials ADD COLUMN link_url VARCHAR(500) DEFAULT NULL");

$stmt = $conn->prepare(
    "SELECT id, grading_period, title, description, file_name, file_original_name, file_type, file_size, link_url, uploaded_at
     FROM teacher_uploaded_materials
     WHERE student_id = ?
     ORDER BY grading_period ASC, uploaded_at DESC"
);
$stmt->bind_param("i", $student_id);
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
