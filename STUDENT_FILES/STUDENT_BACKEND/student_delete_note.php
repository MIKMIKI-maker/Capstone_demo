<?php
header('Content-Type: application/json');

require_once __DIR__ . '/student_auth.php';
requireStudentSession();

http_response_code(403);
echo json_encode(['success' => false, 'message' => 'Students can view teacher notes but cannot delete them.']);
?>
