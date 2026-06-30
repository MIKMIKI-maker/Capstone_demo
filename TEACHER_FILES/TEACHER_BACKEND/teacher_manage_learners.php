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
    case 'add':
        addLearner($conn, $teacher_id);
        break;
    case 'edit':
        editLearner($conn, $teacher_id);
        break;
    case 'delete':
        deleteLearner($conn, $teacher_id);
        break;
    case 'list':
        listLearners($conn, $teacher_id);
        break;
    case 'get':
        getLearner($conn, $teacher_id);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}

$conn->close();

function addLearner($conn, $teacher_id) {
    $student_name = isset($_POST['student_name']) ? trim($_POST['student_name']) : '';
    $parent_name = isset($_POST['parent_name']) ? trim($_POST['parent_name']) : '';
    $parent_email = isset($_POST['parent_email']) ? trim($_POST['parent_email']) : '';
    $parent_phone = isset($_POST['parent_phone']) ? trim($_POST['parent_phone']) : '';
    $disability_type = isset($_POST['disability_type']) ? trim($_POST['disability_type']) : '';
    $disability_category = isset($_POST['disability_category']) ? trim($_POST['disability_category']) : '';
    $grade_level = isset($_POST['grade_level']) ? trim($_POST['grade_level']) : '';
    $age = isset($_POST['age']) ? intval($_POST['age']) : 0;
    
    if (!$student_name) {
        echo json_encode(['success' => false, 'message' => 'Student name is required']);
        return;
    }
    
    $sql = "INSERT INTO students (teacher_id, student_name, parent_name, parent_email, parent_phone, disability_type, disability_category, grade_level, age, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssssii", $teacher_id, $student_name, $parent_name, $parent_email, $parent_phone, $disability_type, $disability_category, $grade_level, $age);
    
    if ($stmt->execute()) {
        $student_id = $stmt->insert_id;
        echo json_encode(['success' => true, 'message' => 'Learner added successfully', 'student_id' => $student_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add learner: ' . $stmt->error]);
    }
    $stmt->close();
}

function editLearner($conn, $teacher_id) {
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    $student_name = isset($_POST['student_name']) ? trim($_POST['student_name']) : '';
    $parent_name = isset($_POST['parent_name']) ? trim($_POST['parent_name']) : '';
    $parent_email = isset($_POST['parent_email']) ? trim($_POST['parent_email']) : '';
    $parent_phone = isset($_POST['parent_phone']) ? trim($_POST['parent_phone']) : '';
    $disability_type = isset($_POST['disability_type']) ? trim($_POST['disability_type']) : '';
    $disability_category = isset($_POST['disability_category']) ? trim($_POST['disability_category']) : '';
    $grade_level = isset($_POST['grade_level']) ? trim($_POST['grade_level']) : '';
    $age = isset($_POST['age']) ? intval($_POST['age']) : 0;
    
    if (!$student_id || !$student_name) {
        echo json_encode(['success' => false, 'message' => 'Student ID and name are required']);
        return;
    }
    
    $sql = "UPDATE students SET student_name=?, parent_name=?, parent_email=?, parent_phone=?, disability_type=?, disability_category=?, grade_level=?, age=?, updated_at=NOW()
            WHERE id=? AND teacher_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssiii", $student_name, $parent_name, $parent_email, $parent_phone, $disability_type, $disability_category, $grade_level, $age, $student_id, $teacher_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Learner updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update learner']);
    }
    $stmt->close();
}

function deleteLearner($conn, $teacher_id) {
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
        return;
    }
    
    $sql = "DELETE FROM students WHERE id=? AND teacher_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $student_id, $teacher_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Learner deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete learner']);
    }
    $stmt->close();
}

function listLearners($conn, $teacher_id) {
    $sql = "SELECT id, student_name, parent_name, parent_email, parent_phone, disability_type, disability_category, grade_level, age, status, created_at
            FROM students WHERE teacher_id=? ORDER BY student_name ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $learners = [];
    while ($row = $result->fetch_assoc()) {
        $learners[] = $row;
    }
    
    echo json_encode(['success' => true, 'learners' => $learners, 'count' => count($learners)]);
    $stmt->close();
}

function getLearner($conn, $teacher_id) {
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
        return;
    }
    
    $sql = "SELECT * FROM students WHERE id=? AND teacher_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $student_id, $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'learner' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Learner not found']);
    }
    $stmt->close();
}
?>
