<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/admin_push_notification.php';
require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
requireAdminSession();

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

// Fetch names before deleting so we can include them in the notification
$deletedNames = [];
$fetchStmt = $conn->prepare(
    "SELECT id, CONCAT(first_name,' ',last_name) AS full_name, role
     FROM admin_accounts WHERE id IN ($placeholders) AND is_deleted = 0"
);
if ($fetchStmt) {
    $fetchStmt->bind_param($types, ...$ids);
    $fetchStmt->execute();
    $fetchRows = $fetchStmt->get_result();
    while ($r = $fetchRows->fetch_assoc()) {
        $deletedNames[] = trim($r['full_name']) . ' (' . ucfirst($r['role']) . ')';
    }
    $fetchStmt->close();
}

// Soft delete: set is_deleted = 1, record the deletion timestamp
$stmt = $conn->prepare(
    "UPDATE admin_accounts SET is_deleted = 1, deleted_at = NOW()
     WHERE id IN ($placeholders) AND is_deleted = 0"
);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Delete query failed: ' . $conn->error]);
    $conn->close();
    exit;
}
$stmt->bind_param($types, ...$ids);
$ok = $stmt->execute();
$error = $stmt->error;
$stmt->close();

if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $error]);
    $conn->close();
    exit;
}

// Keep the teacher-facing learner inactive while the student account is deleted.
$teacherConn = getTeacherDatabaseConnection();
if ($teacherConn) {
    $studentStmt = $teacherConn->prepare(
        "UPDATE students SET status = 'inactive' WHERE admin_account_id IN ($placeholders)"
    );
    if ($studentStmt) {
        $studentStmt->bind_param($types, ...$ids);
        $studentStmt->execute();
        $studentStmt->close();
    }
    $teacherConn->close();
}

if ($ok && !empty($deletedNames)) {
    $count = count($deletedNames);
    $title = $count === 1 ? 'Account Deleted' : "{$count} Accounts Deleted";
    $msg   = implode(', ', $deletedNames) . ' ' . ($count === 1 ? 'has' : 'have') . ' been moved to deleted accounts.';
    pushAdminNotification($conn, 'account', $title, $msg);
}

$conn->close();

echo json_encode(['success' => true]);
?>
