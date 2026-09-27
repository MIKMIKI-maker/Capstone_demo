<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/teacher_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$teacher_id = requireTeacherId();

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$sqls = [
    "DELETE FROM student_notes WHERE teacher_id = ?",
    "DELETE FROM activity_submissions WHERE teacher_id = ?",
    "DELETE FROM learner_progress WHERE teacher_id = ?",
];

$conn->begin_transaction();
$ok = true;
foreach ($sqls as $sql) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $ok = false;
        break;
    }

    $stmt->bind_param('i', $teacher_id);

    if (!$stmt->execute()) {
        $ok = false;
        $stmt->close();
        break;
    }
    $stmt->close();
}

if ($ok) {
    $conn->commit();
} else {
    $conn->rollback();
}

$conn->close();

if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'Failed to reset records']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Records reset successfully']);