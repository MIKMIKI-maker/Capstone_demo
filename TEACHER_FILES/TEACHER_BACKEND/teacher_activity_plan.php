<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$conn = getTeacherDatabaseConnection();
if (!$conn) { echo json_encode(['success' => false]); exit; }

$conn->query("CREATE TABLE IF NOT EXISTS teacher_activity_plan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT NOT NULL,
  grading_period ENUM('First','Second','Third') NOT NULL,
  item_text VARCHAR(255) NOT NULL,
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_teacher_grading (teacher_id, grading_period)
)");

$teacher_id = isset($_REQUEST['teacher_id']) ? intval($_REQUEST['teacher_id']) : 0;
if (!$teacher_id) { echo json_encode(['success' => false, 'message' => 'teacher_id required']); exit; }

$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : 'get';

// The plan used to be hardcoded HTML — seed a new teacher's plan with that
// same content once, so making it editable doesn't leave the dashboard
// looking blank on first load.
function seedDefaultPlan($conn, $teacher_id) {
    $defaults = [
        'First'  => ['Cognitive Development', 'Communication Skills', 'Fine Motor Skills', 'Attention & Routine'],
        'Second' => ['Social Skills', 'Emotional Regulation', 'Gross Motor Skills', 'Behavior Support'],
        'Third'  => ['Life Skills', 'Self-Help Skills', 'Functional Communication', 'Evaluation & Progress Monitoring'],
    ];
    $stmt = $conn->prepare("INSERT INTO teacher_activity_plan (teacher_id, grading_period, item_text, sort_order) VALUES (?, ?, ?, ?)");
    foreach ($defaults as $period => $items) {
        foreach ($items as $i => $text) {
            $stmt->bind_param("issi", $teacher_id, $period, $text, $i);
            $stmt->execute();
        }
    }
    $stmt->close();
}

switch ($action) {
    case 'get':
        $check = $conn->prepare("SELECT COUNT(*) AS cnt FROM teacher_activity_plan WHERE teacher_id=?");
        $check->bind_param("i", $teacher_id);
        $check->execute();
        $cnt = $check->get_result()->fetch_assoc()['cnt'];
        $check->close();
        if ($cnt == 0) seedDefaultPlan($conn, $teacher_id);

        $stmt = $conn->prepare("SELECT id, grading_period, item_text FROM teacher_activity_plan WHERE teacher_id=? ORDER BY grading_period, sort_order ASC, id ASC");
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $plan = ['First' => [], 'Second' => [], 'Third' => []];
        while ($row = $res->fetch_assoc()) {
            $plan[$row['grading_period']][] = ['id' => $row['id'], 'text' => $row['item_text']];
        }
        $stmt->close();
        echo json_encode(['success' => true, 'plan' => $plan]);
        break;

    case 'create':
        $period = in_array($_POST['grading_period'] ?? '', ['First', 'Second', 'Third']) ? $_POST['grading_period'] : null;
        $text = trim($_POST['item_text'] ?? '');
        if (!$period || $text === '') { echo json_encode(['success' => false, 'message' => 'grading_period and item_text required']); break; }
        if (mb_strlen($text) > 255) $text = mb_substr($text, 0, 255);

        $ord = $conn->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 AS next_ord FROM teacher_activity_plan WHERE teacher_id=? AND grading_period=?");
        $ord->bind_param("is", $teacher_id, $period);
        $ord->execute();
        $nextOrd = $ord->get_result()->fetch_assoc()['next_ord'];
        $ord->close();

        $stmt = $conn->prepare("INSERT INTO teacher_activity_plan (teacher_id, grading_period, item_text, sort_order) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issi", $teacher_id, $period, $text, $nextOrd);
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();
        echo json_encode(['success' => true, 'id' => $newId, 'text' => $text]);
        break;

    case 'delete':
        $item_id = intval($_POST['item_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM teacher_activity_plan WHERE id=? AND teacher_id=?");
        $stmt->bind_param("ii", $item_id, $teacher_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
$conn->close();
