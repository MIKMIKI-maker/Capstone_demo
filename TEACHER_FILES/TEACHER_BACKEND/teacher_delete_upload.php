<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$upload_id  = isset($_POST['upload_id'])  ? intval($_POST['upload_id'])  : 0;
$teacher_id = isset($_POST['teacher_id']) ? intval($_POST['teacher_id']) : 0;

if (!$upload_id || !$teacher_id) {
    echo json_encode(['success' => false, 'message' => 'Missing params']);
    exit;
}

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'DB error']);
    exit;
}

$stmt = $conn->prepare("SELECT file_name FROM teacher_uploaded_materials WHERE id = ? AND teacher_id = ?");
$stmt->bind_param("ii", $upload_id, $teacher_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Not found']);
    $conn->close();
    exit;
}

$del = $conn->prepare("DELETE FROM teacher_uploaded_materials WHERE id = ? AND teacher_id = ?");
$del->bind_param("ii", $upload_id, $teacher_id);
$del->execute();
$del->close();
$conn->close();

$filePath = __DIR__ . '/../uploads/materials/' . $row['file_name'];
if (file_exists($filePath)) {
    @unlink($filePath);
}

echo json_encode(['success' => true]);
?>
