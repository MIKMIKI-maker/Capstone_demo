<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]);
    exit;
}

$teacher_id = isset($_POST['teacher_id']) ? intval($_POST['teacher_id']) : 0;
$photo      = isset($_POST['photo']) ? $_POST['photo'] : '';

if (!$teacher_id) {
    echo json_encode(['success' => false, 'message' => 'Missing teacher_id']);
    exit;
}

$tconn = getTeacherDatabaseConnection();
if (!$tconn) { echo json_encode(['success' => false]); exit; }

$stmt = $tconn->prepare("SELECT teacher_email FROM teacher_accounts WHERE id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$tconn->close();

if (!$row) { echo json_encode(['success' => false, 'message' => 'Teacher not found']); exit; }

$aconn = getDatabaseConnection();
if (!$aconn) { echo json_encode(['success' => false]); exit; }

$pp = $aconn->query("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_accounts' AND COLUMN_NAME='profile_photo'");
if ($pp && $pp->fetch_assoc()['cnt'] == 0) { $aconn->query("ALTER TABLE admin_accounts ADD COLUMN profile_photo LONGTEXT NULL DEFAULT NULL"); }

$stmt2 = $aconn->prepare("UPDATE admin_accounts SET profile_photo = ? WHERE admin_email = ? AND role = 'teacher'");
$stmt2->bind_param("ss", $photo, $row['teacher_email']);
$ok = $stmt2->execute();
$stmt2->close();
$aconn->close();

echo json_encode(['success' => $ok]);
