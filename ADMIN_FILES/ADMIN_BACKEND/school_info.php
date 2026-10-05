<?php
// Public (no login needed — the login screen shows it): the school's name
// and location from school_config.php, so pages don't hardcode them.
require_once __DIR__ . '/school_config.php';
header('Content-Type: application/json');
header('Cache-Control: public, max-age=300');
echo json_encode(['name' => SCHOOL_NAME, 'location' => SCHOOL_LOCATION]);
