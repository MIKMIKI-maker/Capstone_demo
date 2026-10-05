<?php
/**
 * School Year (S.Y.) support. One S.Y. is Active at a time; everything new
 * (activities, the Learning Activity Plan, grading period locks) is tagged
 * with it. Assignments and submissions belong to an activity, so they follow
 * its S.Y. without needing their own column.
 *
 * Admins and teachers can switch which S.Y. they are *viewing* (stored in the
 * session). A past S.Y. is read-only: write endpoints call
 * requireActiveSchoolYearView() to refuse changes while one is being viewed.
 * Students always see the Active S.Y. only.
 */

/** Default name/dates for the S.Y. that contains $date (DepEd year starts in June). */
function defaultSchoolYearFor(DateTime $date): array {
    $year = (int)$date->format('Y');
    $start = (int)$date->format('n') >= 6 ? $year : $year - 1;
    return [
        'name'       => $start . '-' . ($start + 1),
        'start_date' => $start . '-06-01',
        'end_date'   => ($start + 1) . '-05-31',
    ];
}

/**
 * Creates the school_years table and tags existing data with the Active S.Y.
 * Safe to run repeatedly; called from the teacher DB setup block.
 */
function ensureSchoolYearSchema(mysqli $conn): void {
    $activeId = getActiveSchoolYearId($conn);

    // Existing rows from before S.Y. support all belong to the first Active S.Y.
    foreach (['teacher_activities', 'teacher_activity_plan'] as $table) {
        $exists = $conn->query("SHOW TABLES LIKE '$table'");
        if (!$exists || $exists->num_rows === 0) continue;
        $col = $conn->query("SHOW COLUMNS FROM $table LIKE 'school_year_id'");
        if ($col && $col->num_rows === 0) {
            $conn->query("ALTER TABLE $table ADD COLUMN school_year_id INT NULL, ADD INDEX idx_school_year (school_year_id)");
        }
        $conn->query("UPDATE $table SET school_year_id = $activeId WHERE school_year_id IS NULL");
    }

    // Grading period locks are per S.Y.: a new S.Y. starts with only First open.
    $conn->query("CREATE TABLE IF NOT EXISTS grading_period_locks (
        school_year_id INT NOT NULL,
        grading_period VARCHAR(20) NOT NULL,
        is_unlocked    TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (school_year_id, grading_period)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $col = $conn->query("SHOW COLUMNS FROM grading_period_locks LIKE 'school_year_id'");
    if ($col && $col->num_rows === 0) {
        $conn->query("ALTER TABLE grading_period_locks ADD COLUMN school_year_id INT NOT NULL DEFAULT $activeId FIRST");
        $conn->query("ALTER TABLE grading_period_locks DROP PRIMARY KEY, ADD PRIMARY KEY (school_year_id, grading_period)");
        $conn->query("ALTER TABLE grading_period_locks ALTER COLUMN school_year_id DROP DEFAULT");
    }
    seedGradingLocks($conn, $activeId);

    // Classes (S.Y. + Section + Teacher) and who is enrolled in each one.
    // A teacher may have more than one class in a S.Y.; a student is in at
    // most one class per S.Y. Rows are never deleted when a S.Y. ends, so a
    // student's past classes (and their teacher) stay on record.
    $conn->query("CREATE TABLE IF NOT EXISTS classes (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        school_year_id   INT NOT NULL,
        section_name     VARCHAR(100) NOT NULL,
        teacher_admin_id INT NOT NULL,
        created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_class (school_year_id, section_name, teacher_admin_id),
        INDEX idx_teacher (teacher_admin_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $conn->query("CREATE TABLE IF NOT EXISTS class_enrollments (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        school_year_id   INT NOT NULL,
        class_id         INT NOT NULL,
        student_admin_id INT NOT NULL,
        exit_reason      VARCHAR(50) NULL,
        created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_student_year (school_year_id, student_admin_id),
        INDEX idx_class (class_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $hasAccounts = $conn->query("SHOW TABLES LIKE 'admin_accounts'");
    if ($hasAccounts && $hasAccounts->num_rows > 0) {
        reconcileActiveEnrollments($conn);
    }
}

/** Sections offered when creating a class (same list as the Add Account form). */
const SCHOOL_SECTIONS = ['Non-Graded Maya', 'Non-Graded Pipit', 'Non-Graded Aguila', 'Non-Graded Kilyawan', 'Non-Graded Tikling'];

/**
 * Keeps the Active S.Y.'s classes/enrollments in step with the accounts:
 * admin_accounts.assigned_teacher_id stays the source of truth for a
 * student's *current* teacher (the rest of the app reads it), and every
 * assigned student gets an enrollment in one of that teacher's classes.
 * Cheap enough to call after any account change.
 */
function reconcileActiveEnrollments(mysqli $conn): void {
    $sy = getActiveSchoolYearId($conn);

    // Deleted accounts leave this S.Y.: their enrollments go, and so does a
    // deleted teacher's class once nobody is left in it.
    $conn->query("DELETE ce FROM class_enrollments ce LEFT JOIN admin_accounts a ON a.id = ce.student_admin_id
        WHERE ce.school_year_id = $sy AND (a.id IS NULL OR a.is_deleted = 1)");
    $conn->query("DELETE c FROM classes c LEFT JOIN admin_accounts a ON a.id = c.teacher_admin_id
        WHERE c.school_year_id = $sy AND (a.id IS NULL OR a.is_deleted = 1)
          AND NOT EXISTS (SELECT 1 FROM class_enrollments ce WHERE ce.class_id = c.id)");

    // A teacher whose Section was changed in Manage Users: their (single)
    // class follows, as long as no other class already has that Section.
    $res = $conn->query("SELECT c.id, c.section_name, TRIM(a.condition_info) AS wanted,
            (SELECT COUNT(*) FROM classes c2 WHERE c2.school_year_id = $sy AND c2.teacher_admin_id = c.teacher_admin_id) AS n
        FROM classes c INNER JOIN admin_accounts a ON a.id = c.teacher_admin_id AND a.role = 'teacher' AND a.is_deleted = 0
        WHERE c.school_year_id = $sy AND TRIM(COALESCE(a.condition_info, '')) <> '' AND c.section_name <> TRIM(a.condition_info)");
    $rename = $conn->prepare("UPDATE classes SET section_name = ? WHERE id = ?");
    $isTaken = $conn->prepare("SELECT 1 FROM classes WHERE school_year_id = ? AND section_name = ?");
    while ($res && ($row = $res->fetch_assoc())) {
        if ((int)$row['n'] !== 1) continue;
        $isTaken->bind_param("is", $sy, $row['wanted']);
        $isTaken->execute();
        if ($isTaken->get_result()->fetch_assoc()) continue;
        $classId = (int)$row['id'];
        $rename->bind_param("si", $row['wanted'], $classId);
        $rename->execute();
    }
    $rename->close();
    $isTaken->close();

    // A teacher with a Section but no class yet this S.Y. gets one — unless
    // another class already has that Section (one class per Section per S.Y.).
    $res = $conn->query("SELECT a.id, TRIM(a.condition_info) AS section FROM admin_accounts a
        WHERE a.role = 'teacher' AND a.is_deleted = 0 AND TRIM(COALESCE(a.condition_info, '')) <> ''
          AND NOT EXISTS (SELECT 1 FROM classes c WHERE c.school_year_id = $sy AND c.teacher_admin_id = a.id)");
    $add = $conn->prepare("INSERT INTO classes (school_year_id, section_name, teacher_admin_id)
        SELECT ?, ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM classes WHERE school_year_id = ? AND section_name = ?)");
    while ($res && ($t = $res->fetch_assoc())) {
        $teacherId = (int)$t['id'];
        $add->bind_param("isiis", $sy, $t['section'], $teacherId, $sy, $t['section']);
        $add->execute();
    }
    $add->close();

    // A student whose teacher was deleted counts as unassigned.
    $res = $conn->query("SELECT a.id, COALESCE(t.id, 0) AS assigned_teacher_id, ce.id AS enrollment_id, c.teacher_admin_id AS class_teacher
        FROM admin_accounts a
        LEFT JOIN admin_accounts t ON t.id = a.assigned_teacher_id AND t.role = 'teacher' AND t.is_deleted = 0
        LEFT JOIN class_enrollments ce ON ce.student_admin_id = a.id AND ce.school_year_id = $sy
        LEFT JOIN classes c ON c.id = ce.class_id
        WHERE a.role = 'student' AND a.is_deleted = 0");
    if (!$res) return;
    while ($row = $res->fetch_assoc()) {
        $studentId = (int)$row['id'];
        $teacherId = (int)$row['assigned_teacher_id'];
        $enrollmentId = (int)$row['enrollment_id'];
        if ($teacherId <= 0) {
            // Unassigned this S.Y. — drop a stale enrollment, keep exits on record.
            if ($enrollmentId) $conn->query("DELETE FROM class_enrollments WHERE id = $enrollmentId AND exit_reason IS NULL");
            continue;
        }
        if ($enrollmentId && (int)$row['class_teacher'] === $teacherId) continue;
        $classId = firstClassOfTeacher($conn, $teacherId, $sy);
        if ($enrollmentId) {
            $conn->query("UPDATE class_enrollments SET class_id = $classId, exit_reason = NULL WHERE id = $enrollmentId");
        } else {
            $conn->query("INSERT IGNORE INTO class_enrollments (school_year_id, class_id, student_admin_id) VALUES ($sy, $classId, $studentId)");
        }
    }
}

/** The teacher's first class in a S.Y., created from their Section if they have none. */
function firstClassOfTeacher(mysqli $conn, int $teacherAdminId, int $sy): int {
    $res = $conn->query("SELECT id FROM classes WHERE school_year_id = $sy AND teacher_admin_id = $teacherAdminId ORDER BY id LIMIT 1");
    if ($res && ($row = $res->fetch_assoc())) return (int)$row['id'];

    $section = 'No Section';
    $res = $conn->query("SELECT TRIM(COALESCE(condition_info, '')) AS s FROM admin_accounts WHERE id = $teacherAdminId");
    if ($res && ($row = $res->fetch_assoc()) && $row['s'] !== '') {
        $taken = $conn->query("SELECT 1 FROM classes WHERE school_year_id = $sy AND section_name = '" . $conn->real_escape_string($row['s']) . "'");
        if (!$taken || $taken->num_rows === 0) $section = $row['s'];
    }
    $stmt = $conn->prepare("INSERT IGNORE INTO classes (school_year_id, section_name, teacher_admin_id) VALUES (?, ?, ?)");
    $stmt->bind_param("isi", $sy, $section, $teacherAdminId);
    $stmt->execute();
    $stmt->close();
    $res = $conn->query("SELECT id FROM classes WHERE school_year_id = $sy AND teacher_admin_id = $teacherAdminId ORDER BY id LIMIT 1");
    return (int)$res->fetch_assoc()['id'];
}

/**
 * Name of the teacher who already has this Section (by their account's
 * Section, or a class in the Active S.Y.), or null when it's free.
 * $exceptTeacherAdminId lets a teacher keep their own Section.
 */
function sectionTakenBy(mysqli $conn, string $section, int $exceptTeacherAdminId = 0): ?string {
    $section = trim($section);
    if ($section === '') return null;
    $sy = getActiveSchoolYearId($conn);
    $stmt = $conn->prepare("SELECT CONCAT(a.first_name, ' ', a.last_name) AS name FROM admin_accounts a
        WHERE a.role = 'teacher' AND a.is_deleted = 0 AND a.id <> ?
          AND (TRIM(a.condition_info) = ?
               OR a.id IN (SELECT c.teacher_admin_id FROM classes c WHERE c.school_year_id = ? AND c.section_name = ?))
        LIMIT 1");
    $stmt->bind_param("isis", $exceptTeacherAdminId, $section, $sy, $section);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? trim($row['name']) : null;
}

/** admin_accounts.id of a teacher, from their teacher_accounts.id. */
function teacherAdminIdFor(mysqli $conn, int $teacherId): int {
    $stmt = $conn->prepare("SELECT a.id FROM admin_accounts a INNER JOIN teacher_accounts t ON a.admin_email = t.teacher_email
                            WHERE t.id = ? AND a.role = 'teacher' LIMIT 1");
    $stmt->bind_param("i", $teacherId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['id'] : 0;
}

/** teacher_accounts.id of a teacher, from their admin_accounts.id. */
function teacherIdForAdmin(mysqli $conn, int $teacherAdminId): int {
    $stmt = $conn->prepare("SELECT t.id FROM teacher_accounts t INNER JOIN admin_accounts a ON a.admin_email = t.teacher_email
                            WHERE a.id = ? AND a.role = 'teacher' LIMIT 1");
    $stmt->bind_param("i", $teacherAdminId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['id'] : 0;
}

/**
 * SQL condition (on a `students` alias) for a teacher's learners in a S.Y.
 * Active S.Y.: their current learners. Any other S.Y.: whoever was enrolled
 * in their classes that year, even if the student has a new teacher now.
 */
function teacherRosterCondition(mysqli $conn, int $teacherId, int $sy, string $alias = 's'): string {
    $teacherAdminId = teacherAdminIdFor($conn, $teacherId);
    $enrolled = "$alias.admin_account_id IN (SELECT ce.student_admin_id FROM class_enrollments ce
        INNER JOIN classes c ON c.id = ce.class_id
        WHERE ce.school_year_id = $sy AND c.teacher_admin_id = $teacherAdminId AND ce.exit_reason IS NULL)";
    if ($sy === getActiveSchoolYearId($conn)) {
        // Learners added by the teacher without a linked account have no enrollment.
        // 'pending' = moved to this teacher in Manage Users but not enrolled
        // by them yet ("+ Add Student"), so not one of their learners yet.
        return "($alias.teacher_id = $teacherId AND $alias.status <> 'pending' AND (COALESCE($alias.admin_account_id, 0) = 0 OR $enrolled))";
    }
    $everEnrolled = str_replace(' AND ce.exit_reason IS NULL', '', $enrolled);
    return "($everEnrolled)";
}

/** Whether a teacher may see a student's records: current learner, or was in one of their classes. */
function teacherCanSeeStudent(mysqli $conn, int $teacherId, int $studentRecordId): bool {
    $teacherAdminId = teacherAdminIdFor($conn, $teacherId);
    $stmt = $conn->prepare("SELECT 1 FROM students s WHERE s.id = ? AND (s.teacher_id = ? OR s.admin_account_id IN (
        SELECT ce.student_admin_id FROM class_enrollments ce INNER JOIN classes c ON c.id = ce.class_id WHERE c.teacher_admin_id = ?))");
    $stmt->bind_param("iii", $studentRecordId, $teacherId, $teacherAdminId);
    $stmt->execute();
    $ok = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $ok;
}

/**
 * Makes the accounts match a S.Y.'s classes — used when that S.Y. becomes
 * Active (started by the wizard, or switched back to). Each teacher's
 * Section becomes their class's Section that year, and each enrolled
 * student goes back to that year's teacher. Without this, the accounts would
 * still hold the other S.Y.'s setup, and reconcileActiveEnrollments() would
 * rewrite this year's classes to match it.
 */
function applySchoolYearToAccounts(mysqli $conn, int $sy): void {
    $res = $conn->query("SELECT teacher_admin_id, section_name FROM classes WHERE school_year_id = $sy ORDER BY id");
    $sectionOf = [];
    while ($row = $res->fetch_assoc()) {
        $tid = (int)$row['teacher_admin_id'];
        if (!isset($sectionOf[$tid])) $sectionOf[$tid] = $row['section_name'];
    }
    $stmt = $conn->prepare("UPDATE admin_accounts SET condition_info = ? WHERE id = ? AND role = 'teacher'");
    foreach ($sectionOf as $tid => $section) {
        $stmt->bind_param("si", $section, $tid);
        $stmt->execute();
    }
    $stmt->close();

    $res = $conn->query("SELECT ce.student_admin_id, c.teacher_admin_id FROM class_enrollments ce
        INNER JOIN classes c ON c.id = ce.class_id WHERE ce.school_year_id = $sy AND ce.exit_reason IS NULL");
    while ($row = $res->fetch_assoc()) {
        moveStudentToTeacher($conn, (int)$row['student_admin_id'], (int)$row['teacher_admin_id']);
    }
}

/**
 * School Year moves (Start New School Year, Set as Active): makes a teacher
 * the student's current teacher AND enrolls them right away — the student
 * shows in that teacher's My Learners and their own portal with no "+ Add
 * Student" step. The existing record (with all past activities/scores) is
 * re-used; one is created if the student was never enrolled before.
 * (A teacher change made in Manage Users instead waits for the new teacher
 * to enroll the student — see syncStudentRecord in admin_manage_user_save.php.)
 */
function moveStudentToTeacher(mysqli $conn, int $studentAdminId, int $teacherAdminId): void {
    $stmt = $conn->prepare("UPDATE admin_accounts SET assigned_teacher_id = ? WHERE id = ? AND role = 'student'");
    $stmt->bind_param("ii", $teacherAdminId, $studentAdminId);
    $stmt->execute();
    $stmt->close();
    $teacherId = $teacherAdminId > 0 ? teacherIdForAdmin($conn, $teacherAdminId) : 0;
    if ($teacherId <= 0) return;

    $stmt = $conn->prepare("SELECT id FROM students WHERE admin_account_id = ? LIMIT 1");
    $stmt->bind_param("i", $studentAdminId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($existing) {
        $stmt = $conn->prepare("UPDATE students SET teacher_id = ?, status = 'active' WHERE id = ?");
        $stmt->bind_param("ii", $teacherId, $existing['id']);
        $stmt->execute();
        $stmt->close();
        return;
    }
    $stmt = $conn->prepare("INSERT INTO students (teacher_id, admin_account_id, student_name, parent_name, parent_email, parent_phone, disability_type, grade_level, age, status)
        SELECT ?, a.id, TRIM(CONCAT(a.first_name, ' ', a.last_name)), COALESCE(a.parent_name, ''), '', '', COALESCE(a.condition_info, ''),
               COALESCE((SELECT t.condition_info FROM admin_accounts t WHERE t.id = ?), ''), 0, 'active'
        FROM admin_accounts a WHERE a.id = ? AND a.role = 'student'");
    $stmt->bind_param("iii", $teacherId, $teacherAdminId, $studentAdminId);
    $stmt->execute();
    $stmt->close();
}

/** Seeds First (open) / Second / Third (locked) for a S.Y. that has no locks yet. */
function seedGradingLocks(mysqli $conn, int $schoolYearId): void {
    $stmt = $conn->prepare("INSERT IGNORE INTO grading_period_locks (school_year_id, grading_period, is_unlocked)
                            VALUES (?, 'First', 1), (?, 'Second', 0), (?, 'Third', 0)");
    if (!$stmt) return;
    $stmt->bind_param("iii", $schoolYearId, $schoolYearId, $schoolYearId);
    $stmt->execute();
    $stmt->close();
}

/** Id of the Active S.Y., creating one for the current date if none exists yet. */
function getActiveSchoolYearId(mysqli $conn): int {
    // Any page can be the first to need a S.Y., so make sure the table exists
    // (once per request).
    static $tableReady = false;
    if (!$tableReady) {
        $conn->query("CREATE TABLE IF NOT EXISTS school_years (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            name       VARCHAR(20) NOT NULL UNIQUE,
            start_date DATE NOT NULL,
            end_date   DATE NOT NULL,
            is_active  TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $tableReady = true;
    }

    $res = $conn->query("SELECT id FROM school_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    if ($res && ($row = $res->fetch_assoc())) return (int)$row['id'];

    $res = $conn->query("SELECT id FROM school_years ORDER BY start_date DESC LIMIT 1");
    if ($res && ($row = $res->fetch_assoc())) {
        $id = (int)$row['id'];
    } else {
        $sy = defaultSchoolYearFor(new DateTime());
        $stmt = $conn->prepare("INSERT INTO school_years (name, start_date, end_date) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $sy['name'], $sy['start_date'], $sy['end_date']);
        $stmt->execute();
        $id = (int)$stmt->insert_id;
        $stmt->close();
    }
    $conn->query("UPDATE school_years SET is_active = (id = $id)");
    return $id;
}

/** The S.Y. the logged-in Admin/Teacher has chosen to view (defaults to Active). */
function getViewedSchoolYearId(mysqli $conn): int {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    // Admin pages have no School Year picker — the Admin always works in the
    // Active S.Y. (past years are browsed from Settings → School Year → Classes).
    if (strtolower((string)($_SESSION['admin_role'] ?? '')) === 'admin') {
        unset($_SESSION['sy_view_id']);
        return getActiveSchoolYearId($conn);
    }
    $viewId = (int)($_SESSION['sy_view_id'] ?? 0);
    if ($viewId > 0) {
        $stmt = $conn->prepare("SELECT id FROM school_years WHERE id = ?");
        $stmt->bind_param("i", $viewId);
        $stmt->execute();
        $found = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($found) return $viewId;
    }
    return getActiveSchoolYearId($conn);
}

/** Stops a write request while a past (read-only) S.Y. is being viewed. */
function requireActiveSchoolYearView(?mysqli $conn = null): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    // No saved choice means the user follows the Active S.Y.
    if (empty($_SESSION['sy_view_id'])) return;
    if (!$conn) {
        require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
        $conn = getTeacherDatabaseConnection();
    }
    if (getViewedSchoolYearId($conn) === getActiveSchoolYearId($conn)) return;
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'You are viewing a School Year that is not Active, so it is read-only. Switch back to the Active School Year to make changes.']);
    exit;
}

/** Classes, their students, plus the teacher/section choices for the forms. */
function loadClassesForSchoolYear(mysqli $conn, int $sy): array {
    $classes = [];
    $res = $conn->query("SELECT c.id, c.section_name, c.teacher_admin_id, CONCAT(a.first_name, ' ', a.last_name) AS teacher_name
        FROM classes c LEFT JOIN admin_accounts a ON a.id = c.teacher_admin_id AND a.is_deleted = 0
        WHERE c.school_year_id = $sy ORDER BY c.section_name, teacher_name");
    while ($row = $res->fetch_assoc()) {
        $classes[(int)$row['id']] = [
            'id' => (int)$row['id'],
            'section_name' => $row['section_name'],
            'teacher_admin_id' => (int)$row['teacher_admin_id'],
            'teacher_name' => trim((string)$row['teacher_name']),
            'students' => [],
        ];
    }
    // Deleted accounts are left out of every S.Y.'s list.
    $res = $conn->query("SELECT ce.class_id, ce.student_admin_id, ce.exit_reason, CONCAT(a.first_name, ' ', a.last_name) AS name
        FROM class_enrollments ce INNER JOIN admin_accounts a ON a.id = ce.student_admin_id AND a.is_deleted = 0
        WHERE ce.school_year_id = $sy ORDER BY name");
    while ($row = $res->fetch_assoc()) {
        if (!isset($classes[(int)$row['class_id']])) continue;
        $classes[(int)$row['class_id']]['students'][] = [
            'student_admin_id' => (int)$row['student_admin_id'],
            'name' => trim((string)$row['name']),
            'exit_reason' => $row['exit_reason'],
        ];
    }
    // A deleted teacher's class isn't listed at all, in any S.Y.
    foreach ($classes as $id => $c) {
        if ($c['teacher_name'] === '') unset($classes[$id]);
    }

    $teachers = [];
    $res = $conn->query("SELECT id, CONCAT(first_name, ' ', last_name) AS name FROM admin_accounts
        WHERE role = 'teacher' AND is_deleted = 0 ORDER BY first_name, last_name");
    while ($row = $res->fetch_assoc()) $teachers[] = ['id' => (int)$row['id'], 'name' => trim($row['name'])];

    return ['classes' => array_values($classes), 'teachers' => $teachers, 'sections' => SCHOOL_SECTIONS];
}
