<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode([]);
    exit;
}

session_start();
$teacher_id = isset($_REQUEST['teacher_id']) ? intval($_REQUEST['teacher_id'])
            : (isset($_SESSION['admin_id']) ? intval($_SESSION['admin_id']) : 1);

$stmt = $conn->prepare("SELECT id, student_id, report_title, report_type, report_date, created_at
                        FROM teacher_reports
                        WHERE teacher_id = ?
                        ORDER BY report_date DESC LIMIT 50");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$reports = [];

$name_stmt = $conn->prepare("SELECT student_name FROM students WHERE id = ?");

while ($row = $result->fetch_assoc()) {
    $student_name = '';
    $name_stmt->bind_param("i", $row['student_id']);
    $name_stmt->execute();
    $nr = $name_stmt->get_result();
    if ($nr && $nr->num_rows > 0) {
        $student_name = $nr->fetch_assoc()['student_name'];
    }
    $reports[] = [
        'id'          => $row['id'],
        'student_id'  => $row['student_id'],
        'student_name'=> $student_name,
        'title'       => $row['report_title'],
        'type'        => $row['report_type'],
        'date'        => $row['report_date'],
        'created_at'  => $row['created_at']
    ];
}

$name_stmt->close();
$stmt->close();

echo json_encode($reports);
$conn->close();
