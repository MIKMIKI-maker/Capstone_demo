<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/teacher_auth.php';

header('Content-Type: application/json');

$conn = getTeacherDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'GET'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';
$teacher_id = requireTeacherId();

switch($action) {
    case 'generate_report':
        generateReport($conn, $teacher_id);
        break;
    case 'get_student_report':
        getStudentReport($conn, $teacher_id);
        break;
    case 'get_progress_report':
        getProgressReport($conn, $teacher_id);
        break;
    case 'save_report':
        saveReport($conn, $teacher_id);
        break;
    case 'list_reports':
        listReports($conn, $teacher_id);
        break;
    case 'get_overview':
        getOverview($conn, $teacher_id);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}

$conn->close();

function generateReport($conn, $teacher_id) {
    $report_type = isset($_POST['report_type']) ? trim($_POST['report_type']) : 'summary';
    $start_date = isset($_POST['start_date']) ? trim($_POST['start_date']) : date('Y-m-01');
    $end_date = isset($_POST['end_date']) ? trim($_POST['end_date']) : date('Y-m-d');
    
    // Get data for report
    $sql = "SELECT 
            s.student_name,
            COUNT(ar.id) as assessments_count,
            ROUND(AVG(ar.score), 2) as avg_score,
            MAX(ar.score) as highest_score,
            MIN(ar.score) as lowest_score,
            COUNT(DISTINCT ar.activity_id) as activities_count
            FROM students s
            LEFT JOIN learner_progress ar ON s.id = ar.student_id AND ar.assessment_date BETWEEN ? AND ?
            WHERE s.teacher_id=? AND s.status='active'
            GROUP BY s.id
            ORDER BY s.student_name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $start_date, $end_date, $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $report_data = [];
    $total_students = 0;
    $avg_score_all = 0;
    
    while ($row = $result->fetch_assoc()) {
        $report_data[] = $row;
        $total_students++;
        $avg_score_all += $row['avg_score'];
    }
    
    $avg_score_all = $total_students > 0 ? round($avg_score_all / $total_students, 2) : 0;
    
    echo json_encode([
        'success' => true,
        'report_type' => $report_type,
        'period' => ['start' => $start_date, 'end' => $end_date],
        'total_students' => $total_students,
        'overall_average' => $avg_score_all,
        'data' => $report_data
    ]);
    $stmt->close();
}

function getStudentReport($conn, $teacher_id) {
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
        return;
    }
    
    // Get student info
    $studentSQL = "SELECT * FROM students WHERE id=? AND teacher_id=?";
    $studentStmt = $conn->prepare($studentSQL);
    $studentStmt->bind_param("ii", $student_id, $teacher_id);
    $studentStmt->execute();
    $studentResult = $studentStmt->get_result();
    $student = $studentResult->fetch_assoc();
    $studentStmt->close();
    
    // Get assessments
    $assessmentSQL = "SELECT ar.*, ta.activity_title FROM learner_progress ar
                      LEFT JOIN teacher_activities ta ON ar.activity_id = ta.id
                      WHERE ar.student_id=? AND ar.teacher_id=?
                      ORDER BY ar.assessment_date DESC";
    $assessmentStmt = $conn->prepare($assessmentSQL);
    $assessmentStmt->bind_param("ii", $student_id, $teacher_id);
    $assessmentStmt->execute();
    $assessmentResult = $assessmentStmt->get_result();
    
    $assessments = [];
    while ($row = $assessmentResult->fetch_assoc()) {
        $assessments[] = $row;
    }
    $assessmentStmt->close();
    
    // Get IEP info
    $iepSQL = "SELECT * FROM iep_materials WHERE student_id=? AND teacher_id=?";
    $iepStmt = $conn->prepare($iepSQL);
    $iepStmt->bind_param("ii", $student_id, $teacher_id);
    $iepStmt->execute();
    $iepResult = $iepStmt->get_result();
    $iep = $iepResult->fetch_assoc();
    $iepStmt->close();
    
    echo json_encode([
        'success' => true,
        'student' => $student,
        'assessments' => $assessments,
        'iep' => $iep
    ]);
}

function getProgressReport($conn, $teacher_id) {
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    $months = isset($_POST['months']) ? intval($_POST['months']) : 3;
    
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
        return;
    }
    
    // Get progress over time
    $sql = "SELECT 
            DATE_FORMAT(ar.assessment_date, '%Y-%m') as month,
            COUNT(*) as assessments_count,
            ROUND(AVG(ar.score), 2) as avg_score,
            MAX(ar.score) as highest,
            MIN(ar.score) as lowest
            FROM learner_progress ar
            WHERE ar.student_id=? AND ar.teacher_id=? AND ar.assessment_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
            GROUP BY DATE_FORMAT(ar.assessment_date, '%Y-%m')
            ORDER BY month DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $student_id, $teacher_id, $months);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $progress = [];
    while ($row = $result->fetch_assoc()) {
        $progress[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'period_months' => $months,
        'progress' => $progress
    ]);
    $stmt->close();
}

function saveReport($conn, $teacher_id) {
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    $report_title = isset($_POST['report_title']) ? trim($_POST['report_title']) : '';
    $report_content = isset($_POST['report_content']) ? trim($_POST['report_content']) : '';
    $report_type = isset($_POST['report_type']) ? trim($_POST['report_type']) : 'assessment';
    
    if (!$student_id || !$report_title) {
        echo json_encode(['success' => false, 'message' => 'Student ID and report title are required']);
        return;
    }
    
    $report_date = date('Y-m-d');
    
    $sql = "INSERT INTO teacher_reports (teacher_id, student_id, report_title, report_content, report_date, report_type)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iissss", $teacher_id, $student_id, $report_title, $report_content, $report_date, $report_type);
    
    if ($stmt->execute()) {
        $report_id = $stmt->insert_id;
        echo json_encode(['success' => true, 'message' => 'Report saved successfully', 'report_id' => $report_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save report']);
    }
    $stmt->close();
}

function listReports($conn, $teacher_id) {
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    
    $sql = "SELECT tr.*, s.student_name FROM teacher_reports tr
            LEFT JOIN students s ON tr.student_id = s.id
            WHERE tr.teacher_id=?";
    $params = [$teacher_id];
    $types = 'i';
    
    if ($student_id > 0) {
        $sql .= " AND tr.student_id=?";
        $params[] = $student_id;
        $types .= 'i';
    }
    
    $sql .= " ORDER BY tr.report_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reports = [];
    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }
    
    echo json_encode(['success' => true, 'reports' => $reports, 'count' => count($reports)]);
    $stmt->close();
}

function getOverview($conn, $teacher_id) {
    // Learner count
    $s = $conn->prepare("SELECT COUNT(*) as c FROM students WHERE teacher_id=? AND status='active'");
    $s->bind_param("i", $teacher_id); $s->execute();
    $total_learners = (int)($s->get_result()->fetch_assoc()['c'] ?? 0); $s->close();

    // Activity count
    $s = $conn->prepare("SELECT COUNT(*) as c FROM teacher_activities WHERE teacher_id=?");
    $s->bind_param("i", $teacher_id); $s->execute();
    $total_activities = (int)($s->get_result()->fetch_assoc()['c'] ?? 0); $s->close();

    // Overall average score
    $s = $conn->prepare("SELECT ROUND(AVG(score),0) as avg FROM learner_progress WHERE teacher_id=?");
    $s->bind_param("i", $teacher_id); $s->execute();
    $avg_progress = (int)($s->get_result()->fetch_assoc()['avg'] ?? 0); $s->close();

    // Skills overview: average score per subject category
    $skills = ['Cognitive' => 0, 'Communication' => 0, 'Fine Motor' => 0, 'Life Skills' => 0];
    $s = $conn->prepare("SELECT COALESCE(ta.subject,'Other') as subj, ROUND(AVG(lp.score),0) as avg
                         FROM learner_progress lp
                         LEFT JOIN teacher_activities ta ON lp.activity_id = ta.id
                         WHERE lp.teacher_id=?
                         GROUP BY ta.subject");
    $s->bind_param("i", $teacher_id); $s->execute();
    $r = $s->get_result(); $s->close();
    $skillMap = ['cognitive'=>'Cognitive','communication'=>'Communication','motor'=>'Fine Motor','life'=>'Life Skills','self'=>'Life Skills'];
    while ($row = $r->fetch_assoc()) {
        $subj = strtolower($row['subj']);
        foreach ($skillMap as $key => $label) {
            if (strpos($subj, $key) !== false) {
                $skills[$label] = intval($row['avg']);
                break;
            }
        }
    }

    // Per-learner performance
    $learners = [];
    $stmt = $conn->prepare("SELECT s.student_name, s.disability_type,
            COUNT(lp.id) as sessions, ROUND(AVG(lp.score),0) as avg_score
            FROM students s
            LEFT JOIN learner_progress lp ON s.id = lp.student_id AND lp.teacher_id=?
            WHERE s.teacher_id=? AND s.status='active'
            GROUP BY s.id ORDER BY s.student_name");
    $stmt->bind_param("ii", $teacher_id, $teacher_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $avg = intval($row['avg_score']);
        $status = $avg >= 75 ? 'Passing' : ($avg > 0 ? 'Needs Support' : 'No Data');
        $remark = $avg >= 90 ? 'Excellent' : ($avg >= 75 ? 'Good' : ($avg > 0 ? 'Needs Improvement' : '—'));
        $learners[] = [
            'name'       => $row['student_name'],
            'condition'  => $row['disability_type'] ?: '—',
            'avg_score'  => $avg ?: '—',
            'status'     => $status,
            'remark'     => $remark
        ];
    }
    $stmt->close();

    echo json_encode([
        'success'          => true,
        'total_learners'   => intval($total_learners),
        'total_activities' => intval($total_activities),
        'avg_progress'     => intval($avg_progress),
        'skills'           => $skills,
        'learners'         => $learners
    ]);
}
?>
