<?php
/**
 * AI Education App — Teacher Integrity Flags
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_role('teacher', 'admin');
$db   = get_db();

// Handle flag resolution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'resolve') {
    csrf_validate();
    $id     = (int)($_POST['violation_id'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['reviewed', 'dismissed'], true) ? $_POST['status'] : 'reviewed';
    $stmt = $db->prepare('UPDATE policy_violations SET resolution_status = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?');
    $stmt->execute([$status, $user['id'], $id]);
    redirect('/teacher/flags.php');
}

// Fetch violations
$stmt = $db->prepare(
    'SELECT pv.*, u.name AS student_name, d.title AS document_title
     FROM policy_violations pv
     JOIN users u ON u.id = pv.user_id
     LEFT JOIN documents d ON d.id = pv.document_id
     ORDER BY pv.created_at DESC
     LIMIT 100'
);
$stmt->execute();
$violations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Integrity Flags — <?= h(APP_NAME) ?></title>
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
        <span class="text-sm font-semibold text-gray-600">Integrity Flags</span>
      </div>
      <div class="flex items-center space-x-4 text-sm">
        <a href="/teacher/index.php" class="text-gray-500 hover:text-indigo-600">Dashboard</a>
        <a href="/teacher/class.php" class="text-gray-500 hover:text-indigo-600">Classes</a>
        <a href="/teacher/assignment.php" class="text-gray-500 hover:text-indigo-600">Assignments</a>
        <a href="/teacher/flags.php" class="text-indigo-600 font-semibold">Flags</a>
        <a href="/api/auth.php?action=logout" class="text-red-500 hover:underline">Sign Out</a>
      </div>
    </div>
  </nav>

  <div class="max-w-7xl mx-auto px-6 py-8">
    <h1 class="text-2xl font-bold mb-6">Integrity Flags</h1>

    <?php if (empty($violations)): ?>
      <div class="bg-white border rounded-xl p-8 text-center text-gray-400">
        No integrity flags recorded. All clear!
      </div>
    <?php else: ?>
      <div class="bg-white border rounded-xl overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-left text-xs text-gray-500 uppercase">
            <tr>
              <th class="px-4 py-3">Student</th>
              <th class="px-4 py-3">Document</th>
              <th class="px-4 py-3">Policy</th>
              <th class="px-4 py-3">Severity</th>
              <th class="px-4 py-3">Request</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3">Date</th>
              <th class="px-4 py-3">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <?php foreach ($violations as $v): ?>
              <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium"><?= h($v['student_name']) ?></td>
                <td class="px-4 py-3 text-gray-500"><?= h($v['document_title'] ?? '—') ?></td>
                <td class="px-4 py-3"><?= h($v['policy_triggered']) ?></td>
                <td class="px-4 py-3">
                  <span class="px-2 py-0.5 rounded-full text-xs font-semibold
                    <?= $v['severity'] === 'high' ? 'bg-red-100 text-red-700' : ($v['severity'] === 'medium' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600') ?>">
                    <?= h($v['severity']) ?>
                  </span>
                </td>
                <td class="px-4 py-3 text-gray-500 max-w-xs truncate"><?= h($v['flagged_request']) ?></td>
                <td class="px-4 py-3">
                  <span class="text-xs <?= $v['resolution_status'] === 'pending' ? 'text-orange-600' : 'text-green-600' ?>">
                    <?= h($v['resolution_status']) ?>
                  </span>
                </td>
                <td class="px-4 py-3 text-gray-400 text-xs"><?= h($v['created_at']) ?></td>
                <td class="px-4 py-3">
                  <?php if ($v['resolution_status'] === 'pending'): ?>
                    <form method="POST" class="inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="form_action" value="resolve">
                      <input type="hidden" name="violation_id" value="<?= (int)$v['id'] ?>">
                      <button type="submit" name="status" value="reviewed" class="text-xs text-indigo-600 hover:underline mr-2">Review</button>
                      <button type="submit" name="status" value="dismissed" class="text-xs text-gray-400 hover:underline">Dismiss</button>
                    </form>
                  <?php else: ?>
                    <span class="text-xs text-gray-400">Done</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</body>
</html>
