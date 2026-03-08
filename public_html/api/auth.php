<?php
/**
 * AI Education App — Auth API
 *
 * Handles login, register, logout, and current-user info.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';

init_session();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['error' => 'POST required'], 405);
        }
        csrf_validate();
        $data = $_POST ?: json_input();
        $user = attempt_login($data['email'] ?? '', $data['password'] ?? '');
        if ($user) {
            unset($user['password_hash']);
            json_response(['user' => $user]);
        }
        json_response(['error' => 'Invalid credentials'], 401);
        break;

    case 'register':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['error' => 'POST required'], 405);
        }
        csrf_validate();
        $data = $_POST ?: json_input();
        $missing = validate_required($data, ['name', 'email', 'password']);
        if ($missing) {
            json_response(['error' => 'Missing fields: ' . implode(', ', $missing)], 422);
        }
        $role = in_array($data['role'] ?? 'student', ['student', 'teacher'], true) ? $data['role'] : 'student';
        try {
            $id = register_user($data['name'], $data['email'], $data['password'], $role);
            attempt_login($data['email'], $data['password']);
            json_response(['id' => $id], 201);
        } catch (PDOException $e) {
            if ((int)$e->getCode() === 23000) {
                json_response(['error' => 'Email already registered'], 409);
            }
            json_response(['error' => 'Registration failed'], 500);
        }
        break;

    case 'logout':
        logout();
        if (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            json_response(['ok' => true]);
        }
        header('Location: /login.php');
        exit;

    case 'me':
        $user = current_user();
        if ($user) {
            unset($user['password_hash']);
            json_response(['user' => $user]);
        }
        json_response(['error' => 'Not authenticated'], 401);
        break;

    default:
        json_response(['error' => 'Unknown action'], 400);
}
