<?php
function requireTeacherSession() {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $role = strtolower(trim((string)($_SESSION['admin_role'] ?? '')));
    $teacherId = (int)($_SESSION['teacher_id'] ?? 0);
    if ($role !== 'teacher' || $teacherId <= 0) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Not authenticated as a teacher']);
        exit;
    }
    return $teacherId;
}

function requireTeacherId() {
    return requireTeacherSession();
}
?>
