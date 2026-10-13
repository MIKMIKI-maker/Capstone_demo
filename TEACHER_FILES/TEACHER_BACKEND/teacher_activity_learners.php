<?php
// "👥 Add Learners" on a published activity (Review Activities): a learner
// forgotten when the activity was published can be added afterwards,
// without making the activity again.
//
// GET  ?activity_id=N             → assigned learners + the rest of the class
// POST activity_id, student_ids   → assign those learners (comma-separated ids)
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/teacher_auth.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/school_year.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$teacher_id  = requireTeacherId();
$activity_id = (int)($_POST['activity_id'] ?? $_GET['activity_id'] ?? 0);
$conn = getTeacherDatabaseConnection();
if (!$conn || !$activity_id) {
    echo json_encode(['success' => false, 'message' => 'Missing activity']);
    exit;
}

$a = $conn->prepare("SELECT id, activity_title, status, term_name FROM teacher_activities WHERE id = ? AND teacher_id = ?");
$a->bind_param("ii", $activity_id, $teacher_id);
$a->execute();
$act = $a->get_result()->fetch_assoc();
$a->close();
if (!$act) {
    echo json_encode(['success' => false, 'message' => 'Activity not found']);
    exit;
}

// This teacher's active learners in the current School Year.
function classLearners($conn, $teacher_id) {
    $roster = teacherRosterCondition($conn, $teacher_id, getActiveSchoolYearId($conn), 's');
    $out = [];
    $r = $conn->query("SELECT s.id, s.student_name FROM students s WHERE $roster AND s.status = 'active' ORDER BY s.student_name");
    if ($r) while ($row = $r->fetch_assoc()) $out[(int)$row['id']] = $row['student_name'];
    return $out;
}
function assignedIds($conn, $activity_id) {
    $ids = [];
    $q = $conn->prepare("SELECT student_id FROM activity_assignments WHERE activity_id = ?");
    $q->bind_param("i", $activity_id);
    $q->execute();
    $res = $q->get_result();
    while ($row = $res->fetch_assoc()) $ids[(int)$row['student_id']] = true;
    $q->close();
    return $ids;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireActiveSchoolYearView($conn);
    if ($act['status'] !== 'published') {
        echo json_encode(['success' => false, 'message' => 'Only published activities can get more learners.']);
        exit;
    }
    $class = classLearners($conn, $teacher_id);
    $already = assignedIds($conn, $activity_id);
    $want = array_unique(array_filter(array_map('intval', explode(',', (string)($_POST['student_ids'] ?? '')))));
    // Only learners from this teacher's class who don't have it yet.
    $add = array_values(array_filter($want, function ($id) use ($class, $already) { return isset($class[$id]) && !isset($already[$id]); }));
    if (!$add) {
        echo json_encode(['success' => false, 'message' => 'Pick at least one learner who does not have this activity yet.']);
        exit;
    }

    // Some databases have a required teacher_id column on assignments (see
    // teacher_create_activity.php) — fill it when it exists.
    $tcol = $conn->query("SHOW COLUMNS FROM activity_assignments LIKE 'teacher_id'");
    $hasTeacherCol = $tcol && $tcol->num_rows > 0;
    $term = $act['term_name'] ?: 'First';
    $ins = $hasTeacherCol
        ? $conn->prepare("INSERT IGNORE INTO activity_assignments (activity_id, student_id, term_name, teacher_id) VALUES (?, ?, ?, ?)")
        : $conn->prepare("INSERT IGNORE INTO activity_assignments (activity_id, student_id, term_name) VALUES (?, ?, ?)");
    $added = [];
    foreach ($add as $sid) {
        if ($hasTeacherCol) $ins->bind_param("iisi", $activity_id, $sid, $term, $teacher_id);
        else $ins->bind_param("iis", $activity_id, $sid, $term);
        if ($ins->execute() && $ins->affected_rows > 0) $added[] = $class[$sid];
    }
    $ins->close();

    if ($added) {
        require_once __DIR__ . '/teacher_push_notification.php';
        pushTeacherNotification($conn, $teacher_id, 'activity', 'Learners Added',
            'Added ' . implode(', ', $added) . ' to "' . $act['activity_title'] . '".');
    }
    echo json_encode(['success' => true, 'added' => count($added), 'names' => $added]);
    exit;
}

$class = classLearners($conn, $teacher_id);
$already = assignedIds($conn, $activity_id);
$assigned = [];
$available = [];
foreach ($class as $id => $name) {
    if (isset($already[$id])) $assigned[] = ['id' => $id, 'name' => $name];
    else $available[] = ['id' => $id, 'name' => $name];
}
// Assigned learners no longer in the class (moved on) still show as assigned.
foreach (array_keys($already) as $id) {
    if (!isset($class[$id])) {
        $q = $conn->prepare("SELECT student_name FROM students WHERE id = ?");
        $q->bind_param("i", $id);
        $q->execute();
        $row = $q->get_result()->fetch_assoc();
        $q->close();
        if ($row) $assigned[] = ['id' => $id, 'name' => $row['student_name']];
    }
}

echo json_encode([
    'success'   => true,
    'activity'  => ['id' => (int)$act['id'], 'title' => $act['activity_title'], 'status' => $act['status']],
    'assigned'  => $assigned,
    'available' => $available,
]);
