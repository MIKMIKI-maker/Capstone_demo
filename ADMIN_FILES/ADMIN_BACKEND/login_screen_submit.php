<?php
// This is a public endpoint (reachable without being logged in), so an
// unhandled PHP error/warning here would print raw technical details
// (file paths, DB structure) straight into the response body for anyone
// to see. display_errors stays off; log_errors keeps them visible to
// developers via the server's own error log instead.
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ini_set('session.cookie_secure', '1');
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/login_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Handle POST login request
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = isset($_POST['admin_email'])    ? trim($_POST['admin_email'])    : '';
    $password = isset($_POST['admin_password']) ? trim($_POST['admin_password']) : '';

    if ($email === '' || $password === '') {
        echo json_encode(['status' => 'error', 'message' => 'Email and password required']);
        $conn->close();
        exit;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Locked out per-account (by email), not per-IP — otherwise one
    // person's failed attempts on a shared network (e.g. school WiFi)
    // would also block every other account trying to log in from there.
    $MAX_ATTEMPTS = 5;
    $LOCKOUT_MINUTES = 5;
    // Fetching the oldest attempt in the window (not just the count) is what
    // lets the frontend show a live countdown instead of a static "5
    // minutes" — the lockout actually clears as soon as that oldest attempt
    // ages past the window, which is almost never exactly 5 minutes away.
    $chk = $conn->prepare("SELECT COUNT(*) AS cnt, MIN(attempted_at) AS oldest FROM login_attempts WHERE email = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    if ($chk) {
        $chk->bind_param("si", $email, $LOCKOUT_MINUTES);
        $chk->execute();
        $chkRow = $chk->get_result()->fetch_assoc();
        $chk->close();
        if ($chkRow && (int)$chkRow['cnt'] >= $MAX_ATTEMPTS) {
            $retryAfter = max(1, ($LOCKOUT_MINUTES * 60) - (time() - strtotime($chkRow['oldest'])));
            echo json_encode(['status' => 'error', 'code' => 'too_many_attempts', 'message' => 'Too many failed login attempts. Please try again in ' . $LOCKOUT_MINUTES . ' minutes.', 'retry_after_seconds' => $retryAfter]);
            $conn->close();
            exit;
        }
    }

    $stmt = $conn->prepare("SELECT id, admin_password, first_name, last_name, role, COALESCE(profile_photo,'') AS profile_photo, COALESCE(is_deleted,0) AS is_deleted, COALESCE(totp_enabled,0) AS totp_enabled, COALESCE(must_change_password,0) AS must_change_password FROM admin_accounts WHERE admin_email = ?");
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'Database query failed']);
        $conn->close();
        exit;
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();

        if (!empty($row['is_deleted'])) {
            echo json_encode(['status' => 'error', 'code' => 'account_deleted', 'message' => 'Your account has been deactivated. Please contact your administrator.']);
            $stmt->close();
            $conn->close();
            exit;
        }

        $stored_pw = $row['admin_password'];

        $auth_ok = false;
        if (password_verify($password, $stored_pw)) {
            $auth_ok = true;
        } elseif (!str_starts_with($stored_pw, '$2y$') && $password === $stored_pw) {
            $auth_ok = true;
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            $upg = $conn->prepare("UPDATE admin_accounts SET admin_password = ? WHERE id = ?");
            if ($upg) { $upg->bind_param("si", $new_hash, $row['id']); $upg->execute(); $upg->close(); }
        }

        if ($auth_ok) {
            session_regenerate_id(true);
            $clr = $conn->prepare("DELETE FROM login_attempts WHERE email = ?");
            if ($clr) { $clr->bind_param("s", $email); $clr->execute(); $clr->close(); }

            // 2FA is mandatory for every admin account. Password is correct,
            // but the real session isn't granted yet — only a "pending"
            // marker good for the one follow-up request, verified in
            // admin_2fa_login_verify.php (already has 2FA) or
            // admin_2fa_setup_confirm.php (first-time setup) before any
            // admin_* session var is set.
            if ($row['role'] === 'admin') {
                if (!empty($row['totp_enabled'])) {
                    $_SESSION['pending_2fa_admin_id'] = $row['id'];
                    echo json_encode(['status' => 'need_2fa', 'message' => 'Enter your 2FA code']);
                } else {
                    $_SESSION['pending_2fa_setup_admin_id'] = $row['id'];
                    echo json_encode(['status' => 'need_2fa_setup', 'message' => '2FA setup required']);
                }
                $stmt->close();
                $conn->close();
                exit;
            }

            // Teacher/Student accounts start on a password the Admin set or
            // a role default ("Teacher@123"/"Student@123") - block the real
            // session until they pick their own, the same "pending" pattern
            // used above for 2FA (no admin_* session var set yet).
            if (in_array($row['role'], ['teacher', 'student'], true) && !empty($row['must_change_password'])) {
                $_SESSION['pending_pwd_change_id']   = $row['id'];
                $_SESSION['pending_pwd_change_role'] = $row['role'];
                echo json_encode(['status' => 'need_password_change', 'message' => 'Please set a new password to continue.']);
                $stmt->close();
                $conn->close();
                exit;
            }

            echo json_encode(completeAccountSession($conn, $row, $email));
        } else {
            $fail = $conn->prepare("INSERT INTO login_attempts (ip_address, email) VALUES (?, ?)");
            if ($fail) { $fail->bind_param("ss", $ip, $email); $fail->execute(); $fail->close(); }
            $conn->query("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");

            echo json_encode(['status' => 'error', 'message' => 'Invalid password']);
        }
    } else {
        $fail = $conn->prepare("INSERT INTO login_attempts (ip_address, email) VALUES (?, ?)");
        if ($fail) { $fail->bind_param("ss", $ip, $email); $fail->execute(); $fail->close(); }

        echo json_encode(['status' => 'error', 'message' => 'User not found']);
    }

    $stmt->close();
}

$conn->close();