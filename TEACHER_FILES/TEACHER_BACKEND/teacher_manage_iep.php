<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action = isset($_POST['action']) ? trim($_POST['action']) : '';
$teacher_id = isset($_POST['teacher_id']) ? intval($_POST['teacher_id']) : 1;

switch($action) {
    case 'create':
        createIEP($conn, $teacher_id);
        break;
    case 'update':
        updateIEP($conn, $teacher_id);
        break;
    case 'delete':
        deleteIEP($conn, $teacher_id);
        break;
    case 'list':
        listIEPs($conn, $teacher_id);
        break;
    case 'get':
        getIEP($conn, $teacher_id);
        break;
    case 'list_by_student':
        listIEPsByStudent($conn, $teacher_id);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}

$conn->close();

function createIEP($conn, $teacher_id) {
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    $iep_goal = isset($_POST['iep_goal']) ? trim($_POST['iep_goal']) : '';
    $learning_objective = isset($_POST['learning_objective']) ? trim($_POST['learning_objective']) : '';
    $strategies = isset($_POST['strategies']) ? trim($_POST['strategies']) : '';
    $materials = isset($_POST['materials']) ? trim($_POST['materials']) : '';
    $assessment_method = isset($_POST['assessment_method']) ? trim($_POST['assessment_method']) : '';
    
    if (!$student_id || !$iep_goal) {
        echo json_encode(['success' => false, 'message' => 'Student ID and IEP goal are required']);
        return;
    }
    
    $sql = "INSERT INTO iep_materials (teacher_id, student_id, iep_goal, learning_objective, strategies, materials, assessment_method, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisssss", $teacher_id, $student_id, $iep_goal, $learning_objective, $strategies, $materials, $assessment_method);
    
    if ($stmt->execute()) {
        $iep_id = $stmt->insert_id;
        echo json_encode(['success' => true, 'message' => 'IEP created successfully', 'iep_id' => $iep_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create IEP']);
    }
    $stmt->close();
}

function updateIEP($conn, $teacher_id) {
    $iep_id = isset($_POST['iep_id']) ? intval($_POST['iep_id']) : 0;
    $iep_goal = isset($_POST['iep_goal']) ? trim($_POST['iep_goal']) : '';
    $learning_objective = isset($_POST['learning_objective']) ? trim($_POST['learning_objective']) : '';
    $strategies = isset($_POST['strategies']) ? trim($_POST['strategies']) : '';
    $materials = isset($_POST['materials']) ? trim($_POST['materials']) : '';
    $assessment_method = isset($_POST['assessment_method']) ? trim($_POST['assessment_method']) : '';
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'active';
    
    if (!$iep_id) {
        echo json_encode(['success' => false, 'message' => 'IEP ID is required']);
        return;
    }
    
    $sql = "UPDATE iep_materials SET iep_goal=?, learning_objective=?, strategies=?, materials=?, assessment_method=?, status=?, updated_at=NOW()
            WHERE id=? AND teacher_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssii", $iep_goal, $learning_objective, $strategies, $materials, $assessment_method, $status, $iep_id, $teacher_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'IEP updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update IEP']);
    }
    $stmt->close();
}

function deleteIEP($conn, $teacher_id) {
    $iep_id = isset($_POST['iep_id']) ? intval($_POST['iep_id']) : 0;
    
    if (!$iep_id) {
        echo json_encode(['success' => false, 'message' => 'IEP ID is required']);
        return;
    }
    
    $sql = "DELETE FROM iep_materials WHERE id=? AND teacher_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $iep_id, $teacher_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'IEP deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete IEP']);
    }
    $stmt->close();
}

function listIEPs($conn, $teacher_id) {
    $sql = "SELECT im.*, s.student_name FROM iep_materials im
            LEFT JOIN students s ON im.student_id = s.id
            WHERE im.teacher_id=? ORDER BY im.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $ieps = [];
    while ($row = $result->fetch_assoc()) {
        $ieps[] = $row;
    }
    
    echo json_encode(['success' => true, 'ieps' => $ieps, 'count' => count($ieps)]);
    $stmt->close();
}

function getIEP($conn, $teacher_id) {
    $iep_id = isset($_POST['iep_id']) ? intval($_POST['iep_id']) : 0;
    
    if (!$iep_id) {
        echo json_encode(['success' => false, 'message' => 'IEP ID is required']);
        return;
    }
    
    $sql = "SELECT im.*, s.student_name, s.disability_type FROM iep_materials im
            LEFT JOIN students s ON im.student_id = s.id
            WHERE im.id=? AND im.teacher_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $iep_id, $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'iep' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'IEP not found']);
    }
    $stmt->close();
}

function listIEPsByStudent($conn, $teacher_id) {
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
        return;
    }
    
    $sql = "SELECT * FROM iep_materials WHERE teacher_id=? AND student_id=? ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $teacher_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $ieps = [];
    while ($row = $result->fetch_assoc()) {
        $ieps[] = $row;
    }
    
    echo json_encode(['success' => true, 'ieps' => $ieps, 'count' => count($ieps)]);
    $stmt->close();
}
?>
