<?php
// Pictures of an activity exactly as the student left it after each
// "Check answers" — one per slide per try — so the teacher can see every
// take side by side, not just its score.
//
// A snapshot is tied to the student and a random run id (one per time the
// student opens the activity). The submission's attempt_history_json
// carries the same run id, which is how the teacher's review finds them;
// that way a picture still uploading when the student taps Submit is not
// lost.
//
// Storage stays bounded: a picture is `kept` only while a submission uses
// its run. Runs never submitted (the student left, or replayed an activity
// already submitted) and runs replaced by a resubmission are deleted —
// file and row — by cleanupSnapshots() after SNAPSHOT_GRACE_HOURS.

const SNAPSHOT_MAX_BYTES        = 2 * 1024 * 1024;
const SNAPSHOT_MAX_PER_RUN      = 200;   // 20 slides × 10 tries is already generous
const SNAPSHOT_MAX_PER_HOUR     = 150;   // per student, across all runs
const SNAPSHOT_GRACE_HOURS      = 48;    // unsubmitted pictures live this long

function ensureSnapshotSchema($conn) {
    static $done = false;
    if ($done) return;
    $done = true;
    $conn->query("CREATE TABLE IF NOT EXISTS attempt_snapshots (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        run_id VARCHAR(32) NOT NULL,
        slide INT NOT NULL,
        attempt INT NOT NULL,
        image_url VARCHAR(500) NOT NULL,
        kept TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_snapshot (student_id, run_id, slide, attempt),
        INDEX idx_run (run_id),
        INDEX idx_cleanup (kept, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Tables created before `kept` existed.
    $c = $conn->query("SHOW COLUMNS FROM attempt_snapshots LIKE 'kept'");
    if ($c && $c->num_rows == 0) {
        $conn->query("ALTER TABLE attempt_snapshots ADD COLUMN kept TINYINT(1) NOT NULL DEFAULT 0, ADD INDEX idx_cleanup (kept, created_at)");
        // Anything already linked to a submission must survive the cleanup.
        $conn->query("UPDATE attempt_snapshots s SET kept = 1 WHERE EXISTS (
            SELECT 1 FROM activity_submissions sub
            WHERE sub.student_id = s.student_id AND sub.attempt_history_json LIKE CONCAT('%\"run\":\"', s.run_id, '\"%'))");
    }
}

function isValidSnapshotRunId($run) {
    return is_string($run) && preg_match('/^[a-z0-9]{8,32}$/', $run) === 1;
}

// The run ids an attempt_history_json mentions.
function snapshotRunsIn($attemptHistoryJson) {
    $runs = [];
    $ah = json_decode((string)$attemptHistoryJson, true);
    if (is_array($ah)) {
        foreach ($ah as $sl) {
            if (is_array($sl) && isValidSnapshotRunId($sl['run'] ?? null)) $runs[$sl['run']] = true;
        }
    }
    return array_keys($runs);
}

// Saves JPEG bytes to Cloudinary, or (plain XAMPP with no Cloudinary
// account) to TEACHER_FILES/uploads/snapshots/. Returns what to store in
// image_url — a full URL or a path from the site root — or null.
function saveSnapshotImage($bytes) {
    require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/cloudinary_upload.php';
    if (defined('CLOUDINARY_API_KEY') && CLOUDINARY_API_KEY !== '') {
        $tmp = tempnam(sys_get_temp_dir(), 'snap');
        file_put_contents($tmp, $bytes);
        $url = cloudinaryUpload(new CURLFile($tmp, 'image/jpeg', 'snapshot.jpg'), 'image', 'snapshots');
        @unlink($tmp);
        return $url ?: null;
    }
    $dir = __DIR__ . '/../uploads/snapshots';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) return null;
    $name = bin2hex(random_bytes(16)) . '.jpg';
    if (file_put_contents($dir . '/' . $name, $bytes) === false) return null;
    return 'TEACHER_FILES/uploads/snapshots/' . $name;
}

function deleteSnapshotImage($url) {
    $url = (string)$url;
    if (strpos($url, 'res.cloudinary.com') !== false) {
        require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/cloudinary_upload.php';
        cloudinaryDeleteByUrl($url);
        return;
    }
    // Only ever our own random names — never a path someone could aim elsewhere.
    if (preg_match('#^TEACHER_FILES/uploads/snapshots/([a-f0-9]{32}\.jpg)$#', $url, $m)) {
        $path = __DIR__ . '/../uploads/snapshots/' . $m[1];
        if (is_file($path)) @unlink($path);
    }
}

// A submission was saved: its runs' pictures are kept; the runs it
// replaced (a resubmission) go back to being cleaned up.
function markSnapshotRuns($conn, $student_id, array $keepRuns, array $dropRuns) {
    ensureSnapshotSchema($conn);
    $set = $conn->prepare("UPDATE attempt_snapshots SET kept = ? WHERE student_id = ? AND run_id = ?");
    if (!$set) return;
    foreach ([[1, $keepRuns], [0, array_diff($dropRuns, $keepRuns)]] as $pair) {
        foreach ($pair[1] as $run) {
            if (!isValidSnapshotRunId($run)) continue;
            $k = $pair[0];
            $set->bind_param("iis", $k, $student_id, $run);
            $set->execute();
        }
    }
    $set->close();
}

// Is this run already part of one of the student's submissions? (A picture
// can finish uploading a moment after the student tapped Submit.)
function snapshotRunIsSubmitted($conn, $student_id, $run) {
    $like = '%"run":"' . $run . '"%';
    $q = $conn->prepare("SELECT 1 FROM activity_submissions WHERE student_id = ? AND attempt_history_json LIKE ? LIMIT 1");
    if (!$q) return false;
    $q->bind_param("is", $student_id, $like);
    $q->execute();
    $found = (bool)$q->get_result()->fetch_row();
    $q->close();
    return $found;
}

// Deletes a batch of pictures nobody's submission uses any more.
function cleanupSnapshots($conn, $limit = 40) {
    ensureSnapshotSchema($conn);
    $hours = SNAPSHOT_GRACE_HOURS;
    $limit = max(1, (int)$limit);
    $r = $conn->query("SELECT id, image_url FROM attempt_snapshots
        WHERE kept = 0 AND created_at < DATE_SUB(NOW(), INTERVAL $hours HOUR) LIMIT $limit");
    if (!$r) return 0;
    $ids = [];
    while ($row = $r->fetch_assoc()) {
        deleteSnapshotImage($row['image_url']);
        $ids[] = (int)$row['id'];
    }
    if ($ids) $conn->query("DELETE FROM attempt_snapshots WHERE id IN (" . implode(',', $ids) . ")");
    return count($ids);
}

// "slide-attempt" => image_url for one student's run.
function loadSnapshots($conn, $student_id, $run) {
    if (!isValidSnapshotRunId($run)) return [];
    ensureSnapshotSchema($conn);
    $out = [];
    $q = $conn->prepare("SELECT slide, attempt, image_url FROM attempt_snapshots WHERE student_id = ? AND run_id = ?");
    if (!$q) return [];
    $q->bind_param("is", $student_id, $run);
    $q->execute();
    $res = $q->get_result();
    while ($r = $res->fetch_assoc()) {
        $out[$r['slide'] . '-' . $r['attempt']] = $r['image_url'];
    }
    $q->close();
    return $out;
}
