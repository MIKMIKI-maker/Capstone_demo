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

// Get recent activities from admin_activities table (last 15) - only show teaching-related activity, not account/admin housekeeping
$result = $conn->query("SELECT id, activity_type, user_type, user_name, user_email, action_detail, created_at
                        FROM admin_activities
                        WHERE activity_type IN ('Create Activity', 'Complete Activity', 'Save Draft', 'Material Uploaded', 'Material Deleted', 'Unpublish Activity', 'Delete Draft')
                        ORDER BY created_at DESC
                        LIMIT 15");

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

// Sort all activities by created_at descending and return top 10
usort($activities, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

echo json_encode(array_slice($activities, 0, 10));
$conn->close();
