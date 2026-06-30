<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$conn = getDatabaseConnection();
if (!$conn) { echo json_encode([]); exit; }

$result = $conn->query(
    "SELECT id, admin_email, first_name, last_name,
            role, condition_info,
            COALESCE(status, 'inactive') AS status,
            COALESCE(created_at, NOW()) AS created_at,
            deleted_at,
            COALESCE(profile_photo, '') AS profile_photo
     FROM admin_accounts
     WHERE is_deleted = 1
     ORDER BY deleted_at DESC, id DESC"
);

$accounts = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $accounts[] = $row;
    }
}

echo json_encode($accounts);
$conn->close();
?>
