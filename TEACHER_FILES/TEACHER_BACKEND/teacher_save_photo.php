<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/teacher_auth.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/db.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/photo_validation.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/cloudinary_upload.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]);
    exit;
}

$teacher_id  = requireTeacherId();
$photoInput  = isset($_POST['photo']) ? $_POST['photo'] : '';

if (!$teacher_id) {
    echo json_encode(['success' => false, 'message' => 'Missing teacher_id']);
    exit;
}

// Empty input clears the photo. Anything else must be a genuine small
// base64 image — validated before it ever reaches Cloudinary or the DB
// (a crafted "photo" value rendered unescaped elsewhere was an XSS vector).
$photo = '';
if ($photoInput !== '') {
    $validated = sanitizeProfilePhotoDataUri($photoInput);
    if ($validated === null) {
        echo json_encode(['success' => false, 'message' => 'Invalid image. Please try a different photo.']);
        exit;
    }
    $photo = cloudinaryUpload($validated, 'image', 'profile_photos');
    if ($photo === null) {
        echo json_encode(['success' => false, 'message' => 'Photo upload failed. Please try again.']);
        exit;
    }
}

$tconn = getTeacherDatabaseConnection();
if (!$tconn) { echo json_encode(['success' => false]); exit; }

$stmt = $tconn->prepare("SELECT teacher_email FROM teacher_accounts WHERE id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$tconn->close();

if (!$row) { echo json_encode(['success' => false, 'message' => 'Teacher not found']); exit; }

$aconn = getDatabaseConnection();
if (!$aconn) { echo json_encode(['success' => false]); exit; }

$pp = $aconn->query("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_accounts' AND COLUMN_NAME='profile_photo'");
if ($pp && $pp->fetch_assoc()['cnt'] == 0) { $aconn->query("ALTER TABLE admin_accounts ADD COLUMN profile_photo LONGTEXT NULL DEFAULT NULL"); }

$oldPhoto = null;
$oldStmt = $aconn->prepare("SELECT profile_photo FROM admin_accounts WHERE admin_email = ? AND role = 'teacher'");
if ($oldStmt) {
    $oldStmt->bind_param("s", $row['teacher_email']);
    $oldStmt->execute();
    $oldRow = $oldStmt->get_result()->fetch_assoc();
    $oldStmt->close();
    $oldPhoto = $oldRow['profile_photo'] ?? null;
}

$stmt2 = $aconn->prepare("UPDATE admin_accounts SET profile_photo = ? WHERE admin_email = ? AND role = 'teacher'");
$stmt2->bind_param("ss", $photo, $row['teacher_email']);
$ok = $stmt2->execute();
$stmt2->close();
$aconn->close();

// Best-effort cleanup — the save itself already succeeded either way, so a
// failed delete here shouldn't turn into a failed response.
if ($ok && $oldPhoto && $oldPhoto !== $photo) {
    cloudinaryDeleteByUrl($oldPhoto, 'image');
}

echo json_encode(['success' => $ok]);
