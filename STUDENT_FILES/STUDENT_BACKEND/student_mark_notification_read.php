<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Cache-Control: no-cache');

require_once __DIR__ . '/student_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$student_admin_id = requireStudentSession();
$notif_key = isset($_POST['notif_key']) ? trim($_POST['notif_key']) : '';

$conn = new mysqli('127.0.0.1', 'root', '', 'spedalm_db', 3306);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}
$conn->set_charset('utf8mb4');

$rec = resolveStudentRecord($conn, $student_admin_id);
if (!$rec) {
    echo json_encode(['success' => false, 'message' => 'Not enrolled']);
    $conn->close();
    exit;
}
$student_id = (int)$rec['student_record_id'];
$teacher_id = (int)$rec['teacher_id'];

if ($notif_key === 'all') {
    // Mark every currently-open note/activity notification read for this student
    $ins = $conn->prepare("INSERT IGNORE INTO student_notification_reads (student_id, notif_key) VALUES (?, ?)");

    $notesStmt = $conn->prepare("SELECT id FROM student_notes WHERE teacher_id=? AND student_id=? ORDER BY created_at DESC LIMIT 15");
    if ($notesStmt) {
        $notesStmt->bind_param("ii", $teacher_id, $student_id);
        $notesStmt->execute();
        $res = $notesStmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $key = 'note_' . $row['id'];
            $ins->bind_param("is", $student_id, $key);
            $ins->execute();
        }
        $notesStmt->close();
    }

    $actStmt = $conn->prepare("
        SELECT a.id
        FROM teacher_activities a
        INNER JOIN activity_assignments aa ON aa.activity_id = a.id AND aa.student_id = ?
        LEFT JOIN learner_progress lp ON lp.activity_id = a.id AND lp.student_id = ?
        WHERE a.teacher_id = ? AND a.status = 'published' AND lp.id IS NULL
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    if ($actStmt) {
        $actStmt->bind_param("iii", $student_id, $student_id, $teacher_id);
        $actStmt->execute();
        $res = $actStmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $key = 'activity_' . $row['id'];
            $ins->bind_param("is", $student_id, $key);
            $ins->execute();
        }
        $actStmt->close();
    }

    $ins->close();
    echo json_encode(['success' => true]);
} else {
    if ($notif_key === '' || !preg_match('/^(note|activity)_\d+$/', $notif_key)) {
        echo json_encode(['success' => false, 'message' => 'Invalid notif_key']);
        $conn->close();
        exit;
    }
    $stmt = $conn->prepare("INSERT IGNORE INTO student_notification_reads (student_id, notif_key) VALUES (?, ?)");
    $stmt->bind_param("is", $student_id, $notif_key);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
}

$conn->close();
?>
