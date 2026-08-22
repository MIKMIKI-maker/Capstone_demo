<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/admin_push_notification.php';
requireAdminSession();

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $firstName = isset($_POST['enter_first_name']) ? trim($_POST['enter_first_name']) : '';
    $lastName  = isset($_POST['enter_last_name'])  ? trim($_POST['enter_last_name'])  : '';
    $email = isset($_POST['enter_email_address']) ? trim($_POST['enter_email_address']) : '';
    $role = isset($_POST['enter_role']) ? trim($_POST['enter_role']) : 'teacher';
    if (!in_array($role, ['admin', 'teacher', 'student'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid account role']);
        $conn->close();
        exit;
    }
    $condition = '';
    if (isset($_POST['admin_add_cond_select'])) {
        $condition = trim($_POST['admin_add_cond_select']);
    } elseif (isset($_POST['enter_condition'])) {
        $condition = trim($_POST['enter_condition']);
    }
    // For teachers, section name comes from the grade_level dropdown
    if ($role === 'teacher' && $condition === '' && isset($_POST['grade_level']) && trim($_POST['grade_level']) !== '') {
        $condition = trim($_POST['grade_level']);
    }
    $defaults = ['admin' => 'Admin@123', 'teacher' => 'Teacher@123', 'student' => 'Student@123'];
    $raw_password = isset($_POST['enter_password']) && trim($_POST['enter_password']) !== '' ? trim($_POST['enter_password']) : ($defaults[$role] ?? 'Teacher@123');
    $password = password_hash($raw_password, PASSWORD_DEFAULT);
    $assigned_teacher_id = ($role === 'student' && !empty($_POST['assigned_teacher_id'])) ? intval($_POST['assigned_teacher_id']) : 0;
    $parent_name_val = ($role === 'student' && isset($_POST['enter_parent_name'])) ? trim($_POST['enter_parent_name']) : '';

    if ($firstName === '' || $email === '') {
        echo json_encode(['success' => false, 'message' => 'First name and email are required']);
        $conn->close();
        exit;
    }

    if (mb_strlen($firstName) > 100 || mb_strlen($lastName) > 100 || mb_strlen($email) > 255) {
        echo json_encode(['success' => false, 'message' => 'Name or email is too long']);
        $conn->close();
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
        $conn->close();
        exit;
    }

    $fullName = trim($firstName . ' ' . $lastName);

    $schoolName = 'Mamatid Elementary School';
    $status = 'inactive';

    $stmt = $conn->prepare("INSERT INTO admin_accounts (admin_email, admin_password, first_name, last_name, school_name, role, condition_info, status, assigned_teacher_id, parent_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database prepare failed']);
        $conn->close();
        exit;
    }

    $stmt->bind_param("ssssssssis", $email, $password, $firstName, $lastName, $schoolName, $role, $condition, $status, $assigned_teacher_id, $parent_name_val);

    if ($stmt->execute()) {
        // If the role is 'teacher', also create/update in teacher_accounts
        if ($role === 'teacher') {
            syncTeacherAccount($email, $firstName, $lastName);
        }
        
        // Log the activity
        $logStmt = $conn->prepare("INSERT INTO admin_activities (activity_type, user_type, user_name, user_email, action_detail) VALUES (?,?,?,?,?)");
        if ($logStmt) {
            $logType = 'Add User'; $logUType = 'admin';
            $logName  = isset($_SESSION['admin_name'])  ? $_SESSION['admin_name']  : '';
            $logEmail = isset($_SESSION['admin_email']) ? $_SESSION['admin_email'] : '';
            $logDetail = "Added {$role}: {$fullName}";
            $logStmt->bind_param("sssss", $logType, $logUType, $logName, $logEmail, $logDetail);
            $logStmt->execute(); $logStmt->close();
        }

        // Push admin notification
        $roleLabel = ucfirst($role);
        pushAdminNotification(
            $conn,
            'account',
            "New {$roleLabel} Account Created",
            "{$fullName} ({$email}) has been added as a {$roleLabel}.",
            $stmt->insert_id ?? null
        );

        echo json_encode(['success' => true, 'message' => 'Account added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add account: ' . $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

// Function to sync teacher to teacher_accounts table
function syncTeacherAccount($email, $firstName, $lastName) {
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
        $insert_stmt = $teacher_conn->prepare("INSERT INTO teacher_accounts (teacher_email, teacher_password, first_name, last_name, school_name, status) VALUES (?, ?, ?, ?, 'Mamatid Elementary School', 'active')");
        $password = password_hash('Teacher@123', PASSWORD_DEFAULT);
        $insert_stmt->bind_param("ssss", $email, $password, $firstName, $lastName);
        $insert_stmt->execute();
        $insert_stmt->close();
    } else {
        // Update existing teacher account with latest name
        $update_stmt = $teacher_conn->prepare("UPDATE teacher_accounts SET first_name = ?, last_name = ? WHERE teacher_email = ?");
        $update_stmt->bind_param("sss", $firstName, $lastName, $email);
        $update_stmt->execute();
        $update_stmt->close();
    }
    
    $check_stmt->close();
    $teacher_conn->close();
    return true;
}

$conn->close();
?>