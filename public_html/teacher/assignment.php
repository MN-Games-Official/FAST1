<?php
/**
 * AI Education App — Teacher Assignment Management
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_role('teacher', 'admin');
$db   = get_db();

// Fetch classes for dropdown
$stmt = $db->prepare('SELECT id, name FROM classes WHERE teacher_id = ?');
$stmt->execute([$user['id']]);
$classes = $stmt->fetchAll();

// Fetch assignments
$stmt = $db->prepare(
    'SELECT a.*, c.name AS class_name
     FROM assignments a
     JOIN classes c ON c.id = a.class_id
     WHERE a.teacher_id = ?
     ORDER BY a.created_at DESC'
);
$stmt->execute([$user['id']]);
$assignments = $stmt->fetchAll();

// Handle assignment creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'create_assignment') {
    csrf_validate();
    $classId      = (int)($_POST['class_id'] ?? 0);
    $title        = trim($_POST['title'] ?? '');
    $instructions = trim($_POST['instructions'] ?? '');
    $dueDate      = $_POST['due_date'] ?? null;

    if ($classId && $title && $instructions) {
        $stmt = $db->prepare(
            'INSERT INTO assignments (class_id, teacher_id, title, instructions, due_date, created_at) VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$classId, $user['id'], $title, $instructions, $dueDate ?: null]);
        redirect('/teacher/assignment.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Assignments — <?= h(APP_NAME) ?></title>
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
        <span class="text-sm font-semibold text-gray-600">Assignments</span>
      </div>
      <div class="flex items-center space-x-4 text-sm">
        <a href="/teacher/index.php" class="text-gray-500 hover:text-indigo-600">Dashboard</a>
        <a href="/teacher/class.php" class="text-gray-500 hover:text-indigo-600">Classes</a>
        <a href="/teacher/assignment.php" class="text-indigo-600 font-semibold">Assignments</a>
        <a href="/teacher/flags.php" class="text-gray-500 hover:text-indigo-600">Flags</a>
        <a href="/api/auth.php?action=logout" class="text-red-500 hover:underline">Sign Out</a>
      </div>
    </div>
  </nav>

  <div class="max-w-7xl mx-auto px-6 py-8">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

      <!-- Create assignment -->
      <div>
        <h2 class="text-lg font-bold mb-4">Create Assignment</h2>
        <div class="bg-white border rounded-xl p-4">
          <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="create_assignment">

            <label class="block text-sm font-medium text-gray-700 mb-1">Class</label>
            <select name="class_id" required class="w-full border rounded-lg px-3 py-2 text-sm mb-3">
              <option value="">Select class…</option>
              <?php foreach ($classes as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= h($c['name']) ?></option>
              <?php endforeach; ?>
            </select>

            <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
            <input type="text" name="title" required class="w-full border rounded-lg px-3 py-2 text-sm mb-3">

            <label class="block text-sm font-medium text-gray-700 mb-1">Instructions</label>
            <textarea name="instructions" required rows="6" class="w-full border rounded-lg px-3 py-2 text-sm mb-3"
                      placeholder="Write the full assignment prompt here…"></textarea>

            <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
            <input type="datetime-local" name="due_date" class="w-full border rounded-lg px-3 py-2 text-sm mb-4">

            <button type="submit" class="w-full bg-indigo-600 text-white py-2 rounded-lg text-sm font-semibold hover:bg-indigo-700">
              Create Assignment
            </button>
          </form>
        </div>
      </div>

      <!-- Assignment list -->
      <div class="lg:col-span-2">
        <h2 class="text-lg font-bold mb-4">Your Assignments</h2>
        <?php if (empty($assignments)): ?>
          <div class="bg-white border rounded-xl p-8 text-center text-gray-400">
            No assignments yet. Create one using the form.
          </div>
        <?php else: ?>
          <div class="space-y-3">
            <?php foreach ($assignments as $a): ?>
              <div class="bg-white border rounded-xl p-4">
                <div class="flex justify-between items-start">
                  <div>
                    <div class="font-semibold"><?= h($a['title']) ?></div>
                    <div class="text-xs text-gray-400"><?= h($a['class_name']) ?></div>
                  </div>
                  <?php if ($a['due_date']): ?>
                    <span class="text-xs text-red-500">Due: <?= h($a['due_date']) ?></span>
                  <?php endif; ?>
                </div>
                <div class="text-sm text-gray-600 mt-2 whitespace-pre-wrap"><?= h(mb_substr($a['instructions'], 0, 200)) ?>…</div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    </div>

  </div>

</body>
</html>
