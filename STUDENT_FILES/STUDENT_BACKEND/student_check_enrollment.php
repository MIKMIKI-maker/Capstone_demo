<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Cache-Control: no-cache');

require_once __DIR__ . '/student_auth.php';
$student_admin_id = requireStudentSession();

$conn = new mysqli('127.0.0.1', 'root', '', 'spedalm_db', 3306);
if ($conn->connect_error) { echo json_encode(['enrolled' => false]); exit; }
$conn->set_charset('utf8mb4');

$row = resolveStudentRecord($conn, $student_admin_id);
$conn->close();

if ($row) {
    echo json_encode([
        'enrolled'          => true,
        'student_record_id' => (int)$row['student_record_id'],
        'teacher_id'        => (int)$row['teacher_id'],
        'condition'         => $row['disability_type'] ?: '',
        'grade'             => $row['grade_level']     ?: '',
        'student_name'      => $row['student_name']    ?: ''
    ]);
} else {
    echo json_encode(['enrolled' => false]);
}
?>
