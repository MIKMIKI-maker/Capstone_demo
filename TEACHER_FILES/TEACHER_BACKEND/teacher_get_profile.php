<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/db.php';

header('Content-Type: application/json');

// Get teacher_id from POST or SESSION
$teacher_id = isset($_POST['teacher_id']) ? intval($_POST['teacher_id']) : 
              (isset($_SESSION['teacher_id']) ? intval($_SESSION['teacher_id']) : 1);

$conn = getTeacherDatabaseConnection();
$admin_conn = getDatabaseConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// CHANGED: Added bio field to SELECT so the teacher's bio is returned to the settings page
$sql = "SELECT id, teacher_email, first_name, last_name, school_name, phone_number, specialization, COALESCE(bio,'') as bio, class_section, status
        FROM teacher_accounts WHERE id=?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Query prepare failed: ' . $conn->error]);
    $conn->close();
    if ($admin_conn) $admin_conn->close();
    exit;
}

$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $first_name = $row['first_name'];
    $last_name  = $row['last_name'];

    $display_name = trim($first_name . ' ' . $last_name) ?: $row['teacher_email'];
    $avatar_letter = strtoupper(substr($display_name, 0, 1));

    // Use class_section from teacher_accounts; fall back to condition_info from admin_accounts
    $class_section = $row['class_section'] ?? '';
    $profile_photo = '';
    if ($admin_conn) {
        $aStmt = $admin_conn->prepare("SELECT condition_info, COALESCE(profile_photo,'') AS profile_photo FROM admin_accounts WHERE admin_email = ? AND role='teacher' LIMIT 1");
        if ($aStmt) {
            $aStmt->bind_param("s", $row['teacher_email']);
            $aStmt->execute();
            $aRow = $aStmt->get_result()->fetch_assoc();
            $aStmt->close();
            if ($class_section === '') $class_section = $aRow['condition_info'] ?? '';
            $profile_photo = $aRow['profile_photo'] ?? '';
        }
    }

    echo json_encode([
        'success' => true,
        'teacher' => [
            'id'             => $row['id'],
            'email'          => $row['teacher_email'],
            'name'           => $display_name,
            'firstName'      => $first_name,
            'lastName'       => $last_name,
            'schoolName'     => $row['school_name'],
            'phone'          => $row['phone_number'],
            'specialization' => $row['specialization'],
            'bio'            => $row['bio'],
            'classSection'   => $class_section,
            'status'         => $row['status'],
            'avatarLetter'   => $avatar_letter,
            'profilePhoto'   => $profile_photo
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Teacher not found']);
}

$stmt->close();
$conn->close();
if ($admin_conn) {
    $admin_conn->close();
}
