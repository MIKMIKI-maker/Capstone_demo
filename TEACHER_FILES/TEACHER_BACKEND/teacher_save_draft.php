<?php
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/db.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$teacher_id  = intval($_POST['teacher_id']    ?? 0);
$title       = trim($_POST['activity_title']  ?? '');
$type        = trim($_POST['activity_type']   ?? '');
$db_draft_id = intval($_POST['db_draft_id']   ?? 0);

if (!$teacher_id || !$title) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$teacher_conn = getTeacherDatabaseConnection();
$conn         = getDatabaseConnection();
if (!$teacher_conn || !$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$result_id = 0;

if ($db_draft_id > 0) {
    // Update existing draft record
    $stmt = $teacher_conn->prepare(
        "UPDATE teacher_activities SET activity_title=?, activity_type=?, status='draft' WHERE id=? AND teacher_id=?"
    );
    if ($stmt) {
        $stmt->bind_param("ssii", $title, $type, $db_draft_id, $teacher_id);
        $stmt->execute();
        $stmt->close();
        $result_id = $db_draft_id;
    }
} else {
    // Insert new draft record
    $stmt = $teacher_conn->prepare(
        "INSERT INTO teacher_activities (teacher_id, activity_title, activity_type, status) VALUES (?, ?, ?, 'draft')"
    );
    if ($stmt) {
        $stmt->bind_param("iss", $teacher_id, $title, $type);
        $stmt->execute();
        $result_id = $teacher_conn->insert_id;
        $stmt->close();
    }
}

// Resolve teacher name/email for the activity log
$teacher_name  = 'Unknown Teacher';
$teacher_email = '';
$teq = $teacher_conn->prepare("SELECT teacher_email FROM teacher_accounts WHERE id = ?");
if ($teq) {
    $teq->bind_param("i", $teacher_id);
    $teq->execute();
    if ($row = $teq->get_result()->fetch_assoc()) $teacher_email = $row['teacher_email'];
    $teq->close();
}
if ($teacher_email) {
    $tq = $conn->prepare("SELECT first_name, last_name FROM admin_accounts WHERE admin_email = ?");
    if ($tq) {
        $tq->bind_param("s", $teacher_email);
        $tq->execute();
        if ($row = $tq->get_result()->fetch_assoc())
            $teacher_name = trim($row['first_name'] . ' ' . $row['last_name']);
        $tq->close();
    }
}

// Log only new drafts (not re-saves) to avoid flooding the feed
if (!$db_draft_id && $result_id) {
    $logStmt = $conn->prepare(
        "INSERT INTO admin_activities (activity_type, user_type, user_name, user_email, action_detail)
         VALUES ('Save Draft', 'teacher', ?, ?, ?)"
    );
    if ($logStmt) {
        $detail = 'Draft: ' . substr($title, 0, 50) . ' (' . $type . ')';
        $logStmt->bind_param("sss", $teacher_name, $teacher_email, $detail);
        $logStmt->execute();
        $logStmt->close();
    }
}

echo json_encode(['success' => true, 'db_draft_id' => $result_id]);
$teacher_conn->close();
$conn->close();
?>
