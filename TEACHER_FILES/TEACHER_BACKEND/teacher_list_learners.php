<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/db.php';
require_once __DIR__ . '/teacher_auth.php';

header('Content-Type: application/json');

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode([]);
    exit;
}

// Ensure admin DB is initialised (same spedalm_db, but tables must exist)
getDatabaseConnection();

$teacher_id = requireTeacherId();

// Auto-purge orphaned students: linked accounts that admin has since deleted
$conn->query("DELETE FROM students
              WHERE admin_account_id IS NOT NULL
                AND admin_account_id > 0
                AND admin_account_id NOT IN (SELECT id FROM admin_accounts)");

// Get all students for this teacher
$stmt = $conn->prepare("SELECT id, student_name, parent_name, parent_email, parent_phone, disability_type, status, age, grade_level, created_at FROM students WHERE teacher_id = ? ORDER BY created_at DESC");
if (!$stmt) { echo json_encode([]); $conn->close(); exit; }
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$students = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $students[] = [
            'id' => $row['id'],
            'name' => $row['student_name'],
            'parent_name' => $row['parent_name'],
            'parent_email' => $row['parent_email'],
            'parent_phone' => $row['parent_phone'],
            'disability' => $row['disability_type'],
            'status' => $row['status'],
            'age' => $row['age'],
            'grade_level' => $row['grade_level'],
            'created_at' => $row['created_at']
        ];
    }
}
$stmt->close();

echo json_encode($students);
$conn->close();
