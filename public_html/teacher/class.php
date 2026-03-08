<?php
/**
 * AI Education App — Teacher Class Management
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_role('teacher', 'admin');
$db   = get_db();

$classId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$class   = null;
$students = [];

if ($classId) {
    $stmt = $db->prepare('SELECT * FROM classes WHERE id = ? AND teacher_id = ?');
    $stmt->execute([$classId, $user['id']]);
    $class = $stmt->fetch();

    if ($class) {
        $stmt = $db->prepare(
            'SELECT u.id, u.name, u.email,
                    COUNT(DISTINCT d.id) AS doc_count,
                    COUNT(DISTINCT pv.id) AS flag_count
             FROM users u
             JOIN enrollments e ON e.student_id = u.id
             LEFT JOIN documents d ON d.user_id = u.id
             LEFT JOIN policy_violations pv ON pv.user_id = u.id
             WHERE e.class_id = ?
             GROUP BY u.id
             ORDER BY u.name'
        );
        $stmt->execute([$classId]);
        $students = $stmt->fetchAll();
    }
}

// All classes for listing
$stmt = $db->prepare('SELECT * FROM classes WHERE teacher_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$allClasses = $stmt->fetchAll();

// Handle class creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'create_class') {
    csrf_validate();
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($name) {
        $stmt = $db->prepare('INSERT INTO classes (school_id, teacher_id, name, description, created_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([$user['school_id'], $user['id'], $name, $desc]);
        redirect('/teacher/class.php?id=' . $db->lastInsertId());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Classes — <?= h(APP_NAME) ?></title>
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
        <span class="text-sm font-semibold text-gray-600">Classes</span>
      </div>
      <div class="flex items-center space-x-4 text-sm">
        <a href="/teacher/index.php" class="text-gray-500 hover:text-indigo-600">Dashboard</a>
        <a href="/teacher/class.php" class="text-indigo-600 font-semibold">Classes</a>
        <a href="/teacher/assignment.php" class="text-gray-500 hover:text-indigo-600">Assignments</a>
        <a href="/teacher/flags.php" class="text-gray-500 hover:text-indigo-600">Flags</a>
        <a href="/api/auth.php?action=logout" class="text-red-500 hover:underline">Sign Out</a>
      </div>
    </div>
  </nav>

  <div class="max-w-7xl mx-auto px-6 py-8">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

      <!-- Class list -->
      <div>
        <h2 class="text-lg font-bold mb-4">Your Classes</h2>
        <div class="space-y-2 mb-6">
          <?php foreach ($allClasses as $c): ?>
            <a href="/teacher/class.php?id=<?= (int)$c['id'] ?>"
               class="block bg-white border rounded-lg px-4 py-3 hover:shadow-md text-sm <?= $classId === (int)$c['id'] ? 'border-indigo-400 bg-indigo-50' : '' ?>">
              <?= h($c['name']) ?>
            </a>
          <?php endforeach; ?>
        </div>

        <!-- Create class form -->
        <div class="bg-white border rounded-xl p-4">
          <h3 class="font-semibold text-sm mb-3">Create New Class</h3>
          <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="create_class">
            <input type="text" name="name" placeholder="Class Name" required
                   class="w-full border rounded-lg px-3 py-2 text-sm mb-2">
            <textarea name="description" placeholder="Description (optional)" rows="2"
                      class="w-full border rounded-lg px-3 py-2 text-sm mb-2"></textarea>
            <button type="submit" class="w-full bg-indigo-600 text-white py-2 rounded-lg text-sm font-semibold hover:bg-indigo-700">
              Create Class
            </button>
          </form>
        </div>
      </div>

      <!-- Class detail -->
      <div class="lg:col-span-2">
        <?php if ($class): ?>
          <h2 class="text-lg font-bold mb-4"><?= h($class['name']) ?></h2>
          <?php if ($class['description']): ?>
            <p class="text-sm text-gray-500 mb-4"><?= h($class['description']) ?></p>
          <?php endif; ?>

          <h3 class="font-semibold text-sm mb-3">Students (<?= count($students) ?>)</h3>
          <?php if (empty($students)): ?>
            <p class="text-gray-400 text-sm">No students enrolled yet.</p>
          <?php else: ?>
            <div class="bg-white border rounded-xl overflow-hidden">
              <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs text-gray-500 uppercase">
                  <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Documents</th>
                    <th class="px-4 py-3">Flags</th>
                  </tr>
                </thead>
                <tbody class="divide-y">
                  <?php foreach ($students as $s): ?>
                    <tr class="hover:bg-gray-50">
                      <td class="px-4 py-3 font-medium"><?= h($s['name']) ?></td>
                      <td class="px-4 py-3 text-gray-500"><?= h($s['email']) ?></td>
                      <td class="px-4 py-3"><?= (int)$s['doc_count'] ?></td>
                      <td class="px-4 py-3">
                        <?php if ((int)$s['flag_count'] > 0): ?>
                          <span class="text-red-600 font-semibold"><?= (int)$s['flag_count'] ?></span>
                        <?php else: ?>
                          <span class="text-green-600">0</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        <?php else: ?>
          <div class="bg-white border rounded-xl p-8 text-center text-gray-400">
            Select a class from the left or create a new one.
          </div>
        <?php endif; ?>
      </div>

    </div>

  </div>

</body>
</html>
