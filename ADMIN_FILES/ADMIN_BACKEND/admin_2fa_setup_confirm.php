<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/TOTP.php';
require_once __DIR__ . '/csrf.php';
requireAdminSession();
csrf_require_valid_token();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$code = isset($_POST['code']) ? trim($_POST['code']) : '';
$pendingSecret = $_SESSION['pending_totp_secret'] ?? '';

if (!$pendingSecret) {
    echo json_encode(['success' => false, 'message' => 'No 2FA setup in progress. Please start setup again.']);
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
$adminId = $_SESSION['admin_id'];
$stmt->bind_param("ssi", $pendingSecret, $backupCodesJson, $adminId);
$success = $stmt->execute();
$stmt->close();
$conn->close();

unset($_SESSION['pending_totp_secret']);

if ($success) {
    echo json_encode(['success' => true, 'backup_codes' => $backupCodes]);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not save 2FA settings.']);
}
