<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Create settings table if it doesn't exist
$createTableSql = "CREATE TABLE IF NOT EXISTS admin_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    setting_name VARCHAR(255) NOT NULL,
    setting_value VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_setting (admin_id, setting_name),
    FOREIGN KEY (admin_id) REFERENCES admin_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$conn->query($createTableSql);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $admin_id = isset($_GET['admin_id']) ? intval($_GET['admin_id']) : 0;
    if (!$admin_id) {
        echo json_encode(['success' => false, 'message' => 'Missing admin_id']);
        exit;
    }

    $settings = [];
    $stmt = $conn->prepare("SELECT setting_name, setting_value FROM admin_settings WHERE admin_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_name']] = $row['setting_value'];
    }
    $stmt->close();

    echo json_encode(['success' => true, 'settings' => $settings]);
    $conn->close();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_id = isset($_POST['admin_id']) ? intval($_POST['admin_id']) : 0;
    if (!$admin_id) {
        echo json_encode(['success' => false, 'message' => 'Missing admin_id']);
        exit;
    }

    // Collect notification preferences
    $notification_attendance = isset($_POST['checkbox_input']) ? 1 : 0;
    $notification_activity = isset($_POST['checkbox_input_2']) ? 1 : 0;
    $notification_system = isset($_POST['checkbox_input_3']) ? 1 : 0;
    
    // Save settings to database
    $settings = [
        'notification_attendance' => $notification_attendance,
        'notification_activity' => $notification_activity,
        'notification_system' => $notification_system
    ];
    
    $success = true;
    foreach ($settings as $setting_name => $setting_value) {
        $stmt = $conn->prepare("INSERT INTO admin_settings (admin_id, setting_name, setting_value) 
                                VALUES (?, ?, ?) 
                                ON DUPLICATE KEY UPDATE setting_value = ?");
        
        if (!$stmt) {
            $success = false;
            break;
        }
        
        $setting_value_str = (string)$setting_value;
        $stmt->bind_param("isss", $admin_id, $setting_name, $setting_value_str, $setting_value_str);
        
        if (!$stmt->execute()) {
            $success = false;
            $stmt->close();
            break;
        }
        $stmt->close();
    }
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save settings']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>
