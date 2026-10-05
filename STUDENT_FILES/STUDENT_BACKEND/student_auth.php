<?php
// Shared session-based authentication for STUDENT_BACKEND endpoints.
//
// Every endpoint here must call requireStudentSession() to get the caller's
// OWN admin_accounts.id from the PHP session set at login — never trust a
// student_id / student_record_id passed in the request itself, since that's
// just client-side JS state (sessionStorage) that anyone can edit or spoof
// with a raw HTTP request to view or modify another student's data.

function requireStudentSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['admin_id']) || ($_SESSION['admin_role'] ?? '') !== 'student') {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'enrolled' => false, 'message' => 'Not authenticated']);
        exit;
    }
    return (int)$_SESSION['admin_id'];
}

// Resolves the logged-in student's admin_accounts.id to their students-table
// record (id, teacher_id, ...), mirroring the 3-tier lookup originally in
// login_screen_submit.php / student_check_enrollment.php. Returns null if no
// teacher has enrolled this student yet.
function resolveStudentRecord($conn, $admin_account_id) {
    $row = null;

    // 1. Exact admin_account_id match (fast path once linked)
    $stmt = $conn->prepare("SELECT id AS student_record_id, teacher_id, disability_type, grade_level, student_name FROM students WHERE admin_account_id = ? AND status = 'active' LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $admin_account_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    $full_name = isset($_SESSION['admin_name']) ? trim($_SESSION['admin_name']) : '';

    // 2./3. Name matching is only for old records no account is linked to yet,
    // and only when the name points to exactly ONE such record — otherwise two
    // learners with the same name could open each other's activities.
    // 2. Case-insensitive exact name match
    if (!$row && $full_name !== '') {
        $stmt2 = $conn->prepare("SELECT id AS student_record_id, teacher_id, disability_type, grade_level, student_name FROM students WHERE LOWER(TRIM(student_name)) = LOWER(TRIM(?)) AND status = 'active' AND COALESCE(admin_account_id, 0) = 0 LIMIT 2");
        if ($stmt2) {
            $stmt2->bind_param("s", $full_name);
            $stmt2->execute();
            $matches = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt2->close();
            if (count($matches) === 1) $row = $matches[0];
        }
    }

    // 3. Partial name match — teacher may have saved only first+last name
    if (!$row && $full_name !== '') {
        $parts = preg_split('/\s+/', $full_name);
        if (count($parts) >= 2) {
            $first = strtolower($parts[0]);
            $last  = strtolower(end($parts));
            $like  = '%' . $conn->real_escape_string($first) . '%';
            $like2 = '%' . $conn->real_escape_string($last)  . '%';
            $stmt3 = $conn->prepare("SELECT id AS student_record_id, teacher_id, disability_type, grade_level, student_name FROM students WHERE LOWER(student_name) LIKE ? AND LOWER(student_name) LIKE ? AND status = 'active' AND COALESCE(admin_account_id, 0) = 0 LIMIT 2");
            if ($stmt3) {
                $stmt3->bind_param("ss", $like, $like2);
                $stmt3->execute();
                $matches = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt3->close();
                if (count($matches) === 1) $row = $matches[0];
            }
        }
    }

    // Auto-link admin_account_id once found so future logins use the fast path
    if ($row && !empty($row['student_record_id'])) {
        $upd = $conn->prepare("UPDATE students SET admin_account_id = ? WHERE id = ? AND (admin_account_id IS NULL OR admin_account_id = 0)");
        if ($upd) { $upd->bind_param("ii", $admin_account_id, $row['student_record_id']); $upd->execute(); $upd->close(); }
    }

    return $row ?: null;
}
