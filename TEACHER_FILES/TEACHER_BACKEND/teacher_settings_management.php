<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';
$teacher_id = isset($_REQUEST['teacher_id']) ? intval($_REQUEST['teacher_id']) : 1;

switch($action) {
    case 'get_profile':
        getTeacherProfile($conn, $teacher_id);
        break;
    case 'update_profile':
        updateTeacherProfile($conn, $teacher_id);
        break;
    case 'get_settings':
        getTeacherSettings($conn, $teacher_id);
        break;
    case 'update_settings':
        updateTeacherSettings($conn, $teacher_id);
        break;
    case 'change_password':
        changePassword($conn, $teacher_id);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}

$conn->close();

function getTeacherProfile($conn, $teacher_id) {
    $sql = "SELECT ta.* FROM teacher_accounts ta WHERE ta.id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Remove password from response
        unset($row['password']);
        echo json_encode(['success' => true, 'profile' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Profile not found']);
    }
    $stmt->close();
}

// CHANGED: Rewrote updateTeacherProfile — old version used wrong column names
// (teacher_name, email, phone, grade_level) that don't exist in the table.
// Fixed to use the correct columns: first_name, last_name, phone_number, specialization, bio.
// Also added bio support and a sync back to admin_accounts so both tables stay consistent.
function updateTeacherProfile($conn, $teacher_id) {
    $first_name     = isset($_POST['first_name'])     ? trim($_POST['first_name'])     : '';
    $last_name      = isset($_POST['last_name'])      ? trim($_POST['last_name'])      : '';
    $phone          = isset($_POST['phone_number'])   ? trim($_POST['phone_number'])   : '';
    $specialization = isset($_POST['specialization']) ? trim($_POST['specialization']) : '';
    $bio            = isset($_POST['bio'])            ? trim($_POST['bio'])            : '';

    if (!$first_name) {
        echo json_encode(['success' => false, 'message' => 'First name is required']);
        return;
    }

    // Update teacher_accounts with all profile fields including bio
    $sql  = "UPDATE teacher_accounts SET first_name=?, last_name=?, phone_number=?, specialization=?, bio=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssi", $first_name, $last_name, $phone, $specialization, $bio, $teacher_id);

    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to update: ' . $conn->error]);
        $stmt->close();
        return;
    }
    $stmt->close();

    // CHANGED: Sync updated name to admin_accounts so both tables stay consistent.
    // Without this, the login dashboard would still show the old name from admin_accounts.
    $email_stmt = $conn->prepare("SELECT teacher_email FROM teacher_accounts WHERE id=?");
    $email_stmt->bind_param("i", $teacher_id);
    $email_stmt->execute();
    $email_result = $email_stmt->get_result();
    $email_stmt->close();

    if ($email_row = $email_result->fetch_assoc()) {
        require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/db.php';
        $admin_conn = getDatabaseConnection();
        if ($admin_conn) {
            $upd = $admin_conn->prepare("UPDATE admin_accounts SET first_name=?, last_name=? WHERE admin_email=? AND role='teacher'");
            if ($upd) {
                $upd->bind_param("sss", $first_name, $last_name, $email_row['teacher_email']);
                $upd->execute();
                $upd->close();
            }
            $admin_conn->close();
        }
    }

    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
}

function getTeacherSettings($conn, $teacher_id) {
    // Default settings structure
    $settings = [
        'notifications_enabled' => true,
        'email_notifications' => true,
        'activity_reminders' => true,
        'progress_notifications' => true,
        'theme_preference' => 'light',
        'language' => 'en',
        'display_format' => 'compact',
        'show_hints' => true
    ];
    
    // Try to get from database if stored
    $sql = "SELECT settings_data FROM teacher_settings WHERE teacher_id=?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stored_settings = json_decode($row['settings_data'], true);
            $settings = array_merge($settings, $stored_settings);
        }
        $stmt->close();
    }
    
    echo json_encode(['success' => true, 'settings' => $settings]);
}

function updateTeacherSettings($conn, $teacher_id) {
    $settings = isset($_POST['settings']) ? $_POST['settings'] : [];
    $settings_json = json_encode($settings);
    
    // Check if record exists
    $checkSQL = "SELECT id FROM teacher_settings WHERE teacher_id=?";
    $checkStmt = $conn->prepare($checkSQL);
    $checkStmt->bind_param("i", $teacher_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $exists = $checkResult->num_rows > 0;
    $checkStmt->close();
    
    if ($exists) {
        $sql = "UPDATE teacher_settings SET settings_data=?, updated_at=NOW() WHERE teacher_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $settings_json, $teacher_id);
    } else {
        $sql = "INSERT INTO teacher_settings (teacher_id, settings_data) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $teacher_id, $settings_json);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update settings']);
    }
    $stmt->close();
}

// CHANGED: Rewrote changePassword — old version verified/stored password using md5()
// against teacher_accounts.password, but login checks admin_accounts.admin_password (plain text).
// So changing password in settings had zero effect on login. Fixed to:
// 1. Verify current password against admin_accounts (plain text, same as login)
// 2. Update admin_accounts.admin_password with the new plain text password
// 3. Also update teacher_accounts.teacher_password for consistency
function changePassword($conn, $teacher_id) {
    $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $new_password     = isset($_POST['new_password'])     ? $_POST['new_password']     : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    if (!$current_password || !$new_password || !$confirm_password) {
        echo json_encode(['success' => false, 'message' => 'All password fields are required']);
        return;
    }
    if ($new_password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
        return;
    }
    if (strlen($new_password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
        return;
    }

    // Get teacher email to look up admin_accounts
    $email_stmt = $conn->prepare("SELECT teacher_email FROM teacher_accounts WHERE id=?");
    $email_stmt->bind_param("i", $teacher_id);
    $email_stmt->execute();
    $email_row = $email_stmt->get_result()->fetch_assoc();
    $email_stmt->close();

    if (!$email_row) {
        echo json_encode(['success' => false, 'message' => 'Teacher not found']);
        return;
    }

    // Verify current password against admin_accounts (login uses this table)
    require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/db.php';
    $admin_conn = getDatabaseConnection();
    if (!$admin_conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }

    $check = $admin_conn->prepare("SELECT admin_password FROM admin_accounts WHERE admin_email=? AND role='teacher'");
    $check->bind_param("s", $email_row['teacher_email']);
    $check->execute();
    $check_row = $check->get_result()->fetch_assoc();
    $check->close();

    if (!$check_row) {
        echo json_encode(['success' => false, 'message' => 'Account not found']);
        $admin_conn->close();
        return;
    }

    $stored = $check_row['admin_password'];
    $match  = password_verify($current_password, $stored)
           || (!str_starts_with($stored, '$2y$') && $current_password === $stored);

    if (!$match) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        $admin_conn->close();
        return;
    }

    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);

    // Update admin_accounts (this is what login checks)
    $upd_admin = $admin_conn->prepare("UPDATE admin_accounts SET admin_password=? WHERE admin_email=? AND role='teacher'");
    $upd_admin->bind_param("ss", $new_hash, $email_row['teacher_email']);
    $upd_admin->execute();
    $upd_admin->close();
    $admin_conn->close();

    // Also update teacher_accounts.teacher_password for consistency
    $upd_teacher = $conn->prepare("UPDATE teacher_accounts SET teacher_password=? WHERE id=?");
    $upd_teacher->bind_param("si", $new_hash, $teacher_id);
    $upd_teacher->execute();
    $upd_teacher->close();

    echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
}
?>
