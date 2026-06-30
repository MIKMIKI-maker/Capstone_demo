<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get user_id from POST (should be passed via JavaScript)
    // For now, we'll extract it from a hidden input or from the session
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $temp_password = isset($_POST['enter_temporary_password']) && trim($_POST['enter_temporary_password']) !== ''
        ? trim($_POST['enter_temporary_password'])
        : 'Temp@1234';

    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        exit;
    }

    $hashed = password_hash($temp_password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE admin_accounts SET admin_password = ? WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Query preparation failed']);
        exit;
    }

    $stmt->bind_param("si", $hashed, $user_id);

    if ($stmt->execute()) {
        echo json_encode([
            'success'       => true,
            'message'       => 'Password reset successfully',
            'temp_password' => $temp_password
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to reset password']);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>
