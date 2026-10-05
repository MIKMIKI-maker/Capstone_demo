<?php
/**
 * Admin Settings → School Year.
 * POST action=add      (name, start_date, end_date): adds a S.Y. (not Active yet).
 * POST action=delete   (id): removes a S.Y. that is not Active and holds no activities or enrollments.
 * POST action=activate (id): makes that S.Y. the Active one. New activities and
 *      the Learning Activity Plan go to it from then on, and it gets its own
 *      grading period locks (only First open). Older S.Y.s become read-only.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/school_year.php';
requireAdminSession();
csrf_require_valid_token();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $name  = trim($_POST['name'] ?? '');
    $start = trim($_POST['start_date'] ?? '');
    $end   = trim($_POST['end_date'] ?? '');

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

    $stmt = $conn->prepare("INSERT INTO school_years (name, start_date, end_date) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $start, $end);
    if (!$stmt->execute()) {
        $dup = $conn->errno === 1062;
        echo json_encode(['success' => false, 'message' => $dup ? "School Year $name already exists." : 'Could not add School Year.']);
        exit;
    }
    $stmt->close();
    echo json_encode(['success' => true, 'message' => "School Year $name added."]);
    exit;
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $conn->prepare("SELECT name, is_active FROM school_years WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'School Year not found.']);
        exit;
    }
    if ((int)$row['is_active'] === 1) {
        echo json_encode(['success' => false, 'message' => 'The Active School Year cannot be deleted.']);
        exit;
    }
    // A S.Y. that holds activities or enrollments is someone's history — keep it.
    $used = $conn->query("SELECT
        (SELECT COUNT(*) FROM teacher_activities WHERE school_year_id = $id) +
        (SELECT COUNT(*) FROM class_enrollments WHERE school_year_id = $id) AS n")->fetch_assoc();
    if ((int)$used['n'] > 0) {
        echo json_encode(['success' => false, 'message' => "S.Y. {$row['name']} already has activities or enrolled students, so it is kept as history and cannot be deleted."]);
        exit;
    }
    $conn->query("DELETE FROM classes WHERE school_year_id = $id");
    $conn->query("DELETE FROM grading_period_locks WHERE school_year_id = $id");
    $conn->query("DELETE FROM teacher_activity_plan WHERE school_year_id = $id");
    $conn->query("DELETE FROM school_years WHERE id = $id");
    // Anyone viewing it falls back to the Active S.Y. (getViewedSchoolYearId checks it exists).
    echo json_encode(['success' => true, 'message' => "School Year {$row['name']} deleted."]);
    exit;
}

if ($action === 'activate') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $conn->prepare("SELECT name FROM school_years WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'School Year not found.']);
        exit;
    }

    $conn->query("UPDATE school_years SET is_active = (id = $id)");
    seedGradingLocks($conn, $id);
    // Going back to a S.Y. that already has classes: its setup wins, so each
    // teacher returns to that year's Section and each student to that year's
    // teacher, instead of the year's record being rewritten to match the other S.Y.
    applySchoolYearToAccounts($conn, $id);
    // Anyone without a class in this S.Y. yet keeps their current teacher.
    reconcileActiveEnrollments($conn);
    // Switch the Admin's own view to follow the newly Active S.Y.
    unset($_SESSION['sy_view_id']);

    $logStmt = $conn->prepare("INSERT INTO admin_activities (activity_type, user_type, user_name, user_email, action_detail) VALUES (?,?,?,?,?)");
    if ($logStmt) {
        $logType = 'School Year'; $logUType = 'admin';
        $logName  = $_SESSION['admin_name']  ?? '';
        $logEmail = $_SESSION['admin_email'] ?? '';
        $logDetail = "Set School Year {$row['name']} as Active";
        $logStmt->bind_param("sssss", $logType, $logUType, $logName, $logEmail, $logDetail);
        $logStmt->execute();
        $logStmt->close();
    }

    echo json_encode(['success' => true, 'message' => "School Year {$row['name']} is now Active."]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
