<?php
// Which dashboard items the logged-in student has already seen, so the
// "Today's Activities / Uploaded Materials / Completed" tabs can show a
// number for what's new since they last looked. Kept in the database (not
// the browser) so a student who switches devices doesn't see old items as
// new again.
//
// GET  → { first_time, seen: { act: [ids], mat: [ids], done: [ids] } }
//        first_time = nothing recorded yet: the page treats everything
//        already there as seen, so only later additions get a number.
// POST type=act|mat|done|init, ids=1,2,3 → marks those items seen.
require_once __DIR__ . '/../../TEACHER_FILES/TEACHER_BACKEND/db.php';
require_once __DIR__ . '/student_auth.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache');

$student_admin_id = requireStudentSession();

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}
$rec = resolveStudentRecord($conn, $student_admin_id);
if (!$rec) {
    echo json_encode(['success' => false, 'message' => 'Not enrolled']);
    $conn->close();
    exit;
}
$student_id = (int)$rec['student_record_id'];

$conn->query("CREATE TABLE IF NOT EXISTS student_seen_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    item_type VARCHAR(10) NOT NULL,
    item_id INT NOT NULL,
    seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_seen_item (student_id, item_type, item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 'act' = an unfinished activity (Today's Activities), 'mat' = an uploaded
// material, 'done' = a completed activity, 'init' = first-load marker.
$TYPES = ['act', 'mat', 'done', 'init'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = isset($_POST['type']) ? (string)$_POST['type'] : '';
    if (!in_array($type, $TYPES, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid type']);
        $conn->close();
        exit;
    }
    $ids = array_slice(array_values(array_unique(array_filter(
        array_map('intval', explode(',', (string)($_POST['ids'] ?? ''))),
        function ($v) { return $v >= 0; }
    ))), 0, 500);
    if ($ids) {
        $stmt = $conn->prepare("INSERT IGNORE INTO student_seen_items (student_id, item_type, item_id) VALUES (?, ?, ?)");
        foreach ($ids as $id) {
            $stmt->bind_param("isi", $student_id, $type, $id);
            $stmt->execute();
        }
        $stmt->close();
    }
    $conn->close();
    echo json_encode(['success' => true, 'marked' => count($ids)]);
    exit;
}

$seen = ['act' => [], 'mat' => [], 'done' => []];
$any = false;
$stmt = $conn->prepare("SELECT item_type, item_id FROM student_seen_items WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $any = true;
    if (isset($seen[$row['item_type']])) $seen[$row['item_type']][] = (int)$row['item_id'];
}
$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'first_time' => !$any, 'seen' => $seen]);
