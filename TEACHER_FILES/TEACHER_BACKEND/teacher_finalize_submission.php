<?php
require_once __DIR__ . '/db.php';

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
$teacher_id       = intval($_POST['teacher_id']       ?? 0);
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

    // If a final score was given, sync it to learner_progress so student portal reflects it
    if ($finalized_score !== null) {
        $look = $conn->prepare("SELECT student_id, activity_id FROM activity_submissions WHERE id = ?");
        $look->bind_param("i", $submission_id);
        $look->execute();
        $row = $look->get_result()->fetch_assoc();
        $look->close();
        if ($row) {
            $upd = $conn->prepare("UPDATE learner_progress SET score = ? WHERE student_id = ? AND activity_id = ? AND teacher_id = ?");
            $upd->bind_param("iiii", $finalized_score, $row['student_id'], $row['activity_id'], $teacher_id);
            $upd->execute();
            $upd->close();
        }
    }

    echo json_encode(['success' => true]);
} else {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Update failed or submission not found']);
}

$conn->close();
?>
