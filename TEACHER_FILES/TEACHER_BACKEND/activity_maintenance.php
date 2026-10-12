<?php
// One-time data tidy-ups for teacher_activities, run from the migration
// block in db.php. Each one records itself in schema_meta so it never runs
// twice on the same database.

// Activities published before the templates were fixed saved the full-size
// html2canvas PNG of the stage (often 1–3 MB) inside content_json as
// "thumb", on top of the small JPEG kept in the `thumbnail` column — so
// every time a student opened the activity it downloaded megabytes for a
// picture nothing shows. Swap that PNG for the small copy.
//
// Only the "thumb" value is replaced, by string position — the rest of the
// content stays byte-for-byte as the template saved it (a json_decode +
// json_encode round trip would turn {} into [] and could break a template).
function shrinkActivityThumbs($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS schema_meta (component VARCHAR(50) PRIMARY KEY, version INT NOT NULL)");
    $meta = $conn->query("SELECT version FROM schema_meta WHERE component = 'activity_thumbs'");
    $metaRow = $meta ? $meta->fetch_assoc() : null;
    if ($metaRow && (int)$metaRow['version'] >= 1) return 0;

    $ids = [];
    $r = $conn->query("SELECT id FROM teacher_activities WHERE content_json LIKE '%\"thumb\":\"data:image/png;base64,%'");
    if ($r) while ($row = $r->fetch_assoc()) $ids[] = (int)$row['id'];

    $fixed = 0;
    $get = $conn->prepare("SELECT content_json, thumbnail FROM teacher_activities WHERE id = ?");
    $put = $conn->prepare("UPDATE teacher_activities SET content_json = ? WHERE id = ?");
    if (!$get || !$put) return 0;
    foreach ($ids as $id) {
        $get->bind_param("i", $id);
        $get->execute();
        $row = $get->get_result()->fetch_assoc();
        if (!$row) continue;
        $cj = (string)$row['content_json'];
        $decoded = json_decode($cj, true);
        $big = is_array($decoded) ? ($decoded['thumb'] ?? null) : null;
        if (!is_string($big) || strlen($big) < 100000 || strpos($big, 'data:image/png;base64,') !== 0) continue;

        $key = '"thumb":"' . $big . '"';          // as JSON.stringify wrote it (base64 needs no escaping)
        $pos = strpos($cj, $key);
        if ($pos === false) continue;             // written some other way — leave it alone

        $small = (is_string($row['thumbnail']) && strpos($row['thumbnail'], 'data:image/') === 0 && strlen($row['thumbnail']) < 200000)
            ? $row['thumbnail'] : null;
        $new = substr_replace($cj, '"thumb":' . json_encode($small, JSON_UNESCAPED_SLASHES), $pos, strlen($key));
        if (!is_array(json_decode($new, true))) continue;   // never save something that no longer parses

        $put->bind_param("si", $new, $id);
        if ($put->execute()) $fixed++;
    }
    $get->close();
    $put->close();

    $conn->query("INSERT INTO schema_meta (component, version) VALUES ('activity_thumbs', 1) ON DUPLICATE KEY UPDATE version = GREATEST(version, 1)");
    return $fixed;
}
