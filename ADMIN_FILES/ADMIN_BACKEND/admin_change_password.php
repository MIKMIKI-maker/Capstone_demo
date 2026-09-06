<?php
require_once __DIR__ . '/db.php';
requireAdminSession();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
$new_password     = isset($_POST['new_password'])     ? $_POST['new_password']     : '';
$confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

if (!$current_password || !$new_password || !$confirm_password) {
    echo json_encode(['success' => false, 'message' => 'All password fields are required']);
    exit;
}
if ($new_password !== $confirm_password) {
    echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
    exit;
}
if (strlen($new_password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
    exit;
}

$adminId = $_SESSION['admin_id'];

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$stmt = $conn->prepare("SELECT admin_password FROM admin_accounts WHERE id = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || !password_verify($current_password, $row['admin_password'])) {
    echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
    $conn->close();
    exit;
}

$newHash = password_hash($new_password, PASSWORD_DEFAULT);
$upd = $conn->prepare("UPDATE admin_accounts SET admin_password = ? WHERE id = ?");
$upd->bind_param("si", $newHash, $adminId);
$success = $upd->execute();
$upd->close();
$conn->close();

echo json_encode(['success' => (bool)$success, 'message' => $success ? 'Password updated successfully' : 'Could not update password']);
