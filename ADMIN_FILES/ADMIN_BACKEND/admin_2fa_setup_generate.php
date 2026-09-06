<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/TOTP.php';
requireAdminSession();

header('Content-Type: application/json');

// Held in the session only, not the DB, until admin_2fa_setup_confirm.php
// verifies a real code from the app — an abandoned setup (scanned the QR,
// never confirmed, closed the tab) should never half-enable 2FA.
$secret = TOTP::generateSecret();
$_SESSION['pending_totp_secret'] = $secret;

$accountEmail = $_SESSION['admin_email'] ?? 'admin';
$otpAuthUri = TOTP::getOtpAuthUri($secret, $accountEmail);

echo json_encode([
    'success' => true,
    'secret' => $secret,
    'otpauth_uri' => $otpAuthUri,
]);
