<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/db.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/admin_push_notification.php';
require_once __DIR__ . '/../../STUDENT_FILES/STUDENT_BACKEND/student_auth.php';

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

// Only a logged-in student can submit, and only for themselves — the
// student and teacher are worked out from the session and the activity,
// never taken from what the browser sends (anyone could post a fake score).
$student_admin_id = requireStudentSession();
$activity_id = isset($_POST['activity_id'])  ? intval($_POST['activity_id'])  : 0;
$score       = isset($_POST['score'])        ? intval($_POST['score'])        : 0;
$notes       = isset($_POST['notes'])        ? trim($_POST['notes'])          : '';
$total_items   = isset($_POST['total_items'])   ? intval($_POST['total_items'])   : 0;
$pub_id        = isset($_POST['pub_id'])        ? trim($_POST['pub_id'])          : '';
$answers_json  = isset($_POST['answers_json'])  ? trim($_POST['answers_json'])    : '';
$retake_count  = isset($_POST['retake_count'])  ? intval($_POST['retake_count'])  : 0;
$scaffold_used = isset($_POST['scaffold_used']) ? intval($_POST['scaffold_used']) : 0;
$struggled_items_json = isset($_POST['struggled_items_json']) ? trim($_POST['struggled_items_json']) : '';
$attempt_history_json = isset($_POST['attempt_history_json']) ? trim($_POST['attempt_history_json']) : '';
// Comes from the browser and is rendered on teacher pages — rebuild it from
// integers only so nothing but numbers can ever reach the HTML.
$ah = json_decode($attempt_history_json, true);
$attempt_history_json = '';
if (is_array($ah)) {
    $clean = [];
    foreach (array_slice($ah, 0, 20) as $i => $slide) {
        $tries = [];
        foreach (array_slice(is_array($slide) && is_array($slide['attempts'] ?? null) ? $slide['attempts'] : [], 0, 50) as $a) {
            if (!is_array($a)) continue;
            $c = (int)($a['correct'] ?? 0); $t = (int)($a['total'] ?? 0);
            if ($t > 0) $tries[] = ['correct' => $c, 'total' => $t, 'percent' => (int)round($c / $t * 100)];
        }
        $clean[] = ['slide' => $i + 1, 'attempts' => $tries];
    }
    $attempt_history_json = json_encode($clean);
}

if (!$activity_id) {
    echo json_encode(['success' => false, 'message' => 'Activity ID is required']);
    exit;
}

// Connect to teacher database
$teacher_conn = getTeacherDatabaseConnection();
if (!$teacher_conn) {
    echo json_encode(['success' => false, 'message' => 'Teacher database connection failed']);
    exit;
}

$rec = resolveStudentRecord($teacher_conn, $student_admin_id);
if (!$rec) {
    echo json_encode(['success' => false, 'message' => 'Not enrolled']);
    exit;
}
$student_id = (int)$rec['student_record_id'];

// The activity must be published, assigned to this student, and from their
// current teacher — its teacher is who the result is recorded for.
$own = $teacher_conn->prepare("SELECT a.teacher_id FROM teacher_activities a
    INNER JOIN activity_assignments aa ON aa.activity_id = a.id AND aa.student_id = ?
    WHERE a.id = ? AND a.status = 'published' AND a.teacher_id = ?");
$current_teacher_id = (int)$rec['teacher_id'];
$own->bind_param("iii", $student_id, $activity_id, $current_teacher_id);
$own->execute();
$ownRow = $own->get_result()->fetch_assoc();
$own->close();
if (!$ownRow) {
    echo json_encode(['success' => false, 'message' => 'This activity is not assigned to you']);
    exit;
}
$teacher_id = (int)$ownRow['teacher_id'];

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
        "INSERT INTO activity_submissions (teacher_id, student_id, activity_id, pub_id, score, total_items, retake_count, scaffold_used, struggled_items_json, attempt_history_json, answers_json)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE score=VALUES(score), total_items=VALUES(total_items), retake_count=VALUES(retake_count), scaffold_used=VALUES(scaffold_used), struggled_items_json=VALUES(struggled_items_json), attempt_history_json=VALUES(attempt_history_json), answers_json=VALUES(answers_json), submitted_at=NOW()"
    );
    if ($subStmt) {
        $subStmt->bind_param("iiisiiiisss", $teacher_id, $student_id, $activity_id, $pub_id, $score, $total_items, $retake_count, $scaffold_used, $struggled_items_json, $attempt_history_json, $answers_json);
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

    // Push teacher notification
    require_once __DIR__ . '/teacher_push_notification.php';
    pushTeacherNotification(
        $teacher_conn,
        $teacher_id,
        'activity',
        'Student Activity Completed',
        "{$scoreLabel} {$student_name} completed \"{$activity_title}\" with a score of {$score}%."
    );

    // If the system had to step in after repeated failed attempts, let the
    // teacher know separately so they can factor it into grading/IEP notes.
    // Level 1 = a light hint was shown (mild); Level 2 = the system had to
    // fully re-teach the activity (more serious) — these get distinct
    // notification types so the teacher can tell them apart at a glance.
    if ($scaffold_used >= 2) {
        $struggledItems = [];
        if ($struggled_items_json) {
            $decoded = json_decode($struggled_items_json, true);
            if (is_array($decoded)) $struggledItems = $decoded;
        }
        pushTeacherNotification(
            $teacher_conn,
            $teacher_id,
            'scaffold',
            '🚨 Student Needed Extra Help',
            "{$student_name} needed the system to re-teach \"{$activity_title}\" after {$retake_count} attempt(s) before passing. Please review when grading.",
            [
                'student_name'    => $student_name,
                'activity_title'  => $activity_title,
                'retake_count'    => $retake_count,
                'final_score'     => $score,
                'struggled_items' => $struggledItems,
            ]
        );
    } elseif ($scaffold_used == 1) {
        $struggledItems = [];
        if ($struggled_items_json) {
            $decoded = json_decode($struggled_items_json, true);
            if (is_array($decoded)) $struggledItems = $decoded;
        }
        pushTeacherNotification(
            $teacher_conn,
            $teacher_id,
            'scaffold_hint',
            '💡 Student Needed a Hint',
            "{$student_name} needed a hint on \"{$activity_title}\" after {$retake_count} attempt(s) before passing.",
            [
                'student_name'    => $student_name,
                'activity_title'  => $activity_title,
                'retake_count'    => $retake_count,
                'final_score'     => $score,
                'struggled_items' => $struggledItems,
            ]
        );
    }

    echo json_encode(['success' => true, 'message' => 'Activity completed successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to record activity completion']);
}

$stmt->close();
$teacher_conn->close();
$conn->close();
?>
