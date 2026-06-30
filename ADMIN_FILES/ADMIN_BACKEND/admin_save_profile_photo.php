<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$admin_account_id = isset($_POST['admin_account_id']) ? intval($_POST['admin_account_id']) : 0;
$photo            = isset($_POST['photo']) ? $_POST['photo'] : '';

if (!$admin_account_id) {
    echo json_encode(['success' => false, 'message' => 'Missing admin_account_id']);
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
