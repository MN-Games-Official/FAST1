<?php
/**
 * AI Education App — Classes API
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';

$user   = require_auth();
$db     = get_db();
$action = $_GET['action'] ?? '';

switch ($action) {

    /* List classes */
    case 'list':
        if ($user['role'] === 'student') {
            $stmt = $db->prepare(
                'SELECT c.*, u.name AS teacher_name
                 FROM classes c
                 JOIN enrollments e ON e.class_id = c.id
                 JOIN users u ON u.id = c.teacher_id
                 WHERE e.student_id = ?'
            );
            $stmt->execute([$user['id']]);
        } else {
            $stmt = $db->prepare('SELECT * FROM classes WHERE teacher_id = ?');
            $stmt->execute([$user['id']]);
        }
        json_response(['classes' => $stmt->fetchAll()]);
        break;

    /* Create class (teacher only) */
    case 'create':
        $user = require_role('teacher', 'admin');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'POST required'], 405);
        csrf_validate();
        $data = json_input();
        if (empty($data['name'])) json_response(['error' => 'Class name required'], 422);

        $stmt = $db->prepare('INSERT INTO classes (school_id, teacher_id, name, description, created_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([$user['school_id'], $user['id'], $data['name'], $data['description'] ?? '']);
        json_response(['id' => (int)$db->lastInsertId()], 201);
        break;

    /* Enroll student */
    case 'enroll':
        $user = require_role('teacher', 'admin');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'POST required'], 405);
        csrf_validate();
        $data = json_input();
        $classId   = (int)($data['class_id'] ?? 0);
        $studentId = (int)($data['student_id'] ?? 0);

        // Verify teacher owns the class
        $stmt = $db->prepare('SELECT id FROM classes WHERE id = ? AND teacher_id = ?');
        $stmt->execute([$classId, $user['id']]);
        if (!$stmt->fetch()) json_response(['error' => 'Class not found'], 404);

        try {
            $stmt = $db->prepare('INSERT INTO enrollments (class_id, student_id) VALUES (?, ?)');
            $stmt->execute([$classId, $studentId]);
            json_response(['ok' => true], 201);
        } catch (PDOException $e) {
            json_response(['error' => 'Already enrolled or invalid student'], 409);
        }
        break;

    /* List students in a class */
    case 'students':
        $classId = (int)($_GET['class_id'] ?? 0);
        $stmt = $db->prepare(
            'SELECT u.id, u.name, u.email
             FROM users u
             JOIN enrollments e ON e.student_id = u.id
             WHERE e.class_id = ?
             ORDER BY u.name'
        );
        $stmt->execute([$classId]);
        json_response(['students' => $stmt->fetchAll()]);
        break;

    default:
        json_response(['error' => 'Unknown action'], 400);
}
