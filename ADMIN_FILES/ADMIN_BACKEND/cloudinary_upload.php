<?php
require_once __DIR__ . '/cloudinary_credentials.php';

/**
 * Signed upload to Cloudinary via their REST API (no SDK/Composer needed).
 *
 * $fileParam is either a "data:...;base64,..." string or a CURLFile
 * instance (for streaming an actual uploaded temp file without loading
 * it fully into memory). Returns the HTTPS delivery URL, or null on
 * any failure (missing credentials, network error, rejected upload).
 */
function cloudinaryUpload($fileParam, $resourceType = 'image', $folder = null) {
    if (!defined('CLOUDINARY_API_KEY') || CLOUDINARY_API_KEY === '') {
        return null;
    }

    $timestamp = time();
    $signParams = ['timestamp' => $timestamp];
    if ($folder) {
        $signParams['folder'] = $folder;
    }
    ksort($signParams);

    $toSign = '';
    foreach ($signParams as $key => $value) {
        $toSign .= ($toSign === '' ? '' : '&') . $key . '=' . $value;
    }
    $signature = sha1($toSign . CLOUDINARY_API_SECRET);

    $postFields = [
        'file'      => $fileParam,
        'api_key'   => CLOUDINARY_API_KEY,
        'timestamp' => $timestamp,
        'signature' => $signature,
    ];
    if ($folder) {
        $postFields['folder'] = $folder;
    }

    $url = 'https://api.cloudinary.com/v1_1/' . CLOUDINARY_CLOUD_NAME . '/' . $resourceType . '/upload';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Materials can be up to 20MB — generous timeout so a slow connection
    // doesn't abort a large file mid-upload.
    curl_setopt($ch, CURLOPT_TIMEOUT, 180);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        error_log('Cloudinary upload failed (' . $httpCode . '): ' . ($curlErr ?: $response));
        return null;
    }

    $data = json_decode($response, true);
    return $data['secure_url'] ?? null;
}

/**
 * Deletes a previously uploaded Cloudinary asset given its delivery URL,
 * so replacing/removing a photo/material doesn't leave the old one behind
 * eating into the free-tier storage quota. Safe to call with a
 * non-Cloudinary value (e.g. empty string, or a leftover pre-migration
 * value) — it just returns false without making any request.
 *
 * $resourceType is auto-detected from the URL ("image"/"video"/"raw" —
 * Cloudinary encodes it right in the path) when not given explicitly.
 */
function cloudinaryDeleteByUrl($url, $resourceType = null) {
    if (!defined('CLOUDINARY_API_KEY') || CLOUDINARY_API_KEY === '') {
        return false;
    }
    if (!$url || strpos($url, 'res.cloudinary.com') === false) {
        return false;
    }
    if (!preg_match('#res\.cloudinary\.com/[^/]+/(image|video|raw)/upload/(?:v\d+/)?(.+)$#', $url, $m)) {
        return false;
    }
    if ($resourceType === null) {
        $resourceType = $m[1];
    }
    $publicId = $m[2];
    // Cloudinary keeps the extension in the public_id for "raw" assets
    // (documents, PPTs, etc.) but strips it for image/video assets.
    if ($resourceType !== 'raw') {
        $publicId = preg_replace('/\.[A-Za-z0-9]+(?:\?.*)?$/', '', $publicId);
    } else {
        $publicId = preg_replace('/\?.*$/', '', $publicId);
    }

    $timestamp = time();
    $signParams = ['public_id' => $publicId, 'timestamp' => $timestamp];
    ksort($signParams);
    $toSign = '';
    foreach ($signParams as $key => $value) {
        $toSign .= ($toSign === '' ? '' : '&') . $key . '=' . $value;
    }
    $signature = sha1($toSign . CLOUDINARY_API_SECRET);

    $postFields = [
        'public_id' => $publicId,
        'api_key'   => CLOUDINARY_API_KEY,
        'timestamp' => $timestamp,
        'signature' => $signature,
    ];

    $apiUrl = 'https://api.cloudinary.com/v1_1/' . CLOUDINARY_CLOUD_NAME . '/' . $resourceType . '/destroy';
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        error_log('Cloudinary delete failed (' . $httpCode . '): ' . $response);
        return false;
    }
    $data = json_decode($response, true);
    return ($data['result'] ?? '') === 'ok';
}
