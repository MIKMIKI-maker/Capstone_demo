<?php
/**
 * "Start New School Year" wizard (Admin Settings → School Year).
 * GET  → suggested name/dates and the Active S.Y.'s classes + students, so
 *        the wizard can offer to copy them.
 * POST (JSON body) → creates the S.Y., its classes, and each student's
 *        enrollment, then makes it the Active S.Y.:
 *   { name, start_date, end_date,
 *     classes:  [{ key, section_name, teacher_admin_id }],
 *     students: [{ student_admin_id, action: "continue"|"move"|"exit",
 *                  class_key (continue/move), exit_reason (exit) }] }
 * "continue"/"move" put the student in that class and make its teacher the
 * student's teacher; "exit" (Graduated/Transferred/Dropped) records the
 * reason on last year's enrollment and leaves the student unassigned.
 * Nothing is deleted, so last year stays viewable read-only.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/school_year.php';
requireAdminSession();

header('Content-Type: application/json');

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$activeId = getActiveSchoolYearId($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reconcileActiveEnrollments($conn);
    $latest = $conn->query("SELECT name FROM school_years ORDER BY start_date DESC LIMIT 1")->fetch_assoc();
    $nextStart = (int)explode('-', $latest['name'])[1];
    $current = $conn->query("SELECT name FROM school_years WHERE id = $activeId")->fetch_assoc();
    echo json_encode(['success' => true,
        'current_name' => $current['name'],
        'suggested' => [
            'name' => $nextStart . '-' . ($nextStart + 1),
            'start_date' => $nextStart . '-06-01',
            'end_date' => ($nextStart + 1) . '-05-31',
        ],
    ] + loadClassesForSchoolYear($conn, $activeId));
    exit;
}

csrf_require_valid_token();
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$name  = trim((string)($input['name'] ?? ''));
$start = trim((string)($input['start_date'] ?? ''));
$end   = trim((string)($input['end_date'] ?? ''));
if (!preg_match('/^(\d{4})-(\d{4})$/', $name, $m) || (int)$m[2] !== (int)$m[1] + 1) {
    echo json_encode(['success' => false, 'message' => 'School Year must look like 2027-2028.']);
    exit;
}
$startDate = DateTime::createFromFormat('!Y-m-d', $start);
$endDate   = DateTime::createFromFormat('!Y-m-d', $end);
if (!$startDate || !$endDate || $endDate <= $startDate) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid start and end date (end must be after start).']);
    exit;
}

// Validate classes: real, non-deleted teachers and a Section each.
$teacherIds = [];
$res = $conn->query("SELECT id FROM admin_accounts WHERE role = 'teacher' AND is_deleted = 0");
while ($row = $res->fetch_assoc()) $teacherIds[(int)$row['id']] = true;
$classes = [];
foreach ((array)($input['classes'] ?? []) as $c) {
    $key = (string)($c['key'] ?? '');
    $section = trim((string)($c['section_name'] ?? ''));
    $teacher = (int)($c['teacher_admin_id'] ?? 0);
    if ($key === '' || $section === '' || mb_strlen($section) > 100 || !isset($teacherIds[$teacher])) {
        echo json_encode(['success' => false, 'message' => 'Every class needs a Section and a Teacher.']);
        exit;
    }
    $classes[$key] = ['section' => $section, 'teacher' => $teacher];
}
// Each Section gets one class per S.Y.
$sections = array_column($classes, 'section');
$dups = array_unique(array_diff_assoc($sections, array_unique($sections)));
if ($dups) {
    echo json_encode(['success' => false, 'message' => implode(', ', $dups) . ' is used by more than one class. Each Section can only have one class per School Year.']);
    exit;
}

$exitReasons = ['Graduated', 'Transferred', 'Dropped'];
$students = [];
foreach ((array)($input['students'] ?? []) as $s) {
    $sid = (int)($s['student_admin_id'] ?? 0);
    $action = (string)($s['action'] ?? '');
    if ($sid <= 0) continue;
    if ($action === 'exit') {
        $reason = in_array($s['exit_reason'] ?? '', $exitReasons, true) ? $s['exit_reason'] : 'Transferred';
        $students[] = ['id' => $sid, 'action' => 'exit', 'reason' => $reason];
    } elseif (in_array($action, ['continue', 'move'], true) && isset($classes[(string)($s['class_key'] ?? '')])) {
        $students[] = ['id' => $sid, 'action' => 'enroll', 'class_key' => (string)$s['class_key']];
    } else {
        echo json_encode(['success' => false, 'message' => 'Every continuing or moving student needs a class.']);
        exit;
    }
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("INSERT INTO school_years (name, start_date, end_date) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $start, $end);
    if (!$stmt->execute()) {
        throw new RuntimeException($conn->errno === 1062 ? "School Year $name already exists." : 'Could not create School Year.');
    }
    $newId = (int)$stmt->insert_id;
    $stmt->close();

    $classIds = [];
    $stmt = $conn->prepare("INSERT INTO classes (school_year_id, section_name, teacher_admin_id) VALUES (?, ?, ?)
                            ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)");
    foreach ($classes as $key => $c) {
        $stmt->bind_param("isi", $newId, $c['section'], $c['teacher']);
        $stmt->execute();
        $classIds[$key] = (int)$conn->insert_id;
    }
    $stmt->close();

    $enroll = $conn->prepare("INSERT IGNORE INTO class_enrollments (school_year_id, class_id, student_admin_id) VALUES (?, ?, ?)");
    $markExit = $conn->prepare("UPDATE class_enrollments SET exit_reason = ? WHERE school_year_id = ? AND student_admin_id = ?");
    $moved = 0; $exited = 0;
    foreach ($students as $s) {
        if ($s['action'] === 'enroll') {
            $classId = $classIds[$s['class_key']];
            $enroll->bind_param("iii", $newId, $classId, $s['id']);
            $enroll->execute();
            moveStudentToTeacher($conn, $s['id'], $classes[$s['class_key']]['teacher']);
            $moved++;
        } else {
            $markExit->bind_param("sii", $s['reason'], $activeId, $s['id']);
            $markExit->execute();
            moveStudentToTeacher($conn, $s['id'], 0);
            $exited++;
        }
    }
    $enroll->close();
    $markExit->close();

    $conn->query("UPDATE school_years SET is_active = (id = $newId)");
    seedGradingLocks($conn, $newId);
    // Teachers take the Sections chosen for them in this new S.Y.
    applySchoolYearToAccounts($conn, $newId);

    $logStmt = $conn->prepare("INSERT INTO admin_activities (activity_type, user_type, user_name, user_email, action_detail) VALUES (?,?,?,?,?)");
    if ($logStmt) {
        $logType = 'School Year'; $logUType = 'admin';
        $logName  = $_SESSION['admin_name']  ?? '';
        $logEmail = $_SESSION['admin_email'] ?? '';
        $logDetail = "Started School Year $name: " . count($classIds) . " class(es), $moved student(s) enrolled, $exited exited";
        $logStmt->bind_param("sssss", $logType, $logUType, $logName, $logEmail, $logDetail);
        $logStmt->execute();
        $logStmt->close();
    }
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e instanceof RuntimeException ? $e->getMessage() : 'Could not start the new School Year.']);
    exit;
}

unset($_SESSION['sy_view_id']);
echo json_encode(['success' => true, 'message' => "School Year $name is now Active: " . count($classIds) . " class(es), $moved student(s) enrolled, $exited exited."]);
