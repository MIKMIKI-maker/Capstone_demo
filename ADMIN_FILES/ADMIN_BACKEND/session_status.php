<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$adminId = (int)($_SESSION['admin_id'] ?? 0);
$sessionRole = strtolower(trim((string)($_SESSION['admin_role'] ?? '')));
if (!$adminId || !$sessionRole) {
    echo json_encode(['authenticated' => false]);
    exit;
}

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['authenticated' => false]);
    exit;
}

$stmt = $conn->prepare('SELECT role, status, COALESCE(is_deleted, 0) AS is_deleted FROM admin_accounts WHERE id = ? LIMIT 1');
if (!$stmt) {
    $conn->close();
    echo json_encode(['authenticated' => false]);
    exit;
}
$stmt->bind_param('i', $adminId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

$accountRole = strtolower(trim((string)($row['role'] ?? '')));
$authenticated = $row
    && $accountRole === $sessionRole
    && (int)$row['is_deleted'] === 0
    && strtolower((string)$row['status']) === 'active';

echo json_encode([
    'authenticated' => $authenticated,
    'role' => $authenticated ? $accountRole : null,
]);
?>
