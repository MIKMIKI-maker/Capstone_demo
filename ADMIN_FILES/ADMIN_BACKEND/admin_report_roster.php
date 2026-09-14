<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
requireAdminSession();

header('Content-Type: application/json');

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// One row per teacher (a "section"), with their active roster students
// GROUP_CONCAT'd alongside. Excluding a teacher whose matching admin_accounts
// row is deleted mirrors the same join admin_stats.php already uses for
// activity counts, so a removed/deactivated teacher's old roster doesn't
// still show up here.
//
// The section name an admin picks in Manage Users → Edit Teacher (the
// "Non-Graded Maya" etc. dropdown) is actually saved into admin_accounts.
// condition_info, not teacher_accounts.class_section — class_section has no
// save path anywhere yet. teacher_get_profile.php already falls back to
// condition_info when class_section is blank; mirror that same precedence
// here so a section the admin already set actually shows up.
$sql = "SELECT tc.id AS teacher_id, tc.first_name, tc.last_name, tc.teacher_email,
               COALESCE(NULLIF(tc.class_section, ''), NULLIF(aa.condition_info, ''), '') AS class_section,
               GROUP_CONCAT(s.student_name ORDER BY s.student_name SEPARATOR '||') AS student_names
        FROM teacher_accounts tc
        INNER JOIN admin_accounts aa ON aa.admin_email = tc.teacher_email AND aa.role = 'teacher'
        LEFT JOIN students s ON s.teacher_id = tc.id AND s.status = 'active'
        WHERE (aa.is_deleted IS NULL OR aa.is_deleted = 0)
        GROUP BY tc.id, tc.first_name, tc.last_name, tc.teacher_email, tc.class_section, aa.condition_info
        ORDER BY tc.first_name, tc.last_name";

$result = $conn->query($sql);
if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Query failed']);
    $conn->close();
    exit;
}

$sections = [];
$totalStudents = 0;
while ($row = $result->fetch_assoc()) {
    $teacherName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    if ($teacherName === '') $teacherName = $row['teacher_email'];

    $students = $row['student_names'] ? explode('||', $row['student_names']) : [];
    $totalStudents += count($students);

    $sections[] = [
        'teacher_id'    => (int)$row['teacher_id'],
        'teacher_name'  => $teacherName,
        'class_section' => $row['class_section'],
        'students'      => $students,
    ];
}
$conn->close();

echo json_encode([
    'success'        => true,
    'sections'       => $sections,
    'total_teachers' => count($sections),
    'total_students' => $totalStudents,
]);
