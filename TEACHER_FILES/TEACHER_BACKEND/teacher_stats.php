<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

session_start();
$teacher_id = isset($_REQUEST['teacher_id']) ? intval($_REQUEST['teacher_id'])
            : (isset($_SESSION['admin_id']) ? intval($_SESSION['admin_id']) : 1);

$stats = ['total_students' => 0, 'active_students' => 0, 'total_activities' => 0, 'assigned_learners' => 0];

$queries = [
    ['total_students',   "SELECT COUNT(*) as c FROM students WHERE teacher_id=?",                        'i'],
    ['active_students',  "SELECT COUNT(*) as c FROM students WHERE teacher_id=? AND status='active'",    'i'],
    ['total_activities', "SELECT COUNT(*) as c FROM teacher_activities WHERE teacher_id=?",              'i'],
];

foreach ($queries as [$key, $sql, $types]) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, $teacher_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stats[$key] = (int)($row['c'] ?? 0);
    $stmt->close();
}

$stats['assigned_learners'] = $stats['total_students'];

echo json_encode($stats);
$conn->close();
