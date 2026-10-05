<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/../../MAILER/email_domains.php';
require_once __DIR__ . '/school_year.php';
requireAdminSession();
csrf_require_valid_token();

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
$role_check = $conn->prepare("SELECT role, assigned_teacher_id, admin_email, condition_info FROM admin_accounts WHERE id = ?");
$role_check->bind_param("i", $user_id);
$role_check->execute();
$role_result = $role_check->get_result();
$user_role = '';
$previous_assigned_teacher_id = 0;
$previous_email = '';
$previous_section = '';
if ($role_row = $role_result->fetch_assoc()) {
    $user_role = $role_row['role'];
    $previous_assigned_teacher_id = (int)($role_row['assigned_teacher_id'] ?? 0);
    $previous_email = (string)($role_row['admin_email'] ?? '');
    $previous_section = trim((string)($role_row['condition_info'] ?? ''));
}
$role_check->close();

// One teacher per Section — checked only when the Section changes, so a
// teacher saved before this rule can still be edited.
if ($user_role === 'teacher' && trim($condition_info) !== $previous_section
    && ($owner = sectionTakenBy($conn, $condition_info, $user_id))) {
    echo json_encode(['success' => false, 'message' => trim($condition_info) . " already has a teacher ($owner). Choose another Section."]);
    exit;
}

// Only check a changed email, so accounts created before the provider
// restriction can still be edited without being forced onto a new address.
if (strcasecmp($email_address, $previous_email) !== 0) {
    if (!filter_var($email_address, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
        exit;
    }
    if (!isAllowedEmailDomain($email_address)) {
        echo json_encode(['success' => false, 'message' => ALLOWED_EMAIL_DOMAINS_MESSAGE]);
        exit;
    }
}

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
    // Keep this S.Y.'s class enrollments in step with the new assignment.
    reconcileActiveEnrollments($conn);
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

    // The account's status is only an online indicator (Active on login,
    // Inactive on logout), so it is NOT copied onto the student record —
    // doing so made a student who was edited while logged out look
    // un-enrolled (no teacher in their portal, "Inactive" for the teacher).
    $stmt = $teacher_conn->prepare(
        "UPDATE students SET student_name = ?, parent_name = ?, disability_type = ?, grade_level = ?
         WHERE admin_account_id = ?"
    );
    $stmt->bind_param("ssssi", $studentName, $parentName, $condition, $gradeLevel, $adminAccountId);
    $ok = $stmt->execute();
    $stmt->close();

    // A new teacher chosen here must enroll the student themselves ("+ Add
    // Student"). Until then the record is 'pending': the student leaves the
    // old teacher's list and shows as not enrolled. The record (and all past
    // activities/scores) is kept — the new teacher's enrollment re-uses it.
    // Moves made by the School Year tools enroll automatically instead
    // (see moveStudentToTeacher in school_year.php).
    if ($ok && $assignedTeacherAdminId !== $previousAssignedTeacherAdminId) {
        $pending = $teacher_conn->prepare("UPDATE students SET status = 'pending' WHERE admin_account_id = ?");
        $pending->bind_param("i", $adminAccountId);
        $ok = $pending->execute();
        $pending->close();
    }

    $teacher_conn->close();
    return $ok;
}
