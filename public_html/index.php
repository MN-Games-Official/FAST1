<?php
/**
 * AI Education App — Landing / Entry Point
 */
require_once __DIR__ . '/includes/auth.php';

init_session();
$user = current_user();

if ($user) {
    header('Location: /dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h(APP_NAME) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen flex flex-col">

  <!-- Header -->
  <header class="bg-white border-b shadow-sm">
    <div class="max-w-6xl mx-auto flex items-center justify-between px-6 py-4">
      <h1 class="text-xl font-bold text-indigo-600"><?= h(APP_NAME) ?></h1>
      <nav class="space-x-4">
        <a href="/login.php" class="text-sm font-medium text-gray-600 hover:text-indigo-600">Sign In</a>
        <a href="/register.php" class="text-sm font-medium bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">Get Started</a>
      </nav>
    </div>
  </header>

  <!-- Hero -->
  <main class="flex-1 flex items-center justify-center px-6">
    <div class="max-w-2xl text-center">
      <h2 class="text-4xl font-extrabold mb-4">Learn to Write. Think. Succeed.</h2>
      <p class="text-lg text-gray-600 mb-8">
        An AI-powered academic writing workspace that helps students understand assignments,
        plan their work, and improve their writing — without doing the work for them.
      </p>
      <div class="space-x-4">
        <a href="/register.php" class="inline-block bg-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-700">Start Writing</a>
        <a href="/login.php" class="inline-block border border-gray-300 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100">Sign In</a>
      </div>
      <div class="mt-12 grid grid-cols-1 md:grid-cols-3 gap-6 text-left">
        <div class="bg-white p-6 rounded-xl shadow-sm border">
          <h3 class="font-bold text-indigo-600 mb-2">Understand Assignments</h3>
          <p class="text-sm text-gray-600">Get confusing instructions explained in simpler language with step-by-step breakdowns.</p>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-sm border">
          <h3 class="font-bold text-indigo-600 mb-2">Guided Writing Support</h3>
          <p class="text-sm text-gray-600">Brainstorm, outline, draft, and revise with AI coaching — not AI answers.</p>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-sm border">
          <h3 class="font-bold text-indigo-600 mb-2">School-Safe Design</h3>
          <p class="text-sm text-gray-600">Built-in anti-cheating detection, teacher oversight, and policy controls.</p>
        </div>
      </div>
    </div>
  </main>

  <!-- Footer -->
  <footer class="text-center text-xs text-gray-400 py-6">
    &copy; <?= date('Y') ?> <?= h(APP_NAME) ?>. All rights reserved.
  </footer>

</body>
</html>
