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
    http_response_code(401);
    echo json_encode(['authenticated' => false]);
    exit;
}

$conn = getDatabaseConnection();
if (!$conn) {
    http_response_code(503);
    echo json_encode(['authenticated' => null, 'message' => 'Database temporarily unavailable']);
    exit;
}

$stmt = $conn->prepare('SELECT role, status, COALESCE(is_deleted, 0) AS is_deleted FROM admin_accounts WHERE id = ? LIMIT 1');
if (!$stmt) {
    $conn->close();
    http_response_code(503);
    echo json_encode(['authenticated' => null, 'message' => 'Session check temporarily unavailable']);
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

if (!$authenticated) {
    http_response_code(401);
} else {
    // portal_guard.js polls this endpoint on an interval while a page is
    // open — piggybacking a last_seen refresh here gives Manage Users a
    // real "currently online" signal (last_seen within the last couple of
    // minutes) without touching the separate `status` flag that
    // authentication above already depends on.
    $touch = getDatabaseConnection();
    if ($touch) {
        $upd = $touch->prepare('UPDATE admin_accounts SET last_seen = NOW() WHERE id = ?');
        if ($upd) { $upd->bind_param('i', $adminId); $upd->execute(); $upd->close(); }
        $touch->close();
    }
}

echo json_encode([
    'authenticated' => $authenticated,
    'role' => $authenticated ? $accountRole : null,
]);
?>
