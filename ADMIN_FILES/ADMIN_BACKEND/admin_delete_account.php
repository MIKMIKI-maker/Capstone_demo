<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['ids']) || !is_array($data['ids']) || empty($data['ids'])) {
    echo json_encode(['success' => false, 'message' => 'No IDs provided']);
    $conn->close();
    exit;
}

$ids = array_values(array_filter($data['ids'], function ($id) {
    return is_numeric($id) && $id > 0;
}));

if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'Invalid IDs']);
    $conn->close();
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types        = str_repeat('i', count($ids));

// Soft delete: set is_deleted = 1, record the deletion timestamp
$stmt = $conn->prepare(
    "UPDATE admin_accounts SET is_deleted = 1, deleted_at = NOW()
     WHERE id IN ($placeholders) AND is_deleted = 0"
);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed']);
    $conn->close();
    exit;
}
$stmt->bind_param($types, ...$ids);
$ok = $stmt->execute();
$stmt->close();
$conn->close();

echo json_encode(['success' => (bool)$ok]);
?>
