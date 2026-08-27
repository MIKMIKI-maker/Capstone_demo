<?php
require_once __DIR__ . '/db.php';
requireAdminSession();

header('Content-Type: application/json');

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
$email_address = isset($_POST['email_address']) ? trim($_POST['email_address']) : '';
$phone_number = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';
$status = isset($_POST['admin_status_input']) ? trim($_POST['admin_status_input']) : 'active';
$grade_level = isset($_POST['grade_level']) ? trim($_POST['grade_level']) : '';
$condition_info = isset($_POST['admin_edit_cond_select']) ? trim($_POST['admin_edit_cond_select']) : '';
// For teachers, section name comes from the grade_level dropdown (not the condition dropdown)
if ($condition_info === '' && isset($_POST['grade_level']) && trim($_POST['grade_level']) !== '') {
    $condition_info = trim($_POST['grade_level']);
}
$assigned_teacher_id = isset($_POST['assigned_teacher_id']) ? intval($_POST['assigned_teacher_id']) : 0;
$parent_name_val = isset($_POST['parent_name']) ? trim($_POST['parent_name']) : '';

// Parse full name into first and last name
$name_parts = explode(' ', $full_name, 2);
$first_name = $name_parts[0];
$last_name = isset($name_parts[1]) ? $name_parts[1] : '';

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit;
}

// Get the user's role before updating
$role_check = $conn->prepare("SELECT role, assigned_teacher_id FROM admin_accounts WHERE id = ?");
$role_check->bind_param("i", $user_id);
$role_check->execute();
$role_result = $role_check->get_result();
$user_role = '';
$previous_assigned_teacher_id = 0;
if ($role_row = $role_result->fetch_assoc()) {
    $user_role = $role_row['role'];
    $previous_assigned_teacher_id = (int)($role_row['assigned_teacher_id'] ?? 0);
}
$role_check->close();

// Update the user account
$sql = "UPDATE admin_accounts SET first_name = ?, last_name = ?, admin_email = ?, phone_number = ?, status = ?, condition_info = ?, assigned_teacher_id = ?, parent_name = ? WHERE id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Query preparation failed']);
    exit;
}

$stmt->bind_param("ssssssisi", $first_name, $last_name, $email_address, $phone_number, $status, $condition_info, $assigned_teacher_id, $parent_name_val, $user_id);

if ($stmt->execute()) {
    if ($user_role === 'teacher') {
        syncTeacherAccount($email_address, $first_name, $last_name, $phone_number);
    } elseif ($user_role === 'student') {
        if (!syncStudentRecord($user_id, trim($full_name), $parent_name_val, $status, $condition_info, $grade_level, $assigned_teacher_id, $previous_assigned_teacher_id)) {
            echo json_encode(['success' => false, 'message' => 'Student assignment could not be synchronized']);
            $stmt->close();
            $conn->close();
            exit;
        }
    }
    echo json_encode(['success' => true, 'message' => 'User updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}

$stmt->close();
$conn->close();

// Function to sync teacher to teacher_accounts table
function syncTeacherAccount($email, $firstName, $lastName, $phone = '') {
    require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
    $teacher_conn = getTeacherDatabaseConnection();

    if (!$teacher_conn) {
        return false;
    }

    // Check if teacher exists
    $check_stmt = $teacher_conn->prepare("SELECT id FROM teacher_accounts WHERE teacher_email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows == 0) {
        // Create new teacher account
        $insert_stmt = $teacher_conn->prepare("INSERT INTO teacher_accounts (teacher_email, teacher_password, first_name, last_name, phone_number, school_name, status) VALUES (?, ?, ?, ?, ?, 'Mamatid Elementary School', 'active')");
        $password = password_hash('Teacher@123', PASSWORD_DEFAULT);
        $insert_stmt->bind_param("sssss", $email, $password, $firstName, $lastName, $phone);
        $insert_stmt->execute();
        $insert_stmt->close();
    } else {
        // Update existing teacher account with latest name/phone
        $update_stmt = $teacher_conn->prepare("UPDATE teacher_accounts SET first_name = ?, last_name = ?, phone_number = ? WHERE teacher_email = ?");
        $update_stmt->bind_param("ssss", $firstName, $lastName, $phone, $email);
        $update_stmt->execute();
        $update_stmt->close();
    }

    $check_stmt->close();
    $teacher_conn->close();
    return true;
}

// Function to sync a student's corrected name/parent name into the teacher-facing students table
function syncStudentRecord($adminAccountId, $studentName, $parentName, $accountStatus, $condition, $gradeLevel, $assignedTeacherAdminId, $previousAssignedTeacherAdminId) {
    require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
    $teacher_conn = getTeacherDatabaseConnection();

    if (!$teacher_conn) {
        return false;
    }

    $studentStatus = strtolower($accountStatus) === 'active' ? 'active' : 'inactive';

    // Assignment changes require a fresh enrollment by the newly assigned teacher.
    if ($assignedTeacherAdminId !== $previousAssignedTeacherAdminId) {
        $removeStmt = $teacher_conn->prepare("DELETE FROM students WHERE admin_account_id = ?");
        if (!$removeStmt) {
            $teacher_conn->close();
            return false;
        }
        $removeStmt->bind_param("i", $adminAccountId);
        if (!$removeStmt->execute()) {
            $removeStmt->close();
            $teacher_conn->close();
            return false;
        }
        $removeStmt->close();
        $teacher_conn->close();
        return true;
    }

    $teacherId = 0;
    if ($assignedTeacherAdminId > 0) {
        $teacherLookup = $teacher_conn->prepare(
            "SELECT t.id
             FROM teacher_accounts t
             INNER JOIN admin_accounts a ON a.admin_email = t.teacher_email
             WHERE a.id = ? AND a.role = 'teacher'
             LIMIT 1"
        );
        if ($teacherLookup) {
            $teacherLookup->bind_param("i", $assignedTeacherAdminId);
            $teacherLookup->execute();
            if ($teacherRow = $teacherLookup->get_result()->fetch_assoc()) {
                $teacherId = (int)$teacherRow['id'];
            }
            $teacherLookup->close();
        }
    }

    if ($assignedTeacherAdminId > 0 && $teacherId <= 0) {
        $teacher_conn->close();
        return false;
    }

    if ($teacherId > 0) {
        $stmt = $teacher_conn->prepare(
            "UPDATE students
             SET student_name = ?, parent_name = ?, disability_type = ?, grade_level = ?, status = ?, teacher_id = ?
             WHERE admin_account_id = ?"
        );
        $stmt->bind_param("sssssii", $studentName, $parentName, $condition, $gradeLevel, $studentStatus, $teacherId, $adminAccountId);
    } else {
        $stmt = $teacher_conn->prepare(
            "UPDATE students
             SET student_name = ?, parent_name = ?, disability_type = ?, grade_level = ?, status = ?
             WHERE admin_account_id = ?"
        );
        $stmt->bind_param("sssssi", $studentName, $parentName, $condition, $gradeLevel, $studentStatus, $adminAccountId);
    }
    if (!$stmt || !$stmt->execute()) {
        if ($stmt) $stmt->close();
        $teacher_conn->close();
        return false;
    }
    $updated = $stmt->affected_rows >= 0;
    $stmt->close();
    $teacher_conn->close();
    return $updated;
}
