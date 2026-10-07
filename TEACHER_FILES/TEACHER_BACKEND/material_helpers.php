<?php
// Shared by every "Uploaded Materials" endpoint (teacher + student side).
//
// A teacher-uploaded material is either:
//   'file'     — something to read/watch (the original behaviour), or
//   'activity' — the student opens it and submits work back (photos,
//                videos, documents), stored one file per row in
//                material_submissions.

const MATERIAL_SUBMISSION_MAX_VIDEO_BYTES = 25 * 1024 * 1024; // matches PHP's 25M upload cap on Render (docker/uploads.ini)
const MATERIAL_SUBMISSION_MAX_FILE_BYTES  = 10 * 1024 * 1024; // Cloudinary free plan cap for images/documents
const MATERIAL_SUBMISSION_MAX_FILES       = 10;               // per material, per student

// Runs the CREATE/ALTERs below once per database, then just checks a
// schema_meta marker — every materials endpoint calls this, and on the
// remote (high-latency) database four schema queries per request add up.
function ensureMaterialSchema($conn) {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    $meta = $conn->query("SELECT version FROM schema_meta WHERE component = 'materials'");
    $metaRow = $meta ? $meta->fetch_assoc() : null;
    if ($metaRow && (int)$metaRow['version'] >= 1) return;

    $conn->query("CREATE TABLE IF NOT EXISTS teacher_uploaded_materials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        student_id INT NOT NULL,
        grading_period VARCHAR(20) NOT NULL DEFAULT 'First',
        title VARCHAR(255) NOT NULL,
        description TEXT,
        file_name VARCHAR(255) NOT NULL DEFAULT '',
        file_original_name VARCHAR(255) NOT NULL DEFAULT '',
        file_type VARCHAR(100),
        file_size INT,
        link_url VARCHAR(500) DEFAULT NULL,
        material_kind VARCHAR(10) NOT NULL DEFAULT 'file',
        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    // Tables that predate these columns.
    $c = $conn->query("SHOW COLUMNS FROM teacher_uploaded_materials LIKE 'link_url'");
    if ($c && $c->num_rows == 0) {
        $conn->query("ALTER TABLE teacher_uploaded_materials ADD COLUMN link_url VARCHAR(500) DEFAULT NULL");
    }
    $c = $conn->query("SHOW COLUMNS FROM teacher_uploaded_materials LIKE 'material_kind'");
    if ($c && $c->num_rows == 0) {
        $conn->query("ALTER TABLE teacher_uploaded_materials ADD COLUMN material_kind VARCHAR(10) NOT NULL DEFAULT 'file'");
    }

    $conn->query("CREATE TABLE IF NOT EXISTS material_submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        material_id INT NOT NULL,
        teacher_id INT NOT NULL,
        student_id INT NOT NULL,
        file_name VARCHAR(500) NOT NULL,
        file_original_name VARCHAR(255) NOT NULL DEFAULT '',
        file_type VARCHAR(100) DEFAULT NULL,
        file_size INT DEFAULT 0,
        caption VARCHAR(500) DEFAULT NULL,
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_material_student (material_id, student_id),
        INDEX idx_teacher (teacher_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Only mark it done once everything above really exists.
    $kind = $conn->query("SHOW COLUMNS FROM teacher_uploaded_materials LIKE 'material_kind'");
    $subs = $conn->query("SHOW TABLES LIKE 'material_submissions'");
    if ($kind && $kind->num_rows > 0 && $subs && $subs->num_rows > 0) {
        $conn->query("CREATE TABLE IF NOT EXISTS schema_meta (component VARCHAR(50) PRIMARY KEY, version INT NOT NULL)");
        $conn->query("INSERT INTO schema_meta (component, version) VALUES ('materials', 1) ON DUPLICATE KEY UPDATE version = GREATEST(version, 1)");
    }
}

// What a student may submit, keyed by the MIME type PHP detects from the
// file's actual content (never the client-supplied name/extension).
function materialSubmissionMimeExt() {
    return [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp',
        'image/heic' => 'heic', 'image/heif' => 'heif',
        'video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm', 'video/3gpp' => '3gp',
        'video/x-m4v' => 'm4v', 'video/mpeg' => 'mpeg', 'video/x-matroska' => 'mkv', 'video/x-msvideo' => 'avi',
        'audio/mpeg' => 'mp3', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'audio/ogg' => 'ogg',
        'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'audio/aac' => 'aac', 'audio/webm' => 'weba', 'audio/3gpp' => '3gp',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
    ];
}

function materialSubmissionLocalDir() {
    return __DIR__ . '/../uploads/submissions';
}

// Stores an uploaded submission file and returns what goes in
// material_submissions.file_name: a Cloudinary URL, or (plain XAMPP with no
// Cloudinary account) a random local filename. Returns null on failure.
function saveMaterialSubmissionFile($tmpName, $mime, $origName) {
    $map = materialSubmissionMimeExt();
    if (!isset($map[$mime])) return null;

    require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/cloudinary_upload.php';
    if (defined('CLOUDINARY_API_KEY') && CLOUDINARY_API_KEY !== '') {
        return cloudinaryUpload(new CURLFile($tmpName, $mime, $origName), 'auto', 'submissions');
    }

    // Same safeguards as the materials folder: a random name with an
    // extension derived from the verified MIME type, and an .htaccess that
    // stops anything in the folder from ever running as a script.
    $dir = materialSubmissionLocalDir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) return null;
    $htaccess = $dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "<FilesMatch \"\\.(php|php[3-8]?|phtml|pht)\$\">\n    Require all denied\n</FilesMatch>\nOptions -Indexes\nphp_flag engine off\n");
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $map[$mime];
    if (!move_uploaded_file($tmpName, $dir . '/' . $filename)) return null;
    return $filename;
}

function deleteMaterialSubmissionFile($stored) {
    $stored = (string)$stored;
    if ($stored === '') return;
    if (strpos($stored, 'res.cloudinary.com') !== false) {
        require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/cloudinary_upload.php';
        cloudinaryDeleteByUrl($stored);
        return;
    }
    // Only ever our own random names — never a path someone could aim elsewhere.
    if (preg_match('/^[a-f0-9]{32}\.[a-z0-9]{2,5}$/', $stored)) {
        $path = materialSubmissionLocalDir() . '/' . $stored;
        if (is_file($path)) @unlink($path);
    }
}

function listMaterialSubmissions($conn, $material_id, $student_id) {
    $stmt = $conn->prepare("SELECT id, file_name, file_original_name, file_type, file_size, caption, submitted_at
        FROM material_submissions WHERE material_id = ? AND student_id = ? ORDER BY submitted_at ASC, id ASC");
    if (!$stmt) return [];
    $stmt->bind_param("ii", $material_id, $student_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// A material as the logged-in student may see it: it must be theirs and
// from their current teacher (the same rule the materials list uses).
function loadStudentMaterial($conn, $material_id, $student_id, $teacher_id) {
    $stmt = $conn->prepare("SELECT id, teacher_id, student_id, grading_period, title, description, file_name, file_original_name,
            file_type, file_size, link_url, material_kind, uploaded_at
        FROM teacher_uploaded_materials WHERE id = ? AND student_id = ? AND teacher_id = ?");
    if (!$stmt) return null;
    $stmt->bind_param("iii", $material_id, $student_id, $teacher_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}
