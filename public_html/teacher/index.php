<?php
/**
 * AI Education App — Teacher Dashboard
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_role('teacher', 'admin');
$db   = get_db();

// Fetch teacher's classes
$stmt = $db->prepare('SELECT * FROM classes WHERE teacher_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$classes = $stmt->fetchAll();

// Fetch recent violations
$stmt = $db->prepare(
    'SELECT pv.*, u.name AS student_name
     FROM policy_violations pv
     JOIN users u ON u.id = pv.user_id
     WHERE pv.resolution_status = "pending"
     ORDER BY pv.created_at DESC
     LIMIT 10'
);
$stmt->execute();
$recentFlags = $stmt->fetchAll();

// Count assignments
$stmt = $db->prepare('SELECT COUNT(*) FROM assignments WHERE teacher_id = ?');
$stmt->execute([$user['id']]);
$assignmentCount = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Teacher Dashboard — <?= h(APP_NAME) ?></title>
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
        <span class="text-sm font-semibold text-gray-600">Teacher Panel</span>
      </div>
      <div class="flex items-center space-x-4 text-sm">
        <a href="/teacher/index.php" class="text-indigo-600 font-semibold">Dashboard</a>
        <a href="/teacher/class.php" class="text-gray-500 hover:text-indigo-600">Classes</a>
        <a href="/teacher/assignment.php" class="text-gray-500 hover:text-indigo-600">Assignments</a>
        <a href="/teacher/flags.php" class="text-gray-500 hover:text-indigo-600">Flags</a>
        <a href="/api/auth.php?action=logout" class="text-red-500 hover:underline">Sign Out</a>
      </div>
    </div>
  </nav>

  <div class="max-w-7xl mx-auto px-6 py-8">

    <h1 class="text-2xl font-bold mb-6">Teacher Dashboard</h1>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
      <div class="bg-white border rounded-xl p-5">
        <div class="text-3xl font-bold text-indigo-600"><?= count($classes) ?></div>
        <div class="text-sm text-gray-500">Classes</div>
      </div>
      <div class="bg-white border rounded-xl p-5">
        <div class="text-3xl font-bold text-indigo-600"><?= $assignmentCount ?></div>
        <div class="text-sm text-gray-500">Assignments</div>
      </div>
      <div class="bg-white border rounded-xl p-5">
        <div class="text-3xl font-bold text-red-500"><?= count($recentFlags) ?></div>
        <div class="text-sm text-gray-500">Pending Flags</div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

      <!-- Classes -->
      <div>
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-lg font-bold">Your Classes</h2>
          <a href="/teacher/class.php" class="text-sm text-indigo-600 hover:underline">Manage →</a>
        </div>
        <?php if (empty($classes)): ?>
          <div class="bg-white border rounded-xl p-6 text-center text-gray-400">
            No classes yet. <a href="/teacher/class.php" class="text-indigo-600 hover:underline">Create one</a>
          </div>
        <?php else: ?>
          <div class="space-y-3">
            <?php foreach ($classes as $c): ?>
              <a href="/teacher/class.php?id=<?= (int)$c['id'] ?>" class="block bg-white border rounded-xl p-4 hover:shadow-md transition">
                <div class="font-semibold"><?= h($c['name']) ?></div>
                <div class="text-sm text-gray-400"><?= h($c['description'] ?? '') ?></div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Recent Flags -->
      <div>
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-lg font-bold">Recent Integrity Flags</h2>
          <a href="/teacher/flags.php" class="text-sm text-indigo-600 hover:underline">View All →</a>
        </div>
        <?php if (empty($recentFlags)): ?>
          <div class="bg-white border rounded-xl p-6 text-center text-gray-400">
            No pending flags. All clear!
          </div>
        <?php else: ?>
          <div class="space-y-3">
            <?php foreach ($recentFlags as $f): ?>
              <div class="bg-white border rounded-xl p-4">
                <div class="flex justify-between">
                  <span class="font-semibold text-sm"><?= h($f['student_name']) ?></span>
                  <span class="text-xs px-2 py-0.5 rounded-full <?= $f['severity'] === 'high' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700' ?>">
                    <?= h($f['severity']) ?>
                  </span>
                </div>
                <div class="text-xs text-gray-500 mt-1"><?= h($f['policy_triggered']) ?></div>
                <div class="text-xs text-gray-400 mt-1"><?= h($f['created_at']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    </div>

  </div>

</body>
</html>
