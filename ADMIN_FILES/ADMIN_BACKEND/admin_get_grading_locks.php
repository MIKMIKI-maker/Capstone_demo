<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
requireAdminSession();

header('Content-Type: application/json');

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Same table teacher_activity_plan.php reads/writes — created here too in
// case Settings is opened before any teacher has loaded their dashboard.
$conn->query("CREATE TABLE IF NOT EXISTS grading_period_locks (
  grading_period VARCHAR(20) PRIMARY KEY,
  is_unlocked TINYINT(1) NOT NULL DEFAULT 0
)");
$seedCheck = $conn->query("SELECT COUNT(*) AS cnt FROM grading_period_locks");
if ($seedCheck && $seedCheck->fetch_assoc()['cnt'] == 0) {
    $conn->query("INSERT INTO grading_period_locks (grading_period, is_unlocked) VALUES
        ('First', 1), ('Second', 0), ('Third', 0)");
}

$locks = ['First' => false, 'Second' => false, 'Third' => false];
$res = $conn->query("SELECT grading_period, is_unlocked FROM grading_period_locks");
while ($row = $res->fetch_assoc()) {
    $locks[$row['grading_period']] = (bool)$row['is_unlocked'];
}
$conn->close();

echo json_encode(['success' => true, 'locks' => $locks]);
