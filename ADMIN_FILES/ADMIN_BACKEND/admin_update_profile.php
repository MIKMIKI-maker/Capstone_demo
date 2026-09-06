<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
requireAdminSession();
csrf_require_valid_token();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Deliberately narrow: only the admin's own display name. Email is the
// login identity (and constrained to the @spedalm.edu.ph domain), and
// school_name is institution-level, not personal — both stay out of a
// self-service profile form to avoid side effects elsewhere in the app.
$first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
$last_name  = isset($_POST['last_name'])  ? trim($_POST['last_name'])  : '';

if ($first_name === '') {
    echo json_encode(['success' => false, 'message' => 'First name is required']);
    exit;
}
if (mb_strlen($first_name) > 100 || mb_strlen($last_name) > 100) {
    echo json_encode(['success' => false, 'message' => 'Name is too long']);
    exit;
}

$adminId = (int)$_SESSION['admin_id'];

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$stmt = $conn->prepare("UPDATE admin_accounts SET first_name = ?, last_name = ? WHERE id = ?");
$stmt->bind_param("ssi", $first_name, $last_name, $adminId);
$success = $stmt->execute();
$stmt->close();
$conn->close();

echo json_encode([
    'success' => (bool)$success,
    'message' => $success ? 'Profile updated successfully' : 'Could not update profile',
    'first_name' => $first_name,
    'last_name' => $last_name,
]);
