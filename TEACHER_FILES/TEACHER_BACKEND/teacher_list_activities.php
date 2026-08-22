<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/teacher_auth.php';

header('Content-Type: application/json');

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode([]);
    exit;
}

$teacher_id = requireTeacherId();

$single_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($single_id) {
    $stmt = $conn->prepare(
        "SELECT id, activity_title, activity_description, subject, grade_level, difficulty,
                learning_materials, instructions, status, created_at
         FROM teacher_activities
         WHERE teacher_id = ? AND id = ?
         LIMIT 1"
    );
    $stmt->bind_param("ii", $teacher_id, $single_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $activity = null;
    if ($row = $result->fetch_assoc()) {
        $activity = [
            'id'                 => $row['id'],
            'title'              => $row['activity_title'],
            'description'        => $row['activity_description'],
            'subject'            => $row['subject'],
            'grade_level'        => $row['grade_level'],
            'difficulty'         => $row['difficulty'],
            'learning_materials' => $row['learning_materials'],
            'instructions'       => $row['instructions'],
            'status'             => $row['status'],
            'created_at'         => $row['created_at']
        ];
    }
    $stmt->close();
    echo json_encode($activity);
} else {
    /*
     * Deduplicated list: one row per activity_title.
     * For each title: latest published row wins; fallback to latest draft row.
     * This prevents duplicates caused by publish-creates-new-row behaviour.
     */
    $stmt = $conn->prepare(
        "SELECT ta.id, ta.activity_title, ta.activity_description,
                ta.activity_type, ta.subject, ta.grade_level,
                ta.difficulty, ta.status, ta.created_at
         FROM teacher_activities ta
         WHERE ta.teacher_id = ?
           AND ta.id IN (
               SELECT COALESCE(
                   MAX(CASE WHEN status = 'published' THEN id END),
                   MAX(CASE WHEN status = 'draft'     THEN id END)
               )
               FROM teacher_activities
               WHERE teacher_id = ?
               GROUP BY activity_title
           )
         ORDER BY ta.created_at DESC"
    );
    $stmt->bind_param("ii", $teacher_id, $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $activities = [];
    while ($row = $result->fetch_assoc()) {
        $activities[] = [
            'id'            => $row['id'],
            'title'         => $row['activity_title'],
            'description'   => $row['activity_description'],
            'activity_type' => $row['activity_type'],
            'subject'       => $row['subject'],
            'grade_level'   => $row['grade_level'],
            'difficulty'    => $row['difficulty'],
            'status'        => $row['status'],
            'created_at'    => $row['created_at']
        ];
    }
    $stmt->close();
    echo json_encode($activities);
}

$conn->close();
?>
