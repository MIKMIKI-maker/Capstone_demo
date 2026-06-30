<?php
require_once __DIR__ . '/db.php';

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
    tc.first_name,
    tc.last_name
FROM teacher_activities ta
LEFT JOIN teacher_accounts tc ON ta.teacher_id = tc.id
ORDER BY ta.created_at DESC
LIMIT 200";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $created_by = ($row['first_name'] && $row['last_name'])
            ? $row['first_name'] . ' ' . $row['last_name']
            : 'Unknown';

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
            'created_at' => $row['created_at']
        ];
    }
}

echo json_encode($activities);
$conn->close();
