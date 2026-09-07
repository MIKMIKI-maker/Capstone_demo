<?php
// Brevo (api.brevo.com) sends every email over HTTPS instead of raw SMTP —
// Render's hosting blocks outbound SMTP (port 587/465) entirely, which is
// what direct-SMTP sending (the old PHPMailer path) was hitting: every send
// timed out with "SMTP Error: Could not connect to SMTP host... Connection
// timed out". A plain HTTPS POST isn't affected by that block.
//
// Read from environment variables so this file holds no secrets and is safe
// to commit. Set these locally via docker-compose.yml's environment: block
// and on Render via the dashboard's Environment tab — same pattern
// ADMIN_BACKEND/cloudinary_credentials.php already uses.
//
// BREVO_API_KEY comes from Brevo's dashboard (Settings > SMTP & API > API
// Keys). SMTP_USER is the sender address, verified as a "Sender" in Brevo's
// dashboard first (Senders, Domains & Dedicated IPs) - Brevo rejects sends
// from an unverified sender address.
define('BREVO_API_KEY', getenv('BREVO_API_KEY') ?: '');
define('SMTP_USER', getenv('SMTP_USER') ?: '');
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
 * The <img src> value for the SPED ALM logo inside an email.
 * A base64 data: URI looked like a fix for PUBLIC_SITE_URL going cold
 * (Render's free tier spins down after inactivity) — but Gmail and most
 * mail clients strip data: URIs out of HTML email bodies as a security
 * measure, so that "fix" just rendered broken too. CID (Content-ID) inline
 * attachment worked, but only over direct SMTP, which Render's outbound
 * network blocks entirely regardless of the logo. GitHub's raw content CDN
 * is always warm (no cold-start) and doesn't depend on this app's own
 * uptime, so it survives both problems at once.
 */
define('LOGO_CID_SRC', 'https://raw.githubusercontent.com/MIKMIKI-maker/Capstone_demo/main/MAILER/logo_email.png');
