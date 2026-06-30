<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$student_admin_id = isset($_GET['student_id'])   ? intval($_GET['student_id'])     : 0;
$student_name     = isset($_GET['student_name']) ? trim($_GET['student_name'])     : '';

if (!$student_admin_id) {
    echo json_encode(['enrolled' => false]);
    exit;
}

$conn = getTeacherDatabaseConnection();
if (!$conn) { echo json_encode(['enrolled' => false]); exit; }

$row = null;

// 1. Exact admin_account_id match
$stmt = $conn->prepare("SELECT id AS student_record_id, teacher_id, disability_type, grade_level, student_name FROM students WHERE admin_account_id = ? AND status = 'active' LIMIT 1");
if ($stmt) {
    $stmt->bind_param("i", $student_admin_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// 2. Case-insensitive name match (covers manual enrollments where admin_account_id wasn't linked)
if (!$row && $student_name !== '') {
    $stmt2 = $conn->prepare("SELECT id AS student_record_id, teacher_id, disability_type, grade_level, student_name FROM students WHERE LOWER(TRIM(student_name)) = LOWER(TRIM(?)) AND status = 'active' LIMIT 1");
    if ($stmt2) {
        $stmt2->bind_param("s", $student_name);
        $stmt2->execute();
        $row = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
        if ($row) {
            // Auto-link so future logins use the fast path
            $upd = $conn->prepare("UPDATE students SET admin_account_id = ? WHERE id = ?");
            if ($upd) { $upd->bind_param("ii", $student_admin_id, $row['student_record_id']); $upd->execute(); $upd->close(); }
        }
    }
}

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
