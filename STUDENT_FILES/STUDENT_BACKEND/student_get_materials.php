<?php
require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
require_once __DIR__ . '/student_auth.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$student_admin_id = requireStudentSession();

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$rec = resolveStudentRecord($conn, $student_admin_id);
if (!$rec) {
    echo json_encode(['success' => false, 'enrolled' => false, 'message' => 'Not enrolled']);
    $conn->close();
    exit;
}
$student_record_id = (int)$rec['student_record_id'];

// Only return activities explicitly assigned to this student via activity_assignments
$stmt = $conn->prepare("
    SELECT a.id, a.activity_title, a.activity_description, a.activity_type, a.subject,
           a.grade_level, a.difficulty, a.status AS activity_status, a.created_at,
           lp.score, lp.assessment_date,
           CASE
               WHEN lp.id IS NULL THEN 'new'
               WHEN lp.score >= 80 THEN 'completed'
               ELSE 'in_progress'
           END AS progress_status
    FROM teacher_activities a
    INNER JOIN activity_assignments aa ON aa.activity_id = a.id AND aa.student_id = ?
    LEFT JOIN learner_progress lp ON lp.activity_id = a.id AND lp.student_id = ?
    WHERE a.status = 'published'
    ORDER BY a.created_at DESC
");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Query failed']);
    $conn->close();
    exit;
}

$stmt->bind_param("ii", $student_record_id, $student_record_id);
$stmt->execute();
$result = $stmt->get_result();

$materials = [];
$total = 0;
$completed = 0;
$in_progress = 0;
$new_count = 0;

while ($row = $result->fetch_assoc()) {
    $total++;
    if ($row['progress_status'] === 'completed') $completed++;
    elseif ($row['progress_status'] === 'in_progress') $in_progress++;
    else $new_count++;
    $materials[] = $row;
}
$stmt->close();
$conn->close();

echo json_encode([
    'success'    => true,
    'enrolled'   => true,
    'total'      => $total,
    'completed'  => $completed,
    'in_progress'=> $in_progress,
    'new'        => $new_count,
    'materials'  => $materials
]);
?>
