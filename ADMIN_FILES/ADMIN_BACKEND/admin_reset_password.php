<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/school_config.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/password_policy.php';
require_once __DIR__ . '/../../MAILER/send_email.php';
requireAdminSession();
csrf_require_valid_token();

header('Content-Type: application/json');

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    $conn->close();
    exit;
}

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    $conn->close();
    exit;
}

$userStmt = $conn->prepare("SELECT first_name, last_name, admin_email, role FROM admin_accounts WHERE id = ? AND (is_deleted = 0 OR is_deleted IS NULL)");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Account not found']);
    $conn->close();
    exit;
}

// Same random temporary password as a new account gets, and — like a new
// account — teachers and students must replace it on their next login.
// Admins are left out of the forced change, matching Add Account.
$temp_password = generateTemporaryPassword($user['first_name'], $user['last_name']);
$hashed = password_hash($temp_password, PASSWORD_DEFAULT);
$mustChange = in_array($user['role'], ['teacher', 'student'], true) ? 1 : 0;

$stmt = $conn->prepare("UPDATE admin_accounts SET admin_password = ?, must_change_password = ? WHERE id = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Query preparation failed']);
    $conn->close();
    exit;
}
$stmt->bind_param("sii", $hashed, $mustChange, $user_id);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to reset password']);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

$fullName = trim($user['first_name'] . ' ' . $user['last_name']);
$logStmt = $conn->prepare("INSERT INTO admin_activities (activity_type, user_type, user_name, user_email, action_detail) VALUES ('Reset Password', 'admin', ?, ?, ?)");
if ($logStmt) {
    $logName  = $_SESSION['admin_name']  ?? '';
    $logEmail = $_SESSION['admin_email'] ?? '';
    $logDetail = "Reset password for {$user['role']}: {$fullName}";
    $logStmt->bind_param("sss", $logName, $logEmail, $logDetail);
    $logStmt->execute();
    $logStmt->close();
}
$conn->close();

// Email the new password straight to the account's own inbox, the same way
// the welcome email delivers the first one.
$logoUrl   = LOGO_CID_SRC;
$loginUrl  = PUBLIC_SITE_URL . '/ADMIN_FILES/login_screen.html';
$safeName  = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
$safeEmail = htmlspecialchars($user['admin_email'], ENT_QUOTES, 'UTF-8');
$safePw    = htmlspecialchars($temp_password, ENT_QUOTES, 'UTF-8');
$changeNote = $mustChange ? "<p style=\"margin:0 0 16px;\">You will be asked to choose a new password the next time you log in.</p>" : '';
$html = "<div style=\"max-width:480px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:32px 28px;font-family:Arial,Helvetica,sans-serif;color:#1e293b;\">"
    . "<div style=\"text-align:center;margin-bottom:18px;\"><img src=\"{$logoUrl}\" alt=\"SPED ALM\" width=\"64\" height=\"64\" style=\"width:64px;height:64px;border-radius:50%;\"></div>"
    . "<h2 style=\"text-align:center;color:#1e3a8a;margin:0 0 24px;font-size:22px;\">Your Password Was Reset</h2>"
    . "<p style=\"margin:0 0 12px;\">Dear {$safeName},</p>"
    . "<p style=\"margin:0 0 16px;\">An administrator has reset the password of your SPED ALM account. Use this temporary password to log in:</p>"
    . "<div style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px 20px;margin:0 0 24px;\">"
    . "<p style=\"margin:0 0 8px;font-size:14px;\"><strong>Username:</strong> {$safeEmail}</p>"
    . "<p style=\"margin:0;font-size:14px;\"><strong>Temporary Password:</strong> {$safePw}</p>"
    . "</div>"
    . $changeNote
    . "<div style=\"text-align:center;margin:0 0 24px;\"><a href=\"{$loginUrl}\" style=\"display:inline-block;background:#1e3a8a;color:#ffffff;text-decoration:none;padding:12px 32px;border-radius:10px;font-weight:700;font-size:14px;\">Log In to SPED ALM</a></div>"
    . "<p style=\"font-size:13px;color:#64748b;margin:0 0 20px;\">If you did not ask for a password reset, please contact your school's SPED ALM administrator.</p>"
    . "<hr style=\"border:none;border-top:1px solid #e2e8f0;margin:0 0 16px;\">"
    . "<p style=\"margin:0 0 16px;\">Sincerely yours,<br>SPED ALM System</p>"
    . "<p style=\"font-size:11px;color:#94a3b8;text-align:center;margin:0;\">" . htmlspecialchars(SCHOOL_NAME) . " &middot; SPED Program &middot; " . htmlspecialchars(SCHOOL_LOCATION) . "</p>"
    . "</div>";
$emailSent = send_email($user['admin_email'], $fullName, 'Your SPED ALM password was reset', $html);

// The password is only shown to the Admin when the email didn't go out, so
// it can still be passed to the user another way.
if ($emailSent) {
    echo json_encode(['success' => true, 'email_sent' => true, 'email' => $user['admin_email'], 'message' => 'Password reset']);
} else {
    echo json_encode(['success' => true, 'email_sent' => false, 'temp_password' => $temp_password, 'message' => 'Password reset, but the email could not be sent']);
}
