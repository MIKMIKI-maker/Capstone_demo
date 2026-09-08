<?php
// Reached only mid-login (see login_screen_submit.php) - a Teacher/Student
// account whose password was set by an Admin (role default or typed) must
// replace it here before a real session is granted. No requireAdminSession()
// gate: the caller isn't logged in yet, only holds the "pending" marker set
// right after password auth succeeded.
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ini_set('session.cookie_secure', '1');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/login_helpers.php';
require_once __DIR__ . '/password_policy.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$pendingId   = $_SESSION['pending_pwd_change_id']   ?? 0;
$pendingRole = $_SESSION['pending_pwd_change_role'] ?? '';
if (!$pendingId || !in_array($pendingRole, ['teacher', 'student'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'No pending password change. Please log in again.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$newPassword     = isset($_POST['new_password'])     ? trim($_POST['new_password'])     : '';
$confirmPassword = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';

if ($newPassword === '' || $confirmPassword === '') {
    echo json_encode(['status' => 'error', 'message' => 'Both password fields are required.']);
    exit;
}
if ($newPassword !== $confirmPassword) {
    echo json_encode(['status' => 'error', 'message' => 'Passwords do not match.']);
    exit;
}
if (strlen($newPassword) < 8) {
    echo json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters.']);
    exit;
}

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

$stmt = $conn->prepare("SELECT id, admin_email, first_name, last_name, role, COALESCE(profile_photo,'') AS profile_photo, COALESCE(is_deleted,0) AS is_deleted FROM admin_accounts WHERE id = ? AND role = ?");
$stmt->bind_param("is", $pendingId, $pendingRole);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || !empty($row['is_deleted'])) {
    unset($_SESSION['pending_pwd_change_id'], $_SESSION['pending_pwd_change_role']);
    echo json_encode(['status' => 'error', 'message' => 'Account not found. Please log in again.']);
    $conn->close();
    exit;
}

$violation = passwordPolicyViolation($newPassword, $row['first_name'] ?? '', $row['last_name'] ?? '');
if ($violation) {
    echo json_encode(['status' => 'error', 'message' => $violation]);
    $conn->close();
    exit;
}

$newHash = password_hash($newPassword, PASSWORD_DEFAULT);
$upd = $conn->prepare("UPDATE admin_accounts SET admin_password = ?, must_change_password = 0 WHERE id = ?");
$upd->bind_param("si", $newHash, $row['id']);
$ok = $upd->execute();
$upd->close();

if (!$ok) {
    echo json_encode(['status' => 'error', 'message' => 'Could not update password. Please try again.']);
    $conn->close();
    exit;
}

unset($_SESSION['pending_pwd_change_id'], $_SESSION['pending_pwd_change_role']);
session_regenerate_id(true);

echo json_encode(completeAccountSession($conn, $row, $row['admin_email']));
$conn->close();
