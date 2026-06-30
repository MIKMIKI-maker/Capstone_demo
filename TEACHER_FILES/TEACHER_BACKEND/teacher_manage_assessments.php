<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action = isset($_POST['action']) ? trim($_POST['action']) : '';
$teacher_id = isset($_POST['teacher_id']) ? intval($_POST['teacher_id']) : 1;

switch($action) {
    case 'record_assessment':
        recordAssessment($conn, $teacher_id);
        break;
    case 'update_assessment':
        updateAssessment($conn, $teacher_id);
        break;
    case 'get_assessments':
        getAssessments($conn, $teacher_id);
        break;
    case 'get_student_assessments':
        getStudentAssessments($conn, $teacher_id);
        break;
    case 'get_progress':
        getProgress($conn, $teacher_id);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}

$conn->close();

function recordAssessment($conn, $teacher_id) {
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    $activity_id = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : 0;
    $assessment_date = isset($_POST['assessment_date']) ? trim($_POST['assessment_date']) : date('Y-m-d');
    $score = isset($_POST['score']) ? intval($_POST['score']) : 0;
    $feedback = isset($_POST['feedback']) ? trim($_POST['feedback']) : '';
    $strengths = isset($_POST['strengths']) ? trim($_POST['strengths']) : '';
    $areas_for_improvement = isset($_POST['areas_for_improvement']) ? trim($_POST['areas_for_improvement']) : '';
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'completed';
    
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
        return;
    }
    
    $sql = "INSERT INTO assessment_records (teacher_id, student_id, activity_id, assessment_date, score, feedback, strengths, areas_for_improvement, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiisssss", $teacher_id, $student_id, $activity_id, $assessment_date, $score, $feedback, $strengths, $areas_for_improvement, $status);
    
    if ($stmt->execute()) {
        $assessment_id = $stmt->insert_id;
        
        // Also update learner_progress table
        $progressSQL = "INSERT INTO learner_progress (teacher_id, student_id, activity_id, score, notes, assessment_date, feedback)
                        VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE score=?, feedback=?";
        $progressStmt = $conn->prepare($progressSQL);
        $progressStmt->bind_param("iiiiisssss", $teacher_id, $student_id, $activity_id, $score, $feedback, $assessment_date, $feedback, $score, $feedback);
        $progressStmt->execute();
        $progressStmt->close();
        
        echo json_encode(['success' => true, 'message' => 'Assessment recorded successfully', 'assessment_id' => $assessment_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to record assessment']);
    }
    $stmt->close();
}

function updateAssessment($conn, $teacher_id) {
    $assessment_id = isset($_POST['assessment_id']) ? intval($_POST['assessment_id']) : 0;
    $score = isset($_POST['score']) ? intval($_POST['score']) : 0;
    $feedback = isset($_POST['feedback']) ? trim($_POST['feedback']) : '';
    $strengths = isset($_POST['strengths']) ? trim($_POST['strengths']) : '';
    $areas_for_improvement = isset($_POST['areas_for_improvement']) ? trim($_POST['areas_for_improvement']) : '';
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'completed';
    
    if (!$assessment_id) {
        echo json_encode(['success' => false, 'message' => 'Assessment ID is required']);
        return;
    }
    
    $sql = "UPDATE assessment_records SET score=?, feedback=?, strengths=?, areas_for_improvement=?, status=?
            WHERE id=? AND teacher_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssii", $score, $feedback, $strengths, $areas_for_improvement, $status, $assessment_id, $teacher_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Assessment updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update assessment']);
    }
    $stmt->close();
}

function getAssessments($conn, $teacher_id) {
    $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 50;
    
    $sql = "SELECT ar.*, s.student_name, ta.activity_title FROM assessment_records ar
            LEFT JOIN students s ON ar.student_id = s.id
            LEFT JOIN teacher_activities ta ON ar.activity_id = ta.id
            WHERE ar.teacher_id=? ORDER BY ar.assessment_date DESC LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $teacher_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $assessments = [];
    while ($row = $result->fetch_assoc()) {
        $assessments[] = $row;
    }
    
    echo json_encode(['success' => true, 'assessments' => $assessments, 'count' => count($assessments)]);
    $stmt->close();
}

function getStudentAssessments($conn, $teacher_id) {
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
        return;
    }
    
    $sql = "SELECT ar.*, ta.activity_title FROM assessment_records ar
            LEFT JOIN teacher_activities ta ON ar.activity_id = ta.id
            WHERE ar.teacher_id=? AND ar.student_id=? ORDER BY ar.assessment_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $teacher_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $assessments = [];
    $total_score = 0;
    $count = 0;
    
    while ($row = $result->fetch_assoc()) {
        $assessments[] = $row;
        $total_score += $row['score'];
        $count++;
    }
    
    $average_score = $count > 0 ? round($total_score / $count, 2) : 0;
    
    echo json_encode([
        'success' => true,
        'assessments' => $assessments,
        'count' => $count,
        'average_score' => $average_score,
        'total_score' => $total_score
    ]);
    $stmt->close();
}

function getProgress($conn, $teacher_id) {
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
        return;
    }
    
    // Get overall progress
    $progressSQL = "SELECT 
                    COUNT(*) as total_activities,
                    AVG(score) as average_score,
                    MAX(score) as highest_score,
                    MIN(score) as lowest_score,
                    MAX(assessment_date) as last_assessment
                    FROM learner_progress WHERE teacher_id=? AND student_id=?";
    $stmt = $conn->prepare($progressSQL);
    $stmt->bind_param("ii", $teacher_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $progress = $result->fetch_assoc();
    
    echo json_encode(['success' => true, 'progress' => $progress]);
    $stmt->close();
}
?>
