<?php
/**
 * AI Education App — Student/Teacher Dashboard
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';

$user = require_auth();
$db   = get_db();

// Fetch recent documents
$stmt = $db->prepare('SELECT * FROM documents WHERE user_id = ? ORDER BY updated_at DESC LIMIT 10');
$stmt->execute([$user['id']]);
$documents = $stmt->fetchAll();

// Fetch assignments for student
$assignments = [];
if ($user['role'] === 'student') {
    $stmt = $db->prepare(
        'SELECT a.*, c.name AS class_name
         FROM assignments a
         JOIN classes c ON c.id = a.class_id
         JOIN enrollments e ON e.class_id = c.id
         WHERE e.student_id = ?
         ORDER BY a.due_date ASC
         LIMIT 10'
    );
    $stmt->execute([$user['id']]);
    $assignments = $stmt->fetchAll();
}

// Fetch notifications
$stmt = $db->prepare('SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5');
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — <?= h(APP_NAME) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="bg-gray-50 min-h-screen">

  <!-- Top Navigation -->
  <nav class="bg-white border-b shadow-sm">
    <div class="max-w-7xl mx-auto flex items-center justify-between px-6 py-3">
      <a href="/dashboard.php" class="text-lg font-bold text-indigo-600"><?= h(APP_NAME) ?></a>
      <div class="flex items-center space-x-4">
        <?php if ($user['role'] === 'teacher'): ?>
          <a href="/teacher/index.php" class="text-sm text-gray-600 hover:text-indigo-600">Teacher Panel</a>
        <?php elseif ($user['role'] === 'admin'): ?>
          <a href="/admin/index.php" class="text-sm text-gray-600 hover:text-indigo-600">Admin Panel</a>
        <?php endif; ?>
        <span class="text-sm text-gray-500"><?= h($user['name']) ?> (<?= h($user['role']) ?>)</span>
        <a href="/api/auth.php?action=logout" class="text-sm text-red-500 hover:underline">Sign Out</a>
      </div>
    </div>
  </nav>

  <div class="max-w-7xl mx-auto px-6 py-8">

    <!-- Welcome -->
    <div class="mb-8">
      <h1 class="text-2xl font-bold">Welcome back, <?= h(explode(' ', $user['name'])[0]) ?>!</h1>
      <p class="text-gray-500 mt-1">Here's what you're working on.</p>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
      <a href="/editor.php" class="block bg-indigo-600 text-white rounded-xl p-5 hover:bg-indigo-700 transition">
        <div class="font-bold text-lg mb-1">+ New Document</div>
        <div class="text-indigo-200 text-sm">Start a blank writing workspace</div>
      </a>
      <a href="/editor.php?mode=assignment" class="block bg-white border rounded-xl p-5 hover:shadow-md transition">
        <div class="font-bold text-lg mb-1 text-indigo-600">📝 Start Assignment</div>
        <div class="text-gray-500 text-sm">Paste or select an assignment prompt</div>
      </a>
      <div class="bg-white border rounded-xl p-5">
        <div class="font-bold text-lg mb-1 text-gray-800">📄 Documents</div>
        <div class="text-gray-500 text-sm"><?= count($documents) ?> recent documents</div>
      </div>
      <div class="bg-white border rounded-xl p-5">
        <div class="font-bold text-lg mb-1 text-gray-800">🔔 Notifications</div>
        <div class="text-gray-500 text-sm"><?= count($notifications) ?> unread</div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

      <!-- Recent Documents -->
      <div class="lg:col-span-2">
        <h2 class="text-lg font-bold mb-4">Recent Documents</h2>
        <?php if (empty($documents)): ?>
          <div class="bg-white border rounded-xl p-8 text-center text-gray-400">
            <p>No documents yet. <a href="/editor.php" class="text-indigo-600 hover:underline">Create your first one!</a></p>
          </div>
        <?php else: ?>
          <div class="space-y-3">
            <?php foreach ($documents as $doc): ?>
              <a href="/editor.php?id=<?= (int)$doc['id'] ?>" class="block bg-white border rounded-xl p-4 hover:shadow-md transition">
                <div class="flex justify-between items-center">
                  <div>
                    <div class="font-semibold"><?= h($doc['title']) ?></div>
                    <div class="text-sm text-gray-400"><?= (int)$doc['word_count'] ?> words · Updated <?= h($doc['updated_at'] ?? $doc['created_at']) ?></div>
                  </div>
                  <span class="text-indigo-500 text-sm">Open →</span>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Sidebar: Assignments & Notifications -->
      <div>
        <?php if ($user['role'] === 'student' && !empty($assignments)): ?>
          <h2 class="text-lg font-bold mb-4">Current Assignments</h2>
          <div class="space-y-3 mb-8">
            <?php foreach ($assignments as $a): ?>
              <div class="bg-white border rounded-xl p-4">
                <div class="font-semibold text-sm"><?= h($a['title']) ?></div>
                <div class="text-xs text-gray-400"><?= h($a['class_name']) ?></div>
                <?php if ($a['due_date']): ?>
                  <div class="text-xs text-red-500 mt-1">Due: <?= h($a['due_date']) ?></div>
                <?php endif; ?>
                <a href="/editor.php?assignment=<?= (int)$a['id'] ?>" class="text-xs text-indigo-600 hover:underline mt-2 inline-block">Start →</a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($notifications)): ?>
          <h2 class="text-lg font-bold mb-4">Notifications</h2>
          <div class="space-y-3">
            <?php foreach ($notifications as $n): ?>
              <div class="bg-white border rounded-xl p-4 text-sm">
                <div class="text-gray-700"><?= h($n['message']) ?></div>
                <div class="text-xs text-gray-400 mt-1"><?= h($n['created_at']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    </div>

  </div>

</body>
</html>
