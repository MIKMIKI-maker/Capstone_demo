<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
requireAdminSession();

header('Content-Type: application/json');
echo json_encode(['success' => true, 'token' => csrf_get_token()]);
