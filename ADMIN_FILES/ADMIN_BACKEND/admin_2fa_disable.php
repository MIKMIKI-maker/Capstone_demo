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

$password = isset($_POST['password']) ? trim($_POST['password']) : '';
$code     = isset($_POST['code'])     ? trim($_POST['code'])     : '';
$adminId  = $_SESSION['admin_id'];

if ($code === '') {
    echo json_encode(['success' => false, 'message' => 'Enter your 6-digit authenticator code.']);
    exit;
}

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Re-checking the password here (not just trusting the active session)
// means a device left logged in can't be used on its own to turn off 2FA.
$stmt = $conn->prepare("SELECT admin_password, totp_secret, totp_backup_codes FROM admin_accounts WHERE id = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || !password_verify($password, $row['admin_password'])) {
    echo json_encode(['success' => false, 'message' => 'Incorrect password.']);
    $conn->close();
    exit;
}

// Requiring a live code (not just the password) means an attacker who only
// has the password — say, from a leak — still can't turn 2FA off, because
// doing so also requires proving control of the authenticator device or a
// saved backup code.
$verified = TOTP::verifyCode($row['totp_secret'] ?? '', $code);
if (!$verified && $row['totp_backup_codes']) {
    $backupCodes = json_decode($row['totp_backup_codes'], true) ?: [];
    foreach ($backupCodes as $hashedCode) {
        if (password_verify(strtolower(trim($code)), $hashedCode)) { $verified = true; break; }
    }
}

if (!$verified) {
    echo json_encode(['success' => false, 'message' => 'Incorrect authenticator code.']);
    $conn->close();
    exit;
}

// Disabling only clears the secret — it doesn't grant a free pass. The next
// login will route back through the mandatory setup step (see
// login_screen_submit.php), so this is really "re-pair a new device," not
// "turn 2FA off."
$upd = $conn->prepare("UPDATE admin_accounts SET totp_secret = NULL, totp_enabled = 0, totp_backup_codes = NULL WHERE id = ?");
$upd->bind_param("i", $adminId);
$success = $upd->execute();
$upd->close();
$conn->close();

echo json_encode(['success' => (bool)$success]);
