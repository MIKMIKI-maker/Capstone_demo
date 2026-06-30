<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$conn = getTeacherDatabaseConnection();
if (!$conn) { echo json_encode(['success' => false]); exit; }

session_start();
$teacher_id = isset($_REQUEST['teacher_id']) ? intval($_REQUEST['teacher_id']) : 0;
if (!$teacher_id) { echo json_encode(['success' => false, 'message' => 'teacher_id required']); exit; }

$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : 'get';

switch ($action) {
    case 'get':
        $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
        if ($filter === 'done') {
            $stmt = $conn->prepare("SELECT id, task_text, is_done, created_at FROM teacher_tasks WHERE teacher_id=? AND is_done=1 ORDER BY updated_at DESC");
        } elseif ($filter === 'todo') {
            $stmt = $conn->prepare("SELECT id, task_text, is_done, created_at FROM teacher_tasks WHERE teacher_id=? AND is_done=0 ORDER BY created_at ASC");
        } else {
            $stmt = $conn->prepare("SELECT id, task_text, is_done, created_at FROM teacher_tasks WHERE teacher_id=? ORDER BY is_done ASC, created_at DESC");
        }
        if (!$stmt) { echo json_encode(['success' => true, 'tasks' => []]); break; }
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $tasks = [];
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $tasks[] = $row;
        $stmt->close();
        echo json_encode(['success' => true, 'tasks' => $tasks]);
        break;

    case 'create':
        $text = trim($_POST['task_text'] ?? '');
        if (!$text) { echo json_encode(['success' => false, 'message' => 'Empty task']); break; }
        $stmt = $conn->prepare("INSERT INTO teacher_tasks (teacher_id, task_text) VALUES (?, ?)");
        if (!$stmt) { echo json_encode(['success' => false]); break; }
        $stmt->bind_param("is", $teacher_id, $text);
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();
        echo json_encode(['success' => true, 'id' => $newId, 'task_text' => $text, 'is_done' => '0']);
        break;

    case 'toggle':
        $task_id = intval($_POST['task_id'] ?? 0);
        $is_done = intval($_POST['is_done'] ?? 0);
        $stmt = $conn->prepare("UPDATE teacher_tasks SET is_done=? WHERE id=? AND teacher_id=?");
        if (!$stmt) { echo json_encode(['success' => false]); break; }
        $stmt->bind_param("iii", $is_done, $task_id, $teacher_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
        break;

    case 'delete':
        $task_id = intval($_POST['task_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM teacher_tasks WHERE id=? AND teacher_id=?");
        if (!$stmt) { echo json_encode(['success' => false]); break; }
        $stmt->bind_param("ii", $task_id, $teacher_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
        break;

    case 'clear_done':
        $stmt = $conn->prepare("DELETE FROM teacher_tasks WHERE teacher_id=? AND is_done=1");
        if (!$stmt) { echo json_encode(['success' => false]); break; }
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
        break;
}
$conn->close();
