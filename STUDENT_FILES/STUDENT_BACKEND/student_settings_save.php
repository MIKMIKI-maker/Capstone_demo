<?php
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/db.php';
require_once __DIR__ . '/student_auth.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$admin_account_id = requireStudentSession();
$first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
$last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$stmt = $conn->prepare("UPDATE admin_accounts SET first_name = ?, last_name = ? WHERE id = ? AND role = 'student'");
$stmt->bind_param("ssi", $first_name, $last_name, $admin_account_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update profile: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
