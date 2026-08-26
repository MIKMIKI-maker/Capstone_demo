<?php
require_once __DIR__ . '/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$id = (int)($_SESSION['admin_id'] ?? 0);
if (!$id) { http_response_code(401); echo json_encode(['active' => false]); exit; }

$conn = getDatabaseConnection();
if (!$conn) { http_response_code(503); echo json_encode(['active' => null, 'error' => 'Database unavailable']); exit; }

$stmt = $conn->prepare("SELECT is_deleted, status FROM admin_accounts WHERE id = ? LIMIT 1");
if (!$stmt) { $conn->close(); http_response_code(503); echo json_encode(['active' => null]); exit; }
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$row) { echo json_encode(['active' => false]); exit; }
echo json_encode(['active' => (int)$row['is_deleted'] === 0 && strtolower((string)$row['status']) === 'active']);
?>
