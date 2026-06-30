<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';

header('Content-Type: application/json');

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$data   = json_decode(file_get_contents('php://input'), true);
$action = isset($data['action']) ? trim($data['action']) : '';
$ids    = isset($data['ids']) && is_array($data['ids'])
          ? array_values(array_filter($data['ids'], function($id){ return is_numeric($id) && $id > 0; }))
          : [];

if (!$action || empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'Missing action or IDs']);
    $conn->close();
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types        = str_repeat('i', count($ids));

// ── RESTORE ──────────────────────────────────────────────────────────────────
if ($action === 'restore') {
    $stmt = $conn->prepare(
        "UPDATE admin_accounts SET is_deleted = 0, deleted_at = NULL
         WHERE id IN ($placeholders) AND is_deleted = 1"
    );
    if (!$stmt) { echo json_encode(['success' => false, 'message' => 'Prepare failed']); $conn->close(); exit; }
    $stmt->bind_param($types, ...$ids);
    $ok = $stmt->execute();
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => (bool)$ok]);
    exit;
}

// ── PERMANENT DELETE ──────────────────────────────────────────────────────────
if ($action === 'permanent') {
    // Collect role info before deleting
    $infoStmt = $conn->prepare(
        "SELECT id, role, admin_email FROM admin_accounts
         WHERE id IN ($placeholders) AND is_deleted = 1"
    );
    $infoStmt->bind_param($types, ...$ids);
    $infoStmt->execute();
    $res  = $infoStmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $infoStmt->close();

    $studentAdminIds = [];
    $teacherEmails   = [];
    foreach ($rows as $r) {
        if ($r['role'] === 'student') $studentAdminIds[] = (int)$r['id'];
        if ($r['role'] === 'teacher') $teacherEmails[]   = $r['admin_email'];
    }

    $tconn = getTeacherDatabaseConnection();

    // Cascade: remove student enrollment records
    if (!empty($studentAdminIds) && $tconn) {
        $sph    = implode(',', array_fill(0, count($studentAdminIds), '?'));
        $stypes = str_repeat('i', count($studentAdminIds));
        $ds = $tconn->prepare("DELETE FROM students WHERE admin_account_id IN ($sph)");
        if ($ds) { $ds->bind_param($stypes, ...$studentAdminIds); $ds->execute(); $ds->close(); }
    }

    // Cascade: remove teacher accounts (and their associated records)
    if (!empty($teacherEmails) && $tconn) {
        $tph    = implode(',', array_fill(0, count($teacherEmails), '?'));
        $ttypes = str_repeat('s', count($teacherEmails));
        $dt = $tconn->prepare("DELETE FROM teacher_accounts WHERE teacher_email IN ($tph)");
        if ($dt) { $dt->bind_param($ttypes, ...$teacherEmails); $dt->execute(); $dt->close(); }
    }

    if ($tconn) $tconn->close();

    // Hard delete from admin_accounts (only soft-deleted rows)
    $delStmt = $conn->prepare(
        "DELETE FROM admin_accounts WHERE id IN ($placeholders) AND is_deleted = 1"
    );
    if (!$delStmt) { echo json_encode(['success' => false, 'message' => 'Prepare failed']); $conn->close(); exit; }
    $delStmt->bind_param($types, ...$ids);
    $ok = $delStmt->execute();
    $delStmt->close();
    $conn->close();
    echo json_encode(['success' => (bool)$ok]);
    exit;
}

$conn->close();
echo json_encode(['success' => false, 'message' => 'Unknown action']);
?>
