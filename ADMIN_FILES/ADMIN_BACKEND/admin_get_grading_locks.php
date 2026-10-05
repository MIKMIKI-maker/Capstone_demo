<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
require_once __DIR__ . '/school_year.php';
requireAdminSession();

header('Content-Type: application/json');

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Same table teacher_activity_plan.php reads/writes — created here too in
// case Settings is opened before any teacher has loaded their dashboard.
// Locks shown/edited in Admin Settings always belong to the Active S.Y.
$sy_id = getActiveSchoolYearId($conn);
seedGradingLocks($conn, $sy_id);

$locks = ['First' => false, 'Second' => false, 'Third' => false];
$stmt = $conn->prepare("SELECT grading_period, is_unlocked FROM grading_period_locks WHERE school_year_id = ?");
$stmt->bind_param("i", $sy_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $locks[$row['grading_period']] = (bool)$row['is_unlocked'];
}
$conn->close();

echo json_encode(['success' => true, 'locks' => $locks]);
