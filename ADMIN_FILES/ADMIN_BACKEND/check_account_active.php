<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) { echo json_encode(['active' => false]); exit; }

$conn = getDatabaseConnection();
// Fail open on DB error so a DB hiccup doesn't mass-kick all users
if (!$conn) { echo json_encode(['active' => true]); exit; }

$stmt = $conn->prepare("SELECT is_deleted FROM admin_accounts WHERE id = ? LIMIT 1");
if (!$stmt) { $conn->close(); echo json_encode(['active' => true]); exit; }
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$row) { echo json_encode(['active' => false]); exit; }
echo json_encode(['active' => (int)$row['is_deleted'] === 0]);
?>
