<?php
/**
 * AI Education App — Authentication Helpers
 */

require_once __DIR__ . '/db.php';

/**
 * Start a secure session if one is not already active.
 */
function init_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => APP_ENV === 'production',
            'httponly'  => true,
            'samesite'  => 'Lax',
        ]);
        session_start();
    }
}

/**
 * Return the currently authenticated user row or null.
 */
function current_user(): ?array
{
    init_session();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

/**
 * Require authentication. Redirects to login if not signed in.
 */
function require_auth(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: /login.php');
        exit;
    }
    return $user;
}

/**
 * Require a specific role. Aborts with 403 if the user lacks the role.
 */
function require_role(string ...$roles): array
{
    $user = require_auth();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        echo 'Access denied.';
        exit;
    }
    return $user;
}

/**
 * Attempt to log in with email and password.
 * Returns the user row on success, null on failure.
 */
function attempt_login(string $email, string $password): ?array
{
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        init_session();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        return $user;
    }
    return null;
}

/**
 * Register a new user. Returns the new user id or throws on failure.
 */
function register_user(string $name, string $email, string $password, string $role = 'student', ?int $school_id = null): int
{
    $db = get_db();
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $db->prepare(
        'INSERT INTO users (name, email, password_hash, role, school_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([$name, $email, $hash, $role, $school_id]);
    return (int) $db->lastInsertId();
}

/**
 * Log the current user out.
 */
function logout(): void
{
    init_session();
    $_SESSION = [];
    session_destroy();
}
