<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ini_set('session.cookie_secure', '1');
session_start();

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Function to sync teacher account to teacher_accounts table.
// Returns the teacher_accounts.id (0 on failure) so the caller doesn't need
// a second, separate lookup connection to find it (that separate lookup was
// unreliable and left $_SESSION['teacher_id'] unset after login).
function syncTeacherAccount($email, $first_name, $last_name, &$debug = null) {
    $teacher_db_path = __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
    if (!file_exists($teacher_db_path)) { $debug = 'no teacher db.php file'; return 0; }

    require_once $teacher_db_path;
    if (!function_exists('getTeacherDatabaseConnection')) { $debug = 'getTeacherDatabaseConnection missing'; return 0; }

    $teacher_conn = getTeacherDatabaseConnection();
    if (!$teacher_conn) { $debug = 'getTeacherDatabaseConnection returned null'; return 0; }

    $teacher_id = 0;
    $check_stmt = $teacher_conn->prepare("SELECT id FROM teacher_accounts WHERE teacher_email = ?");
    if ($check_stmt) {
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        if ($row = $check_stmt->get_result()->fetch_assoc()) {
            $teacher_id = (int)$row['id'];
        }
        $check_stmt->close();
    } else {
        $debug = 'check prepare failed: ' . $teacher_conn->error;
    }

    if ($teacher_id > 0) {
        $update_stmt = $teacher_conn->prepare("UPDATE teacher_accounts SET first_name = ?, last_name = ? WHERE id = ?");
        if ($update_stmt) {
            $update_stmt->bind_param("ssi", $first_name, $last_name, $teacher_id);
            $update_stmt->execute();
            $update_stmt->close();
        } else {
            $debug = 'update prepare failed: ' . $teacher_conn->error;
        }
    } else {
        $insert_stmt = $teacher_conn->prepare("INSERT INTO teacher_accounts (teacher_email, teacher_password, first_name, last_name, school_name, status) VALUES (?, ?, ?, ?, 'Mamatid Elementary School', 'active')");
        if ($insert_stmt) {
            $password = password_hash('Teacher@123', PASSWORD_DEFAULT);
            $insert_stmt->bind_param("ssss", $email, $password, $first_name, $last_name);
            if ($insert_stmt->execute()) {
                $teacher_id = (int)$teacher_conn->insert_id;
            } else {
                $debug = 'insert execute failed: ' . $insert_stmt->error;
            }
            $insert_stmt->close();
        } else {
            $debug = 'insert prepare failed: ' . $teacher_conn->error;
        }
    }

    $teacher_conn->close();
    return $teacher_id;
}

// Function to get student record from teacher DB
function getStudentRecord($admin_account_id, $full_name = '') {
    $teacher_db_path = __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
    if (!file_exists($teacher_db_path)) return null;

    require_once $teacher_db_path;
    if (!function_exists('getTeacherDatabaseConnection')) return null;

    $conn = getTeacherDatabaseConnection();
    if (!$conn) return null;

    $row = null;

    if ($admin_account_id > 0) {
        $stmt = $conn->prepare("SELECT id AS student_record_id, teacher_id, disability_type, grade_level, student_name FROM students WHERE admin_account_id = ? AND status = 'active' LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $admin_account_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    }

    if (!$row && $full_name !== '') {
        $stmt2 = $conn->prepare("SELECT id AS student_record_id, teacher_id, disability_type, grade_level, student_name FROM students WHERE LOWER(TRIM(student_name)) = LOWER(TRIM(?)) AND status = 'active' LIMIT 1");
        if ($stmt2) {
            $stmt2->bind_param("s", $full_name);
            $stmt2->execute();
            $row = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
            if ($row && $admin_account_id > 0) {
                $upd = $conn->prepare("UPDATE students SET admin_account_id = ? WHERE id = ?");
                if ($upd) { $upd->bind_param("ii", $admin_account_id, $row['student_record_id']); $upd->execute(); $upd->close(); }
            }
        }
    }

    $conn->close();
    return $row ?: null;
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

        if (!empty($row['is_deleted'])) {
            echo json_encode(['status' => 'error', 'code' => 'account_deleted', 'message' => 'Your account has been deactivated. Please contact your administrator.']);
            $stmt->close();
            $conn->close();
            exit;
        }

        $stored_pw = $row['admin_password'];

        $auth_ok = false;
        if (password_verify($password, $stored_pw)) {
            $auth_ok = true;
        } elseif (!str_starts_with($stored_pw, '$2y$') && $password === $stored_pw) {
            $auth_ok = true;
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            $upg = $conn->prepare("UPDATE admin_accounts SET admin_password = ? WHERE id = ?");
            if ($upg) { $upg->bind_param("si", $new_hash, $row['id']); $upg->execute(); $upg->close(); }
        }

        if ($auth_ok) {
            session_regenerate_id(true);
            $clr = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
            if ($clr) { $clr->bind_param("s", $ip); $clr->execute(); $clr->close(); }

            $_SESSION['admin_id']    = $row['id'];
            $_SESSION['admin_email'] = $email;
            $_SESSION['admin_name']  = trim($row['first_name'] . ' ' . $row['last_name']);
            $_SESSION['admin_role']  = $row['role'];
            $_SESSION['login_time']  = date('Y-m-d H:i:s');

            $upd = $conn->prepare("UPDATE admin_accounts SET last_login = NOW(), last_seen = NOW(), status = 'active' WHERE id = ?");
            if ($upd) { $upd->bind_param("i", $row['id']); $upd->execute(); $upd->close(); }

            $teacher_id     = null;
            $student_record = null;

            $teacher_section = '';
            if ($row['role'] === 'teacher') {
                $sync_debug = null;
                $teacher_id = syncTeacherAccount($email, $row['first_name'], $row['last_name'], $sync_debug);
                $_SESSION['teacher_id'] = $teacher_id;
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
            elseif ($row['role'] === 'teacher') { $response['_debug_sync_error'] = $sync_debug; }

            if ($student_record) {
                $response['student_record_id'] = $student_record['student_record_id'];
                $response['teacher_id']        = $student_record['teacher_id'];
                $response['student_condition'] = $student_record['disability_type'];
                $response['student_grade']     = $student_record['grade_level'];
            }

            echo json_encode($response);
        } else {
            $fail = $conn->prepare("INSERT INTO login_attempts (ip_address, email) VALUES (?, ?)");
            if ($fail) { $fail->bind_param("ss", $ip, $email); $fail->execute(); $fail->close(); }
            $conn->query("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");

            echo json_encode(['status' => 'error', 'message' => 'Invalid password']);
        }
    } else {
        $fail = $conn->prepare("INSERT INTO login_attempts (ip_address, email) VALUES (?, ?)");
        if ($fail) { $fail->bind_param("ss", $ip, $email); $fail->execute(); $fail->close(); }

        echo json_encode(['status' => 'error', 'message' => 'User not found']);
    }

    $stmt->close();
}

$conn->close();