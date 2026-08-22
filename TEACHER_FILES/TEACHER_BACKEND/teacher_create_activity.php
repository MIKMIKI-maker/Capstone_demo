<?php
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/db.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/admin_push_notification.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/teacher_auth.php';

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

$teacher_id = requireTeacherId();
$activity_title = isset($_POST['activity_title']) ? trim($_POST['activity_title']) : '';
$activity_description = isset($_POST['activity_description']) ? trim($_POST['activity_description']) : '';
$activity_type = isset($_POST['activity_type']) ? trim($_POST['activity_type']) : '';
$subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
$grade_level = isset($_POST['grade_level']) ? trim($_POST['grade_level']) : '';
$difficulty = isset($_POST['difficulty']) ? trim($_POST['difficulty']) : '';
$status = isset($_POST['status']) ? trim($_POST['status']) : 'draft';
// Full activity content (slides/items/answer keys/design settings) so any
// device can load it later — not just the browser that published it.
$content_json = isset($_POST['content_json']) ? $_POST['content_json'] : null;
$deadline = isset($_POST['deadline']) ? trim($_POST['deadline']) : '';
if ($deadline === '') $deadline = null;
// JSON array of students.id values to assign this activity to
$assigned_student_ids_raw = isset($_POST['assigned_student_ids']) ? trim($_POST['assigned_student_ids']) : '[]';
$assigned_student_ids = json_decode($assigned_student_ids_raw, true);
if (!is_array($assigned_student_ids)) $assigned_student_ids = [];

if (!$teacher_id || !$activity_title) {
    echo json_encode(['success' => false, 'message' => 'Teacher ID and activity title are required']);
    exit;
}

// Connect to teacher database
$teacher_conn = getTeacherDatabaseConnection();
if (!$teacher_conn) {
    echo json_encode(['success' => false, 'message' => 'Teacher database connection failed']);
    exit;
}

// Insert activity into teacher database
$sql = "INSERT INTO teacher_activities (teacher_id, activity_title, activity_description, activity_type, subject, grade_level, difficulty, status, content_json, deadline)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $teacher_conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Query preparation failed']);
    exit;
}

$stmt->bind_param("isssssssss", $teacher_id, $activity_title, $activity_description, $activity_type, $subject, $grade_level, $difficulty, $status, $content_json, $deadline);

if ($stmt->execute()) {
    $activity_id = $stmt->insert_id;
    
    // Get teacher name: look up email from teacher_accounts, then name from admin_accounts
    $teacher_name = 'Unknown Teacher';
    $teacher_email_for_log = '';
    $teq = $teacher_conn->prepare("SELECT teacher_email FROM teacher_accounts WHERE id = ?");
    if ($teq) {
        $teq->bind_param("i", $teacher_id);
        $teq->execute();
        if ($terow = $teq->get_result()->fetch_assoc()) {
            $teacher_email_for_log = $terow['teacher_email'];
        }
        $teq->close();
    }
    if ($teacher_email_for_log) {
        $tq = $conn->prepare("SELECT first_name, last_name FROM admin_accounts WHERE admin_email = ?");
        if ($tq) {
            $tq->bind_param("s", $teacher_email_for_log);
            $tq->execute();
            if ($trow = $tq->get_result()->fetch_assoc()) {
                $teacher_name = trim($trow['first_name'] . ' ' . $trow['last_name']);
            }
            $tq->close();
        }
    }
    
    // Log activity to admin_activities table
    $logSql = "INSERT INTO admin_activities (activity_type, user_type, user_name, user_email, action_detail) 
               VALUES ('Create Activity', 'teacher', ?, ?, ?)";
    $logStmt = $conn->prepare($logSql);
    if ($logStmt) {
        $actionDetail = "Activity: " . substr($activity_title, 0, 50);
        $logStmt->bind_param("sss", $teacher_name, $teacher_email_for_log, $actionDetail);
        $logStmt->execute();
        $logStmt->close();
    }
    
    // If publishing with no explicit student selection, default to the
    // teacher's whole class — otherwise the activity is "published" but
    // invisible to every student (student_get_materials.php only shows
    // activities that have an activity_assignments row).
    if ($status === 'published' && empty($assigned_student_ids)) {
        $allStmt = $teacher_conn->prepare("SELECT id FROM students WHERE teacher_id = ? AND status = 'active'");
        if ($allStmt) {
            $allStmt->bind_param("i", $teacher_id);
            $allStmt->execute();
            $allRes = $allStmt->get_result();
            while ($arow = $allRes->fetch_assoc()) { $assigned_student_ids[] = (int)$arow['id']; }
            $allStmt->close();
        }
    }

    // Insert activity_assignments for each selected student.
    if (!empty($assigned_student_ids)) {
        $asStmt = $teacher_conn->prepare("INSERT IGNORE INTO activity_assignments (activity_id, student_id) VALUES (?, ?)");
        if ($asStmt) {
            foreach ($assigned_student_ids as $sid) {
                $sid = intval($sid);
                if ($sid > 0) {
                    $asStmt->bind_param("ii", $activity_id, $sid);
                    $asStmt->execute();
                }
            }
            $asStmt->close();
        }
    }

    // Push admin notification only when published (not drafts)
    if ($status === 'published') {
        $notifMsg = "{$teacher_name} published a new {$activity_type} activity: \"{$activity_title}\"";
        if (!empty($assigned_student_ids)) {
            $notifMsg .= ' — assigned to ' . count($assigned_student_ids) . ' student(s).';
        }
        pushAdminNotification($conn, 'activity', 'New Activity Published', $notifMsg, $activity_id);

        // Push to teacher's own notification inbox
        require_once __DIR__ . '/teacher_push_notification.php';
        $selfMsg = 'You published "' . $activity_title . '"';
        if (!empty($assigned_student_ids)) {
            $selfMsg .= ' — assigned to ' . count($assigned_student_ids) . ' student(s).';
        }
        pushTeacherNotification($teacher_conn, $teacher_id, 'activity', 'Activity Published', $selfMsg);
    }

    echo json_encode(['success' => true, 'activity_id' => $activity_id, 'message' => 'Activity created successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to create activity']);
}

$stmt->close();
$teacher_conn->close();
$conn->close();
?>
