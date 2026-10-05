<?php
/**
 * Admin Settings → School Year → Classes (view only).
 * GET ?school_year_id=N → that S.Y.'s classes (Section + Teacher) and their students.
 * Classes follow the accounts: a teacher's Section and a student's teacher
 * are edited in Manage Users, and the Active S.Y.'s classes update to match.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/school_year.php';
requireAdminSession();

header('Content-Type: application/json');

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$activeId = getActiveSchoolYearId($conn);
$sy = (int)($_GET['school_year_id'] ?? 0) ?: $activeId;
if ($sy === $activeId) reconcileActiveEnrollments($conn);
echo json_encode(['success' => true] + loadClassesForSchoolYear($conn, $sy) + ['is_active' => $sy === $activeId]);
