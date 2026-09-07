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
         WHERE id IN ($placeholders) AND is_deleted = 1"
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
// The account row itself is genuinely gone from admin_accounts - not a flag,
// a real DELETE - so its email is immediately reusable for a new account.
//
// teacher_accounts is deliberately NOT deleted: it has teacher_activities/
// teacher_uploaded_materials pointing at it (teacher_activities via an
// ON DELETE CASCADE FK), so removing it would silently erase that teacher's
// activities/uploads along with the account - the data is meant to survive.
// Instead it's marked status='inactive' (the signal Activity Library/Recent
// Activity/Uploads now check directly - no more join to admin_accounts,
// since that row won't exist to join to) and its email is retired
// ("deleted_<id>_" prefix) so a brand new account using the same email gets
// its own fresh teacher_accounts row instead of reattaching to this one and
// resurfacing the old owner's history under the new name.
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

    $tconn = getTeacherDatabaseConnection();
    if ($tconn) {
        foreach ($rows as $r) {
            if ($r['role'] !== 'teacher') continue;
            $newEmail = 'deleted_' . $r['id'] . '_' . $r['admin_email'];
            $rn = $tconn->prepare("UPDATE teacher_accounts SET teacher_email = ?, status = 'inactive' WHERE teacher_email = ?");
            if ($rn) { $rn->bind_param("ss", $newEmail, $r['admin_email']); $rn->execute(); $rn->close(); }
        }
        $tconn->close();
    }

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
