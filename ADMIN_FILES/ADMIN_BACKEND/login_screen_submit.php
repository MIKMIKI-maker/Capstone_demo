<?php
error_reporting(0);
ini_set('display_errors', '0');

session_start();
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

set_error_handler(function($severity, $message, $file, $line) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $message,
        'severity' => $severity,
        'file' => $file,
        'line' => $line
    ]);
    exit;
});

register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Fatal error',
            'details' => $err['message'],
            'file' => $err['file'],
            'line' => $err['line']
        ]);
    }
});


// Function to sync teacher account to teacher_accounts table
function syncTeacherAccount($admin_id, $email, $first_name, $last_name) {
    require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
    $teacher_conn = getTeacherDatabaseConnection();
    
    if (!$teacher_conn) {
        return false;
    }
    
    // Check if teacher exists in teacher_accounts
    $check_stmt = $teacher_conn->prepare("SELECT id FROM teacher_accounts WHERE teacher_email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows == 0) {
        // Create new teacher_accounts entry with the correct name from admin_accounts
        $insert_stmt = $teacher_conn->prepare("INSERT INTO teacher_accounts (teacher_email, teacher_password, first_name, last_name, school_name, status) VALUES (?, ?, ?, ?, 'Mamatid Elementary School', 'active')");
        $password = password_hash('Teacher@123', PASSWORD_DEFAULT);
        $insert_stmt->bind_param("ssss", $email, $password, $first_name, $last_name);
        $insert_stmt->execute();
        $insert_stmt->close();
    } else {
        // Update existing teacher_accounts entry with the latest name from admin_accounts
        $update_stmt = $teacher_conn->prepare("UPDATE teacher_accounts SET first_name = ?, last_name = ? WHERE teacher_email = ?");
        $update_stmt->bind_param("sss", $first_name, $last_name, $email);
        $update_stmt->execute();
        $update_stmt->close();
    }
    
    $check_stmt->close();
    $teacher_conn->close();
    return true;
}

// Function to get student record from teacher DB
function getStudentRecord($admin_account_id, $full_name = '') {
    require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
    $conn = getTeacherDatabaseConnection();
    if (!$conn) return null;

    $row = null;

    // 1. Exact match by admin_account_id
    if ($admin_account_id > 0) {
        $stmt = $conn->prepare("SELECT id AS student_record_id, teacher_id, disability_type, grade_level, student_name FROM students WHERE admin_account_id = ? AND status = 'active' LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $admin_account_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    }

    // 2. Case-insensitive name match — no admin_account_id restriction so it
    //    catches students enrolled via the modal (admin_account_id already set)
    //    as well as manually-added ones (admin_account_id = 0 / null)
    if (!$row && $full_name !== '') {
        $stmt2 = $conn->prepare("SELECT id AS student_record_id, teacher_id, disability_type, grade_level, student_name FROM students WHERE LOWER(TRIM(student_name)) = LOWER(TRIM(?)) AND status = 'active' LIMIT 1");
        if ($stmt2) {
            $stmt2->bind_param("s", $full_name);
            $stmt2->execute();
            $row = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
            if ($row && $admin_account_id > 0) {
                // Auto-link so future logins use the fast path
                $upd = $conn->prepare("UPDATE students SET admin_account_id = ? WHERE id = ?");
                if ($upd) { $upd->bind_param("ii", $admin_account_id, $row['student_record_id']); $upd->execute(); $upd->close(); }
            }
        }
    }

    $conn->close();
    return $row ?: null;
}

// Function to get teacher_id from teacher_accounts
function getTeacherIdByEmail($email) {
    require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
    $teacher_conn = getTeacherDatabaseConnection();
    
    if (!$teacher_conn) {
        return null;
    }
    
    $stmt = $teacher_conn->prepare("SELECT id FROM teacher_accounts WHERE teacher_email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $teacher_id = null;
    if ($row = $result->fetch_assoc()) {
        $teacher_id = $row['id'];
    }
    
    $stmt->close();
    $teacher_conn->close();
    return $teacher_id;
}

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Handle POST login request
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = isset($_POST['admin_email'])    ? trim($_POST['admin_email'])    : '';
    $password = isset($_POST['admin_password']) ? trim($_POST['admin_password']) : '';

    if ($email === '' || $password === '') {
        echo json_encode(['status' => 'error', 'message' => 'Email and password required']);
        $conn->close();
        exit;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Brute-force lockout: block after 5 failed attempts from this IP within 15 minutes
    $MAX_ATTEMPTS = 5;
    $LOCKOUT_MINUTES = 15;
    $chk = $conn->prepare("SELECT COUNT(*) AS cnt FROM login_attempts WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    if ($chk) {
        $chk->bind_param("si", $ip, $LOCKOUT_MINUTES);
        $chk->execute();
        $chkRow = $chk->get_result()->fetch_assoc();
        $chk->close();
        if ($chkRow && (int)$chkRow['cnt'] >= $MAX_ATTEMPTS) {
            echo json_encode(['status' => 'error', 'code' => 'too_many_attempts', 'message' => 'Too many failed login attempts. Please try again in ' . $LOCKOUT_MINUTES . ' minutes.']);
            $conn->close();
            exit;
        }
    }

    $stmt = $conn->prepare("SELECT id, admin_password, first_name, last_name, role, COALESCE(profile_photo,'') AS profile_photo, COALESCE(is_deleted,0) AS is_deleted FROM admin_accounts WHERE admin_email = ?");
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'Database query failed']);
        $conn->close();
        exit;
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Block login for soft-deleted accounts
        if (!empty($row['is_deleted'])) {
            echo json_encode(['status' => 'error', 'code' => 'account_deleted', 'message' => 'Your account has been deactivated. Please contact your administrator.']);
            $stmt->close();
            $conn->close();
            exit;
        }

        $stored_pw = $row['admin_password'];

        // Support both bcrypt hashes and plain-text (migration: auto-upgrade on success)
        $auth_ok = false;
        if (password_verify($password, $stored_pw)) {
            $auth_ok = true;
        } elseif (!str_starts_with($stored_pw, '$2y$') && $password === $stored_pw) {
            $auth_ok = true;
            // Upgrade to bcrypt
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            $upg = $conn->prepare("UPDATE admin_accounts SET admin_password = ? WHERE id = ?");
            if ($upg) { $upg->bind_param("si", $new_hash, $row['id']); $upg->execute(); $upg->close(); }
        }

        if ($auth_ok) {
            // Clear failed attempts for this IP on success
            $clr = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
            if ($clr) { $clr->bind_param("s", $ip); $clr->execute(); $clr->close(); }

            $_SESSION['admin_id']   = $row['id'];
            $_SESSION['admin_email'] = $email;
            $_SESSION['admin_name'] = trim($row['first_name'] . ' ' . $row['last_name']);
            $_SESSION['admin_role'] = $row['role'];
            $_SESSION['login_time'] = date('Y-m-d H:i:s');

            $upd = $conn->prepare("UPDATE admin_accounts SET last_login = NOW(), last_seen = NOW(), status = 'active' WHERE id = ?");
            if ($upd) { $upd->bind_param("i", $row['id']); $upd->execute(); $upd->close(); }

            $teacher_id    = null;
            $student_record = null;

            $teacher_section = '';
            if ($row['role'] === 'teacher') {
                syncTeacherAccount($row['id'], $email, $row['first_name'], $row['last_name']);
                $teacher_id = getTeacherIdByEmail($email);
                $_SESSION['teacher_id'] = $teacher_id;
                // Get teacher's assigned section from admin_accounts.condition_info
                $cstmt = $conn->prepare("SELECT condition_info FROM admin_accounts WHERE id = ?");
                if ($cstmt) { $cstmt->bind_param("i", $row['id']); $cstmt->execute(); $crow = $cstmt->get_result()->fetch_assoc(); $cstmt->close(); $teacher_section = $crow['condition_info'] ?? ''; }
            } elseif ($row['role'] === 'student') {
                $student_full_name = trim($row['first_name'] . ' ' . $row['last_name']);
                $student_record = getStudentRecord($row['id'], $student_full_name);
            }

            $response = [
                'status'        => 'success',
                'message'       => 'Login successful',
                'role'          => $row['role'],
                'admin_name'    => $_SESSION['admin_name'],
                'admin_id'      => $row['id'],
                'profile_photo' => $row['profile_photo'] ?? '',
            ];

            if ($teacher_id) { $response['teacher_id'] = $teacher_id; $response['teacher_section'] = $teacher_section; }

            if ($student_record) {
                $response['student_record_id'] = $student_record['student_record_id'];
                $response['teacher_id']        = $student_record['teacher_id'];
                $response['student_condition'] = $student_record['disability_type'];
                $response['student_grade']     = $student_record['grade_level'];
            }

            echo json_encode($response);
        } else {
            // Record failed attempt
            $fail = $conn->prepare("INSERT INTO login_attempts (ip_address, email) VALUES (?, ?)");
            if ($fail) { $fail->bind_param("ss", $ip, $email); $fail->execute(); $fail->close(); }
            // Auto-clean old attempts
            $conn->query("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");

            echo json_encode(['status' => 'error', 'message' => 'Invalid password']);
        }
    } else {
        // Record failed attempt for non-existent user too
        $fail = $conn->prepare("INSERT INTO login_attempts (ip_address, email) VALUES (?, ?)");
        if ($fail) { $fail->bind_param("ss", $ip, $email); $fail->execute(); $fail->close(); }

        echo json_encode(['status' => 'error', 'message' => 'User not found']);
    }

    $stmt->close();
}

$conn->close();
