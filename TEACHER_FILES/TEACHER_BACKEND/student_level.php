<?php
// A learner's level (1–3) for the "per student" randomizer, worked out from
// their scores — nothing for the teacher to set, and it moves up (or down)
// as the learner does.
//
//   average of the last LEVEL_RECENT scored activities and graded materials
//   below 60%  → Level 1 · Madali
//   60–84%     → Level 2 · Katamtaman
//   85% and up → Level 3 · Mahirap
//   no scores yet → Level 1 (start easy)
//
// A finalized activity counts with the teacher's final score.

const LEVEL_RECENT = 5;

function computeStudentLevel($conn, $student_id) {
    $info = studentLevelInfo($conn, $student_id);
    return $info['level'];
}

// ['level' => 1..3, 'average' => int|null, 'based_on' => n]
function studentLevelInfo($conn, $student_id) {
    $n = LEVEL_RECENT;
    $scores = [];
    $q = $conn->prepare("
        SELECT score, at FROM (
            SELECT CASE WHEN is_finalized = 1 AND finalized_score IS NOT NULL THEN finalized_score ELSE score END AS score,
                   submitted_at AS at
            FROM activity_submissions WHERE student_id = ? AND score IS NOT NULL
            UNION ALL
            SELECT final_score AS score, graded_at AS at
            FROM teacher_uploaded_materials WHERE student_id = ? AND is_graded = 1 AND final_score IS NOT NULL
        ) recent
        ORDER BY at DESC LIMIT $n");
    if ($q) {
        $q->bind_param("ii", $student_id, $student_id);
        if ($q->execute()) {
            $res = $q->get_result();
            while ($r = $res->fetch_assoc()) $scores[] = max(0, min(100, (int)$r['score']));
        }
        $q->close();
    }
    if (!$scores) return ['level' => 1, 'average' => null, 'based_on' => 0];
    $avg = (int)round(array_sum($scores) / count($scores));
    return ['level' => levelForAverage($avg), 'average' => $avg, 'based_on' => count($scores)];
}

function levelForAverage($avg) {
    if ($avg >= 85) return 3;
    if ($avg >= 60) return 2;
    return 1;
}

// What an activity is set to: '1' | '2' | '3' | 'auto' (per student), or ''.
function normalizeActivityLevel($v) {
    $v = strtolower(trim((string)$v));
    return in_array($v, ['1', '2', '3', 'auto'], true) ? $v : '';
}

// The level the template saved in its content_json (state.level).
function activityLevelFromContent($contentJson) {
    if (!is_string($contentJson) || $contentJson === '') return '';
    $c = json_decode($contentJson, true);
    return is_array($c) ? normalizeActivityLevel($c['level'] ?? '') : '';
}
