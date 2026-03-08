<?php
/**
 * AI Education App — Admin Analytics
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_role('admin');
$db   = get_db();

// AI usage over last 7 days
$stmt = $db->query(
    "SELECT DATE(created_at) AS day, COUNT(*) AS count
     FROM ai_events
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     GROUP BY DATE(created_at)
     ORDER BY day"
);
$aiUsage = $stmt->fetchAll();

// Top users by AI events
$stmt = $db->query(
    "SELECT u.name, u.role, COUNT(ae.id) AS event_count
     FROM ai_events ae
     JOIN users u ON u.id = ae.user_id
     GROUP BY ae.user_id
     ORDER BY event_count DESC
     LIMIT 10"
);
$topUsers = $stmt->fetchAll();

// Mode distribution
$stmt = $db->query(
    "SELECT mode, COUNT(*) AS count
     FROM ai_events
     WHERE event_type = 'request'
     GROUP BY mode
     ORDER BY count DESC"
);
$modeStats = $stmt->fetchAll();

// Violation stats
$stmt = $db->query(
    "SELECT severity, COUNT(*) AS count
     FROM policy_violations
     GROUP BY severity"
);
$violationStats = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Analytics — <?= h(APP_NAME) ?></title>
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
        <span class="text-sm font-semibold text-gray-600">Analytics</span>
      </div>
      <div class="flex items-center space-x-4 text-sm">
        <a href="/admin/index.php" class="text-gray-500 hover:text-indigo-600">Dashboard</a>
        <a href="/admin/policies.php" class="text-gray-500 hover:text-indigo-600">Policies</a>
        <a href="/admin/analytics.php" class="text-indigo-600 font-semibold">Analytics</a>
        <a href="/api/auth.php?action=logout" class="text-red-500 hover:underline">Sign Out</a>
      </div>
    </div>
  </nav>

  <div class="max-w-7xl mx-auto px-6 py-8">
    <h1 class="text-2xl font-bold mb-6">Analytics</h1>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

      <!-- AI Usage -->
      <div>
        <h2 class="text-lg font-bold mb-4">AI Requests (Last 7 Days)</h2>
        <div class="bg-white border rounded-xl p-4">
          <?php if (empty($aiUsage)): ?>
            <p class="text-gray-400 text-sm text-center py-4">No data yet.</p>
          <?php else: ?>
            <div class="space-y-2">
              <?php foreach ($aiUsage as $day): ?>
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600"><?= h($day['day']) ?></span>
                  <div class="flex items-center">
                    <div class="bg-indigo-200 rounded h-4 mr-2" style="width: <?= min(200, (int)$day['count'] * 2) ?>px"></div>
                    <span class="font-semibold"><?= (int)$day['count'] ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Mode Distribution -->
      <div>
        <h2 class="text-lg font-bold mb-4">AI Mode Usage</h2>
        <div class="bg-white border rounded-xl p-4">
          <?php if (empty($modeStats)): ?>
            <p class="text-gray-400 text-sm text-center py-4">No data yet.</p>
          <?php else: ?>
            <div class="space-y-2">
              <?php foreach ($modeStats as $m): ?>
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600"><?= h($m['mode'] ?? 'unknown') ?></span>
                  <span class="font-semibold"><?= (int)$m['count'] ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Top Users -->
      <div>
        <h2 class="text-lg font-bold mb-4">Top AI Users</h2>
        <div class="bg-white border rounded-xl p-4">
          <?php if (empty($topUsers)): ?>
            <p class="text-gray-400 text-sm text-center py-4">No data yet.</p>
          <?php else: ?>
            <div class="space-y-2">
              <?php foreach ($topUsers as $tu): ?>
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600"><?= h($tu['name']) ?> <span class="text-xs text-gray-400">(<?= h($tu['role']) ?>)</span></span>
                  <span class="font-semibold"><?= (int)$tu['event_count'] ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Violations -->
      <div>
        <h2 class="text-lg font-bold mb-4">Policy Violations by Severity</h2>
        <div class="bg-white border rounded-xl p-4">
          <?php if (empty($violationStats)): ?>
            <p class="text-gray-400 text-sm text-center py-4">No violations recorded.</p>
          <?php else: ?>
            <div class="space-y-2">
              <?php foreach ($violationStats as $vs): ?>
                <div class="flex justify-between text-sm">
                  <span class="px-2 py-0.5 rounded-full text-xs font-semibold
                    <?= $vs['severity'] === 'high' ? 'bg-red-100 text-red-700' : ($vs['severity'] === 'medium' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600') ?>">
                    <?= h($vs['severity']) ?>
                  </span>
                  <span class="font-semibold"><?= (int)$vs['count'] ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

    </div>

  </div>

</body>
</html>
