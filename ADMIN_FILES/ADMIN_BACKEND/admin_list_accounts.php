<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode([]);
    exit;
}

// Check and add missing columns if they don't exist
$checkPhone = $conn->query("SHOW COLUMNS FROM admin_accounts LIKE 'phone_number'");
if ($checkPhone && $checkPhone->num_rows == 0) {
    $conn->query("ALTER TABLE admin_accounts ADD COLUMN phone_number VARCHAR(20) AFTER school_name");
}

$checkCreated = $conn->query("SHOW COLUMNS FROM admin_accounts LIKE 'created_at'");
if ($checkCreated && $checkCreated->num_rows == 0) {
    $conn->query("ALTER TABLE admin_accounts ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER updated_at");
}

// Query with dynamic status:
// - teachers: active only if they have logged in (last_login IS NOT NULL)
// - admin/others: use stored status
// Ensure profile_photo column exists
$conn->query("ALTER TABLE admin_accounts ADD COLUMN IF NOT EXISTS profile_photo LONGTEXT NULL DEFAULT NULL");

$result = $conn->query("SELECT id, admin_email, first_name, last_name,
    COALESCE(phone_number, '') as phone_number,
    role, condition_info,
    COALESCE(assigned_teacher_id, 0) as assigned_teacher_id,
    COALESCE(parent_name, '') as parent_name,
    COALESCE(status, 'inactive') as status,
    last_login,
    COALESCE(created_at, NOW()) as created_at,
    COALESCE(profile_photo, '') as profile_photo
FROM admin_accounts
WHERE is_deleted = 0 OR is_deleted IS NULL
ORDER BY id DESC");

if (!$result) {
    echo json_encode([]);
    $conn->close();
    exit;
}

$accounts = [];
while ($row = $result->fetch_assoc()) {
    $accounts[] = $row;
}

echo json_encode($accounts);
$conn->close();