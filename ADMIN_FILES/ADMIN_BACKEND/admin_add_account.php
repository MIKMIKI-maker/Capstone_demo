<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/school_config.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/admin_push_notification.php';
require_once __DIR__ . '/password_policy.php';
require_once __DIR__ . '/../../MAILER/send_email.php';
require_once __DIR__ . '/../../MAILER/account_emails.php';
require_once __DIR__ . '/../../MAILER/email_domains.php';
require_once __DIR__ . '/school_year.php';
requireAdminSession();
csrf_require_valid_token();

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
    $raw_password = generateTemporaryPassword($firstName, $lastName);
    $password = password_hash($raw_password, PASSWORD_DEFAULT);
    $assigned_teacher_id = ($role === 'student' && !empty($_POST['assigned_teacher_id'])) ? intval($_POST['assigned_teacher_id']) : 0;
    $parent_name_val = ($role === 'student' && isset($_POST['enter_parent_name'])) ? trim($_POST['enter_parent_name']) : '';

    // Every field on the Add Account form is required. $condition holds the
    // Section for teachers and the Condition for students.
    $missing = [];
    if ($firstName === '') $missing[] = 'First Name';
    if ($lastName === '')  $missing[] = 'Last Name';
    if ($email === '')     $missing[] = 'Email Address';
    if ($role === 'teacher' && $condition === '') $missing[] = 'Section';
    if ($role === 'student') {
        if ($condition === '')         $missing[] = 'Condition';
        if ($parent_name_val === '')   $missing[] = 'Parent/Guardian Full Name';
        if ($assigned_teacher_id <= 0) $missing[] = 'Assigned Teacher';
    }
    if ($missing) {
        echo json_encode(['success' => false, 'message' => 'Required: ' . implode(', ', $missing)]);
        $conn->close();
        exit;
    }

    // One teacher per Section.
    if ($role === 'teacher' && ($owner = sectionTakenBy($conn, $condition))) {
        echo json_encode(['success' => false, 'message' => "$condition already has a teacher ($owner). Choose another Section."]);
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

    if (!isAllowedEmailDomain($email)) {
        echo json_encode(['success' => false, 'message' => emailDomainProblem($email)]);
        $conn->close();
        exit;
    }

    $fullName = trim($firstName . ' ' . $lastName);

    $schoolName = SCHOOL_NAME;
    $status = 'inactive';
    // Teacher/Student accounts start with a password the Admin set or a role
    // default - force a change on first login so the account's real owner
    // is the only one who knows it afterward.
    $mustChangePassword = in_array($role, ['teacher', 'student'], true) ? 1 : 0;

    $stmt = $conn->prepare("INSERT INTO admin_accounts (admin_email, admin_password, first_name, last_name, school_name, role, condition_info, status, assigned_teacher_id, parent_name, must_change_password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database prepare failed']);
        $conn->close();
        exit;
    }

    $stmt->bind_param("ssssssssisi", $email, $password, $firstName, $lastName, $schoolName, $role, $condition, $status, $assigned_teacher_id, $parent_name_val, $mustChangePassword);

    if ($stmt->execute()) {
        // If the role is 'teacher', also create/update in teacher_accounts
        if ($role === 'teacher') {
            syncTeacherAccount($email, $firstName, $lastName);
        }
        // Put the new teacher's Section / student's class into the Active S.Y.
        reconcileActiveEnrollments($conn);

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
        // login credentials — $raw_password is the plaintext random
        // temporary password, before it got hashed into $password above
        // (the email itself: MAILER/account_emails.php).
        $welcomeHtml = accountWelcomeEmailHtml($fullName, $roleLabel, $email, $raw_password);
        $emailSent = send_email($email, $fullName, 'Your SPED ALM account has been created', $welcomeHtml);

        // The email is the only place the temporary password is shown, so if
        // it didn't go out, hand it to the Admin to pass along instead.
        if ($emailSent) {
            echo json_encode(['success' => true, 'email_sent' => true, 'message' => 'Account added successfully']);
        } else {
            echo json_encode(['success' => true, 'email_sent' => false, 'temp_password' => $raw_password, 'message' => 'Account added, but the welcome email could not be sent']);
        }
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
        $insert_stmt = $teacher_conn->prepare("INSERT INTO teacher_accounts (teacher_email, teacher_password, first_name, last_name, school_name, status) VALUES (?, ?, ?, ?, ?, 'active')");
        $password = unusableTeacherPasswordHash();
        $school = SCHOOL_NAME;
        $insert_stmt->bind_param("sssss", $email, $password, $firstName, $lastName, $school);
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