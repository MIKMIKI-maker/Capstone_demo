<?php
require_once __DIR__ . '/db.php';
requireAdminSession();

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

// last_activity_taken (students only) proves genuine engagement — an
// activity actually submitted — rather than just last_login, which only
// proves the account was opened and could sit at "Active" forever even if
// the student never did any actual work after that first login.
$result = $conn->query("SELECT a.id, a.admin_email, a.first_name, a.last_name,
    COALESCE(a.phone_number, '') as phone_number,
    a.role, a.condition_info,
    COALESCE(a.assigned_teacher_id, 0) as assigned_teacher_id,
    COALESCE(a.parent_name, '') as parent_name,
    COALESCE(a.status, 'inactive') as status,
    a.last_login,
    COALESCE(a.created_at, NOW()) as created_at,
    COALESCE(a.profile_photo, '') as profile_photo,
    (SELECT MAX(sub.submitted_at) FROM activity_submissions sub
     INNER JOIN students s ON s.id = sub.student_id
     WHERE s.admin_account_id = a.id) AS last_activity_taken
FROM admin_accounts a
WHERE a.is_deleted = 0 OR a.is_deleted IS NULL
ORDER BY a.id DESC");

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