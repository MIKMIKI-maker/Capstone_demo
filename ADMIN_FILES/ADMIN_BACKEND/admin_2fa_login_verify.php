<?php
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ini_set('session.cookie_secure', '1');
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/TOTP.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

// Deliberately not requireAdminSession() — at this point in the flow the
// admin_id/admin_role session vars aren't set yet (that's the whole point
// of the pending step); only this narrower marker from a just-verified
// password check is.
$adminId = $_SESSION['pending_2fa_admin_id'] ?? 0;
if (!$adminId) {
    echo json_encode(['status' => 'error', 'message' => 'No pending 2FA login. Please log in again.']);
    exit;
}

$code = isset($_POST['code']) ? trim($_POST['code']) : '';
if ($code === '') {
    echo json_encode(['status' => 'error', 'message' => 'Please enter your 2FA code']);
    exit;
}

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

$stmt = $conn->prepare("SELECT id, admin_email, first_name, last_name, role, COALESCE(profile_photo,'') AS profile_photo, totp_secret, totp_backup_codes FROM admin_accounts WHERE id = ? AND role = 'admin'");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'Account not found']);
    $conn->close();
    exit;
}

$verified = TOTP::verifyCode($row['totp_secret'] ?? '', $code);

// Fall back to a backup code if the 6-digit code didn't match — each one is
// single-use, so a match here also rewrites the stored list without it.
if (!$verified && $row['totp_backup_codes']) {
    $backupCodes = json_decode($row['totp_backup_codes'], true) ?: [];
    foreach ($backupCodes as $index => $hashedCode) {
        if (password_verify(strtolower(trim($code)), $hashedCode)) {
            $verified = true;
            unset($backupCodes[$index]);
            $newBackupCodesJson = json_encode(array_values($backupCodes));
            $upd = $conn->prepare("UPDATE admin_accounts SET totp_backup_codes = ? WHERE id = ?");
            $upd->bind_param("si", $newBackupCodesJson, $adminId);
            $upd->execute();
            $upd->close();
            break;
        }
    }
}

if (!$verified) {
    echo json_encode(['status' => 'error', 'message' => 'Incorrect code. Please try again.']);
    $conn->close();
    exit;
}

unset($_SESSION['pending_2fa_admin_id']);
session_regenerate_id(true);

$_SESSION['admin_id']    = $row['id'];
$_SESSION['admin_email'] = $row['admin_email'];
$_SESSION['admin_name']  = trim($row['first_name'] . ' ' . $row['last_name']);
$_SESSION['admin_role']  = $row['role'];
$_SESSION['login_time']  = date('Y-m-d H:i:s');

$upd = $conn->prepare("UPDATE admin_accounts SET last_login = NOW(), last_seen = NOW(), status = 'active' WHERE id = ?");
if ($upd) { $upd->bind_param("i", $row['id']); $upd->execute(); $upd->close(); }

echo json_encode([
    'status'        => 'success',
    'message'       => 'Login successful',
    'role'          => $row['role'],
    'admin_name'    => $_SESSION['admin_name'],
    'admin_id'      => $row['id'],
    'profile_photo' => $row['profile_photo'] ?? '',
]);

$conn->close();
