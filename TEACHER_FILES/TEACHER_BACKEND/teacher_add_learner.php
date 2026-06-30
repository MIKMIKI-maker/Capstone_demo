<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

getDatabaseConnection(); // ensure spedalm_db and all admin tables exist first
$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

session_start();
$teacher_id = isset($_POST['teacher_id']) ? intval($_POST['teacher_id'])
            : (isset($_SESSION['admin_id']) ? intval($_SESSION['admin_id']) : 1);

$student_name    = isset($_POST['student_name'])    ? trim($_POST['student_name'])    : '';
$parent_name     = isset($_POST['parent_name'])     ? trim($_POST['parent_name'])     : '';
$parent_email    = isset($_POST['parent_email'])    ? trim($_POST['parent_email'])    : '';
$parent_phone    = isset($_POST['parent_phone'])    ? trim($_POST['parent_phone'])    : '';
$disability_type = isset($_POST['disability_type']) ? trim($_POST['disability_type']) : '';
$grade_level       = isset($_POST['grade_level'])       ? trim($_POST['grade_level'])       : '';
$admin_account_id  = isset($_POST['admin_account_id'])  ? intval($_POST['admin_account_id']) : 0;
$age               = isset($_POST['age'])               ? intval($_POST['age'])             : 0;
$status            = 'active';

if (!$student_name) {
    echo json_encode(['success' => false, 'message' => 'Student name is required']);
    exit;
}

// Verify student is pre-assigned to this teacher before allowing enrollment
if ($admin_account_id > 0) {
    $admin_conn = getDatabaseConnection();
    if ($admin_conn) {
        $teacher_admin_id = 0;
        $ta_stmt = $admin_conn->prepare(
            "SELECT a.id FROM admin_accounts a
             INNER JOIN teacher_accounts t ON a.admin_email = t.teacher_email
             WHERE t.id = ? AND a.role = 'teacher' LIMIT 1"
        );
        if ($ta_stmt) {
            $ta_stmt->bind_param("i", $teacher_id);
            $ta_stmt->execute();
            $ta_res = $ta_stmt->get_result();
            if ($ta_row = $ta_res->fetch_assoc()) $teacher_admin_id = intval($ta_row['id']);
            $ta_stmt->close();
        }
        if ($teacher_admin_id > 0) {
            $asgn_stmt = $admin_conn->prepare(
                "SELECT assigned_teacher_id FROM admin_accounts WHERE id = ? AND role = 'student' LIMIT 1"
            );
            if ($asgn_stmt) {
                $asgn_stmt->bind_param("i", $admin_account_id);
                $asgn_stmt->execute();
                $asgn_row = $asgn_stmt->get_result()->fetch_assoc();
                $asgn_stmt->close();
                if (!$asgn_row || intval($asgn_row['assigned_teacher_id']) !== $teacher_admin_id) {
                    echo json_encode(['success' => false, 'message' => 'This student is not assigned to your class.']);
                    $admin_conn->close();
                    $conn->close();
                    exit;
                }
            }
        }
        $admin_conn->close();
    }
}

$stmt = $conn->prepare("INSERT INTO students (teacher_id, admin_account_id, student_name, parent_name, parent_email, parent_phone, disability_type, grade_level, age, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database prepare failed']);
    $conn->close();
    exit;
}

$stmt->bind_param("iissssssis", $teacher_id, $admin_account_id, $student_name, $parent_name, $parent_email, $parent_phone, $disability_type, $grade_level, $age, $status);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Student added successfully', 'id' => $stmt->insert_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to add student: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
