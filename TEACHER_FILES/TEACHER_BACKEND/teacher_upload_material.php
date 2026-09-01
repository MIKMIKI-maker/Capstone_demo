<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/teacher_auth.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/cloudinary_upload.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/db.php';
header('Content-Type: application/json');

$teacher_id     = requireTeacherId();
$student_id     = isset($_POST['student_id'])     ? intval($_POST['student_id'])     : 0;
$grading_period = isset($_POST['grading_period']) ? trim($_POST['grading_period'])   : 'First';
$title          = isset($_POST['title'])          ? trim($_POST['title'])            : '';
$description    = isset($_POST['description'])    ? trim($_POST['description'])      : '';
$link_url       = isset($_POST['link_url'])       ? trim($_POST['link_url'])         : '';

if (!$teacher_id || !$student_id || !$title) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$hasFile = isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK;
$hasLink = !empty($link_url);

if (!$hasFile && !$hasLink) {
    echo json_encode(['success' => false, 'message' => 'Please provide a file or a link.']);
    exit;
}

if ($hasLink && !filter_var($link_url, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid URL provided.']);
    exit;
}

$safeName = '';
$origName = '';
$fileMime = '';
$fileSize = 0;

if ($hasFile) {
    // Cloudinary's free plan caps uploads at 10MB — confirmed directly
    // against their API (they reject anything larger with a 400), so this
    // stays in sync with that rather than the file input's old 20MB label.
    if ($_FILES['file']['size'] > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File too large (max 10 MB)']);
        exit;
    }

    $allowedMimes = [
        'application/pdf',
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'video/mp4', 'video/mpeg', 'video/quicktime',
        'audio/mpeg', 'audio/wav', 'audio/ogg'
    ];

    // The real file content decides the type here, never the client-supplied
    // filename extension — that mismatch (upload a real image, keep a .php
    // name) was previously an RCE risk when files lived under the webroot.
    // Uploading to Cloudinary instead of local disk removes that risk
    // entirely: nothing under this app's own webroot ever executes what's
    // stored here.
    $fileMime = mime_content_type($_FILES['file']['tmp_name']);
    if (!in_array($fileMime, $allowedMimes)) {
        echo json_encode(['success' => false, 'message' => 'File type not allowed']);
        exit;
    }

    $origName = basename($_FILES['file']['name']);
    $curlFile = new CURLFile($_FILES['file']['tmp_name'], $fileMime, $origName);
    $safeName = cloudinaryUpload($curlFile, 'auto', 'materials');
    if ($safeName === null) {
        echo json_encode(['success' => false, 'message' => 'Failed to save file. Please try again.']);
        exit;
    }

    $fileSize = intval($_FILES['file']['size']);
}

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS teacher_uploaded_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    student_id INT NOT NULL,
    grading_period VARCHAR(20) NOT NULL DEFAULT 'First',
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_name VARCHAR(255) NOT NULL DEFAULT '',
    file_original_name VARCHAR(255) NOT NULL DEFAULT '',
    file_type VARCHAR(100),
    file_size INT,
    link_url VARCHAR(500) DEFAULT NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Add link_url column to existing tables that predate this change
@$conn->query("ALTER TABLE teacher_uploaded_materials ADD COLUMN link_url VARCHAR(500) DEFAULT NULL");

$stmt = $conn->prepare(
    "INSERT INTO teacher_uploaded_materials
     (teacher_id, student_id, grading_period, title, description, file_name, file_original_name, file_type, file_size, link_url)
     VALUES (?,?,?,?,?,?,?,?,?,?)"
);
$stmt->bind_param("iissssssis",
    $teacher_id, $student_id, $grading_period, $title, $description,
    $safeName, $origName, $fileMime, $fileSize, $link_url
);
$stmt->execute();
$newId = $conn->insert_id;
$stmt->close();

// Log to admin_activities so it shows up on the admin dashboard's Recent Activities feed
$teacher_email_for_log = '';
$teq = $conn->prepare("SELECT teacher_email FROM teacher_accounts WHERE id = ?");
if ($teq) {
    $teq->bind_param("i", $teacher_id);
    $teq->execute();
    if ($terow = $teq->get_result()->fetch_assoc()) {
        $teacher_email_for_log = $terow['teacher_email'];
    }
    $teq->close();
}
$teacher_name_for_log = 'Unknown Teacher';
if ($teacher_email_for_log) {
    $adminConn = getDatabaseConnection();
    if ($adminConn) {
        $tq = $adminConn->prepare("SELECT first_name, last_name FROM admin_accounts WHERE admin_email = ?");
        if ($tq) {
            $tq->bind_param("s", $teacher_email_for_log);
            $tq->execute();
            if ($trow = $tq->get_result()->fetch_assoc()) {
                $teacher_name_for_log = trim($trow['first_name'] . ' ' . $trow['last_name']);
            }
            $tq->close();
        }
        $logStmt = $adminConn->prepare("INSERT INTO admin_activities (activity_type, user_type, user_name, user_email, action_detail) VALUES ('Material Uploaded', 'teacher', ?, ?, ?)");
        if ($logStmt) {
            $actionDetail = 'Material: ' . substr($title, 0, 50);
            $logStmt->bind_param("sss", $teacher_name_for_log, $teacher_email_for_log, $actionDetail);
            $logStmt->execute();
            $logStmt->close();
        }
        $adminConn->close();
    }
}

$notif_title = 'New material uploaded';
$notif_msg   = 'Your teacher added "' . $title . '"' . ($description ? ': ' . $description : '') . ' to your materials.';
$nstmt = $conn->prepare("INSERT INTO student_notifications (teacher_id, student_id, title, message, notification_type) VALUES (?, ?, ?, ?, 'new_material')");
if ($nstmt) { $nstmt->bind_param("iiss", $teacher_id, $student_id, $notif_title, $notif_msg); $nstmt->execute(); $nstmt->close(); }

$conn->close();

echo json_encode(['success' => true, 'id' => $newId]);
?>
