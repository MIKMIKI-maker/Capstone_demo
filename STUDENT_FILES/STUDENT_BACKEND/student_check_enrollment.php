<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$student_admin_id = isset($_GET['student_id'])   ? intval($_GET['student_id'])     : 0;
$student_name     = isset($_GET['student_name']) ? trim($_GET['student_name'])     : '';

if (!$student_admin_id) {
    echo json_encode(['enrolled' => false]);
    exit;
}

// Direct lightweight connection — skip getTeacherDatabaseConnection() migration overhead
$conn = new mysqli('127.0.0.1', 'root', '', 'spedalm_db', 3306);
if ($conn->connect_error) { echo json_encode(['enrolled' => false]); exit; }
$conn->set_charset('utf8mb4');

$row = null;

// 1. Exact admin_account_id match
$stmt = $conn->prepare("SELECT id AS student_record_id, teacher_id, disability_type, grade_level, student_name FROM students WHERE admin_account_id = ? AND status = 'active' LIMIT 1");
if ($stmt) {
    $stmt->bind_param("i", $student_admin_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// 2. Case-insensitive exact name match
if (!$row && $student_name !== '') {
    $stmt2 = $conn->prepare("SELECT id AS student_record_id, teacher_id, disability_type, grade_level, student_name FROM students WHERE LOWER(TRIM(student_name)) = LOWER(TRIM(?)) AND status = 'active' LIMIT 1");
    if ($stmt2) {
        $stmt2->bind_param("s", $student_name);
        $stmt2->execute();
        $row = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
    }
}

// 3. Partial name match — teacher may have saved only first+last name
//    e.g. student login name "Nina Genesis Deleon" vs stored "Nina Deleon"
if (!$row && $student_name !== '') {
    $parts = preg_split('/\s+/', trim($student_name));
    if (count($parts) >= 2) {
        $first = strtolower($parts[0]);
        $last  = strtolower(end($parts));
        $like  = '%' . $conn->real_escape_string($first) . '%';
        $like2 = '%' . $conn->real_escape_string($last)  . '%';
        $stmt3 = $conn->prepare("SELECT id AS student_record_id, teacher_id, disability_type, grade_level, student_name FROM students WHERE LOWER(student_name) LIKE ? AND LOWER(student_name) LIKE ? AND status = 'active' LIMIT 1");
        if ($stmt3) {
            $stmt3->bind_param("ss", $like, $like2);
            $stmt3->execute();
            $row = $stmt3->get_result()->fetch_assoc();
            $stmt3->close();
        }
    }
}

// Auto-link admin_account_id once found so next login uses the fast path
if ($row && !empty($row['student_record_id'])) {
    $upd = $conn->prepare("UPDATE students SET admin_account_id = ? WHERE id = ? AND (admin_account_id IS NULL OR admin_account_id = 0)");
    if ($upd) { $upd->bind_param("ii", $student_admin_id, $row['student_record_id']); $upd->execute(); $upd->close(); }
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
