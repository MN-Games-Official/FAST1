<?php
/**
 * AI Education App — Admin Dashboard
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_role('admin');
$db   = get_db();

// Stats
$stats = [];
foreach (['users', 'schools', 'classes', 'documents', 'assignments', 'ai_events', 'policy_violations'] as $table) {
    $stmt = $db->query("SELECT COUNT(*) FROM `$table`");
    $stats[$table] = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — <?= h(APP_NAME) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="bg-gray-50 min-h-screen">

  <!-- Nav -->
  <nav class="bg-white border-b shadow-sm">
    <div class="max-w-7xl mx-auto flex items-center justify-between px-6 py-3">
      <div class="flex items-center space-x-4">
        <a href="/dashboard.php" class="text-lg font-bold text-indigo-600"><?= h(APP_NAME) ?></a>
        <span class="text-gray-300">|</span>
        <span class="text-sm font-semibold text-gray-600">Admin</span>
      </div>
      <div class="flex items-center space-x-4 text-sm">
        <a href="/admin/index.php" class="text-indigo-600 font-semibold">Dashboard</a>
        <a href="/admin/policies.php" class="text-gray-500 hover:text-indigo-600">Policies</a>
        <a href="/admin/analytics.php" class="text-gray-500 hover:text-indigo-600">Analytics</a>
        <a href="/api/auth.php?action=logout" class="text-red-500 hover:underline">Sign Out</a>
      </div>
    </div>
  </nav>

  <div class="max-w-7xl mx-auto px-6 py-8">
    <h1 class="text-2xl font-bold mb-6">Admin Dashboard</h1>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
      <?php
      $labels = [
          'users' => '👤 Users',
          'schools' => '🏫 Schools',
          'classes' => '📚 Classes',
          'documents' => '📄 Documents',
          'assignments' => '📝 Assignments',
          'ai_events' => '🤖 AI Events',
          'policy_violations' => '🚩 Violations',
      ];
      foreach ($stats as $key => $count): ?>
        <div class="bg-white border rounded-xl p-4">
          <div class="text-2xl font-bold text-indigo-600"><?= $count ?></div>
          <div class="text-sm text-gray-500"><?= $labels[$key] ?? $key ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Recent users -->
    <h2 class="text-lg font-bold mb-4">Recent Users</h2>
    <?php
    $stmt = $db->query('SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 20');
    $users = $stmt->fetchAll();
    ?>
    <div class="bg-white border rounded-xl overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-left text-xs text-gray-500 uppercase">
          <tr>
            <th class="px-4 py-3">Name</th>
            <th class="px-4 py-3">Email</th>
            <th class="px-4 py-3">Role</th>
            <th class="px-4 py-3">Joined</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <?php foreach ($users as $u): ?>
            <tr class="hover:bg-gray-50">
              <td class="px-4 py-3 font-medium"><?= h($u['name']) ?></td>
              <td class="px-4 py-3 text-gray-500"><?= h($u['email']) ?></td>
              <td class="px-4 py-3">
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold
                  <?= $u['role'] === 'admin' ? 'bg-purple-100 text-purple-700' : ($u['role'] === 'teacher' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700') ?>">
                  <?= h($u['role']) ?>
                </span>
              </td>
              <td class="px-4 py-3 text-gray-400 text-xs"><?= h($u['created_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</body>
</html>
