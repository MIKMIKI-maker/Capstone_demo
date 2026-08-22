<?php
require_once __DIR__ . '/db.php';
requireAdminSession();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Clear all notifications (delete them)
$sql = "DELETE FROM admin_notifications";
$result = $conn->query($sql);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'All notifications cleared']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to clear notifications']);
}

$conn->close();
?>
