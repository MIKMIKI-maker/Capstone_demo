<?php
/**
 * Additional Alternative Learning Material (Video Learning / Audio Learning).
 *
 * Before a teacher can make a Video or Audio activity for a learner, the
 * teacher answers a short Learning Modality Assessment about that learner
 * (once per School Year, can be retaken). It tells which of the two fits the
 * learner and keeps out the one the learner can't use (a learner with a
 * hearing impairment doesn't get Audio Learning, etc.).
 *
 * Answers: each rating question is 0 = Not yet, 1 = Sometimes, 2 = Often;
 * the two screening questions are 0 = No, 1 = Yes.
 */

const ALT_RATING_QUESTIONS = [
    'v1' => 'visual', 'v2' => 'visual', 'v3' => 'visual', 'v4' => 'visual',
    'a1' => 'auditory', 'a2' => 'auditory', 'a3' => 'auditory', 'a4' => 'auditory',
    'r1' => 'readiness', 'r2' => 'readiness',
];
const ALT_SCREEN_QUESTIONS = ['hearing', 'vision'];
// Activity types that need the assessment, and the part they unlock.
const ALT_MATERIAL_TYPES = ['VideoQuiz' => 'video', 'AudioQuiz' => 'audio'];

function ensureAltMaterialTable(mysqli $conn): void {
    static $ready = false;
    if ($ready) return;
    $conn->query("CREATE TABLE IF NOT EXISTS alt_material_assessments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_year_id INT NOT NULL,
        student_id INT NOT NULL,
        answers_json TEXT NOT NULL,
        visual_pct TINYINT UNSIGNED NOT NULL DEFAULT 0,
        auditory_pct TINYINT UNSIGNED NOT NULL DEFAULT 0,
        readiness_pct TINYINT UNSIGNED NOT NULL DEFAULT 0,
        video_allowed TINYINT(1) NOT NULL DEFAULT 1,
        audio_allowed TINYINT(1) NOT NULL DEFAULT 1,
        recommended VARCHAR(10) NOT NULL DEFAULT 'both',
        notes VARCHAR(500) NULL,
        teacher_id INT NOT NULL,
        assessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_alt_assessment (school_year_id, student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $ready = true;
}

/** Scores the answers. Returns null when an answer is missing or invalid. */
function scoreAltAssessment(array $answers): ?array {
    $sum = ['visual' => 0, 'auditory' => 0, 'readiness' => 0];
    $max = ['visual' => 0, 'auditory' => 0, 'readiness' => 0];
    $clean = [];
    foreach (ALT_RATING_QUESTIONS as $key => $part) {
        if (!isset($answers[$key]) || !in_array((string)$answers[$key], ['0', '1', '2'], true)) return null;
        $clean[$key] = (int)$answers[$key];
        $sum[$part] += $clean[$key];
        $max[$part] += 2;
    }
    foreach (ALT_SCREEN_QUESTIONS as $key) {
        if (!isset($answers[$key]) || !in_array((string)$answers[$key], ['0', '1'], true)) return null;
        $clean[$key] = (int)$answers[$key];
    }
    $pct = [];
    foreach ($sum as $part => $s) $pct[$part] = (int)round($s * 100 / $max[$part]);

    $video = $clean['vision'] === 0;
    $audio = $clean['hearing'] === 0;
    if ($video && $audio) {
        // Within one answer of each other → both fit.
        $diff = $pct['visual'] - $pct['auditory'];
        $recommended = abs($diff) <= 13 ? 'both' : ($diff > 0 ? 'video' : 'audio');
    } else {
        $recommended = $video ? 'video' : ($audio ? 'audio' : 'none');
    }
    return [
        'answers' => $clean,
        'visual_pct' => $pct['visual'],
        'auditory_pct' => $pct['auditory'],
        'readiness_pct' => $pct['readiness'],
        'video_allowed' => $video,
        'audio_allowed' => $audio,
        'recommended' => $recommended,
    ];
}

/** Assessments of these students (students.id) in a S.Y., keyed by student id. */
function altMaterialAssessments(mysqli $conn, int $sy, array $ids): array {
    ensureAltMaterialTable($conn);
    $ids = array_values(array_filter(array_map('intval', $ids), function ($v) { return $v > 0; }));
    if (!$ids) return [];
    $in = implode(',', $ids);
    $out = [];
    $res = $conn->query("SELECT * FROM alt_material_assessments WHERE school_year_id = $sy AND student_id IN ($in)");
    while ($res && ($r = $res->fetch_assoc())) {
        $out[(int)$r['student_id']] = [
            'answers' => json_decode($r['answers_json'], true) ?: [],
            'visual_pct' => (int)$r['visual_pct'],
            'auditory_pct' => (int)$r['auditory_pct'],
            'readiness_pct' => (int)$r['readiness_pct'],
            'video_allowed' => (bool)$r['video_allowed'],
            'audio_allowed' => (bool)$r['audio_allowed'],
            'recommended' => $r['recommended'],
            'notes' => (string)($r['notes'] ?? ''),
            'assessed_at' => $r['assessed_at'],
        ];
    }
    return $out;
}

/**
 * Error message when a Video/Audio activity can't be made for these
 * students yet (not assessed, or that part isn't for them); '' when it can.
 */
function altMaterialBlockReason(mysqli $conn, int $sy, string $activityType, array $studentIds): string {
    if (!isset(ALT_MATERIAL_TYPES[$activityType])) return '';
    $part = ALT_MATERIAL_TYPES[$activityType];
    $label = $part === 'video' ? 'Video Learning' : 'Audio Learning';
    $ids = array_values(array_filter(array_map('intval', $studentIds), function ($v) { return $v > 0; }));
    if (!$ids) {
        return "$label is an Additional Alternative Learning Material — choose the learner(s) and complete their assessment first.";
    }
    $done = altMaterialAssessments($conn, $sy, $ids);
    $missing = array_diff($ids, array_keys($done));
    if ($missing) {
        return "Complete the Learning Modality Assessment of every chosen learner before making $label ("
            . count($missing) . ' learner(s) not assessed yet).';
    }
    foreach ($done as $a) {
        if (!$a[$part . '_allowed']) {
            return "$label is not suited to one of the chosen learners, based on their assessment.";
        }
    }
    return '';
}
