<?php
/**
 * AI Education App — Documents API
 *
 * CRUD and autosave for student documents.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';

$user   = require_auth();
$db     = get_db();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($action) {

    /* ------------------------------------------------------------------ */
    /*  LIST documents for current user                                   */
    /* ------------------------------------------------------------------ */
    case 'list':
        $stmt = $db->prepare('SELECT id, title, word_count, assignment_id, created_at, updated_at FROM documents WHERE user_id = ? ORDER BY updated_at DESC');
        $stmt->execute([$user['id']]);
        json_response(['documents' => $stmt->fetchAll()]);
        break;

    /* ------------------------------------------------------------------ */
    /*  GET a single document                                             */
    /* ------------------------------------------------------------------ */
    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare('SELECT * FROM documents WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user['id']]);
        $doc = $stmt->fetch();
        if (!$doc) json_response(['error' => 'Not found'], 404);
        json_response(['document' => $doc]);
        break;

    /* ------------------------------------------------------------------ */
    /*  CREATE a new document                                             */
    /* ------------------------------------------------------------------ */
    case 'create':
        if ($method !== 'POST') json_response(['error' => 'POST required'], 405);
        csrf_validate();
        $data = json_input();
        $title = $data['title'] ?? 'Untitled';
        $content = $data['content'] ?? '';
        $assignmentId = !empty($data['assignment_id']) ? (int)$data['assignment_id'] : null;
        $wordCount = str_word_count(strip_tags($content));

        $stmt = $db->prepare(
            'INSERT INTO documents (user_id, assignment_id, title, content, word_count, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$user['id'], $assignmentId, $title, $content, $wordCount]);
        $newId = (int)$db->lastInsertId();
        json_response(['id' => $newId], 201);
        break;

    /* ------------------------------------------------------------------ */
    /*  SAVE / autosave an existing document                              */
    /* ------------------------------------------------------------------ */
    case 'save':
        if ($method !== 'POST') json_response(['error' => 'POST required'], 405);
        csrf_validate();
        $data = json_input();
        $id = (int)($data['id'] ?? 0);

        // Verify ownership
        $stmt = $db->prepare('SELECT id FROM documents WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user['id']]);
        if (!$stmt->fetch()) json_response(['error' => 'Not found'], 404);

        $title     = $data['title'] ?? 'Untitled';
        $content   = $data['content'] ?? '';
        $wordCount = str_word_count(strip_tags($content));

        $stmt = $db->prepare('UPDATE documents SET title = ?, content = ?, word_count = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$title, $content, $wordCount, $id]);

        // Save a version snapshot (throttled — only if last version is >2 min old)
        $stmt = $db->prepare('SELECT MAX(created_at) AS last_version FROM document_versions WHERE document_id = ?');
        $stmt->execute([$id]);
        $lastVersion = $stmt->fetchColumn();
        if (!$lastVersion || (time() - strtotime($lastVersion)) > DOCUMENT_VERSION_THROTTLE_SECONDS) {
            $stmt = $db->prepare('INSERT INTO document_versions (document_id, content, word_count, created_at) VALUES (?, ?, ?, NOW())');
            $stmt->execute([$id, $content, $wordCount]);
        }

        json_response(['ok' => true, 'word_count' => $wordCount]);
        break;

    /* ------------------------------------------------------------------ */
    /*  DELETE a document                                                 */
    /* ------------------------------------------------------------------ */
    case 'delete':
        if ($method !== 'POST') json_response(['error' => 'POST required'], 405);
        csrf_validate();
        $data = json_input();
        $id = (int)($data['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM documents WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user['id']]);
        json_response(['ok' => true]);
        break;

    /* ------------------------------------------------------------------ */
    /*  VERSIONS — list revisions                                         */
    /* ------------------------------------------------------------------ */
    case 'versions':
        $id = (int)($_GET['id'] ?? 0);
        // Verify ownership
        $stmt = $db->prepare('SELECT id FROM documents WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user['id']]);
        if (!$stmt->fetch()) json_response(['error' => 'Not found'], 404);

        $stmt = $db->prepare('SELECT id, word_count, created_at FROM document_versions WHERE document_id = ? ORDER BY created_at DESC LIMIT 50');
        $stmt->execute([$id]);
        json_response(['versions' => $stmt->fetchAll()]);
        break;

    default:
        json_response(['error' => 'Unknown action'], 400);
}
