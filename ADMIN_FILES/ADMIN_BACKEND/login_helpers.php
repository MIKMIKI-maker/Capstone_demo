<?php
// Shared by login_screen_submit.php (normal login) and
// admin_force_password_change.php (completes login after a forced
// first-login password change) - both need to land the user in the exact
// same session/response shape, so this logic lives in one place instead of
// being duplicated and risking drift between the two entry points.

// Function to sync teacher account to teacher_accounts table.
// Returns the teacher_accounts.id (0 on failure) so the caller doesn't need
// a second, separate lookup connection to find it (that separate lookup was
// unreliable and left $_SESSION['teacher_id'] unset after login).
function syncTeacherAccount($email, $first_name, $last_name) {
    $teacher_db_path = __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
    if (!file_exists($teacher_db_path)) return 0;

    require_once $teacher_db_path;
    if (!function_exists('getTeacherDatabaseConnection')) return 0;

    $teacher_conn = getTeacherDatabaseConnection();
    if (!$teacher_conn) return 0;

    $teacher_id = 0;
    $check_stmt = $teacher_conn->prepare("SELECT id FROM teacher_accounts WHERE teacher_email = ?");
    if ($check_stmt) {
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        if ($row = $check_stmt->get_result()->fetch_assoc()) {
            $teacher_id = (int)$row['id'];
        }
        $check_stmt->close();
    }

    if ($teacher_id > 0) {
        $update_stmt = $teacher_conn->prepare("UPDATE teacher_accounts SET first_name = ?, last_name = ? WHERE id = ?");
        if ($update_stmt) {
            $update_stmt->bind_param("ssi", $first_name, $last_name, $teacher_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
    } else {
        $insert_stmt = $teacher_conn->prepare("INSERT INTO teacher_accounts (teacher_email, teacher_password, first_name, last_name, school_name, status) VALUES (?, ?, ?, ?, 'Mamatid Elementary School', 'active')");
        if ($insert_stmt) {
            $password = password_hash('Teacher@123', PASSWORD_DEFAULT);
            $insert_stmt->bind_param("ssss", $email, $password, $first_name, $last_name);
            if ($insert_stmt->execute()) {
                $teacher_id = (int)$teacher_conn->insert_id;
            }
            $insert_stmt->close();
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

// Grants the real session and builds the success response, shared by the
// normal login path and admin_force_password_change.php's completion of a
// forced first-login password change - both need identical behavior here.
// $row must have: id, first_name, last_name, role, profile_photo.
function completeAccountSession($conn, $row, $email) {
    $_SESSION['admin_id']    = $row['id'];
    $_SESSION['admin_email'] = $email;
    $_SESSION['admin_name']  = trim($row['first_name'] . ' ' . $row['last_name']);
    $_SESSION['admin_role']  = $row['role'];
    $_SESSION['login_time']  = date('Y-m-d H:i:s');

    $upd = $conn->prepare("UPDATE admin_accounts SET last_login = NOW(), last_seen = NOW(), status = 'active' WHERE id = ?");
    if ($upd) { $upd->bind_param("i", $row['id']); $upd->execute(); $upd->close(); }

    $teacher_id      = null;
    $student_record  = null;
    $teacher_section = '';
    if ($row['role'] === 'teacher') {
        $teacher_id = syncTeacherAccount($email, $row['first_name'], $row['last_name']);
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

    if ($student_record) {
        $response['student_record_id'] = $student_record['student_record_id'];
        $response['teacher_id']        = $student_record['teacher_id'];
        $response['student_condition'] = $student_record['disability_type'];
        $response['student_grade']     = $student_record['grade_level'];
    }

    return $response;
}
