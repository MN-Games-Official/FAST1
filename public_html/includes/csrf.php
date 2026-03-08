<?php
/**
 * AI Education App — CSRF Protection
 */

/**
 * Generate or return existing CSRF token for the session.
 */
function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Return an HTML hidden input containing the CSRF token.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Validate CSRF token from the request. Aborts with 403 on mismatch.
 */
function csrf_validate(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token.']);
        exit;
    }
}
