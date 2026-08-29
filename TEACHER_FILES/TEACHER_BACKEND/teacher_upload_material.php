<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/teacher_auth.php';
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
    if ($_FILES['file']['size'] > 20 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File too large (max 20 MB)']);
        exit;
    }

    $allowedMimes = [
        'application/pdf',
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'video/mp4', 'video/mpeg', 'video/quicktime',
        'audio/mpeg', 'audio/wav', 'audio/ogg'
    ];

    $fileMime = mime_content_type($_FILES['file']['tmp_name']);
    if (!in_array($fileMime, $allowedMimes)) {
        echo json_encode(['success' => false, 'message' => 'File type not allowed']);
        exit;
    }

    $uploadDir = __DIR__ . '/../uploads/materials/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $origName = basename($_FILES['file']['name']);
    $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $safeName = uniqid('mat_', true) . '.' . $ext;
    $destPath = $uploadDir . $safeName;

    if (!move_uploaded_file($_FILES['file']['tmp_name'], $destPath)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save file']);
        exit;
    }

    $fileSize = intval($_FILES['file']['size']);
}

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    if ($safeName) @unlink($uploadDir . $safeName);
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

$notif_title = 'New material uploaded';
$notif_msg   = 'Your teacher added "' . $title . '"' . ($description ? ': ' . $description : '') . ' to your materials.';
$nstmt = $conn->prepare("INSERT INTO student_notifications (teacher_id, student_id, title, message, notification_type) VALUES (?, ?, ?, ?, 'new_material')");
if ($nstmt) { $nstmt->bind_param("iiss", $teacher_id, $student_id, $notif_title, $notif_msg); $nstmt->execute(); $nstmt->close(); }

$conn->close();

echo json_encode(['success' => true, 'id' => $newId]);
?>
