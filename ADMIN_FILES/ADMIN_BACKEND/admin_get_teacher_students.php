<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$teacher_id = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : 0;
if (!$teacher_id) {
    echo json_encode(['students' => [], 'total' => 0]);
    exit;
}

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['students' => [], 'total' => 0]);
    exit;
}

// Fetch students assigned to this teacher from admin_accounts,
// joined with students table to get grade_level (section)
$stmt = $conn->prepare("
    SELECT
        a.id,
        a.admin_email,
        a.first_name,
        a.last_name,
        a.condition_info,
        CASE WHEN a.last_login IS NOT NULL THEN 'active' ELSE 'inactive' END AS status,
        COALESCE(s.grade_level, '') AS section,
        COALESCE(a.profile_photo, '') AS profile_photo
    FROM admin_accounts a
    LEFT JOIN students s ON s.admin_account_id = a.id
    WHERE a.role = 'student' AND a.assigned_teacher_id = ?
    ORDER BY a.first_name, a.last_name
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $fullName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    if (!$fullName) $fullName = $row['admin_email'];

    $nameParts = array_values(array_filter(explode(' ', $fullName)));
    $initials = '';
    foreach (array_slice($nameParts, 0, 2) as $part) {
        $initials .= strtoupper(mb_substr($part, 0, 1));
    }
    if (!$initials) $initials = strtoupper(mb_substr($row['admin_email'], 0, 1));

    $students[] = [
        'id'        => (int) $row['id'],
        'full_name' => $fullName,
        'email'     => $row['admin_email'],
        'section'   => $row['section'],
        'condition' => $row['condition_info'] ?? '',
        'status'       => $row['status'],
        'initials'     => $initials,
        'profile_photo'=> $row['profile_photo'],
    ];
}

$stmt->close();
$conn->close();

echo json_encode(['students' => $students, 'total' => count($students)]);
