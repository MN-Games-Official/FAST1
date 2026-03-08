<?php
/**
 * AI Education App — Teacher API
 *
 * Endpoints for teacher-specific actions: student progress, AI history, policy management.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';

$user   = require_role('teacher', 'admin');
$db     = get_db();
$action = $_GET['action'] ?? '';

switch ($action) {

    /* Student progress for a class */
    case 'student_progress':
        $classId = (int)($_GET['class_id'] ?? 0);
        $stmt = $db->prepare(
            'SELECT u.id, u.name, u.email,
                    COUNT(DISTINCT d.id) AS document_count,
                    COALESCE(SUM(d.word_count), 0) AS total_words,
                    COUNT(DISTINCT pv.id) AS violation_count
             FROM users u
             JOIN enrollments e ON e.student_id = u.id
             LEFT JOIN documents d ON d.user_id = u.id
             LEFT JOIN policy_violations pv ON pv.user_id = u.id
             WHERE e.class_id = ?
             GROUP BY u.id
             ORDER BY u.name'
        );
        $stmt->execute([$classId]);
        json_response(['students' => $stmt->fetchAll()]);
        break;

    /* AI assistance history for a student */
    case 'ai_history':
        $studentId = (int)($_GET['student_id'] ?? 0);
        $stmt = $db->prepare(
            'SELECT ae.*, d.title AS document_title
             FROM ai_events ae
             LEFT JOIN documents d ON d.id = ae.document_id
             WHERE ae.user_id = ?
             ORDER BY ae.created_at DESC
             LIMIT 50'
        );
        $stmt->execute([$studentId]);
        json_response(['events' => $stmt->fetchAll()]);
        break;

    /* Set a policy rule */
    case 'set_rule':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'POST required'], 405);
        csrf_validate();
        $data = json_input();
        $missing = validate_required($data, ['scope', 'scope_id', 'rule_key', 'rule_value']);
        if ($missing) json_response(['error' => 'Missing: ' . implode(', ', $missing)], 422);

        $stmt = $db->prepare(
            'INSERT INTO policy_rules (scope, scope_id, rule_key, rule_value, is_active, created_by, created_at) VALUES (?, ?, ?, ?, 1, ?, NOW())'
        );
        $stmt->execute([$data['scope'], (int)$data['scope_id'], $data['rule_key'], $data['rule_value'], $user['id']]);
        json_response(['id' => (int)$db->lastInsertId()], 201);
        break;

    /* List policy rules for a scope */
    case 'rules':
        $scope   = $_GET['scope'] ?? '';
        $scopeId = (int)($_GET['scope_id'] ?? 0);
        $stmt = $db->prepare('SELECT * FROM policy_rules WHERE scope = ? AND scope_id = ? ORDER BY created_at DESC');
        $stmt->execute([$scope, $scopeId]);
        json_response(['rules' => $stmt->fetchAll()]);
        break;

    default:
        json_response(['error' => 'Unknown action'], 400);
}
