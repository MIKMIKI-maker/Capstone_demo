<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$teacher_id = isset($_REQUEST['teacher_id']) ? intval($_REQUEST['teacher_id']) : 1;

// Get overall stats
$stats = [
    'total_learners' => getTotalLearners($conn, $teacher_id),
    'total_activities' => getTotalActivities($conn, $teacher_id),
    'total_assessments' => getTotalAssessments($conn, $teacher_id),
    'active_students' => getActiveStudents($conn, $teacher_id),
    'average_score' => getAverageScore($conn, $teacher_id),
    'recent_activities' => getRecentActivities($conn, $teacher_id),
    'learner_summary' => getLearnerSummary($conn, $teacher_id),
    'performance_breakdown' => getPerformanceBreakdown($conn, $teacher_id)
];

echo json_encode([
    'success' => true,
    'stats' => $stats,
    'timestamp' => date('Y-m-d H:i:s')
]);

$conn->close();

function getTotalLearners($conn, $teacher_id) {
    $sql = "SELECT COUNT(*) as count FROM students WHERE teacher_id=? AND status='active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['count'];
}

function getTotalActivities($conn, $teacher_id) {
    $sql = "SELECT COUNT(*) as count FROM teacher_activities WHERE teacher_id=? AND status='published'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['count'];
}

function getTotalAssessments($conn, $teacher_id) {
    $sql = "SELECT COUNT(*) as count FROM assessment_records WHERE teacher_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['count'];
}

function getActiveStudents($conn, $teacher_id) {
    $sql = "SELECT COUNT(*) as count FROM students WHERE teacher_id=? AND status='active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['count'];
}

function getAverageScore($conn, $teacher_id) {
    $sql = "SELECT ROUND(AVG(score), 2) as average FROM assessment_records WHERE teacher_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['average'] ?: 0;
}

function getRecentActivities($conn, $teacher_id) {
    $sql = "SELECT ar.id, ar.assessment_date, ar.score, s.student_name, ta.activity_title
            FROM assessment_records ar
            LEFT JOIN students s ON ar.student_id = s.id
            LEFT JOIN teacher_activities ta ON ar.activity_id = ta.id
            WHERE ar.teacher_id=?
            ORDER BY ar.assessment_date DESC LIMIT 5";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $activities = [];
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
    $stmt->close();
    return $activities;
}

function getLearnerSummary($conn, $teacher_id) {
    $sql = "SELECT s.id, s.student_name, COUNT(ar.id) as assessment_count, ROUND(AVG(ar.score), 2) as avg_score
            FROM students s
            LEFT JOIN assessment_records ar ON s.id = ar.student_id
            WHERE s.teacher_id=? AND s.status='active'
            GROUP BY s.id
            ORDER BY avg_score DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $summary = [];
    while ($row = $result->fetch_assoc()) {
        $summary[] = $row;
    }
    $stmt->close();
    return $summary;
}

function getPerformanceBreakdown($conn, $teacher_id) {
    $sql = "SELECT 
            SUM(CASE WHEN score >= 80 THEN 1 ELSE 0 END) as excellent,
            SUM(CASE WHEN score >= 70 AND score < 80 THEN 1 ELSE 0 END) as good,
            SUM(CASE WHEN score >= 60 AND score < 70 THEN 1 ELSE 0 END) as satisfactory,
            SUM(CASE WHEN score < 60 THEN 1 ELSE 0 END) as needs_improvement
            FROM assessment_records WHERE teacher_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $breakdown = $result->fetch_assoc();
    $stmt->close();
    return $breakdown;
}
?>
