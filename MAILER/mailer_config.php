<?php
// SMTP credentials — read from environment variables so this file holds no
// secrets and is safe to commit. Set these locally via docker-compose.yml's
// environment: block and on Render via the dashboard's Environment tab —
// same pattern ADMIN_BACKEND/cloudinary_credentials.php already uses.
//
// SMTP_USER is the Gmail address emails are sent FROM (e.g. a dedicated
// noreply@ Gmail account for this project, not necessarily a personal one).
// SMTP_PASSWORD is a Gmail App Password (myaccount.google.com/apppasswords),
// NOT the account's normal login password — Gmail rejects the real password
// for SMTP since 2022.
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
define('SMTP_USER', getenv('SMTP_USER') ?: '');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'SPED ALM');

// Base URL used for the "Log In"/"View Notification" LINKS inside emails.
// Deliberately NOT derived from the request ($_SERVER['HTTP_HOST']) —
// emails are opened in the recipient's own mail client (Gmail, etc.),
// which can only load a publicly reachable address. A request running on
// localhost/Docker would otherwise put "http://localhost:8080/..." into
// the email, which the recipient can never reach. Always point at the
// real deployed site instead.
define('PUBLIC_SITE_URL', getenv('PUBLIC_SITE_URL') ?: 'https://capstone-demo-le1j.onrender.com');

/**
 * Returns the <img src> value for the SPED ALM logo inside an email.
 * A base64 data: URI looked like the right fix for the logo depending on
 * PUBLIC_SITE_URL being reachable at render time (Render's free tier spins
 * down after inactivity, so a cold instance made the linked logo fail to
 * load) — but Gmail and most mail clients strip data: URIs out of HTML
 * email bodies as a security measure, so that "fix" just rendered broken
 * too. The actual supported way to embed an image in an email is a CID
 * (Content-ID) inline attachment: send_email() calls
 * $mail->addEmbeddedImage(.../logo_email.png, 'sped_logo', ...) on every
 * send, and this constant is the matching "cid:" reference for <img src>.
 */
define('LOGO_CID_SRC', 'cid:sped_logo');
