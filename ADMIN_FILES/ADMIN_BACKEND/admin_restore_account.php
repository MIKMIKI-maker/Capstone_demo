<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
requireAdminSession();

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
        "UPDATE admin_accounts SET is_deleted = 0, deleted_at = NULL, status = 'active'
         WHERE id IN ($placeholders) AND is_deleted = 1 AND permanently_deleted = 0"
    );
    if (!$stmt) { echo json_encode(['success' => false, 'message' => 'Prepare failed']); $conn->close(); exit; }
    $stmt->bind_param($types, ...$ids);
    $ok = $stmt->execute();
    $stmt->close();

    // Match the learner status to the restored account's current status.
    $teacherConn = getTeacherDatabaseConnection();
    if ($teacherConn) {
        $studentStmt = $teacherConn->prepare(
            "UPDATE students s
             INNER JOIN admin_accounts a ON a.id = s.admin_account_id
             SET s.status = CASE WHEN a.status = 'active' AND a.is_deleted = 0
                                 THEN 'active' ELSE 'inactive' END
             WHERE s.admin_account_id IN ($placeholders)"
        );
        if ($studentStmt) {
            $studentStmt->bind_param($types, ...$ids);
            $studentStmt->execute();
            $studentStmt->close();
        }
        $teacherConn->close();
    }

    $conn->close();
    echo json_encode(['success' => (bool)$ok]);
    exit;
}

// ── PERMANENT DELETE ──────────────────────────────────────────────────────────
// Does NOT delete anything - the account row, and every activity/upload/log
// that references it, stays in SQL for good (an audit trail, and undeleted
// data can't accidentally be lost to a schema quirk like teacher_accounts'
// ON DELETE CASCADE onto teacher_activities). "Permanent" here just means
// no longer restorable: flipping permanently_deleted=1 drops it out of the
// Deleted Accounts trash, and it stays hidden everywhere else the same way
// any is_deleted=1 row already does - nothing else needs to change for that.
//
// The email is also retired (prefixed with "deleted_<id>_") so the original
// address is immediately free for a genuinely new account. Reusing the row
// instead - resetting it and letting a new person log in with it - would
// silently attach that new person to the old account's teacher_accounts.id,
// resurfacing the previous owner's activities/uploads under the new name.
// Retiring the email keeps the old row (and its history) permanently intact
// under its own identity while the new account starts as an unrelated row.
if ($action === 'permanent') {
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

    $stmt = $conn->prepare(
        "UPDATE admin_accounts
         SET permanently_deleted = 1, admin_email = CONCAT('deleted_', id, '_', admin_email)
         WHERE id IN ($placeholders) AND is_deleted = 1"
    );
    if (!$stmt) { echo json_encode(['success' => false, 'message' => 'Prepare failed']); $conn->close(); exit; }
    $stmt->bind_param($types, ...$ids);
    $ok = $stmt->execute();
    $stmt->close();

    // Retire the matching teacher_accounts.teacher_email too, so it still
    // matches the now-retired admin_accounts row (LEFT JOINed by email in
    // Activity Library) instead of looking like an unmatched/legacy row.
    $tconn = getTeacherDatabaseConnection();
    if ($tconn) {
        foreach ($rows as $r) {
            if ($r['role'] !== 'teacher') continue;
            $newEmail = 'deleted_' . $r['id'] . '_' . $r['admin_email'];
            $rn = $tconn->prepare("UPDATE teacher_accounts SET teacher_email = ? WHERE teacher_email = ?");
            if ($rn) { $rn->bind_param("ss", $newEmail, $r['admin_email']); $rn->execute(); $rn->close(); }
        }
        $tconn->close();
    }

    $conn->close();
    echo json_encode(['success' => (bool)$ok]);
    exit;
}

$conn->close();
echo json_encode(['success' => false, 'message' => 'Unknown action']);
?>
