<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/admin_push_notification.php';
require_once __DIR__ . '/../../MAILER/send_email.php';
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

        // Welcome email straight to the new account's own inbox with its
        // login credentials — $raw_password is the plaintext default (or
        // admin-chosen) password, before it got hashed into $password above.
        // PUBLIC_SITE_URL (not the request's own host) so the logo/login
        // link work in the recipient's inbox even when this code is running
        // on localhost/Docker — see MAILER/mailer_config.php.
        $logoUrl = get_logo_data_uri();
        $loginUrl = PUBLIC_SITE_URL . '/ADMIN_FILES/login_screen.html';

        $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $safePassword = htmlspecialchars($raw_password, ENT_QUOTES, 'UTF-8');
        $welcomeHtml = "<div style=\"max-width:480px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:32px 28px;font-family:Arial,Helvetica,sans-serif;color:#1e293b;\">"
            . "<div style=\"text-align:center;margin-bottom:18px;\"><img src=\"{$logoUrl}\" alt=\"SPED ALM\" width=\"64\" height=\"64\" style=\"width:64px;height:64px;border-radius:50%;\"></div>"
            . "<h2 style=\"text-align:center;color:#1e3a8a;margin:0 0 24px;font-size:22px;\">Welcome to SPED ALM</h2>"
            . "<p style=\"margin:0 0 12px;\">Dear {$safeName},</p>"
            . "<p style=\"margin:0 0 16px;\">We are pleased to inform you that your {$roleLabel} Account has been successfully created in the SPED ALM System.</p>"
            . "<p style=\"margin:0 0 10px;\">Below are your account credentials:</p>"
            . "<div style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px 20px;margin:0 0 24px;\">"
            . "<p style=\"margin:0 0 8px;font-size:14px;\"><strong>Username:</strong> {$safeEmail}</p>"
            . "<p style=\"margin:0;font-size:14px;\"><strong>Password:</strong> {$safePassword}</p>"
            . "</div>"
            . "<div style=\"text-align:center;margin:0 0 24px;\"><a href=\"{$loginUrl}\" style=\"display:inline-block;background:#1e3a8a;color:#ffffff;text-decoration:none;padding:12px 32px;border-radius:10px;font-weight:700;font-size:14px;\">Log In to SPED ALM</a></div>"
            . "<p style=\"font-size:13px;color:#64748b;margin:0 0 20px;\">Please keep your account credentials confidential and secure. You may now log in to the SPED ALM System using the credentials provided above.</p>"
            . "<hr style=\"border:none;border-top:1px solid #e2e8f0;margin:0 0 16px;\">"
            . "<p style=\"margin:0;\">Sincerely yours,<br>SPED ALM System</p>"
            . "</div>";
        send_email($email, $fullName, 'Your SPED ALM account has been created', $welcomeHtml);

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