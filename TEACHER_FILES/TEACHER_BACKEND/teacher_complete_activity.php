<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/db.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/admin_push_notification.php';

header('Content-Type: application/json');

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$teacher_id  = isset($_POST['teacher_id'])   ? intval($_POST['teacher_id'])   : 0;
$student_id  = isset($_POST['student_id'])   ? intval($_POST['student_id'])   : 0;
$activity_id = isset($_POST['activity_id'])  ? intval($_POST['activity_id'])  : 0;
$score       = isset($_POST['score'])        ? intval($_POST['score'])        : 0;
$notes       = isset($_POST['notes'])        ? trim($_POST['notes'])          : '';
$total_items   = isset($_POST['total_items'])   ? intval($_POST['total_items'])   : 0;
$pub_id        = isset($_POST['pub_id'])        ? trim($_POST['pub_id'])          : '';
$answers_json  = isset($_POST['answers_json'])  ? trim($_POST['answers_json'])    : '';
$retake_count  = isset($_POST['retake_count'])  ? intval($_POST['retake_count'])  : 0;

if (!$teacher_id || !$student_id || !$activity_id) {
    echo json_encode(['success' => false, 'message' => 'Teacher ID, student ID, and activity ID are required']);
    exit;
}

// Connect to teacher database
$teacher_conn = getTeacherDatabaseConnection();
if (!$teacher_conn) {
    echo json_encode(['success' => false, 'message' => 'Teacher database connection failed']);
    exit;
}

// Remove any existing record for this student+activity so retakes update the score
$del = $teacher_conn->prepare("DELETE FROM learner_progress WHERE student_id = ? AND activity_id = ?");
if ($del) { $del->bind_param("ii", $student_id, $activity_id); $del->execute(); $del->close(); }

// Insert progress record
$sql = "INSERT INTO learner_progress (teacher_id, student_id, activity_id, score, notes, assessment_date)
        VALUES (?, ?, ?, ?, ?, NOW())";
$stmt = $teacher_conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Query preparation failed']);
    exit;
}

$stmt->bind_param("iiiis", $teacher_id, $student_id, $activity_id, $score, $notes);

if ($stmt->execute()) {
    // Get student name from teacher database
    $student_name = 'Unknown Student';
    $sq = $teacher_conn->prepare("SELECT student_name FROM students WHERE id=? AND teacher_id=?");
    $sq->bind_param("ii", $student_id, $teacher_id);
    $sq->execute();
    $sqr = $sq->get_result();
    if ($row = $sqr->fetch_assoc()) { $student_name = $row['student_name']; }
    $sq->close();

    // Get activity title
    $activity_title = 'Unknown Activity';
    $aq = $teacher_conn->prepare("SELECT activity_title FROM teacher_activities WHERE id=?");
    $aq->bind_param("i", $activity_id);
    $aq->execute();
    $aqr = $aq->get_result();
    if ($row = $aqr->fetch_assoc()) { $activity_title = $row['activity_title']; }
    $aq->close();
    
    // Log activity to admin_activities table
    $logSql = "INSERT INTO admin_activities (activity_type, user_type, user_name, user_email, action_detail) 
               VALUES ('Complete Activity', 'student', ?, ?, ?)";
    $logStmt = $conn->prepare($logSql);
    if ($logStmt) {
        $actionDetail = "Activity: " . substr($activity_title, 0, 40) . " - Score: " . $score . "%";
        $student_email = ''; // Optional field
        $logStmt->bind_param("sss", $student_name, $student_email, $actionDetail);
        $logStmt->execute();
        $logStmt->close();
    }
    
    // Also upsert into activity_submissions so teacher can review item-by-item answers
    $subStmt = $teacher_conn->prepare(
        "INSERT INTO activity_submissions (teacher_id, student_id, activity_id, pub_id, score, total_items, retake_count, answers_json)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE score=VALUES(score), total_items=VALUES(total_items), retake_count=VALUES(retake_count), answers_json=VALUES(answers_json), submitted_at=NOW()"
    );
    if ($subStmt) {
        $subStmt->bind_param("iiisiiis", $teacher_id, $student_id, $activity_id, $pub_id, $score, $total_items, $retake_count, $answers_json);
        $subStmt->execute();
        $subStmt->close();
    }

    // Push admin notification
    $scoreLabel = $score >= 80 ? '✅' : ($score >= 60 ? '⚠️' : '❌');
    pushAdminNotification(
        $conn,
        'activity',
        'Activity Completed',
        "{$scoreLabel} {$student_name} completed \"{$activity_title}\" with a score of {$score}%.",
        $activity_id
    );

    echo json_encode(['success' => true, 'message' => 'Activity completed successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to record activity completion']);
}

$stmt->close();
$teacher_conn->close();
$conn->close();
?>
