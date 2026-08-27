<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
require_once __DIR__ . '/student_auth.php';
$student_admin_id = requireStudentSession();

$conn = getTeacherDatabaseConnection();
if (!$conn) { echo json_encode(['enrolled' => false]); exit; }
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
