<?php
/**
 * Backs the School Year dropdown on Teacher pages (and the Active S.Y.
 * badge in the student portal).
 * GET  → every S.Y., which one is Active, and which one this user is viewing.
 * POST → view_id: switch the S.Y. this user is viewing (stored in the session).
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/school_year.php';

header('Content-Type: application/json');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$role = strtolower(trim((string)($_SESSION['admin_role'] ?? '')));
if (!in_array($role, ['admin', 'teacher', 'student'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Students only ever work in the Active S.Y. — they get just that one, to
// show which School Year they're in.
if ($role === 'student') {
    $activeId = getActiveSchoolYearId($conn);
    $active = $conn->query("SELECT id, name, start_date, end_date FROM school_years WHERE id = $activeId")->fetch_assoc();
    $conn->close();
    echo json_encode([
        'success'    => true,
        'years'      => [['id' => (int)$active['id'], 'name' => $active['name'], 'start_date' => $active['start_date'], 'end_date' => $active['end_date'], 'is_active' => true]],
        'active_id'  => $activeId,
        'viewing_id' => $activeId,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $viewId = (int)($_POST['view_id'] ?? 0);
    $stmt = $conn->prepare("SELECT id FROM school_years WHERE id = ?");
    $stmt->bind_param("i", $viewId);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$found) {
        echo json_encode(['success' => false, 'message' => 'School Year not found']);
        exit;
    }
    // Choosing the Active S.Y. means "follow whichever S.Y. is Active", so a
    // later change of Active S.Y. by the Admin is picked up automatically.
    if ($viewId === getActiveSchoolYearId($conn)) {
        unset($_SESSION['sy_view_id']);
    } else {
        $_SESSION['sy_view_id'] = $viewId;
    }
}

$activeId = getActiveSchoolYearId($conn);
$viewingId = getViewedSchoolYearId($conn);

$years = [];
$res = $conn->query("SELECT id, name, start_date, end_date, is_active FROM school_years ORDER BY start_date DESC");
while ($row = $res->fetch_assoc()) {
    $years[] = [
        'id'         => (int)$row['id'],
        'name'       => $row['name'],
        'start_date' => $row['start_date'],
        'end_date'   => $row['end_date'],
        'is_active'  => (bool)$row['is_active'],
    ];
}
$conn->close();

echo json_encode([
    'success'    => true,
    'years'      => $years,
    'active_id'  => $activeId,
    'viewing_id' => $viewingId,
]);
