<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/teacher_auth.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/cloudinary_upload.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/db.php';
header('Content-Type: application/json');

$upload_id  = isset($_POST['upload_id'])  ? intval($_POST['upload_id'])  : 0;
$teacher_id = requireTeacherId();

if (!$upload_id || !$teacher_id) {
    echo json_encode(['success' => false, 'message' => 'Missing params']);
    exit;
}

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'DB error']);
    exit;
}

$stmt = $conn->prepare("SELECT file_name, title FROM teacher_uploaded_materials WHERE id = ? AND teacher_id = ?");
$stmt->bind_param("ii", $upload_id, $teacher_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Not found']);
    $conn->close();
    exit;
}

$del = $conn->prepare("DELETE FROM teacher_uploaded_materials WHERE id = ? AND teacher_id = ?");
$del->bind_param("ii", $upload_id, $teacher_id);
$del->execute();
$del->close();
$conn->close();

if (strpos($row['file_name'], 'res.cloudinary.com') !== false) {
    cloudinaryDeleteByUrl($row['file_name']);
} else {
    // Pre-migration entries were saved as a local filename, not a URL.
    $filePath = __DIR__ . '/../uploads/materials/' . $row['file_name'];
    if (file_exists($filePath)) {
        @unlink($filePath);
    }
}

// Log to admin_activities so it shows up on the admin dashboard's Recent Activities feed
$teacher_email_for_log = '';
$teq = getTeacherDatabaseConnection();
if ($teq) {
    $tstmt = $teq->prepare("SELECT teacher_email FROM teacher_accounts WHERE id = ?");
    if ($tstmt) {
        $tstmt->bind_param("i", $teacher_id);
        $tstmt->execute();
        if ($terow = $tstmt->get_result()->fetch_assoc()) {
            $teacher_email_for_log = $terow['teacher_email'];
        }
        $tstmt->close();
    }
    $teq->close();
}
$teacher_name_for_log = 'Unknown Teacher';
if ($teacher_email_for_log) {
    $adminConn = getDatabaseConnection();
    if ($adminConn) {
        $nq = $adminConn->prepare("SELECT first_name, last_name FROM admin_accounts WHERE admin_email = ?");
        if ($nq) {
            $nq->bind_param("s", $teacher_email_for_log);
            $nq->execute();
            if ($nrow = $nq->get_result()->fetch_assoc()) {
                $teacher_name_for_log = trim($nrow['first_name'] . ' ' . $nrow['last_name']);
            }
            $nq->close();
        }
        $logStmt = $adminConn->prepare("INSERT INTO admin_activities (activity_type, user_type, user_name, user_email, action_detail) VALUES ('Material Deleted', 'teacher', ?, ?, ?)");
        if ($logStmt) {
            $actionDetail = 'Material: ' . substr($row['title'], 0, 50);
            $logStmt->bind_param("sss", $teacher_name_for_log, $teacher_email_for_log, $actionDetail);
            $logStmt->execute();
            $logStmt->close();
        }
        $adminConn->close();
    }
}

echo json_encode(['success' => true]);
?>
