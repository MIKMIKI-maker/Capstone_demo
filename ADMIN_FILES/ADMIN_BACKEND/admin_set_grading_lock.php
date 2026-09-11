<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
requireAdminSession();
csrf_require_valid_token();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$period = in_array($_POST['grading_period'] ?? '', ['First', 'Second', 'Third'], true) ? $_POST['grading_period'] : null;
$unlocked = isset($_POST['unlocked']) && $_POST['unlocked'] === '1' ? 1 : 0;

if (!$period) {
    echo json_encode(['success' => false, 'message' => 'Invalid grading period']);
    exit;
}

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS grading_period_locks (
  grading_period VARCHAR(20) PRIMARY KEY,
  is_unlocked TINYINT(1) NOT NULL DEFAULT 0
)");

$stmt = $conn->prepare("INSERT INTO grading_period_locks (grading_period, is_unlocked) VALUES (?, ?)
    ON DUPLICATE KEY UPDATE is_unlocked = VALUES(is_unlocked)");
$stmt->bind_param("si", $period, $unlocked);
$success = $stmt->execute();
$stmt->close();
$conn->close();

echo json_encode(['success' => (bool)$success]);
