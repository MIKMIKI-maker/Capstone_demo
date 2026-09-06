<?php
/**
 * CSRF protection for state-changing Admin endpoints. Pages that call one
 * of these fetch the token once via get_csrf_token.php and send it back as
 * csrf_token on every POST — a page a malicious site doesn't control can't
 * read (browsers' same-origin policy blocks that), so a forged cross-site
 * request has no way to include the right value.
 */

/** One token per session, generated on first use and reused after that. */
function csrf_get_token(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Call at the top of any state-changing endpoint, after requireAdminSession().
 * Exits with a 403 JSON response if the token is missing or doesn't match.
 */
function csrf_require_valid_token(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $submitted = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';
    if ($expected === '' || $submitted === '' || !hash_equals($expected, $submitted)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Invalid or missing security token. Please refresh the page and try again.']);
        exit;
    }
}
