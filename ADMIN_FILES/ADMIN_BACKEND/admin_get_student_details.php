<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
if (!$student_id) {
    echo json_encode(['error' => 'Invalid student ID']);
    exit;
}

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Fetch student base info
$stmt = $conn->prepare("
    SELECT
        a.id, a.admin_email, a.first_name, a.last_name,
        a.condition_info, a.parent_name, a.assigned_teacher_id,
        CASE WHEN a.last_login IS NOT NULL THEN 'active' ELSE 'inactive' END AS status,
        a.created_at,
        COALESCE(a.profile_photo, '') AS profile_photo
    FROM admin_accounts a
    WHERE a.id = ? AND a.role = 'student'
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$studentRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$studentRow) {
    echo json_encode(['error' => 'Student not found']);
    $conn->close();
    exit;
}

// Helper: build initials from full name string
function makeInitials($name) {
    $parts = array_values(array_filter(explode(' ', $name)));
    $init = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $init .= strtoupper(mb_substr($p, 0, 1));
    }
    return $init ?: '?';
}

// Fetch guardian + section from students table (linked via admin_account_id)
$section  = '';
$guardian = null;

$gStmt = $conn->prepare("
    SELECT parent_name, parent_email, parent_phone, grade_level
    FROM students
    WHERE admin_account_id = ?
    ORDER BY id DESC
    LIMIT 1
");
$gStmt->bind_param("i", $student_id);
$gStmt->execute();
$gRow = $gStmt->get_result()->fetch_assoc();
$gStmt->close();

if ($gRow) {
    $section      = $gRow['grade_level'] ?? '';
    $guardianName = $gRow['parent_name'] ?: ($studentRow['parent_name'] ?? '');
    if ($guardianName) {
        $guardian = [
            'name'         => $guardianName,
            'relationship' => 'Parent / Guardian',
            'phone'        => $gRow['parent_phone'] ?? '',
            'email'        => $gRow['parent_email'] ?? '',
            'initials'     => makeInitials($guardianName),
        ];
    }
} elseif (!empty($studentRow['parent_name'])) {
    $guardian = [
        'name'         => $studentRow['parent_name'],
        'relationship' => 'Parent / Guardian',
        'phone'        => '',
        'email'        => '',
        'initials'     => makeInitials($studentRow['parent_name']),
    ];
}

// Fetch assigned teacher
$teacher = null;
if (!empty($studentRow['assigned_teacher_id'])) {
    $tStmt = $conn->prepare("
        SELECT id, admin_email, first_name, last_name, condition_info, COALESCE(profile_photo,'') AS profile_photo
        FROM admin_accounts
        WHERE id = ? AND role = 'teacher'
    ");
    $tStmt->bind_param("i", $studentRow['assigned_teacher_id']);
    $tStmt->execute();
    $tRow = $tStmt->get_result()->fetch_assoc();
    $tStmt->close();

    if ($tRow) {
        $tName = trim(($tRow['first_name'] ?? '') . ' ' . ($tRow['last_name'] ?? ''));
        if (!$tName) $tName = $tRow['admin_email'];
        $teacher = [
            'id'            => (int) $tRow['id'],
            'name'          => $tName,
            'email'         => $tRow['admin_email'],
            'section'       => $tRow['condition_info'] ?? '',
            'initials'      => makeInitials($tName),
            'profile_photo' => $tRow['profile_photo'] ?? '',
        ];
    }
}

$fullName = trim(($studentRow['first_name'] ?? '') . ' ' . ($studentRow['last_name'] ?? ''));
if (!$fullName) $fullName = $studentRow['admin_email'];

echo json_encode([
    'student' => [
        'id'            => (int) $studentRow['id'],
        'full_name'     => $fullName,
        'email'         => $studentRow['admin_email'],
        'condition'     => $studentRow['condition_info'] ?? '',
        'section'       => $section,
        'status'        => $studentRow['status'],
        'created_at'    => $studentRow['created_at'],
        'profile_photo' => $studentRow['profile_photo'] ?? '',
    ],
    'teacher'  => $teacher,
    'guardian' => $guardian,
]);

$conn->close();
