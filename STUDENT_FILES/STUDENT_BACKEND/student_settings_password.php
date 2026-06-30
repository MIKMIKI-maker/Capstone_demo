<?php
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/db.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$admin_account_id = isset($_POST['admin_account_id']) ? intval($_POST['admin_account_id']) : 0;
$current_pw = isset($_POST['current_password']) ? trim($_POST['current_password']) : '';
$new_pw = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';

if (!$admin_account_id || !$current_pw || !$new_pw) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if (strlen($new_pw) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
    exit;
}

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Verify current password
$stmt = $conn->prepare("SELECT admin_password FROM admin_accounts WHERE id = ? AND role = 'student'");
$stmt->bind_param("i", $admin_account_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Account not found']);
    $conn->close();
    exit;
}

$stored = $row['admin_password'];
$match  = password_verify($current_pw, $stored)
       || (!str_starts_with($stored, '$2y$') && $current_pw === $stored);
if (!$match) {
    echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
    $conn->close();
    exit;
}

$new_pw_hash = password_hash($new_pw, PASSWORD_DEFAULT);
$stmt2 = $conn->prepare("UPDATE admin_accounts SET admin_password = ? WHERE id = ? AND role = 'student'");
$stmt2->bind_param("si", $new_pw_hash, $admin_account_id);

if ($stmt2->execute()) {
    echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update password']);
}

$stmt2->close();
$conn->close();
?>
