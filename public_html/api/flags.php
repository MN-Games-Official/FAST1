<?php
/**
 * AI Education App — Flags API
 *
 * View and manage policy violations.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';

$user   = require_role('teacher', 'admin');
$db     = get_db();
$action = $_GET['action'] ?? '';

switch ($action) {

    /* List violations for teacher's students */
    case 'list':
        $classId = !empty($_GET['class_id']) ? (int)$_GET['class_id'] : null;
        if ($classId) {
            $stmt = $db->prepare(
                'SELECT pv.*, u.name AS student_name, d.title AS document_title
                 FROM policy_violations pv
                 JOIN users u ON u.id = pv.user_id
                 LEFT JOIN documents d ON d.id = pv.document_id
                 JOIN enrollments e ON e.student_id = pv.user_id AND e.class_id = ?
                 ORDER BY pv.created_at DESC
                 LIMIT 50'
            );
            $stmt->execute([$classId]);
        } else {
            $stmt = $db->prepare(
                'SELECT pv.*, u.name AS student_name, d.title AS document_title
                 FROM policy_violations pv
                 JOIN users u ON u.id = pv.user_id
                 LEFT JOIN documents d ON d.id = pv.document_id
                 WHERE pv.teacher_id = ? OR pv.teacher_id IS NULL
                 ORDER BY pv.created_at DESC
                 LIMIT 50'
            );
            $stmt->execute([$user['id']]);
        }
        json_response(['violations' => $stmt->fetchAll()]);
        break;

    /* Resolve a violation */
    case 'resolve':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'POST required'], 405);
        csrf_validate();
        $data = json_input();
        $id = (int)($data['id'] ?? 0);
        $status = in_array($data['status'] ?? '', ['reviewed', 'dismissed'], true) ? $data['status'] : 'reviewed';

        $stmt = $db->prepare('UPDATE policy_violations SET resolution_status = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?');
        $stmt->execute([$status, $user['id'], $id]);
        json_response(['ok' => true]);
        break;

    default:
        json_response(['error' => 'Unknown action'], 400);
}
