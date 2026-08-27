<?php
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/db.php';
require_once __DIR__ . '/student_auth.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// First/last name are admin/teacher-assigned and locked on the student side
// (Student_settings.html shows them read-only) — this endpoint no longer
// accepts changes to them, so a direct POST can't bypass that lock.
requireStudentSession();
echo json_encode(['success' => true, 'message' => 'No editable profile fields to update']);
?>
