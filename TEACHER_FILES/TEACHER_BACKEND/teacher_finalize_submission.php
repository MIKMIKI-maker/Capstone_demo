<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/teacher_auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false]);
    exit;
}

$submission_id    = intval($_POST['submission_id']    ?? 0);
$teacher_id       = requireTeacherId();
$assistance_level = trim($_POST['assistance_level']   ?? '');
$teacher_note     = trim($_POST['teacher_note']       ?? '');
$finalized_score  = isset($_POST['finalized_score']) && $_POST['finalized_score'] !== ''
                    ? intval($_POST['finalized_score']) : null;

if (!$submission_id || !$teacher_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

if ($finalized_score !== null) {
    $stmt = $conn->prepare(
        "UPDATE activity_submissions
         SET assistance_level = ?, teacher_note = ?, finalized_score = ?, is_finalized = 1, finalized_at = NOW()
         WHERE id = ? AND teacher_id = ?"
    );
    $stmt->bind_param("ssiii", $assistance_level, $teacher_note, $finalized_score, $submission_id, $teacher_id);
} else {
    $stmt = $conn->prepare(
        "UPDATE activity_submissions
         SET assistance_level = ?, teacher_note = ?, finalized_score = NULL, is_finalized = 1, finalized_at = NOW()
         WHERE id = ? AND teacher_id = ?"
    );
    $stmt->bind_param("ssii", $assistance_level, $teacher_note, $submission_id, $teacher_id);
}

if ($stmt->execute() && $stmt->affected_rows > 0) {
    $stmt->close();

    // Look up the student/activity this submission belongs to, for the progress
    // sync below and for the "your activity was graded" notification.
    $look = $conn->prepare("SELECT sub.student_id, sub.activity_id, a.activity_title FROM activity_submissions sub JOIN teacher_activities a ON a.id = sub.activity_id WHERE sub.id = ?");
    $look->bind_param("i", $submission_id);
    $look->execute();
    $row = $look->get_result()->fetch_assoc();
    $look->close();

    if ($row) {
        // If a final score was given, sync it to learner_progress so student portal reflects it
        if ($finalized_score !== null) {
            $upd = $conn->prepare("UPDATE learner_progress SET score = ? WHERE student_id = ? AND activity_id = ? AND teacher_id = ?");
            $upd->bind_param("iiii", $finalized_score, $row['student_id'], $row['activity_id'], $teacher_id);
            $upd->execute();
            $upd->close();
        }

        $notif_title = 'Activity graded';
        $notif_msg   = 'Your teacher graded "' . $row['activity_title'] . '"' . ($finalized_score !== null ? ' — score: ' . $finalized_score : '') . '.';
        $nstmt = $conn->prepare("INSERT INTO student_notifications (teacher_id, student_id, title, message, notification_type) VALUES (?, ?, ?, ?, 'graded')");
        if ($nstmt) { $nstmt->bind_param("iiss", $teacher_id, $row['student_id'], $notif_title, $notif_msg); $nstmt->execute(); $nstmt->close(); }
    }

    echo json_encode(['success' => true]);
} else {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Update failed or submission not found']);
}

$conn->close();
?>
