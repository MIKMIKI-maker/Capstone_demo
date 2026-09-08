<?php
require_once __DIR__ . '/db.php';
requireAdminSession();

header('Content-Type: application/json');

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode([]);
    exit;
}

$activities = [];

$sql = "SELECT
    ta.id,
    ta.activity_title AS title,
    ta.activity_description AS description,
    ta.activity_type,
    ta.subject,
    ta.grade_level,
    ta.difficulty,
    ta.status,
    ta.created_at,
    ta.deadline,
    tc.first_name,
    tc.last_name,
    tc.status AS teacher_status,
    aa.is_deleted
FROM teacher_activities ta
LEFT JOIN teacher_accounts tc ON ta.teacher_id = tc.id
LEFT JOIN admin_accounts aa ON aa.admin_email = tc.teacher_email
ORDER BY ta.created_at DESC";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $created_by = ($row['first_name'] && $row['last_name'])
            ? $row['first_name'] . ' ' . $row['last_name']
            : 'Unknown';

        // A teacher whose account was deactivated or permanently deleted still
        // keeps their real activities listed here (see the join above), but
        // the admin needs a visible cue that this creator isn't a current,
        // active account - otherwise their name can look like it belongs to
        // whatever unrelated current user happens to share it.
        $creatorInactive = !(
            ($row['teacher_status'] === null || $row['teacher_status'] === 'active')
            && empty($row['is_deleted'])
        );

        $activities[] = [
            'id'         => $row['id'],
            'title'      => $row['title'],
            'description'=> $row['description'],
            'category'   => $row['subject'] ?: 'General',
            'type'       => $row['activity_type'] ?: 'Generated',
            'focus'      => $row['grade_level'] ?: 'General',
            'difficulty' => $row['difficulty'] ?: 'medium',
            'status'     => ucfirst($row['status'] ?: 'draft'),
            'creator'    => $created_by,
            'created_by' => $created_by,
            'creator_inactive' => $creatorInactive,
            'created_at' => $row['created_at'],
            'deadline'   => $row['deadline']
        ];
    }
}

echo json_encode($activities);
$conn->close();
