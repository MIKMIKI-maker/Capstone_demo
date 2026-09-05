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
 * Returns the SPED ALM logo as a base64 data: URI, for embedding directly
 * IN the email HTML rather than linking to PUBLIC_SITE_URL/ASSETS/logo.png.
 * A linked image depends on the live site being reachable and awake at the
 * exact moment the recipient's mail client renders the email — Render's
 * free tier spins down after inactivity, so a cold instance can make the
 * logo fail to load (permanently, for that one open, on some clients) even
 * though the URL works fine moments later. Embedding removes that
 * dependency entirely. MAILER/logo_email.png is a small (~4KB) 96x96
 * downscale of ASSETS/logo.png — the full 1.6MB original would bloat the
 * email and risk spam-filter/clipping thresholds.
 */
function get_logo_data_uri(): string {
    static $cached = null;
    if ($cached !== null) return $cached;
    $path = __DIR__ . '/logo_email.png';
    if (!is_file($path)) { $cached = ''; return $cached; }
    $cached = 'data:image/png;base64,' . base64_encode(file_get_contents($path));
    return $cached;
}
