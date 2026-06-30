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
    case 'generate_from_template':
        generateFromTemplate($conn, $teacher_id);
        break;
    case 'list_templates':
        listTemplates($conn);
        break;
    case 'get_template':
        getTemplate($conn);
        break;
    case 'create_custom_template':
        createCustomTemplate($conn, $teacher_id);
        break;
    case 'get_difficulty_levels':
        getDifficultyLevels();
        break;
    case 'get_categories':
        getCategories();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}

$conn->close();

function generateFromTemplate($conn, $teacher_id) {
    $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
    $activity_title = isset($_POST['activity_title']) ? trim($_POST['activity_title']) : '';
    $activity_description = isset($_POST['activity_description']) ? trim($_POST['activity_description']) : '';
    $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
    $grade_level = isset($_POST['grade_level']) ? trim($_POST['grade_level']) : '';
    $difficulty = isset($_POST['difficulty']) ? trim($_POST['difficulty']) : 'medium';
    
    if (!$activity_title) {
        echo json_encode(['success' => false, 'message' => 'Activity title is required']);
        return;
    }
    
    // Get template content if template_id provided
    $template_content = '';
    if ($template_id > 0) {
        $templateSQL = "SELECT template_content FROM activity_templates WHERE id=?";
        $templateStmt = $conn->prepare($templateSQL);
        $templateStmt->bind_param("i", $template_id);
        $templateStmt->execute();
        $templateResult = $templateStmt->get_result();
        if ($row = $templateResult->fetch_assoc()) {
            $template_content = $row['template_content'];
        }
        $templateStmt->close();
    }
    
    // Create new activity from template
    $sql = "INSERT INTO teacher_activities (teacher_id, activity_title, activity_description, subject, grade_level, difficulty, status)
            VALUES (?, ?, ?, ?, ?, ?, 'draft')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssss", $teacher_id, $activity_title, $activity_description, $subject, $grade_level, $difficulty);
    
    if ($stmt->execute()) {
        $activity_id = $stmt->insert_id;
        
        // Log activity creation
        $admin_conn = getDatabaseConnection();
        if ($admin_conn) {
            $admin_conn->query("INSERT INTO admin_activities (activity_type, user_type, user_name, action_detail) 
                                VALUES ('Create Activity', 'teacher', 'Teacher', 'Activity: $activity_title')");
            $admin_conn->close();
        }
        
        echo json_encode(['success' => true, 'message' => 'Activity generated from template', 'activity_id' => $activity_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to generate activity']);
    }
    $stmt->close();
}

function listTemplates($conn) {
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $difficulty = isset($_POST['difficulty']) ? trim($_POST['difficulty']) : '';
    
    $sql = "SELECT * FROM activity_templates WHERE is_public=TRUE";
    $params = [];
    $types = '';
    
    if (!empty($category)) {
        $sql .= " AND template_category=?";
        $params[] = $category;
        $types .= 's';
    }
    
    if (!empty($difficulty)) {
        $sql .= " AND difficulty=?";
        $params[] = $difficulty;
        $types .= 's';
    }
    
    $sql .= " ORDER BY template_name ASC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $templates = [];
    while ($row = $result->fetch_assoc()) {
        $templates[] = $row;
    }
    
    echo json_encode(['success' => true, 'templates' => $templates, 'count' => count($templates)]);
    $stmt->close();
}

function getTemplate($conn) {
    $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
    
    if (!$template_id) {
        echo json_encode(['success' => false, 'message' => 'Template ID is required']);
        return;
    }
    
    $sql = "SELECT * FROM activity_templates WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $template_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'template' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Template not found']);
    }
    $stmt->close();
}

function createCustomTemplate($conn, $teacher_id) {
    $template_name = isset($_POST['template_name']) ? trim($_POST['template_name']) : '';
    $template_category = isset($_POST['template_category']) ? trim($_POST['template_category']) : '';
    $template_description = isset($_POST['template_description']) ? trim($_POST['template_description']) : '';
    $template_content = isset($_POST['template_content']) ? trim($_POST['template_content']) : '';
    $difficulty = isset($_POST['difficulty']) ? trim($_POST['difficulty']) : 'medium';
    $target_focus = isset($_POST['target_focus']) ? trim($_POST['target_focus']) : '';
    
    if (!$template_name || !$template_category) {
        echo json_encode(['success' => false, 'message' => 'Template name and category are required']);
        return;
    }
    
    $sql = "INSERT INTO activity_templates (template_name, template_category, template_description, template_content, difficulty, target_focus, is_public)
            VALUES (?, ?, ?, ?, ?, ?, FALSE)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $template_name, $template_category, $template_description, $template_content, $difficulty, $target_focus);
    
    if ($stmt->execute()) {
        $template_id = $stmt->insert_id;
        echo json_encode(['success' => true, 'message' => 'Custom template created', 'template_id' => $template_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create template']);
    }
    $stmt->close();
}

function getDifficultyLevels() {
    $levels = ['easy', 'medium', 'hard', 'very_hard'];
    echo json_encode(['success' => true, 'levels' => $levels]);
}

function getCategories() {
    $categories = [
        'Social Skills',
        'Cognitive Development',
        'Communication',
        'Fine Motor',
        'Gross Motor',
        'Emotional Regulation',
        'Self-Help',
        'Life Skills',
        'Academic',
        'Behavioral Support',
        'Sensory Integration'
    ];
    echo json_encode(['success' => true, 'categories' => $categories]);
}
?>
