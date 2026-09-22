<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/db.php';
require_once __DIR__ . '/teacher_auth.php';
require_once __DIR__ . '/../../MAILER/send_email.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

getDatabaseConnection(); // ensure spedalm_db and all admin tables exist first
$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$teacher_id = requireTeacherId();

$student_name    = isset($_POST['student_name'])    ? trim($_POST['student_name'])    : '';
$parent_name     = isset($_POST['parent_name'])     ? trim($_POST['parent_name'])     : '';
$parent_email    = isset($_POST['parent_email'])    ? trim($_POST['parent_email'])    : '';
$parent_phone    = isset($_POST['parent_phone'])    ? trim($_POST['parent_phone'])    : '';
$disability_type = isset($_POST['disability_type']) ? trim($_POST['disability_type']) : '';
$grade_level       = isset($_POST['grade_level'])       ? trim($_POST['grade_level'])       : '';
$admin_account_id  = isset($_POST['admin_account_id'])  ? intval($_POST['admin_account_id']) : 0;
$age               = isset($_POST['age'])               ? intval($_POST['age'])             : 0;
$status            = 'active';

if (!$student_name) {
    echo json_encode(['success' => false, 'message' => 'Student name is required']);
    exit;
}

// Verify student is pre-assigned to this teacher before allowing enrollment
if ($admin_account_id > 0) {
    $admin_conn = getDatabaseConnection();
    if ($admin_conn) {
        $teacher_admin_id = 0;
        $ta_stmt = $admin_conn->prepare(
            "SELECT a.id FROM admin_accounts a
             INNER JOIN teacher_accounts t ON a.admin_email = t.teacher_email
             WHERE t.id = ? AND a.role = 'teacher' LIMIT 1"
        );
        if ($ta_stmt) {
            $ta_stmt->bind_param("i", $teacher_id);
            $ta_stmt->execute();
            $ta_res = $ta_stmt->get_result();
            if ($ta_row = $ta_res->fetch_assoc()) $teacher_admin_id = intval($ta_row['id']);
            $ta_stmt->close();
        }
        if ($teacher_admin_id > 0) {
            $asgn_stmt = $admin_conn->prepare(
                "SELECT assigned_teacher_id FROM admin_accounts WHERE id = ? AND role = 'student' LIMIT 1"
            );
            if ($asgn_stmt) {
                $asgn_stmt->bind_param("i", $admin_account_id);
                $asgn_stmt->execute();
                $asgn_row = $asgn_stmt->get_result()->fetch_assoc();
                $asgn_stmt->close();
                if (!$asgn_row || intval($asgn_row['assigned_teacher_id']) !== $teacher_admin_id) {
                    echo json_encode(['success' => false, 'message' => 'This student is not assigned to your class.']);
                    $admin_conn->close();
                    $conn->close();
                    exit;
                }
            }
        }
        $admin_conn->close();
    }
}

$stmt = $conn->prepare("INSERT INTO students (teacher_id, admin_account_id, student_name, parent_name, parent_email, parent_phone, disability_type, grade_level, age, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database prepare failed']);
    $conn->close();
    exit;
}

$stmt->bind_param("iissssssis", $teacher_id, $admin_account_id, $student_name, $parent_name, $parent_email, $parent_phone, $disability_type, $grade_level, $age, $status);

if ($stmt->execute()) {
    $new_student_id = $stmt->insert_id;

    $teacher_label = 'Your child\'s teacher';
    $tnq = $conn->prepare("SELECT first_name, last_name FROM teacher_accounts WHERE id = ?");
    if ($tnq) {
        $tnq->bind_param("i", $teacher_id);
        $tnq->execute();
        if ($tnrow = $tnq->get_result()->fetch_assoc()) {
            $teacher_label = trim($tnrow['first_name'] . ' ' . $tnrow['last_name']) ?: $teacher_label;
        }
        $tnq->close();
    }

    // Welcome notification in the student's own portal — every newly
    // enrolled student gets this automatically, regardless of whether a
    // parent email is on file.
    $welcomeTitle = "Welcome to SPED ALM, {$student_name}!";
    $welcomeMsg = "Your learning journey starts here! \xF0\x9F\x8E\x89\n\n"
        . "You have been successfully enrolled by {$teacher_label}, your assigned teacher.\n\n"
        . "Explore, learn, and enjoy your activities at your own pace. Remember, every little step you take is a step toward learning and growing. \xF0\x9F\x92\x99\n\n"
        . "We're happy to have you with us! \xF0\x9F\x8C\x88";
    $welcomeStmt = $conn->prepare("INSERT INTO student_notifications (teacher_id, student_id, title, message, notification_type) VALUES (?, ?, ?, ?, 'welcome')");
    if ($welcomeStmt) {
        $welcomeStmt->bind_param("iiss", $teacher_id, $new_student_id, $welcomeTitle, $welcomeMsg);
        $welcomeStmt->execute();
        $welcomeStmt->close();
    }

    if ($parent_email) {
        $safeStudent = htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8');
        $safeTeacher = htmlspecialchars($teacher_label, ENT_QUOTES, 'UTF-8');
        $html = "<p>Hello " . htmlspecialchars($parent_name ?: 'Parent/Guardian', ENT_QUOTES, 'UTF-8') . ",</p>"
            . "<p><b>{$safeStudent}</b> has been enrolled with {$safeTeacher} on SPED ALM.</p>"
            . "<p>You'll receive updates and notifications here at this email address whenever the teacher sends one, and you can track {$safeStudent}'s progress by asking the teacher for access to the student portal.</p>"
            . "<p style=\"color:#64748b;font-size:12px;\">This is an automated message from SPED ALM. Please do not reply directly to this email.</p>";
        send_email($parent_email, $parent_name ?: 'Parent/Guardian', "{$student_name} has been enrolled — SPED ALM", $html);
    }
    echo json_encode(['success' => true, 'message' => 'Student added successfully', 'id' => $new_student_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to add student: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
