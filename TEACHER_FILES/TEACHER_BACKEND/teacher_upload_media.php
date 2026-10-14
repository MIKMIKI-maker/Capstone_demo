<?php
/* Video & Audio template (Media_Template.html): stores one question's video
 * or sound and returns where it lives. Cloudinary on the live site; on plain
 * XAMPP (no Cloudinary account) it goes to TEACHER_FILES/uploads/media/ and
 * the path returned is relative to the project root.
 *
 * POST: media (file), kind ('video' | 'audio')
 * → { success, url } */
require_once __DIR__ . '/teacher_auth.php';
require_once __DIR__ . '/../../ADMIN_FILES/ADMIN_BACKEND/cloudinary_upload.php';
header('Content-Type: application/json');

requireTeacherId();

$kind = (($_POST['kind'] ?? '') === 'audio') ? 'audio' : 'video';
if (!isset($_FILES['media']) || $_FILES['media']['error'] !== UPLOAD_ERR_OK) {
    $tooBig = isset($_FILES['media']) && in_array($_FILES['media']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
    echo json_encode(['success' => false, 'message' => $tooBig ? 'File too large (max 10 MB).' : 'No file received.']);
    exit;
}
// Cloudinary's free plan rejects anything over 10 MB; keep XAMPP the same.
if ($_FILES['media']['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File too large (max 10 MB). Use a shorter clip.']);
    exit;
}

// The real content decides the type, never the file name.
$allowed = $kind === 'audio'
    ? ['audio/mpeg' => 'mp3', 'audio/mp3' => 'mp3', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'audio/ogg' => 'ogg',
       'audio/webm' => 'weba', 'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'audio/aac' => 'aac', 'video/ogg' => 'ogg']
    : ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov'];
$mime = mime_content_type($_FILES['media']['tmp_name']);
if (!isset($allowed[$mime])) {
    echo json_encode(['success' => false, 'message' => $kind === 'audio'
        ? 'Please upload a sound file (MP3, WAV, OGG or M4A).'
        : 'Please upload an MP4 or WebM video.']);
    exit;
}

if (defined('CLOUDINARY_API_KEY') && CLOUDINARY_API_KEY !== '') {
    // Cloudinary keeps sounds under the "video" resource type too.
    $file = new CURLFile($_FILES['media']['tmp_name'], $mime, basename($_FILES['media']['name']));
    $url = cloudinaryUpload($file, 'video', 'media');
    if ($url === null) {
        echo json_encode(['success' => false, 'message' => 'Upload failed. Please try again.']);
        exit;
    }
    echo json_encode(['success' => true, 'url' => $url]);
    exit;
}

// Local fallback: random name + an extension from the verified type, in a
// folder that never runs scripts.
$dir = __DIR__ . '/../uploads/media';
if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
    echo json_encode(['success' => false, 'message' => 'Upload failed. Please try again.']);
    exit;
}
if (!file_exists($dir . '/.htaccess')) {
    file_put_contents($dir . '/.htaccess', "<FilesMatch \"\\.(php|php[3-8]?|phtml|pht)\$\">\n    Require all denied\n</FilesMatch>\nOptions -Indexes\nphp_flag engine off\n");
}
$name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
if (!move_uploaded_file($_FILES['media']['tmp_name'], $dir . '/' . $name)) {
    echo json_encode(['success' => false, 'message' => 'Upload failed. Please try again.']);
    exit;
}
echo json_encode(['success' => true, 'url' => 'TEACHER_FILES/uploads/media/' . $name]);
