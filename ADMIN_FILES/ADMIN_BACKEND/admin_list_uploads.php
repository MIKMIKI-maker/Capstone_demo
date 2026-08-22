<?php
require_once __DIR__ . '/db.php';
requireAdminSession();
header('Content-Type: application/json');
header('Cache-Control: no-cache');

$conn = getDatabaseConnection();
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

$sql = "
    SELECT m.id, m.teacher_id, m.student_id, m.grading_period,
           m.title, m.description, m.file_name, m.file_original_name,
           m.file_type, m.file_size, m.uploaded_at,
           s.student_name,
           CONCAT(tc.first_name, ' ', tc.last_name) AS teacher_name
    FROM teacher_uploaded_materials m
    LEFT JOIN students s ON s.id = m.student_id
    LEFT JOIN teacher_accounts tc ON tc.id = m.teacher_id
    ORDER BY m.uploaded_at DESC
    LIMIT 200
";

$result = $conn->query($sql);
$uploads = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $uploads[] = $row;
    }
}
$conn->close();

echo json_encode(['success' => true, 'uploads' => $uploads]);
?>
