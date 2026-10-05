<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/teacher_auth.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/school_year.php';
header('Content-Type: application/json');

$conn = getTeacherDatabaseConnection();
if (!$conn) { echo json_encode(['success' => false]); exit; }

$conn->query("CREATE TABLE IF NOT EXISTS teacher_activity_plan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT NOT NULL,
  grading_period ENUM('First','Second','Third') NOT NULL,
  item_text VARCHAR(255) NOT NULL,
  sort_order INT DEFAULT 0,
  school_year_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_teacher_grading (teacher_id, grading_period),
  INDEX idx_school_year (school_year_id)
)");

// Each School Year has its own plan and its own grading period locks
// (school-wide, set by the Admin in Settings) — see school_year.php.
$sy_id = getViewedSchoolYearId($conn);
seedGradingLocks($conn, $sy_id);

function isGradingUnlocked($conn, $period) {
    global $sy_id;
    $stmt = $conn->prepare("SELECT is_unlocked FROM grading_period_locks WHERE school_year_id=? AND grading_period=?");
    $stmt->bind_param("is", $sy_id, $period);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (bool)$row['is_unlocked'] : false;
}

function getAllGradingLocks($conn) {
    global $sy_id;
    $locks = ['First' => false, 'Second' => false, 'Third' => false];
    $stmt = $conn->prepare("SELECT grading_period, is_unlocked FROM grading_period_locks WHERE school_year_id=?");
    $stmt->bind_param("i", $sy_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $locks[$row['grading_period']] = (bool)$row['is_unlocked'];
    }
    return $locks;
}

// The logged-in teacher, from the session — never a teacher_id sent by the browser.
$teacher_id = requireTeacherId();
if (!$teacher_id) { echo json_encode(['success' => false, 'message' => 'teacher_id required']); exit; }

$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : 'get';
if ($action !== 'get') requireActiveSchoolYearView($conn);

// The plan used to be hardcoded HTML — seed a new teacher's plan with that
// same content once, so making it editable doesn't leave the dashboard
// looking blank on first load.
function seedDefaultPlan($conn, $teacher_id, $sy_id) {
    $defaults = [
        'First'  => ['Cognitive Development', 'Communication Skills', 'Fine Motor Skills', 'Attention & Routine'],
        'Second' => ['Social Skills', 'Emotional Regulation', 'Gross Motor Skills', 'Behavior Support'],
        'Third'  => ['Life Skills', 'Self-Help Skills', 'Functional Communication', 'Evaluation & Progress Monitoring'],
    ];
    $stmt = $conn->prepare("INSERT INTO teacher_activity_plan (teacher_id, grading_period, item_text, sort_order, school_year_id) VALUES (?, ?, ?, ?, ?)");
    foreach ($defaults as $period => $items) {
        foreach ($items as $i => $text) {
            $stmt->bind_param("issii", $teacher_id, $period, $text, $i, $sy_id);
            $stmt->execute();
        }
    }
    $stmt->close();
}

switch ($action) {
    case 'get':
        $check = $conn->prepare("SELECT COUNT(*) AS cnt FROM teacher_activity_plan WHERE teacher_id=? AND school_year_id=?");
        $check->bind_param("ii", $teacher_id, $sy_id);
        $check->execute();
        $cnt = $check->get_result()->fetch_assoc()['cnt'];
        $check->close();
        // Only the Active S.Y. gets a starter plan — a past one stays as it was.
        if ($cnt == 0 && $sy_id === getActiveSchoolYearId($conn)) seedDefaultPlan($conn, $teacher_id, $sy_id);

        $stmt = $conn->prepare("SELECT id, grading_period, item_text FROM teacher_activity_plan WHERE teacher_id=? AND school_year_id=? ORDER BY grading_period, sort_order ASC, id ASC");
        $stmt->bind_param("ii", $teacher_id, $sy_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $plan = ['First' => [], 'Second' => [], 'Third' => []];
        while ($row = $res->fetch_assoc()) {
            $plan[$row['grading_period']][] = ['id' => $row['id'], 'text' => $row['item_text']];
        }
        $stmt->close();
        echo json_encode(['success' => true, 'plan' => $plan, 'locks' => getAllGradingLocks($conn)]);
        break;

    case 'create':
        $period = in_array($_POST['grading_period'] ?? '', ['First', 'Second', 'Third']) ? $_POST['grading_period'] : null;
        $text = trim($_POST['item_text'] ?? '');
        if (!$period || $text === '') { echo json_encode(['success' => false, 'message' => 'grading_period and item_text required']); break; }
        if (!isGradingUnlocked($conn, $period)) { echo json_encode(['success' => false, 'message' => 'This grading period is locked. Contact your Admin to unlock it.']); break; }
        if (mb_strlen($text) > 255) $text = mb_substr($text, 0, 255);

        $ord = $conn->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 AS next_ord FROM teacher_activity_plan WHERE teacher_id=? AND grading_period=? AND school_year_id=?");
        $ord->bind_param("isi", $teacher_id, $period, $sy_id);
        $ord->execute();
        $nextOrd = $ord->get_result()->fetch_assoc()['next_ord'];
        $ord->close();

        $stmt = $conn->prepare("INSERT INTO teacher_activity_plan (teacher_id, grading_period, item_text, sort_order, school_year_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issii", $teacher_id, $period, $text, $nextOrd, $sy_id);
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();
        echo json_encode(['success' => true, 'id' => $newId, 'text' => $text]);
        break;

    case 'delete':
        $item_id = intval($_POST['item_id'] ?? 0);
        $ownerStmt = $conn->prepare("SELECT grading_period FROM teacher_activity_plan WHERE id=? AND teacher_id=? AND school_year_id=?");
        $ownerStmt->bind_param("iii", $item_id, $teacher_id, $sy_id);
        $ownerStmt->execute();
        $ownerRow = $ownerStmt->get_result()->fetch_assoc();
        $ownerStmt->close();
        if ($ownerRow && !isGradingUnlocked($conn, $ownerRow['grading_period'])) {
            echo json_encode(['success' => false, 'message' => 'This grading period is locked. Contact your Admin to unlock it.']);
            break;
        }
        $stmt = $conn->prepare("DELETE FROM teacher_activity_plan WHERE id=? AND teacher_id=? AND school_year_id=?");
        $stmt->bind_param("iii", $item_id, $teacher_id, $sy_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
        break;

    case 'reorder':
        $period = in_array($_POST['grading_period'] ?? '', ['First', 'Second', 'Third']) ? $_POST['grading_period'] : null;
        $order = trim($_POST['order'] ?? '');
        if (!$period || $order === '') { echo json_encode(['success' => false, 'message' => 'grading_period and order required']); break; }
        if (!isGradingUnlocked($conn, $period)) { echo json_encode(['success' => false, 'message' => 'This grading period is locked. Contact your Admin to unlock it.']); break; }
        $ids = array_filter(array_map('intval', explode(',', $order)));

        $stmt = $conn->prepare("UPDATE teacher_activity_plan SET sort_order=? WHERE id=? AND teacher_id=? AND grading_period=? AND school_year_id=?");
        foreach (array_values($ids) as $i => $id) {
            $stmt->bind_param("iiisi", $i, $id, $teacher_id, $period, $sy_id);
            $stmt->execute();
        }
        $stmt->close();
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
$conn->close();
