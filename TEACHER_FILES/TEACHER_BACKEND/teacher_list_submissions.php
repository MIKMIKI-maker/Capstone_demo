<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/teacher_auth.php';

header('Content-Type: application/json');

$conn = getTeacherDatabaseConnection();
if (!$conn) { echo json_encode(['success' => false]); exit; }

$teacher_id  = requireTeacherId();
$activity_id = intval($_GET['activity_id'] ?? 0);

if (!$teacher_id || !$activity_id) {
    echo json_encode(['success' => false, 'message' => 'Missing params']);
    exit;
}

// Activity info
$aStmt = $conn->prepare(
    "SELECT activity_title, activity_type, status FROM teacher_activities WHERE id = ? AND teacher_id = ?"
);
$aStmt->bind_param("ii", $activity_id, $teacher_id);
$aStmt->execute();
$act = $aStmt->get_result()->fetch_assoc();
$aStmt->close();

if (!$act) {
    echo json_encode(['success' => false, 'message' => 'Activity not found']);
    $conn->close();
    exit;
}

// All assigned students + their submission status
$stmt = $conn->prepare("
    SELECT s.id AS student_id, s.student_name,
           sub.id            AS submission_id,
           sub.score,
           sub.total_items,
           sub.retake_count,
           sub.scaffold_used,
           sub.answers_json,
           sub.assistance_level,
           sub.teacher_note,
           sub.finalized_score,
           sub.is_finalized,
           sub.submitted_at
    FROM students s
    INNER JOIN activity_assignments aa
           ON aa.student_id = s.id AND aa.activity_id = ?
    LEFT JOIN activity_submissions sub
           ON sub.student_id = s.id AND sub.activity_id = ? AND sub.teacher_id = ?
    WHERE s.teacher_id = ?
    ORDER BY sub.submitted_at DESC, s.student_name ASC
");
$stmt->bind_param("iiii", $activity_id, $activity_id, $teacher_id, $teacher_id);
$stmt->execute();
$rows = $stmt->get_result();

$students = [];
while ($row = $rows->fetch_assoc()) {
    $students[] = [
        'student_id'       => (int)$row['student_id'],
        'student_name'     => $row['student_name'],
        'submitted'        => !is_null($row['submission_id']),
        'submission_id'    => $row['submission_id'] ? (int)$row['submission_id'] : null,
        'score'            => $row['score'] !== null ? (int)$row['score'] : null,
        'total_items'      => $row['total_items'] !== null ? (int)$row['total_items'] : null,
        'retake_count'     => $row['retake_count'] !== null ? (int)$row['retake_count'] : 0,
        'scaffold_used'    => (int)$row['scaffold_used'],
        'answers_json'     => $row['answers_json'],
        'assistance_level' => $row['assistance_level'],
        'teacher_note'     => $row['teacher_note'],
        'finalized_score'  => $row['finalized_score'] !== null ? (int)$row['finalized_score'] : null,
        'is_finalized'     => (bool)$row['is_finalized'],
        'submitted_at'     => $row['submitted_at'],
    ];
}
$stmt->close();
$conn->close();

echo json_encode([
    'success'  => true,
    'activity' => [
        'title'  => $act['activity_title'],
        'type'   => $act['activity_type'],
        'status' => $act['status'],
    ],
    'students' => $students,
]);
?>
