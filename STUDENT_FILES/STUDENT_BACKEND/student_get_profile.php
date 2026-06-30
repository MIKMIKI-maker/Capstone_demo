<?php
require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/db.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$admin_account_id = isset($_GET['admin_account_id']) ? intval($_GET['admin_account_id']) : 0;
if (!$admin_account_id) {
    echo json_encode(['success' => false, 'message' => 'Missing admin_account_id']);
    exit;
}

$admin_conn = getDatabaseConnection();
$teacher_conn = getTeacherDatabaseConnection();

if (!$admin_conn || !$teacher_conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get student info from admin_accounts
$stmt = $admin_conn->prepare("SELECT id, first_name, last_name, admin_email, COALESCE(profile_photo,'') AS profile_photo FROM admin_accounts WHERE id = ? AND role = 'student' AND status = 'active'");
$stmt->bind_param("i", $admin_account_id);
$stmt->execute();
$admin_row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin_row) {
    echo json_encode(['success' => false, 'message' => 'Student account not found']);
    $admin_conn->close();
    $teacher_conn->close();
    exit;
}

// Get student record from students table (teacher DB)
$stmt2 = $teacher_conn->prepare("SELECT s.id, s.student_name, s.disability_type, s.grade_level, s.teacher_id,
    COALESCE(s.parent_name,'') AS parent_name,
    CONCAT(t.first_name, ' ', t.last_name) AS teacher_name, t.specialization
    FROM students s
    LEFT JOIN teacher_accounts t ON t.id = s.teacher_id
    WHERE s.admin_account_id = ? AND s.status = 'active'
    LIMIT 1");
$stmt2->bind_param("i", $admin_account_id);
$stmt2->execute();
$student_row = $stmt2->get_result()->fetch_assoc();
$stmt2->close();

$profile = [
    'success' => true,
    'admin_id' => $admin_row['id'],
    'first_name' => $admin_row['first_name'],
    'last_name' => $admin_row['last_name'],
    'full_name' => trim($admin_row['first_name'] . ' ' . $admin_row['last_name']),
    'email' => $admin_row['admin_email'],
    'profile_photo' => $admin_row['profile_photo'] ?? '',
    'student_record_id' => $student_row ? $student_row['id'] : null,
    'disability_type' => $student_row ? $student_row['disability_type'] : '',
    'grade_level' => $student_row ? $student_row['grade_level'] : '',
    'parent_name' => $student_row ? ($student_row['parent_name'] ?? '') : '',
    'teacher_id' => $student_row ? $student_row['teacher_id'] : null,
    'teacher_name' => $student_row ? $student_row['teacher_name'] : '',
    'teacher_specialization' => $student_row ? $student_row['specialization'] : '',
];

echo json_encode($profile);
$admin_conn->close();
$teacher_conn->close();
?>
