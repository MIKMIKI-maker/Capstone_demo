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

        // teacher_uploaded_materials.teacher_id has no FK/cascade of its own
        // (unlike teacher_activities' ON DELETE CASCADE) - capture the
        // teacher_accounts ids before they're deleted below so their
        // uploaded files' metadata doesn't linger as an orphaned row
        // (dangling teacher_id, blank teacher name in Activity Library).
        $teacherAccountIds = [];
        $tidStmt = $tconn->prepare("SELECT id FROM teacher_accounts WHERE teacher_email IN ($tph)");
        if ($tidStmt) {
            $tidStmt->bind_param($ttypes, ...$teacherEmails);
            $tidStmt->execute();
            $tidRes = $tidStmt->get_result();
            while ($r = $tidRes->fetch_assoc()) { $teacherAccountIds[] = (int)$r['id']; }
            $tidStmt->close();
        }

        $dt = $tconn->prepare("DELETE FROM teacher_accounts WHERE teacher_email IN ($tph)");
        if ($dt) { $dt->bind_param($ttypes, ...$teacherEmails); $dt->execute(); $dt->close(); }

        if (!empty($teacherAccountIds)) {
            $mph    = implode(',', array_fill(0, count($teacherAccountIds), '?'));
            $mtypes = str_repeat('i', count($teacherAccountIds));
            $dm = $tconn->prepare("DELETE FROM teacher_uploaded_materials WHERE teacher_id IN ($mph)");
            if ($dm) { $dm->bind_param($mtypes, ...$teacherAccountIds); $dm->execute(); $dm->close(); }
        }
    }

    if ($tconn) $tconn->close();

    // Erase this teacher's Recent Activity log entries too - teacher_activities
    // rows are already gone via teacher_accounts' ON DELETE CASCADE above, and
    // admin_activities has no such FK (it's a flat log table), so without this
    // those rows would linger in the database forever, just hidden by the
    // is_deleted filter on admin_get_recent_activities.php's join instead of
    // actually being erased - inconsistent with what "permanent" means here.
    if (!empty($teacherEmails)) {
        $eph    = implode(',', array_fill(0, count($teacherEmails), '?'));
        $etypes = str_repeat('s', count($teacherEmails));
        $de = $conn->prepare("DELETE FROM admin_activities WHERE user_email IN ($eph)");
        if ($de) { $de->bind_param($etypes, ...$teacherEmails); $de->execute(); $de->close(); }
    }

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
