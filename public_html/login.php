<?php
/**
 * AI Education App — Login Page
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';

init_session();

// Already logged in?
if (current_user()) {
    redirect('/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $user = attempt_login($email, $password);
        if ($user) {
            redirect('/dashboard.php');
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — <?= h(APP_NAME) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center px-4">

  <div class="w-full max-w-md bg-white rounded-2xl shadow-lg p-8">
    <h1 class="text-2xl font-bold text-center text-indigo-600 mb-6"><?= h(APP_NAME) ?></h1>
    <h2 class="text-lg font-semibold text-center mb-4">Sign In</h2>

    <?php if ($error): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm">
        <?= h($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="/login.php">
      <?= csrf_field() ?>
      <div class="mb-4">
        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
        <input type="email" id="email" name="email" required
               value="<?= h($_POST['email'] ?? '') ?>"
               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
      </div>
      <div class="mb-6">
        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
        <input type="password" id="password" name="password" required
               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
      </div>
      <button type="submit"
              class="w-full bg-indigo-600 text-white font-semibold py-2 rounded-lg hover:bg-indigo-700 transition">
        Sign In
      </button>
    </form>

    <p class="text-sm text-center text-gray-500 mt-6">
      Don't have an account? <a href="/register.php" class="text-indigo-600 hover:underline">Create one</a>
    </p>
  </div>

</body>
</html>
