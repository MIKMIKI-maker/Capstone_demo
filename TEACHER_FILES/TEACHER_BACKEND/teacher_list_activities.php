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
     * Every published row is shown — no dedup by title. That used to hide
     * whichever wasn't "the latest" per title, including a real, currently-
     * published activity whenever an accidental duplicate (e.g. a publish
     * click repeated because of a slow connection) or an edit-in-progress
     * happened to share its title. Editing no longer leaves a stale
     * published duplicate behind anyway — see setActivityLocked() in
     * teacher_activity_lock_helpers.php, which now flips a row to 'draft'
     * for the duration of the edit.
     * Drafts still dedup to the latest per title, so in-progress autosaves
     * of the same not-yet-published activity don't clutter the Drafts tab.
     */
    $stmt = $conn->prepare(
        "SELECT ta.id, ta.activity_title, ta.activity_description,
                ta.activity_type, ta.subject, ta.grade_level,
                ta.difficulty, ta.status, ta.created_at, ta.updated_at, ta.is_locked,
                (
                    SELECT GROUP_CONCAT(
                        CONCAT(s.student_name, '|', COALESCE(NULLIF(s.disability_type, ''), 'General'))
                        ORDER BY s.student_name SEPARATOR ', '
                    )
                    FROM activity_assignments aa
                    INNER JOIN students s ON s.id = aa.student_id
                    WHERE aa.activity_id = ta.id
                ) AS learner
         FROM teacher_activities ta
         WHERE ta.teacher_id = ?
           AND (
               ta.status = 'published'
               OR ta.id IN (
                   SELECT MAX(id) FROM teacher_activities
                   WHERE teacher_id = ? AND status = 'draft'
                   GROUP BY activity_title
               )
           )
         ORDER BY ta.updated_at DESC"
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
            'created_at'    => $row['updated_at'] ?: $row['created_at'],
            'is_locked'     => (int)$row['is_locked']
            ,'learner'      => $row['learner'] ?: 'ALL'
        ];
    }
    $stmt->close();
    echo json_encode($activities);
}

$conn->close();
?>
