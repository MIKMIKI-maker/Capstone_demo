<?php
require_once __DIR__ . '/db.php';
requireAdminSession();

header('Content-Type: application/json');

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode([]);
    exit;
}

// Create admin_activities table if it doesn't exist
$createTableSql = "CREATE TABLE IF NOT EXISTS admin_activities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  activity_type VARCHAR(50) NOT NULL,
  user_type VARCHAR(50),
  user_name VARCHAR(100),
  user_email VARCHAR(255),
  action_detail VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created_at (created_at)
)";
$conn->query($createTableSql);

// Get recent activities from admin_activities table (last 20) - only show teaching-related
// activity, not account/admin housekeeping. LEFT JOINed to admin_accounts so a teacher
// removed from Manage Users (is_deleted = 1) drops out of here too — but an unmatched
// email (aa.is_deleted IS NULL) still shows, instead of silently disappearing the way an
// INNER JOIN would for any pre-existing row whose email doesn't cleanly match.
$result = $conn->query("SELECT log.id, log.activity_type, log.user_type, log.user_name, log.user_email, log.action_detail, log.created_at
                        FROM admin_activities log
                        LEFT JOIN admin_accounts aa ON aa.admin_email = log.user_email
                        WHERE log.activity_type IN ('Create Activity', 'Complete Activity', 'Save Draft', 'Material Uploaded', 'Material Deleted', 'Unpublish Activity', 'Delete Draft')
                          AND (aa.is_deleted IS NULL OR aa.is_deleted = 0)
                        ORDER BY log.created_at DESC
                        LIMIT 20");

$activities = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $activities[] = [
            'id' => $row['id'],
            'type' => $row['activity_type'],
            'user_type' => $row['user_type'],
            'user_name' => $row['user_name'],
            'user_email' => $row['user_email'],
            'action_detail' => $row['action_detail'],
            'created_at' => $row['created_at']
        ];
    }
}

echo json_encode($activities);
$conn->close();
