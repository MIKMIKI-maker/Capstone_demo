<?php
// Reached only mid-login (see admin_2fa_setup_generate.php for why this
// isn't requireAdminSession() — no real admin session exists yet at this
// point in the mandatory first-time-setup flow).
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ini_set('session.cookie_secure', '1');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/TOTP.php';

header('Content-Type: application/json');

$adminId = $_SESSION['pending_2fa_setup_admin_id'] ?? 0;
if (!$adminId) {
    echo json_encode(['success' => false, 'message' => 'No pending 2FA setup. Please log in again.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$code = isset($_POST['code']) ? trim($_POST['code']) : '';
$pendingSecret = $_SESSION['pending_totp_secret'] ?? '';

if (!$pendingSecret) {
    echo json_encode(['success' => false, 'message' => 'No 2FA setup in progress. Please log in again.']);
    exit;
}

if (!TOTP::verifyCode($pendingSecret, $code)) {
    echo json_encode(['success' => false, 'message' => 'Incorrect code. Please check your authenticator app and try again.']);
    exit;
}

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$backupCodes = TOTP::generateBackupCodes();
// Backup codes are hashed the same way passwords are — a DB leak shouldn't
// hand out working codes any more than it should hand out working passwords.
$hashedBackupCodes = array_map(fn($c) => password_hash($c, PASSWORD_DEFAULT), $backupCodes);

$stmt = $conn->prepare("UPDATE admin_accounts SET totp_secret = ?, totp_enabled = 1, totp_backup_codes = ? WHERE id = ?");
$backupCodesJson = json_encode($hashedBackupCodes);
$stmt->bind_param("ssi", $pendingSecret, $backupCodesJson, $adminId);
$success = $stmt->execute();
$stmt->close();

unset($_SESSION['pending_totp_secret']);

if (!$success) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Could not save 2FA settings.']);
    exit;
}

// Setup just replaced the password-only login this admin would otherwise
// have had — completing the real session here means "Setup Complete" goes
// straight to the Dashboard instead of asking them to log in a second time.
$infoStmt = $conn->prepare("SELECT admin_email, first_name, last_name, role, COALESCE(profile_photo,'') AS profile_photo FROM admin_accounts WHERE id = ?");
$infoStmt->bind_param("i", $adminId);
$infoStmt->execute();
$info = $infoStmt->get_result()->fetch_assoc();
$infoStmt->close();

unset($_SESSION['pending_2fa_setup_admin_id']);
session_regenerate_id(true);

$_SESSION['admin_id']    = $adminId;
$_SESSION['admin_email'] = $info['admin_email'];
$_SESSION['admin_name']  = trim($info['first_name'] . ' ' . $info['last_name']);
$_SESSION['admin_role']  = $info['role'];
$_SESSION['login_time']  = date('Y-m-d H:i:s');

$upd = $conn->prepare("UPDATE admin_accounts SET last_login = NOW(), last_seen = NOW(), status = 'active' WHERE id = ?");
$upd->bind_param("i", $adminId);
$upd->execute();
$upd->close();
$conn->close();

echo json_encode([
    'success'       => true,
    'backup_codes'  => $backupCodes,
    'admin_id'      => $adminId,
    'admin_name'    => $_SESSION['admin_name'],
    'role'          => $info['role'],
    'profile_photo' => $info['profile_photo'] ?? '',
]);
