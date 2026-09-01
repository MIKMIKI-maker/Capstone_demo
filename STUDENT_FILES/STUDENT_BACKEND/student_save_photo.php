<?php
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/db.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/photo_validation.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/cloudinary_upload.php';
require_once __DIR__ . '/student_auth.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

// Never trust a student id passed in the request — always the caller's own
// session-bound account (see student_auth.php).
$student_account_id = requireStudentSession();
$photoInput = isset($_POST['photo']) ? $_POST['photo'] : '';

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

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'DB error']);
    exit;
}

$oldPhoto = null;
$oldStmt = $conn->prepare("SELECT profile_photo FROM admin_accounts WHERE id = ? AND role = 'student'");
if ($oldStmt) {
    $oldStmt->bind_param("i", $student_account_id);
    $oldStmt->execute();
    $oldRow = $oldStmt->get_result()->fetch_assoc();
    $oldStmt->close();
    $oldPhoto = $oldRow['profile_photo'] ?? null;
}

$stmt = $conn->prepare("UPDATE admin_accounts SET profile_photo = ? WHERE id = ? AND role = 'student'");
$stmt->bind_param("si", $photo, $student_account_id);
$ok = $stmt->execute();
$stmt->close();
$conn->close();

// Best-effort cleanup — the save itself already succeeded either way, so a
// failed delete here shouldn't turn into a failed response.
if ($ok && $oldPhoto && $oldPhoto !== $photo) {
    cloudinaryDeleteByUrl($oldPhoto, 'image');
}

echo json_encode(['success' => $ok]);
