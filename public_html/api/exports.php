<?php
/**
 * AI Education App — Exports API
 *
 * PDF export and document export functionality.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user   = require_auth();
$db     = get_db();
$action = $_GET['action'] ?? '';

switch ($action) {

    /* Export document as HTML (for print/PDF) */
    case 'html':
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare('SELECT * FROM documents WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user['id']]);
        $doc = $stmt->fetch();
        if (!$doc) json_response(['error' => 'Not found'], 404);

        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        echo '<title>' . h($doc['title']) . '</title>';
        echo '<style>body{font-family:Georgia,serif;max-width:800px;margin:40px auto;padding:20px;line-height:1.6;}</style>';
        echo '</head><body>';
        echo '<h1>' . h($doc['title']) . '</h1>';
        echo $doc['content'];
        echo '</body></html>';
        exit;

    default:
        json_response(['error' => 'Unknown action'], 400);
}
