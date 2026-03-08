<?php
/**
 * AI Education App — Registration Page
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';

init_session();

if (current_user()) {
    redirect('/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';
    $role     = $_POST['role'] ?? 'student';

    if ($name === '' || $email === '' || $password === '') {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!in_array($role, ['student', 'teacher'], true)) {
        $error = 'Invalid role selected.';
    } else {
        try {
            $userId = register_user($name, $email, $password, $role);
            // Auto-login
            attempt_login($email, $password);
            redirect('/dashboard.php');
        } catch (PDOException $e) {
            if ((int)$e->getCode() === 23000) {
                $error = 'An account with that email already exists.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Account — <?= h(APP_NAME) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center px-4">

  <div class="w-full max-w-md bg-white rounded-2xl shadow-lg p-8">
    <h1 class="text-2xl font-bold text-center text-indigo-600 mb-6"><?= h(APP_NAME) ?></h1>
    <h2 class="text-lg font-semibold text-center mb-4">Create Account</h2>

    <?php if ($error): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm">
        <?= h($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="/register.php">
      <?= csrf_field() ?>

      <div class="mb-4">
        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
        <input type="text" id="name" name="name" required
               value="<?= h($_POST['name'] ?? '') ?>"
               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
      </div>

      <div class="mb-4">
        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
        <input type="email" id="email" name="email" required
               value="<?= h($_POST['email'] ?? '') ?>"
               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
      </div>

      <div class="mb-4">
        <label for="role" class="block text-sm font-medium text-gray-700 mb-1">I am a…</label>
        <select id="role" name="role"
                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
          <option value="student" <?= ($_POST['role'] ?? '') === 'student' ? 'selected' : '' ?>>Student</option>
          <option value="teacher" <?= ($_POST['role'] ?? '') === 'teacher' ? 'selected' : '' ?>>Teacher</option>
        </select>
      </div>

      <div class="mb-4">
        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
        <input type="password" id="password" name="password" required minlength="8"
               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
      </div>

      <div class="mb-6">
        <label for="password_confirm" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
        <input type="password" id="password_confirm" name="password_confirm" required minlength="8"
               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
      </div>

      <button type="submit"
              class="w-full bg-indigo-600 text-white font-semibold py-2 rounded-lg hover:bg-indigo-700 transition">
        Create Account
      </button>
    </form>

    <p class="text-sm text-center text-gray-500 mt-6">
      Already have an account? <a href="/login.php" class="text-indigo-600 hover:underline">Sign in</a>
    </p>
  </div>

</body>
</html>
