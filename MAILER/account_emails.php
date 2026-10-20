<?php
require_once __DIR__ . '/mailer_config.php';
require_once __DIR__ . '/../ADMIN_FILES/ADMIN_BACKEND/school_config.php';

/*
 * The emails about an account itself: the welcome email with its login
 * credentials (Add Account, and again when the Admin corrects the email of
 * an account that was never used), and the notice that the login email was
 * changed. LOGO_CID_SRC / PUBLIC_SITE_URL so the logo and login link work in
 * the recipient's inbox even from localhost/Docker — see mailer_config.php.
 */

function _accountEmailShell(string $title, string $bodyHtml): string {
    $logoUrl = LOGO_CID_SRC;
    $loginUrl = PUBLIC_SITE_URL . '/ADMIN_FILES/login_screen.html';
    return "<div style=\"max-width:480px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:32px 28px;font-family:Arial,Helvetica,sans-serif;color:#1e293b;\">"
        . "<div style=\"text-align:center;margin-bottom:18px;\"><img src=\"{$logoUrl}\" alt=\"SPED ALM\" width=\"64\" height=\"64\" style=\"width:64px;height:64px;border-radius:50%;\"></div>"
        . "<h2 style=\"text-align:center;color:#1e3a8a;margin:0 0 24px;font-size:22px;\">{$title}</h2>"
        . $bodyHtml
        . "<div style=\"text-align:center;margin:0 0 24px;\"><a href=\"{$loginUrl}\" style=\"display:inline-block;background:#1e3a8a;color:#ffffff;text-decoration:none;padding:12px 32px;border-radius:10px;font-weight:700;font-size:14px;\">Log In to SPED ALM</a></div>"
        . "<hr style=\"border:none;border-top:1px solid #e2e8f0;margin:0 0 16px;\">"
        . "<p style=\"margin:0 0 16px;\">Sincerely yours,<br>SPED ALM System</p>"
        // Real, identifiable sender info (institution name + address) is one
        // of the signals spam filters use to tell a legitimate account email
        // apart from a phishing template — it doesn't guarantee inbox
        // placement, but it's a real, controllable signal.
        . "<p style=\"font-size:11px;color:#94a3b8;text-align:center;margin:0;\">" . htmlspecialchars(SCHOOL_NAME) . " &middot; SPED Program &middot; " . htmlspecialchars(SCHOOL_LOCATION) . "<br>If you did not expect this email, you can safely ignore it.</p>"
        . "</div>";
}

/** Welcome email with the username and temporary password. */
function accountWelcomeEmailHtml(string $fullName, string $roleLabel, string $email, string $password): string {
    $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
    $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $safePassword = htmlspecialchars($password, ENT_QUOTES, 'UTF-8');
    return _accountEmailShell('Welcome to SPED ALM',
        "<p style=\"margin:0 0 12px;\">Dear {$safeName},</p>"
        . "<p style=\"margin:0 0 16px;\">We are pleased to inform you that your {$roleLabel} Account has been successfully created in the SPED ALM System.</p>"
        . "<p style=\"margin:0 0 10px;\">Below are your account credentials:</p>"
        . "<div style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px 20px;margin:0 0 24px;\">"
        . "<p style=\"margin:0 0 8px;font-size:14px;\"><strong>Username:</strong> {$safeEmail}</p>"
        . "<p style=\"margin:0;font-size:14px;\"><strong>Password:</strong> {$safePassword}</p>"
        . "</div>"
        . "<p style=\"font-size:13px;color:#64748b;margin:0 0 20px;\">Please keep your account credentials confidential and secure. You may now log in to the SPED ALM System using the credentials provided above.</p>");
}

/** Notice that the account's login email is now this address (password unchanged). */
function accountEmailChangedHtml(string $fullName, string $email): string {
    $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
    $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    return _accountEmailShell('Your login email was updated',
        "<p style=\"margin:0 0 12px;\">Dear {$safeName},</p>"
        . "<p style=\"margin:0 0 16px;\">The School Administrator updated the email address of your SPED ALM account. From now on, log in and receive SPED ALM emails at this address.</p>"
        . "<div style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px 20px;margin:0 0 16px;\">"
        . "<p style=\"margin:0;font-size:14px;\"><strong>Username:</strong> {$safeEmail}</p>"
        . "</div>"
        . "<p style=\"font-size:13px;color:#64748b;margin:0 0 20px;\">Your password did not change. If you forgot it, ask the School Administrator to reset it.</p>");
}
