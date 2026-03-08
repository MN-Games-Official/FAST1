<?php
/**
 * AI Education App — Document Editor
 *
 * Rich-text editor with AI sidebar, formatting toolbar, and autosave.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';

$user = require_auth();
$db   = get_db();

$docId       = isset($_GET['id']) ? (int)$_GET['id'] : null;
$assignmentId = isset($_GET['assignment']) ? (int)$_GET['assignment'] : null;
$document    = null;
$assignment  = null;

// Load existing document
if ($docId) {
    $stmt = $db->prepare('SELECT * FROM documents WHERE id = ? AND user_id = ?');
    $stmt->execute([$docId, $user['id']]);
    $document = $stmt->fetch();
    if (!$document) {
        redirect('/dashboard.php');
    }
    $assignmentId = $document['assignment_id'];
}

// Load assignment context
if ($assignmentId) {
    $stmt = $db->prepare('SELECT * FROM assignments WHERE id = ?');
    $stmt->execute([$assignmentId]);
    $assignment = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $document ? h($document['title']) : 'New Document' ?> — <?= h(APP_NAME) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="/assets/css/app.css">
  <link rel="stylesheet" href="/assets/css/editor.css">
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">

  <!-- Top bar -->
  <nav class="bg-white border-b shadow-sm flex items-center justify-between px-4 py-2 z-30">
    <div class="flex items-center space-x-3">
      <a href="/dashboard.php" class="text-indigo-600 font-bold text-sm">← Dashboard</a>
      <input type="text" id="doc-title"
             value="<?= h($document['title'] ?? 'Untitled') ?>"
             class="border-0 bg-transparent font-semibold text-lg focus:outline-none focus:border-b-2 focus:border-indigo-400 px-1"
             placeholder="Untitled Document">
      <span id="save-status" class="text-xs text-gray-400">Saved</span>
    </div>
    <div class="flex items-center space-x-3">
      <span class="text-xs text-gray-400" id="word-count">0 words</span>
      <button onclick="exportPDF()" class="text-xs text-gray-500 hover:text-indigo-600">Export PDF</button>
      <span class="text-sm text-gray-500"><?= h($user['name']) ?></span>
    </div>
  </nav>

  <!-- Toolbar -->
  <div class="bg-white border-b px-4 py-2 flex items-center space-x-2 flex-wrap" id="toolbar">
    <button onclick="execCmd('bold')" title="Bold" class="toolbar-btn font-bold">B</button>
    <button onclick="execCmd('italic')" title="Italic" class="toolbar-btn italic">I</button>
    <button onclick="execCmd('underline')" title="Underline" class="toolbar-btn underline">U</button>
    <span class="border-l h-5 mx-1"></span>
    <button onclick="execCmd('insertOrderedList')" title="Ordered List" class="toolbar-btn">1.</button>
    <button onclick="execCmd('insertUnorderedList')" title="Unordered List" class="toolbar-btn">•</button>
    <span class="border-l h-5 mx-1"></span>
    <select onchange="execBlock(this.value); this.value='';" class="text-sm border rounded px-2 py-1">
      <option value="">Heading…</option>
      <option value="h1">Heading 1</option>
      <option value="h2">Heading 2</option>
      <option value="h3">Heading 3</option>
      <option value="p">Paragraph</option>
    </select>
    <button onclick="execCmd('removeFormat')" title="Clear Formatting" class="toolbar-btn text-red-400">✕</button>
  </div>

  <!-- Main layout -->
  <div class="flex flex-1 overflow-hidden">

    <!-- Left sidebar: documents & assignment info -->
    <aside class="w-64 bg-white border-r overflow-y-auto hidden lg:block p-4">
      <?php if ($assignment): ?>
        <div class="mb-6">
          <h3 class="text-xs font-bold text-gray-500 uppercase mb-2">Assignment</h3>
          <div class="text-sm font-semibold"><?= h($assignment['title']) ?></div>
          <div class="text-xs text-gray-500 mt-1 whitespace-pre-wrap"><?= h($assignment['instructions']) ?></div>
          <?php if ($assignment['due_date']): ?>
            <div class="text-xs text-red-500 mt-2">Due: <?= h($assignment['due_date']) ?></div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <h3 class="text-xs font-bold text-gray-500 uppercase mb-2">Your Documents</h3>
      <div id="doc-list" class="space-y-1 text-sm text-gray-600">
        <p class="text-gray-400 text-xs">Loading…</p>
      </div>
    </aside>

    <!-- Editor canvas -->
    <main class="flex-1 flex flex-col overflow-hidden">
      <div class="flex-1 overflow-y-auto px-4 py-6 md:px-16 lg:px-24">
        <div id="editor" contenteditable="true"
             class="min-h-[60vh] max-w-3xl mx-auto bg-white rounded-xl shadow p-8 outline-none prose prose-indigo"
             data-placeholder="Start writing…"><?= $document ? $document['content'] : '' ?></div>
      </div>
    </main>

    <!-- Right sidebar: AI assistant -->
    <aside class="w-80 bg-white border-l flex flex-col overflow-hidden hidden md:flex" id="ai-sidebar">
      <div class="p-4 border-b">
        <h3 class="font-bold text-indigo-600">AI Writing Coach</h3>
        <p class="text-xs text-gray-400 mt-1">Select a mode to get started</p>
      </div>

      <!-- AI Mode buttons -->
      <div class="p-4 border-b space-y-2" id="ai-modes">
        <button onclick="aiRequest('interpreter')" class="ai-mode-btn">🔍 Understand Assignment</button>
        <button onclick="aiRequest('planner')" class="ai-mode-btn">📋 Break Into Steps</button>
        <button onclick="aiRequest('brainstorm')" class="ai-mode-btn">💡 Brainstorm Ideas</button>
        <button onclick="aiRequest('outline')" class="ai-mode-btn">🏗️ Build Outline</button>
        <button onclick="aiRequest('draft_coach')" class="ai-mode-btn">✍️ Review My Writing</button>
        <button onclick="aiRequest('reasoning')" class="ai-mode-btn">🧠 Check Reasoning</button>
        <button onclick="aiRequest('reflection')" class="ai-mode-btn">🪞 Reflect on My Work</button>
      </div>

      <!-- AI chat area -->
      <div class="flex-1 overflow-y-auto p-4 space-y-3" id="ai-chat">
        <div class="text-sm text-gray-400 text-center py-8">
          Choose a mode above or type a question below to get AI guidance.
        </div>
      </div>

      <!-- AI input -->
      <div class="p-4 border-t">
        <div class="flex space-x-2">
          <input type="text" id="ai-input" placeholder="Ask a question…"
                 class="flex-1 border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none"
                 onkeydown="if(event.key==='Enter')sendAI()">
          <button onclick="sendAI()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-indigo-700">Send</button>
        </div>
      </div>
    </aside>

  </div>

  <!-- Pass data to JS -->
  <script>
    const APP = {
      docId: <?= $docId ? (int)$docId : 'null' ?>,
      assignmentId: <?= $assignmentId ? (int)$assignmentId : 'null' ?>,
      csrfToken: '<?= csrf_token() ?>',
      userId: <?= (int)$user['id'] ?>,
      autosaveIntervalMs: <?= defined('AUTOSAVE_INTERVAL_MS') ? (int)AUTOSAVE_INTERVAL_MS : 5000 ?>,
    };
  </script>
  <script src="/assets/js/editor.js"></script>
  <script src="/assets/js/ai-sidebar.js"></script>

</body>
</html>
