<?php
error_reporting(0);
ini_set('display_errors', 0);
mysqli_report(MYSQLI_REPORT_OFF);

function getTeacherDatabaseConnection() {
    $servername = "127.0.0.1";
    $db_username = "root";
    $db_password = "";
    $database = "spedalm_db";

    $conn = new mysqli($servername, $db_username, $db_password, '', 3306);
    if ($conn->connect_error) {
        return null;
    }

    $conn->query("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    if (!$conn->select_db($database)) {
        $conn->close();
        return null;
    }

    // Create teacher_accounts table
    $createTeacherTableSql = "CREATE TABLE IF NOT EXISTS teacher_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_email VARCHAR(255) NOT NULL UNIQUE,
        teacher_password VARCHAR(255) NOT NULL,
        first_name VARCHAR(100),
        last_name VARCHAR(100),
        school_name VARCHAR(255),
        phone_number VARCHAR(20),
        specialization VARCHAR(100),
        bio TEXT, /* CHANGED: added bio column to store teacher's short biography */
        class_section VARCHAR(50),
        status VARCHAR(20) DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($createTeacherTableSql);
    // Migration: old schema lacked bio/class_section columns
    $ta_col = $conn->query("SHOW COLUMNS FROM teacher_accounts LIKE 'bio'");
    if ($ta_col && $ta_col->num_rows == 0) {
        $conn->query("ALTER TABLE teacher_accounts ADD COLUMN bio TEXT NULL");
        $conn->query("ALTER TABLE teacher_accounts ADD COLUMN class_section VARCHAR(50) NULL");
    }

    // Create students table
    $createStudentsTableSql = "CREATE TABLE IF NOT EXISTS students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        admin_account_id INT NULL DEFAULT NULL,
        student_name VARCHAR(255) NOT NULL,
        parent_name VARCHAR(255),
        parent_email VARCHAR(255),
        parent_phone VARCHAR(20),
        disability_type VARCHAR(100),
        grade_level VARCHAR(20),
        status VARCHAR(20) DEFAULT 'active',
        age INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (teacher_id) REFERENCES teacher_accounts(id) ON DELETE CASCADE,
        INDEX (teacher_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($createStudentsTableSql);
    // Add admin_account_id column if missing (migration — MySQL 8.0 safe)
    $col_check = $conn->query("SHOW COLUMNS FROM students LIKE 'admin_account_id'");
    if ($col_check && $col_check->num_rows == 0) {
        $conn->query("ALTER TABLE students ADD COLUMN admin_account_id INT NULL DEFAULT NULL");
    }

    // Create activities table
    $createActivitiesTableSql = "CREATE TABLE IF NOT EXISTS teacher_activities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        activity_title VARCHAR(255) NOT NULL,
        activity_description TEXT,
        activity_type VARCHAR(50) DEFAULT NULL,
        subject VARCHAR(100),
        grade_level VARCHAR(20),
        difficulty VARCHAR(20),
        learning_materials TEXT,
        instructions TEXT,
        status VARCHAR(20) DEFAULT 'draft',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (teacher_id) REFERENCES teacher_accounts(id) ON DELETE CASCADE,
        INDEX (teacher_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($createActivitiesTableSql);
    $act_col = $conn->query("SHOW COLUMNS FROM teacher_activities LIKE 'activity_type'");
    if ($act_col && $act_col->num_rows == 0) {
        $conn->query("ALTER TABLE teacher_activities ADD COLUMN activity_type VARCHAR(50) DEFAULT NULL AFTER activity_description");
    }
    // content_json holds the full activity state (slides, items, answer keys,
    // design settings) so students can load it from ANY device/browser —
    // previously this only ever lived in the publishing teacher's own
    // localStorage/IndexedDB, which broke once teacher and student were on
    // different machines (i.e. any real deployment).
    $cj_col = $conn->query("SHOW COLUMNS FROM teacher_activities LIKE 'content_json'");
    if ($cj_col && $cj_col->num_rows == 0) {
        $conn->query("ALTER TABLE teacher_activities ADD COLUMN content_json LONGTEXT DEFAULT NULL");
    }
    $dl_col = $conn->query("SHOW COLUMNS FROM teacher_activities LIKE 'deadline'");
    if ($dl_col && $dl_col->num_rows == 0) {
        $conn->query("ALTER TABLE teacher_activities ADD COLUMN deadline DATE DEFAULT NULL");
    }
    $lock_col = $conn->query("SHOW COLUMNS FROM teacher_activities LIKE 'is_locked'");
    if ($lock_col && $lock_col->num_rows == 0) {
        $conn->query("ALTER TABLE teacher_activities ADD COLUMN is_locked TINYINT(1) DEFAULT 0");
    }

    // Create IEP materials table
    $createIEPTableSql = "CREATE TABLE IF NOT EXISTS iep_materials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        student_id INT NOT NULL,
        iep_goal TEXT,
        learning_objective TEXT,
        strategies TEXT,
        materials TEXT,
        assessment_method VARCHAR(200),
        status VARCHAR(20) DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (teacher_id) REFERENCES teacher_accounts(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        INDEX (teacher_id, student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($createIEPTableSql);
    // Migration: old schema used learner_id — add student_id/teacher_id if missing
    $iep_col = $conn->query("SHOW COLUMNS FROM iep_materials LIKE 'student_id'");
    if ($iep_col && $iep_col->num_rows == 0) {
        $conn->query("ALTER TABLE iep_materials ADD COLUMN student_id INT NULL DEFAULT NULL");
        $conn->query("ALTER TABLE iep_materials ADD COLUMN teacher_id INT NULL DEFAULT NULL");
    }

    // Create learner progress table
    $createProgressTableSql = "CREATE TABLE IF NOT EXISTS learner_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        student_id INT NOT NULL,
        activity_id INT,
        score INT,
        notes TEXT,
        assessment_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (teacher_id) REFERENCES teacher_accounts(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (activity_id) REFERENCES teacher_activities(id) ON DELETE SET NULL,
        INDEX (teacher_id, student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($createProgressTableSql);
    // If the old database.sql schema is present, learner_progress has a FK on activity_id
    // pointing to learner_activities (wrong table). Every INSERT the new app does is rejected.
    // Detect by checking for the old learner_id column and recreate the table cleanly.
    $lp_old = $conn->query("SHOW COLUMNS FROM learner_progress LIKE 'learner_id'");
    if ($lp_old && $lp_old->num_rows > 0) {
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        $conn->query("DROP TABLE IF EXISTS learner_progress");
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        $conn->query($createProgressTableSql);
    }

    // Create teacher reports table
    $createReportsTableSql = "CREATE TABLE IF NOT EXISTS teacher_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        student_id INT NOT NULL,
        report_title VARCHAR(255),
        report_content TEXT,
        report_date DATE,
        report_type VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (teacher_id) REFERENCES teacher_accounts(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        INDEX (teacher_id, student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($createReportsTableSql);

    // Create notifications table
    $conn->query("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT,
        notification_type VARCHAR(50) DEFAULT 'info',
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (teacher_id) REFERENCES teacher_accounts(id) ON DELETE CASCADE,
        INDEX (teacher_id, is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Activity assignments — maps which students are assigned to which activities
    $conn->query("CREATE TABLE IF NOT EXISTS activity_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        activity_id INT NOT NULL,
        student_id  INT NOT NULL,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (activity_id) REFERENCES teacher_activities(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id)  REFERENCES students(id)           ON DELETE CASCADE,
        UNIQUE KEY unique_assignment (activity_id, student_id),
        INDEX idx_student (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Activity submissions — stores student item-by-item answers + teacher assessment
    $conn->query("CREATE TABLE IF NOT EXISTS activity_submissions (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id       INT NOT NULL,
        student_id       INT NOT NULL,
        activity_id      INT NOT NULL,
        pub_id           VARCHAR(100) DEFAULT NULL,
        score            INT DEFAULT 0,
        total_items      INT DEFAULT 0,
        retake_count     INT DEFAULT 0,
        scaffold_used    TINYINT(1) DEFAULT 0,
        struggled_items_json LONGTEXT DEFAULT NULL,
        answers_json     LONGTEXT DEFAULT NULL,
        assistance_level VARCHAR(100) DEFAULT NULL,
        teacher_note     TEXT DEFAULT NULL,
        finalized_score  INT DEFAULT NULL,
        is_finalized     TINYINT(1) DEFAULT 0,
        submitted_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        finalized_at     TIMESTAMP NULL DEFAULT NULL,
        FOREIGN KEY (teacher_id)  REFERENCES teacher_accounts(id)   ON DELETE CASCADE,
        FOREIGN KEY (student_id)  REFERENCES students(id)           ON DELETE CASCADE,
        FOREIGN KEY (activity_id) REFERENCES teacher_activities(id) ON DELETE CASCADE,
        UNIQUE KEY unique_submission (student_id, activity_id),
        INDEX idx_teacher_activity (teacher_id, activity_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Migration: add retake_count if missing
    $rc_col = $conn->query("SHOW COLUMNS FROM activity_submissions LIKE 'retake_count'");
    if ($rc_col && $rc_col->num_rows == 0) {
        $conn->query("ALTER TABLE activity_submissions ADD COLUMN retake_count INT DEFAULT 0 AFTER total_items");
    }
    // Migration: add scaffold_used if missing
    $sf_col = $conn->query("SHOW COLUMNS FROM activity_submissions LIKE 'scaffold_used'");
    if ($sf_col && $sf_col->num_rows == 0) {
        $conn->query("ALTER TABLE activity_submissions ADD COLUMN scaffold_used TINYINT(1) DEFAULT 0 AFTER retake_count");
    }
    // Migration: add struggled_items_json if missing
    $si_col = $conn->query("SHOW COLUMNS FROM activity_submissions LIKE 'struggled_items_json'");
    if ($si_col && $si_col->num_rows == 0) {
        $conn->query("ALTER TABLE activity_submissions ADD COLUMN struggled_items_json LONGTEXT DEFAULT NULL AFTER scaffold_used");
    }

    // Personal task/checklist notes for teacher dashboard
    $conn->query("CREATE TABLE IF NOT EXISTS teacher_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        task_text VARCHAR(500) NOT NULL,
        is_done TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (teacher_id) REFERENCES teacher_accounts(id) ON DELETE CASCADE,
        INDEX (teacher_id, is_done)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Create teacher_settings table
    $conn->query("CREATE TABLE IF NOT EXISTS teacher_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL UNIQUE,
        settings_data TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (teacher_id) REFERENCES teacher_accounts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Create student_notes table
    $conn->query("CREATE TABLE IF NOT EXISTS student_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        student_id INT NOT NULL,
        note TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (teacher_id) REFERENCES teacher_accounts(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        INDEX (teacher_id, student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // student_notifications — teacher-sent messages visible in student portal
    $conn->query("CREATE TABLE IF NOT EXISTS student_notifications (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id        INT NOT NULL,
        student_id        INT NOT NULL,
        title             VARCHAR(255) NOT NULL,
        message           TEXT,
        notification_type VARCHAR(50) DEFAULT 'message',
        is_read           TINYINT(1)  DEFAULT 0,
        created_at        TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (teacher_id) REFERENCES teacher_accounts(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES students(id)         ON DELETE CASCADE,
        INDEX idx_student_read (student_id, is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Read state for synthesized teacher notes and published-activity notifications
    $conn->query("CREATE TABLE IF NOT EXISTS student_notification_reads (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        notif_key  VARCHAR(100) NOT NULL,
        read_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_student_notification (student_id, notif_key),
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed default teacher only when table is empty
    $t_seed_check = $conn->query("SELECT COUNT(*) AS cnt FROM teacher_accounts");
    if ($t_seed_check && $t_seed_check->fetch_assoc()['cnt'] == 0) {
        $h_teacher_seed = password_hash('Teacher@123', PASSWORD_DEFAULT);
        $conn->query("INSERT IGNORE INTO teacher_accounts (teacher_email, teacher_password, first_name, last_name, school_name, status) VALUES ('teacher@spedalm.edu.ph', '$h_teacher_seed', 'Demo', 'Teacher', 'Mamatid Elementary School', 'active')");
    }

    // Seed default student record — linked to the default teacher above.
    // We look up the teacher_accounts.id dynamically so auto_increment values don't matter.
    $tRes = $conn->query("SELECT id FROM teacher_accounts WHERE teacher_email='teacher@spedalm.edu.ph' LIMIT 1");
    if ($tRes && $tRow = $tRes->fetch_assoc()) {
        $defaultTeacherId = (int)$tRow['id'];
        // admin_account_id=0 is safe as a placeholder when the admin_accounts row isn't accessible here.
        // The real admin_account_id (for student@spedalm.edu.ph) gets set on first login via the login script.
        $conn->query("INSERT IGNORE INTO students (teacher_id, admin_account_id, student_name, disability_type, grade_level, status)
            SELECT $defaultTeacherId, a.id, 'Demo Student', 'ADHD', 'Grade 1', 'active'
            FROM admin_accounts a
            WHERE a.admin_email = 'student@spedalm.edu.ph'
            AND NOT EXISTS (SELECT 1 FROM students s WHERE s.admin_account_id = a.id)
            LIMIT 1");
    }

    return $conn;
}
