<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode([]);
    exit;
}

session_start();
$teacher_id = isset($_REQUEST['teacher_id']) ? intval($_REQUEST['teacher_id'])
            : (isset($_SESSION['admin_id']) ? intval($_SESSION['admin_id']) : 1);

$stmt = $conn->prepare("SELECT id, student_id, iep_goal, learning_objective, strategies, status, created_at
                        FROM iep_materials
                        WHERE teacher_id = ?
                        ORDER BY created_at DESC");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$ieps = [];

$name_stmt = $conn->prepare("SELECT student_name FROM students WHERE id = ?");

while ($row = $result->fetch_assoc()) {
    $student_name = '';
    $name_stmt->bind_param("i", $row['student_id']);
    $name_stmt->execute();
    $nr = $name_stmt->get_result();
    if ($nr && $nr->num_rows > 0) {
        $student_name = $nr->fetch_assoc()['student_name'];
    }

    $ieps[] = [
        'id'           => $row['id'],
        'student_id'   => $row['student_id'],
        'student_name' => $student_name,
        'goal'         => $row['iep_goal'],
        'objective'    => $row['learning_objective'],
        'strategies'   => $row['strategies'],
        'status'       => $row['status'],
        'created_at'   => $row['created_at']
    ];
}

$name_stmt->close();
$stmt->close();

echo json_encode($ieps);
$conn->close();
