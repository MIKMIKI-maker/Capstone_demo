<?php
require_once __DIR__ . '/db.php';
requireAdminSession();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$adminId = (int)($_SESSION['admin_id'] ?? 0);
$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$stmt = $conn->prepare("SELECT id, admin_email, first_name, last_name, role, school_name, condition_info, COALESCE(profile_photo, '') AS profile_photo FROM admin_accounts WHERE id = ? AND (is_deleted = 0 OR is_deleted IS NULL) LIMIT 1");
if (!$stmt) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Profile query failed']);
    exit;
}
$stmt->bind_param('i', $adminId);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$profile) {
    echo json_encode(['success' => false, 'message' => 'Admin profile not found']);
    exit;
}

echo json_encode(['success' => true] + $profile);
?>
