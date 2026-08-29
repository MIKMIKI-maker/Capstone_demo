<?php
error_reporting(0);
ini_set('display_errors', '0');
mysqli_report(MYSQLI_REPORT_OFF);

// Guards account-management endpoints (create/edit/delete/restore/reset password)
// so they can only be used by a logged-in admin, instead of trusting whatever
// user_id/ids the caller sends. Call this before touching request input.
function requireAdminSession() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $adminRole = strtolower(trim((string)($_SESSION['admin_role'] ?? '')));
    if (empty($_SESSION['admin_id']) || $adminRole !== 'admin') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in as an admin.']);
        exit;
    }
}

function getDatabaseConnection() {
    $conn = null;
    $last_error = '';
    $dbName = 'spedalm_db';

    // DB_HOST etc. are set as environment variables on the hosting platform
    // (e.g. Render) when pointing at a remote database like Clever Cloud MySQL.
    // Local dev (XAMPP) leaves these unset and falls back to the attempts below.
    $envHost = getenv('DB_HOST');
    if ($envHost !== false && $envHost !== '') {
        $dbName = getenv('DB_NAME') ?: $dbName;
        $conn = @new mysqli($envHost, getenv('DB_USER') ?: '', getenv('DB_PASS') ?: '', $dbName, (int)(getenv('DB_PORT') ?: 3306));
        if ($conn->connect_error) {
            $last_error = $conn->connect_error;
            $conn = null;
        }
    } else {
        $attempts = [
            ['host' => '127.0.0.1', 'user' => 'root', 'pass' => '', 'port' => 3306],
            ['host' => '127.0.0.1', 'user' => 'root', 'pass' => 'root', 'port' => 3306],
            ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'port' => 3306],
            ['host' => 'localhost', 'user' => 'root', 'pass' => 'root', 'port' => 3306],
        ];

        foreach ($attempts as $a) {
            $test_conn = @new mysqli($a['host'], $a['user'], $a['pass'], "", $a['port']);
            if (!$test_conn->connect_error) {
                $conn = $test_conn;
                break;
            } else {
                $last_error = $test_conn->connect_error;
            }
        }
    }

    if (!$conn) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode([
            'status' => 'error',
            'message' => 'Database connection failed: ' . ($last_error ?: 'Unable to authenticate MySQL user.')
        ]);
        exit;
    }

    // Without this, mysqli negotiates whatever charset the client library
    // defaults to (not necessarily utf8mb4), and every bind_param() string
    // parameter gets tagged with THAT charset/collation. On a MySQL 8 host
    // (utf8mb4_0900_ai_ci columns, e.g. Clever Cloud) that mismatch makes
    // prepared-statement INSERTs/UPDATEs fail outright with "Conversion from
    // collation ... impossible for parameter" — silently, since
    // mysqli_report(MYSQLI_REPORT_OFF) above suppresses the error.
    $conn->set_charset('utf8mb4');

    // Local XAMPP connects without a database selected, so create/select it here.
    // Remote connections (Clever Cloud etc.) already select their database via
    // the mysqli constructor above and don't have CREATE DATABASE privileges.
    if ($envHost === false || $envHost === '') {
        $conn->query("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$conn->select_db($dbName)) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode([
            'status' => 'error', 
            'message' => 'Select DB failed: ' . $conn->error
        ]);
        $conn->close();
        exit;
        }
    }

    // Remote (Clever Cloud etc.) connections are high-latency, so once the schema
    // has been bootstrapped once, skip re-running ~20 CREATE/ALTER/SHOW COLUMNS
    // queries on every single request. Local XAMPP is fast enough that this
    // check isn't worth the complexity, so it always re-verifies the schema.
    $needsSetup = true;
    if ($envHost !== false && $envHost !== '') {
        $tblCheck = $conn->query("SHOW TABLES LIKE 'admin_accounts'");
        $needsSetup = !($tblCheck && $tblCheck->num_rows > 0);
    }

    if ($needsSetup) {

    // admin_accounts table
    $conn->query("CREATE TABLE IF NOT EXISTS admin_accounts (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        admin_email   VARCHAR(255) NOT NULL UNIQUE,
        admin_password VARCHAR(255) NOT NULL,
        first_name    VARCHAR(100),
        last_name     VARCHAR(100),
        school_name   VARCHAR(255),
        phone_number  VARCHAR(20),
        role          VARCHAR(50)  DEFAULT 'admin',
        condition_info VARCHAR(255),
        status        VARCHAR(20)  DEFAULT 'active',
        last_login    TIMESTAMP    NULL DEFAULT NULL,
        created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Safe migrations for existing installs
    $conn->query("ALTER TABLE admin_accounts ADD COLUMN IF NOT EXISTS condition_info VARCHAR(255) AFTER role");
    $conn->query("ALTER TABLE admin_accounts ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL DEFAULT NULL AFTER status");
    // Tanggalin ang email domain check constraint
    $conn->query("ALTER TABLE admin_accounts DROP CHECK chk_admin_email_domain");
    $conn->query("ALTER TABLE admin_accounts DROP CONSTRAINT IF EXISTS chk_admin_email_domain");
    
    $last_seen_col = $conn->query("SHOW COLUMNS FROM admin_accounts LIKE 'last_seen'");
    if ($last_seen_col && $last_seen_col->num_rows == 0) {
        $conn->query("ALTER TABLE admin_accounts ADD COLUMN last_seen TIMESTAMP NULL DEFAULT NULL");
    }
    
    $assigned_teacher_col = $conn->query("SHOW COLUMNS FROM admin_accounts LIKE 'assigned_teacher_id'");
    if ($assigned_teacher_col && $assigned_teacher_col->num_rows == 0) {
        $conn->query("ALTER TABLE admin_accounts ADD COLUMN assigned_teacher_id INT DEFAULT NULL AFTER condition_info");
    }
    
    $parent_name_col = $conn->query("SHOW COLUMNS FROM admin_accounts LIKE 'parent_name'");
    if ($parent_name_col && $parent_name_col->num_rows == 0) {
        $conn->query("ALTER TABLE admin_accounts ADD COLUMN parent_name VARCHAR(255) DEFAULT NULL AFTER assigned_teacher_id");
    }
    
    $pp_col = $conn->query("SHOW COLUMNS FROM admin_accounts LIKE 'profile_photo'");
    if ($pp_col && $pp_col->num_rows == 0) {
        $conn->query("ALTER TABLE admin_accounts ADD COLUMN profile_photo LONGTEXT NULL DEFAULT NULL");
    }
    
    $sd_col = $conn->query("SHOW COLUMNS FROM admin_accounts LIKE 'is_deleted'");
    if ($sd_col && $sd_col->num_rows == 0) {
        $conn->query("ALTER TABLE admin_accounts ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0");
        $conn->query("ALTER TABLE admin_accounts ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
    }

    // admin_activities log table
    $conn->query("CREATE TABLE IF NOT EXISTS admin_activities (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        activity_type VARCHAR(100),
        user_type   VARCHAR(50),
        user_name   VARCHAR(255),
        user_email  VARCHAR(255),
        action_detail TEXT,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Admin notifications
    $conn->query("CREATE TABLE IF NOT EXISTS admin_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        notification_type VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT,
        related_id INT,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (is_read),
        INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Login rate-limiting table
    $conn->query("CREATE TABLE IF NOT EXISTS login_attempts (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        ip_address   VARCHAR(45) NOT NULL,
        email        VARCHAR(255),
        attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip_time (ip_address, attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed default accounts
    $seed_check = $conn->query("SELECT COUNT(*) AS cnt FROM admin_accounts");
    if ($seed_check && $seed_check->fetch_assoc()['cnt'] == 0) {
        $h_admin   = password_hash('Admin@123',   PASSWORD_DEFAULT);
        $h_teacher = password_hash('Teacher@123', PASSWORD_DEFAULT);
        $h_student = password_hash('Student@123', PASSWORD_DEFAULT);
        $conn->query("INSERT IGNORE INTO admin_accounts (admin_email, admin_password, first_name, last_name, school_name, role, status)
            VALUES ('admin@spedalm.edu.ph', '$h_admin', 'Admin', 'User', 'Mamatid Elementary School', 'admin', 'active')");
        $conn->query("INSERT IGNORE INTO admin_accounts (admin_email, admin_password, first_name, last_name, school_name, role, status)
            VALUES ('teacher@spedalm.edu.ph', '$h_teacher', 'Demo', 'Teacher', 'Mamatid Elementary School', 'teacher', 'active')");
        $conn->query("INSERT IGNORE INTO admin_accounts (admin_email, admin_password, first_name, last_name, school_name, role, status, condition_info)
            VALUES ('student@spedalm.edu.ph', '$h_student', 'Demo', 'Student', 'Mamatid Elementary School', 'student', 'active', 'ADHD')");
    }

    }

    return $conn;
}