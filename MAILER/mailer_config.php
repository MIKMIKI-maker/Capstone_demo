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
