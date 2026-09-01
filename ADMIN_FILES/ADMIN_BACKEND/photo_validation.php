<?php
/**
 * Validates a client-submitted profile photo.
 *
 * Returns the original data URI when it is genuinely a small base64-encoded
 * image, or null when it should be rejected. Rejecting anything that
 * doesn't match this strict pattern is what closes the stored-XSS hole —
 * the base64 alphabet has no quote/angle-bracket characters, so a value
 * that passes this check is safe to place inside an <img src="..."> later.
 */
function sanitizeProfilePhotoDataUri($input) {
    if ($input === '' || $input === null) {
        return '';
    }

    // 5 MB of base64 text is already a very generous cap for a profile photo.
    if (strlen($input) > 5 * 1024 * 1024) {
        return null;
    }

    if (!preg_match('/^data:image\/(png|jpe?g|gif|webp);base64,([A-Za-z0-9+\/]+={0,2})$/i', $input, $m)) {
        return null;
    }

    $bytes = base64_decode($m[2], true);
    if ($bytes === false || $bytes === '' || @getimagesizefromstring($bytes) === false) {
        return null;
    }

    return $input;
}
