<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
requireAdminSession();
csrf_require_valid_token();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$password = isset($_POST['password']) ? trim($_POST['password']) : '';
$adminId = $_SESSION['admin_id'];

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Re-checking the password here (not just trusting the active session)
// means a device left logged in can't be used to silently turn off 2FA.
$stmt = $conn->prepare("SELECT admin_password FROM admin_accounts WHERE id = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || !password_verify($password, $row['admin_password'])) {
    echo json_encode(['success' => false, 'message' => 'Incorrect password.']);
    $conn->close();
    exit;
}

$upd = $conn->prepare("UPDATE admin_accounts SET totp_secret = NULL, totp_enabled = 0, totp_backup_codes = NULL WHERE id = ?");
$upd->bind_param("i", $adminId);
$success = $upd->execute();
$upd->close();
$conn->close();

echo json_encode(['success' => (bool)$success]);
