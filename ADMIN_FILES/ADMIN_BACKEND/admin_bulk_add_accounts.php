<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/admin_push_notification.php';
require_once __DIR__ . '/../../MAILER/send_email.php';
requireAdminSession();
csrf_require_valid_token();

header('Content-Type: application/json');

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Every row this endpoint creates is a Student, enrolled under one Teacher
// chosen once for the whole batch (not a per-row column) — bulk import only
// ever needs to handle a class roster, since Teacher accounts are few
// enough to add one at a time from the regular Add Account form.
$assignedTeacherId = isset($_POST['assigned_teacher_id']) ? intval($_POST['assigned_teacher_id']) : 0;
if ($assignedTeacherId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please select a teacher for this import.']);
    exit;
}
$teacherCheck = $conn->prepare("SELECT id FROM admin_accounts WHERE id = ? AND role = 'teacher' AND is_deleted = 0");
$teacherCheck->bind_param("i", $assignedTeacherId);
$teacherCheck->execute();
if (!$teacherCheck->get_result()->fetch_assoc()) {
    echo json_encode(['success' => false, 'message' => 'Selected teacher account was not found.']);
    $teacherCheck->close();
    exit;
}
$teacherCheck->close();

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No CSV file uploaded']);
    exit;
}
if ($_FILES['csv_file']['size'] > 2 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File too large (max 2MB).']);
    exit;
}

$tmpPath = $_FILES['csv_file']['tmp_name'];
if (!is_uploaded_file($tmpPath)) {
    echo json_encode(['success' => false, 'message' => 'Invalid upload']);
    exit;
}

$handle = fopen($tmpPath, 'r');
if (!$handle) {
    echo json_encode(['success' => false, 'message' => 'Could not read file']);
    exit;
}

// A file saved from Excel often starts with a UTF-8 BOM, which would
// otherwise get glued onto the first header name (e.g. "\xEF\xBB\xBFfirst_name")
// and make it fail to match.
$bom = fread($handle, 3);
if ($bom !== "\xEF\xBB\xBF") {
    rewind($handle);
}

$header = fgetcsv($handle);
if (!$header) {
    echo json_encode(['success' => false, 'message' => 'Empty file']);
    fclose($handle);
    exit;
}
$header = array_map(function ($h) { return strtolower(trim($h)); }, $header);
$colIndex = array_flip($header);

foreach (['first_name', 'email'] as $required) {
    if (!isset($colIndex[$required])) {
        echo json_encode(['success' => false, 'message' => "Missing required column: $required"]);
        fclose($handle);
        exit;
    }
}

function bulk_col($row, $colIndex, $name) {
    return isset($colIndex[$name]) && isset($row[$colIndex[$name]]) ? trim($row[$colIndex[$name]]) : '';
}

// Cap how many data rows a single import can carry, so one upload can't
// tie up the request (and flood the mail queue) indefinitely.
$maxRows = 300;
$rows = [];
$rowNum = 1; // the header itself is row 1
while (($row = fgetcsv($handle)) !== false) {
    $rowNum++;
    if (count(array_filter($row, function ($v) { return trim((string)$v) !== ''; })) === 0) continue;
    if (count($rows) >= $maxRows) break;
    $rows[] = ['num' => $rowNum, 'data' => $row];
}
fclose($handle);

if (empty($rows)) {
    echo json_encode(['success' => false, 'message' => 'No data rows found in file']);
    exit;
}

$rawPassword = 'Student@123';
$password = password_hash($rawPassword, PASSWORD_DEFAULT);
$schoolName = 'Mamatid Elementary School';
$results = [];
$createdCount = 0;
$seenEmails = [];

foreach ($rows as $r) {
    $row = $r['data'];
    $num = $r['num'];

    $firstName  = bulk_col($row, $colIndex, 'first_name');
    $lastName   = bulk_col($row, $colIndex, 'last_name');
    $email      = bulk_col($row, $colIndex, 'email');
    $condition  = bulk_col($row, $colIndex, 'condition');
    $parentName = bulk_col($row, $colIndex, 'parent_name');

    if ($firstName === '' || $email === '') {
        $results[] = ['row' => $num, 'email' => $email, 'status' => 'skipped', 'reason' => 'Missing first name or email'];
        continue;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $results[] = ['row' => $num, 'email' => $email, 'status' => 'skipped', 'reason' => 'Invalid email address'];
        continue;
    }
    if (mb_strlen($firstName) > 100 || mb_strlen($lastName) > 100 || mb_strlen($email) > 255) {
        $results[] = ['row' => $num, 'email' => $email, 'status' => 'skipped', 'reason' => 'Name or email too long'];
        continue;
    }
    $emailLower = strtolower($email);
    if (isset($seenEmails[$emailLower])) {
        $results[] = ['row' => $num, 'email' => $email, 'status' => 'skipped', 'reason' => 'Duplicate email already in this file (row ' . $seenEmails[$emailLower] . ')'];
        continue;
    }
    $seenEmails[$emailLower] = $num;

    $status = 'inactive';
    $mustChangePassword = 1;
    $role = 'student';

    $stmt = $conn->prepare(
        "INSERT INTO admin_accounts (admin_email, admin_password, first_name, last_name, school_name, role, condition_info, status, assigned_teacher_id, parent_name, must_change_password)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        $results[] = ['row' => $num, 'email' => $email, 'status' => 'skipped', 'reason' => 'Database error'];
        continue;
    }
    $stmt->bind_param("ssssssssisi", $email, $password, $firstName, $lastName, $schoolName, $role, $condition, $status, $assignedTeacherId, $parentName, $mustChangePassword);

    if ($stmt->execute()) {
        $stmt->close();
        $results[] = ['row' => $num, 'email' => $email, 'status' => 'created'];
        $createdCount++;

        // Best-effort welcome email — a slow/failed send here shouldn't
        // undo the account that was already created.
        $fullName = trim($firstName . ' ' . $lastName);
        $logoUrl = LOGO_CID_SRC;
        $loginUrl = PUBLIC_SITE_URL . '/ADMIN_FILES/login_screen.html';
        $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $safePassword = htmlspecialchars($rawPassword, ENT_QUOTES, 'UTF-8');
        $welcomeHtml = "<div style=\"max-width:480px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:32px 28px;font-family:Arial,Helvetica,sans-serif;color:#1e293b;\">"
            . "<div style=\"text-align:center;margin-bottom:18px;\"><img src=\"{$logoUrl}\" alt=\"SPED ALM\" width=\"64\" height=\"64\" style=\"width:64px;height:64px;border-radius:50%;\"></div>"
            . "<h2 style=\"text-align:center;color:#1e3a8a;margin:0 0 24px;font-size:22px;\">Welcome to SPED ALM</h2>"
            . "<p style=\"margin:0 0 12px;\">Dear {$safeName},</p>"
            . "<p style=\"margin:0 0 16px;\">We are pleased to inform you that your Student Account has been successfully created in the SPED ALM System.</p>"
            . "<p style=\"margin:0 0 10px;\">Below are your account credentials:</p>"
            . "<div style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px 20px;margin:0 0 24px;\">"
            . "<p style=\"margin:0 0 8px;font-size:14px;\"><strong>Username:</strong> {$safeEmail}</p>"
            . "<p style=\"margin:0;font-size:14px;\"><strong>Password:</strong> {$safePassword}</p>"
            . "</div>"
            . "<div style=\"text-align:center;margin:0 0 24px;\"><a href=\"{$loginUrl}\" style=\"display:inline-block;background:#1e3a8a;color:#ffffff;text-decoration:none;padding:12px 32px;border-radius:10px;font-weight:700;font-size:14px;\">Log In to SPED ALM</a></div>"
            . "<p style=\"font-size:13px;color:#64748b;margin:0 0 20px;\">Please keep your account credentials confidential and secure. You may now log in to the SPED ALM System using the credentials provided above.</p>"
            . "<hr style=\"border:none;border-top:1px solid #e2e8f0;margin:0 0 16px;\">"
            . "<p style=\"margin:0 0 16px;\">Sincerely yours,<br>SPED ALM System</p>"
            . "<p style=\"font-size:11px;color:#94a3b8;text-align:center;margin:0;\">Mamatid Elementary School &middot; SPED Program &middot; Cabuyao, Laguna<br>If you did not expect this email, you can safely ignore it.</p>"
            . "</div>";
        send_email($email, $fullName, 'Your SPED ALM account has been created', $welcomeHtml);
    } else {
        $err = $stmt->error;
        $stmt->close();
        $reason = (strpos($err, 'Duplicate') !== false) ? 'An account with this email already exists' : 'Failed to create account';
        $results[] = ['row' => $num, 'email' => $email, 'status' => 'skipped', 'reason' => $reason];
    }
}

if ($createdCount > 0) {
    $logStmt = $conn->prepare("INSERT INTO admin_activities (activity_type, user_type, user_name, user_email, action_detail) VALUES (?,?,?,?,?)");
    if ($logStmt) {
        $logType = 'Bulk Add';
        $logUType = 'admin';
        $logName  = $_SESSION['admin_name']  ?? '';
        $logEmail = $_SESSION['admin_email'] ?? '';
        $logDetail = "Bulk-imported {$createdCount} student(s) via CSV";
        $logStmt->bind_param("sssss", $logType, $logUType, $logName, $logEmail, $logDetail);
        $logStmt->execute();
        $logStmt->close();
    }
    $title = "{$createdCount} Students Added";
    $msg   = "{$createdCount} student account(s) were added via bulk CSV import.";
    pushAdminNotification($conn, 'account', $title, $msg);
}

$conn->close();

echo json_encode([
    'success'    => true,
    'created'    => $createdCount,
    'total_rows' => count($rows),
    'results'    => $results,
]);
