<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/teacher_auth.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/db.php';
session_start();
$_SESSION['teacher_id'] = requireTeacherId();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$teacher_conn = getTeacherDatabaseConnection();
if (!$teacher_conn) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit;
}

$teacher_id  = requireTeacherId();
$activity_id = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : 0;

if (!$teacher_id || !$activity_id) {
    echo json_encode(['success' => false, 'message' => 'Missing params']);
    $teacher_conn->close();
    exit;
}

// Look up title + status before deleting, so we know whether this was an
// unpublish (was 'published') or a draft cleanup (was 'draft') for logging.
$activity_title = '';
$activity_status = '';
$infoStmt = $teacher_conn->prepare("SELECT activity_title, status FROM teacher_activities WHERE id = ? AND teacher_id = ?");
if ($infoStmt) {
    $infoStmt->bind_param("ii", $activity_id, $teacher_id);
    $infoStmt->execute();
    if ($inforow = $infoStmt->get_result()->fetch_assoc()) {
        $activity_title = $inforow['activity_title'];
        $activity_status = $inforow['status'];
    }
    $infoStmt->close();
}

// Remove student assignments for this activity row first
$del_assign = $teacher_conn->prepare("DELETE FROM activity_assignments WHERE activity_id = ?");
if ($del_assign) {
    $del_assign->bind_param("i", $activity_id);
    $del_assign->execute();
    $del_assign->close();
}

// Delete the activity row (scoped to the requesting teacher for safety)
$del = $teacher_conn->prepare("DELETE FROM teacher_activities WHERE id = ? AND teacher_id = ?");
if (!$del) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $teacher_conn->error]);
    $teacher_conn->close();
    exit;
}
$del->bind_param("ii", $activity_id, $teacher_id);
$del->execute();
$affected = $del->affected_rows;
$del->close();
$teacher_conn->close();

// Log to admin_activities so it shows up on the admin dashboard's Recent Activities feed
if ($affected > 0 && $activity_title) {
    $teacher_email_for_log = '';
    $teq = getTeacherDatabaseConnection();
    if ($teq) {
        $tstmt = $teq->prepare("SELECT teacher_email FROM teacher_accounts WHERE id = ?");
        if ($tstmt) {
            $tstmt->bind_param("i", $teacher_id);
            $tstmt->execute();
            if ($terow = $tstmt->get_result()->fetch_assoc()) {
                $teacher_email_for_log = $terow['teacher_email'];
            }
            $tstmt->close();
        }
        $teq->close();
    }
    $teacher_name_for_log = 'Unknown Teacher';
    if ($teacher_email_for_log) {
        $adminConn = getDatabaseConnection();
        if ($adminConn) {
            $nq = $adminConn->prepare("SELECT first_name, last_name FROM admin_accounts WHERE admin_email = ?");
            if ($nq) {
                $nq->bind_param("s", $teacher_email_for_log);
                $nq->execute();
                if ($nrow = $nq->get_result()->fetch_assoc()) {
                    $teacher_name_for_log = trim($nrow['first_name'] . ' ' . $nrow['last_name']);
                }
                $nq->close();
            }
            $logType = ($activity_status === 'published') ? 'Unpublish Activity' : 'Delete Draft';
            $logStmt = $adminConn->prepare("INSERT INTO admin_activities (activity_type, user_type, user_name, user_email, action_detail) VALUES (?, 'teacher', ?, ?, ?)");
            if ($logStmt) {
                $actionDetail = 'Activity: ' . substr($activity_title, 0, 50);
                $logStmt->bind_param("ssss", $logType, $teacher_name_for_log, $teacher_email_for_log, $actionDetail);
                $logStmt->execute();
                $logStmt->close();
            }
            $adminConn->close();
        }
    }
}

echo json_encode(['success' => $affected > 0]);
?>
