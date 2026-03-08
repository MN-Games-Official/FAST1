<?php
/**
 * AI Education App — Admin Policies Management
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_role('admin');
$db   = get_db();

// Handle rule creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'create_rule') {
    csrf_validate();
    $scope    = $_POST['scope'] ?? '';
    $scopeId  = (int)($_POST['scope_id'] ?? 0);
    $ruleKey  = trim($_POST['rule_key'] ?? '');
    $ruleVal  = trim($_POST['rule_value'] ?? '');

    if (in_array($scope, ['school', 'class', 'assignment'], true) && $ruleKey && $ruleVal) {
        $stmt = $db->prepare(
            'INSERT INTO policy_rules (scope, scope_id, rule_key, rule_value, is_active, created_by, created_at) VALUES (?, ?, ?, ?, 1, ?, NOW())'
        );
        $stmt->execute([$scope, $scopeId, $ruleKey, $ruleVal, $user['id']]);
        redirect('/admin/policies.php');
    }
}

// Fetch all rules
$stmt = $db->query('SELECT pr.*, u.name AS created_by_name FROM policy_rules pr LEFT JOIN users u ON u.id = pr.created_by ORDER BY pr.created_at DESC LIMIT 100');
$rules = $stmt->fetchAll();

// Fetch schools for dropdown
$stmt = $db->query('SELECT id, name FROM schools ORDER BY name');
$schools = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Policies — <?= h(APP_NAME) ?></title>
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
        <span class="text-sm font-semibold text-gray-600">Policies</span>
      </div>
      <div class="flex items-center space-x-4 text-sm">
        <a href="/admin/index.php" class="text-gray-500 hover:text-indigo-600">Dashboard</a>
        <a href="/admin/policies.php" class="text-indigo-600 font-semibold">Policies</a>
        <a href="/admin/analytics.php" class="text-gray-500 hover:text-indigo-600">Analytics</a>
        <a href="/api/auth.php?action=logout" class="text-red-500 hover:underline">Sign Out</a>
      </div>
    </div>
  </nav>

  <div class="max-w-7xl mx-auto px-6 py-8">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

      <!-- Create rule -->
      <div>
        <h2 class="text-lg font-bold mb-4">Create Policy Rule</h2>
        <div class="bg-white border rounded-xl p-4">
          <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="create_rule">

            <label class="block text-sm font-medium text-gray-700 mb-1">Scope</label>
            <select name="scope" required class="w-full border rounded-lg px-3 py-2 text-sm mb-3">
              <option value="school">School</option>
              <option value="class">Class</option>
              <option value="assignment">Assignment</option>
            </select>

            <label class="block text-sm font-medium text-gray-700 mb-1">Scope ID</label>
            <input type="number" name="scope_id" required class="w-full border rounded-lg px-3 py-2 text-sm mb-3" placeholder="ID of school/class/assignment">

            <label class="block text-sm font-medium text-gray-700 mb-1">Rule Key</label>
            <select name="rule_key" required class="w-full border rounded-lg px-3 py-2 text-sm mb-3">
              <option value="enforcement_level">Enforcement Level</option>
              <option value="block_mode">Block Mode</option>
              <option value="allow_only_modes">Allow Only Modes</option>
            </select>

            <label class="block text-sm font-medium text-gray-700 mb-1">Rule Value</label>
            <input type="text" name="rule_value" required class="w-full border rounded-lg px-3 py-2 text-sm mb-4"
                   placeholder="e.g. strict, balanced, supportive">

            <button type="submit" class="w-full bg-indigo-600 text-white py-2 rounded-lg text-sm font-semibold hover:bg-indigo-700">
              Create Rule
            </button>
          </form>
        </div>
      </div>

      <!-- Rules list -->
      <div class="lg:col-span-2">
        <h2 class="text-lg font-bold mb-4">Active Policy Rules</h2>
        <?php if (empty($rules)): ?>
          <div class="bg-white border rounded-xl p-8 text-center text-gray-400">No rules defined yet.</div>
        <?php else: ?>
          <div class="bg-white border rounded-xl overflow-hidden">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 text-left text-xs text-gray-500 uppercase">
                <tr>
                  <th class="px-4 py-3">Scope</th>
                  <th class="px-4 py-3">Scope ID</th>
                  <th class="px-4 py-3">Key</th>
                  <th class="px-4 py-3">Value</th>
                  <th class="px-4 py-3">Created By</th>
                  <th class="px-4 py-3">Date</th>
                </tr>
              </thead>
              <tbody class="divide-y">
                <?php foreach ($rules as $r): ?>
                  <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3"><?= h($r['scope']) ?></td>
                    <td class="px-4 py-3"><?= (int)$r['scope_id'] ?></td>
                    <td class="px-4 py-3 font-medium"><?= h($r['rule_key']) ?></td>
                    <td class="px-4 py-3"><?= h($r['rule_value']) ?></td>
                    <td class="px-4 py-3 text-gray-400"><?= h($r['created_by_name'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-gray-400 text-xs"><?= h($r['created_at']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

    </div>

  </div>

</body>
</html>
