<?php
/**
 * AI Education App — Assignments API
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';

$user   = require_auth();
$db     = get_db();
$action = $_GET['action'] ?? '';

switch ($action) {

    /* List assignments for current student */
    case 'list':
        if ($user['role'] === 'student') {
            $stmt = $db->prepare(
                'SELECT a.*, c.name AS class_name
                 FROM assignments a
                 JOIN classes c ON c.id = a.class_id
                 JOIN enrollments e ON e.class_id = c.id
                 WHERE e.student_id = ?
                 ORDER BY a.due_date ASC'
            );
            $stmt->execute([$user['id']]);
        } else {
            // Teacher sees own assignments
            $stmt = $db->prepare('SELECT a.*, c.name AS class_name FROM assignments a JOIN classes c ON c.id = a.class_id WHERE a.teacher_id = ? ORDER BY a.created_at DESC');
            $stmt->execute([$user['id']]);
        }
        json_response(['assignments' => $stmt->fetchAll()]);
        break;

    /* Get single assignment */
    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare('SELECT * FROM assignments WHERE id = ?');
        $stmt->execute([$id]);
        $a = $stmt->fetch();
        if (!$a) json_response(['error' => 'Not found'], 404);
        json_response(['assignment' => $a]);
        break;

    /* Create assignment (teacher only) */
    case 'create':
        $user = require_role('teacher', 'admin');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'POST required'], 405);
        csrf_validate();
        $data = json_input();
        $missing = validate_required($data, ['class_id', 'title', 'instructions']);
        if ($missing) json_response(['error' => 'Missing: ' . implode(', ', $missing)], 422);

        $stmt = $db->prepare(
            'INSERT INTO assignments (class_id, teacher_id, title, instructions, due_date, ai_rules, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            (int)$data['class_id'],
            $user['id'],
            $data['title'],
            $data['instructions'],
            $data['due_date'] ?? null,
            !empty($data['ai_rules']) ? json_encode($data['ai_rules']) : null,
        ]);
        json_response(['id' => (int)$db->lastInsertId()], 201);
        break;

    /* Update assignment (teacher only) */
    case 'update':
        $user = require_role('teacher', 'admin');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'POST required'], 405);
        csrf_validate();
        $data = json_input();
        $id = (int)($data['id'] ?? 0);

        $stmt = $db->prepare('SELECT id FROM assignments WHERE id = ? AND teacher_id = ?');
        $stmt->execute([$id, $user['id']]);
        if (!$stmt->fetch()) json_response(['error' => 'Not found'], 404);

        $stmt = $db->prepare('UPDATE assignments SET title = ?, instructions = ?, due_date = ?, ai_rules = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([
            $data['title'] ?? '',
            $data['instructions'] ?? '',
            $data['due_date'] ?? null,
            !empty($data['ai_rules']) ? json_encode($data['ai_rules']) : null,
            $id,
        ]);
        json_response(['ok' => true]);
        break;

    default:
        json_response(['error' => 'Unknown action'], 400);
}
