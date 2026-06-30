<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/db.php';

header('Content-Type: application/json');

session_start();
$teacher_id = isset($_GET['teacher_id']) ? intval($_GET['teacher_id'])
            : (isset($_SESSION['admin_id']) ? intval($_SESSION['admin_id']) : 1);

$teacher_conn = getTeacherDatabaseConnection();
$admin_conn   = getDatabaseConnection();

if (!$teacher_conn || !$admin_conn) { echo json_encode([]); exit; }

// Find this teacher's admin_accounts.id by matching email across both tables
$teacher_admin_id = 0;
$id_stmt = $admin_conn->prepare(
    "SELECT a.id FROM admin_accounts a
     INNER JOIN teacher_accounts t ON a.admin_email = t.teacher_email
     WHERE t.id = ? AND a.role = 'teacher'
     LIMIT 1"
);
if ($id_stmt) {
    $id_stmt->bind_param("i", $teacher_id);
    $id_stmt->execute();
    $id_res = $id_stmt->get_result();
    if ($id_row = $id_res->fetch_assoc()) {
        $teacher_admin_id = intval($id_row['id']);
    }
    $id_stmt->close();
}

if ($teacher_admin_id <= 0) {
    echo json_encode([]);
    $teacher_conn->close();
    $admin_conn->close();
    exit;
}

// Names already enrolled by this teacher (for the enrolled flag)
$enrolled = [];
$stmt = $teacher_conn->prepare("SELECT LOWER(TRIM(student_name)) as sn FROM students WHERE teacher_id=?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $enrolled[] = $r['sn'];
$stmt->close();

// admin_account_ids already enrolled by this teacher (handles students enrolled before assignment system)
$enrolled_ids = [];
$eid_stmt = $teacher_conn->prepare("SELECT admin_account_id FROM students WHERE teacher_id=? AND admin_account_id IS NOT NULL AND admin_account_id > 0");
$eid_stmt->bind_param("i", $teacher_id);
$eid_stmt->execute();
$eid_res = $eid_stmt->get_result();
while ($eid_row = $eid_res->fetch_assoc()) $enrolled_ids[] = intval($eid_row['admin_account_id']);
$eid_stmt->close();

// Show students assigned to this teacher OR already enrolled by this teacher (migration safety)
$result_stmt = $admin_conn->prepare(
    "SELECT id, admin_email, first_name, last_name, condition_info
     FROM admin_accounts
     WHERE role='student' AND (
         assigned_teacher_id = ?
         OR id IN (SELECT admin_account_id FROM students WHERE teacher_id = ? AND admin_account_id > 0)
     )
     ORDER BY first_name, last_name"
);
$result_stmt->bind_param("ii", $teacher_admin_id, $teacher_id);
$result_stmt->execute();
$result = $result_stmt->get_result();

$students = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $fn = trim($row['first_name'] ?: '');
        $ln = trim($row['last_name'] ?: '');
        $full = trim("$fn $ln");
        if (!$full) $full = explode('@', $row['admin_email'])[0];

        $students[] = [
            'admin_id'   => $row['id'],
            'email'      => $row['admin_email'],
            'first_name' => $fn,
            'last_name'  => $ln,
            'full_name'  => $full,
            'condition'  => $row['condition_info'] ?: '',
            'enrolled'   => in_array(strtolower($full), $enrolled)
        ];
    }
}
$result_stmt->close();

echo json_encode($students);
$teacher_conn->close();
$admin_conn->close();
?>
