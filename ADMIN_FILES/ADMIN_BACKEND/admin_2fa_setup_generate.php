<?php
// Reached only mid-login, before the real admin session exists (2FA setup
// is now mandatory on first login, not an optional Settings toggle) — so
// the gate here is the "pending setup" marker from login_screen_submit.php,
// not requireAdminSession().
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ini_set('session.cookie_secure', '1');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/TOTP.php';

header('Content-Type: application/json');

$adminId = $_SESSION['pending_2fa_setup_admin_id'] ?? 0;
if (!$adminId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No pending 2FA setup. Please log in again.']);
    exit;
}

$conn = getDatabaseConnection();
$accountEmail = 'admin';
if ($conn) {
    $stmt = $conn->prepare("SELECT admin_email FROM admin_accounts WHERE id = ?");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    if ($row) $accountEmail = $row['admin_email'];
}

// Held in the session only, not the DB, until admin_2fa_setup_confirm.php
// verifies a real code from the app — an abandoned setup (scanned the QR,
// never confirmed, closed the tab) should never half-enable 2FA.
$secret = TOTP::generateSecret();
$_SESSION['pending_totp_secret'] = $secret;
$otpAuthUri = TOTP::getOtpAuthUri($secret, $accountEmail);

echo json_encode([
    'success' => true,
    'secret' => $secret,
    'otpauth_uri' => $otpAuthUri,
]);
