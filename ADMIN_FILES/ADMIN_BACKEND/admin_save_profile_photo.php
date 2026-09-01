<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/photo_validation.php';
requireAdminSession();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$admin_account_id = (int)$_SESSION['admin_id'];
$photoInput       = isset($_POST['photo']) ? $_POST['photo'] : '';

if (!$admin_account_id) {
    echo json_encode(['success' => false, 'message' => 'Missing authenticated admin']);
    exit;
}

// Empty input clears the photo. Anything else must be a genuine small
// base64 image — the raw client-supplied string is never trusted as-is
// (that was an XSS vector: a crafted "photo" value rendered unescaped
// elsewhere could run script in an admin's browser).
$photo = sanitizeProfilePhotoDataUri($photoInput);
if ($photo === null) {
    echo json_encode(['success' => false, 'message' => 'Invalid image. Please try a different photo.']);
    exit;
}

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'DB error']);
    exit;
}

// Ensure column exists (runs once per schema, harmless after that)
$conn->query("ALTER TABLE admin_accounts ADD COLUMN IF NOT EXISTS profile_photo LONGTEXT NULL DEFAULT NULL");

$stmt = $conn->prepare("UPDATE admin_accounts SET profile_photo = ? WHERE id = ?");
$stmt->bind_param("si", $photo, $admin_account_id);
$ok = $stmt->execute();
$stmt->close();
$conn->close();

echo json_encode(['success' => $ok]);
