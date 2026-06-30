<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['total_users' => 0, 'teachers' => 0, 'students' => 0, 'active_accounts' => 0]);
    exit;
}

// Total users and teacher counts — exclude soft-deleted accounts
$result = $conn->query("SELECT
    COUNT(*) AS total_users,
    SUM(role = 'teacher') AS teachers,
    SUM(role = 'teacher' AND last_login IS NOT NULL) AS active_teachers,
    SUM(role = 'student') AS student_accounts,
    SUM(status = 'active') AS active_accounts
FROM admin_accounts
WHERE is_deleted = 0 OR is_deleted IS NULL");

if (!$result) {
    echo json_encode(['total_users' => 0, 'teachers' => 0, 'active_teachers' => 0, 'students' => 0, 'active_students' => 0]);
    $conn->close();
    exit;
}

$stats = $result->fetch_assoc();
$result->close();

// Student and activity counts from teacher database
require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
$tconn = getTeacherDatabaseConnection();
$stats['students'] = 0;
$stats['active_students'] = 0;
$stats['total_activities'] = 0;
$stats['published_activities'] = 0;
if ($tconn) {
    $sr = $tconn->query("SELECT COUNT(*) AS total FROM students WHERE status='active'");
    if ($sr) { $stats['students'] = (int)$sr->fetch_assoc()['total']; $sr->close(); }

    $stats['total_activities'] = 0;
    $stats['published_activities'] = 0;
    $ar = $tconn->query("SELECT COUNT(*) AS total, SUM(status='published') AS published FROM teacher_activities");
    if ($ar) {
        $arow = $ar->fetch_assoc();
        $stats['total_activities']     = (int)($arow['total']     ?? 0);
        $stats['published_activities'] = (int)($arow['published'] ?? 0);
        $ar->close();
    }
    $tconn->close();
}

// Active students: last_seen within 2 minutes (heartbeat-based)
$stats['active_students'] = 0;
$lsRes = $conn->query("SELECT COUNT(*) AS cnt FROM admin_accounts WHERE role='student' AND last_seen > DATE_SUB(NOW(), INTERVAL 2 MINUTE) AND (is_deleted = 0 OR is_deleted IS NULL)");
if ($lsRes) { $stats['active_students'] = (int)$lsRes->fetch_assoc()['cnt']; $lsRes->close(); }

// Active teachers: last_seen within 2 minutes
$ltRes = $conn->query("SELECT COUNT(*) AS cnt FROM admin_accounts WHERE role='teacher' AND last_seen > DATE_SUB(NOW(), INTERVAL 2 MINUTE) AND (is_deleted = 0 OR is_deleted IS NULL)");
if ($ltRes) { $stats['active_teachers'] = (int)$ltRes->fetch_assoc()['cnt']; $ltRes->close(); }

$conn->close();
echo json_encode($stats);