<?php
/**
 * AI Education App — Helper Functions
 */

/**
 * Escape a string for safe HTML output.
 */
function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Send a JSON response with the given status code and exit.
 */
function json_response(mixed $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Read JSON body from a request.
 */
function json_input(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Redirect to a URL and exit.
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Flash a message to session for one-time display.
 */
function flash(string $type, string $message): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'][$type][] = $message;
}

/**
 * Retrieve and clear flash messages of a given type.
 */
function get_flash(string $type): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $messages = $_SESSION['flash'][$type] ?? [];
    unset($_SESSION['flash'][$type]);
    return $messages;
}

/**
 * Simple rate-limiter check using session.
 * Returns true if the request is within limits.
 */
function rate_limit(string $key, int $maxRequests, int $windowSeconds): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $now = time();
    $bucket = $_SESSION['rate_limit'][$key] ?? [];
    // Remove expired entries
    $bucket = array_filter($bucket, fn($t) => $t > $now - $windowSeconds);
    if (count($bucket) >= $maxRequests) {
        return false;
    }
    $bucket[] = $now;
    $_SESSION['rate_limit'][$key] = $bucket;
    return true;
}

/**
 * Validate that required fields are present in an array.
 * Returns an array of missing field names.
 */
function validate_required(array $data, array $fields): array
{
    $missing = [];
    foreach ($fields as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            $missing[] = $field;
        }
    }
    return $missing;
}
