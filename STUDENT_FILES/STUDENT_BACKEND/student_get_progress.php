<?php
require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$student_record_id = isset($_GET['student_record_id']) ? intval($_GET['student_record_id']) : 0;
$teacher_id = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : 0;

if (!$student_record_id || !$teacher_id) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Overall stats — only from activities actually assigned to this student
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total_submitted,
           AVG(lp.score) AS avg_score
    FROM learner_progress lp
    INNER JOIN activity_assignments aa ON aa.activity_id = lp.activity_id
                                      AND aa.student_id  = lp.student_id
    WHERE lp.teacher_id = ? AND lp.student_id = ?
");
$stmt->bind_param("ii", $teacher_id, $student_record_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Total activities assigned to THIS student only (denominator)
$stmt2 = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM activity_assignments aa
    INNER JOIN teacher_activities ta ON ta.id = aa.activity_id
    WHERE aa.student_id = ? AND ta.teacher_id = ? AND ta.status = 'published'
");
$stmt2->bind_param("ii", $student_record_id, $teacher_id);
$stmt2->execute();
$total_row = $stmt2->get_result()->fetch_assoc();
$stmt2->close();

// Activity performance list
$stmt3 = $conn->prepare("
    SELECT a.activity_title, a.subject, lp.score, lp.assessment_date,
           CASE WHEN lp.score >= 80 THEN 'Completed' ELSE 'In Progress' END AS status_label
    FROM learner_progress lp
    JOIN teacher_activities a ON a.id = lp.activity_id
    WHERE lp.teacher_id = ? AND lp.student_id = ?
    ORDER BY lp.assessment_date DESC
");
$stmt3->bind_param("ii", $teacher_id, $student_record_id);
$stmt3->execute();
$perf_result = $stmt3->get_result();
$activities = [];
while ($r = $perf_result->fetch_assoc()) $activities[] = $r;
$stmt3->close();

// Teacher notes
$stmt4 = $conn->prepare("SELECT note, created_at FROM student_notes WHERE teacher_id = ? AND student_id = ? ORDER BY created_at DESC LIMIT 20");
$stmt4->bind_param("ii", $teacher_id, $student_record_id);
$stmt4->execute();
$notes_result = $stmt4->get_result();
$notes = [];
while ($n = $notes_result->fetch_assoc()) $notes[] = $n;
$stmt4->close();
$conn->close();

$avg_score        = $stats['avg_score'] ? round(floatval($stats['avg_score'])) : 0;
$total_assigned   = intval($total_row['total']);      // activities assigned to this student
$total_completed  = intval($stats['total_submitted']); // of those, how many the student submitted

echo json_encode([
    'success'          => true,
    'avg_score'        => $avg_score,
    'completed'        => $total_completed,
    'total_activities' => $total_assigned,
    'activities'       => $activities,
    'notes'            => $notes
]);
?>
