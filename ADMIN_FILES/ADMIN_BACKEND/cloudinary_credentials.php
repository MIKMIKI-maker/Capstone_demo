<?php
// Cloudinary credentials — read from environment variables so this file
// holds no secrets and is safe to commit. Set these locally via
// docker-compose.yml's environment: block (gitignored, not committed), and
// on Render via the dashboard's Environment tab — same pattern db.php
// already uses for DB_HOST etc.
define('CLOUDINARY_CLOUD_NAME', getenv('CLOUDINARY_CLOUD_NAME') ?: '');
define('CLOUDINARY_API_KEY', getenv('CLOUDINARY_API_KEY') ?: '');
define('CLOUDINARY_API_SECRET', getenv('CLOUDINARY_API_SECRET') ?: '');
