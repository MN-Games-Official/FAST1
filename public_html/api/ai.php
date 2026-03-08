<?php
/**
 * AI Education App — AI API
 *
 * Handles AI requests with policy checks, prompt building, and integrity logging.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/ai_client.php';
require_once __DIR__ . '/../includes/policies.php';

$user = require_auth();
$db   = get_db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'POST required'], 405);
}
csrf_validate();

// Rate limit
if (!rate_limit('ai_requests', AI_RATE_LIMIT, AI_RATE_WINDOW)) {
    json_response(['error' => 'Rate limit exceeded. Please wait a moment.'], 429);
}

$data = json_input();
$mode       = $data['mode'] ?? 'general';
$message    = trim($data['message'] ?? '');
$docId      = !empty($data['document_id']) ? (int)$data['document_id'] : null;
$assignId   = !empty($data['assignment_id']) ? (int)$data['assignment_id'] : null;
$selection  = $data['selection'] ?? '';
$docContent = $data['document_content'] ?? '';

if ($message === '' && $selection === '') {
    json_response(['error' => 'Please provide a message or select text.'], 422);
}

/* -------------------------------------------------------------------- */
/*  1. Policy checks                                                    */
/* -------------------------------------------------------------------- */
$policy = new PolicyEngine();

// Detect cheating patterns
$detection = $policy->detectCheating($message);
if ($detection['flagged']) {
    $match = $detection['matches'][0];

    // Log the violation
    $violationId = $policy->logViolation(
        $user['id'],
        $docId,
        $message,
        $match['label'],
        $match['severity']
    );

    // Log AI event
    $stmt = $db->prepare(
        'INSERT INTO ai_events (user_id, document_id, event_type, mode, request_text, response_text, created_at) VALUES (?, ?, "refusal", ?, ?, ?, NOW())'
    );
    $refusal = $policy->getRefusalResponse($match['label']);
    $stmt->execute([$user['id'], $docId, $mode, $message, $refusal]);

    // Notify teacher if in a class context
    if ($assignId) {
        $stmt = $db->prepare('SELECT teacher_id FROM assignments WHERE id = ?');
        $stmt->execute([$assignId]);
        $row = $stmt->fetch();
        if ($row) {
            $policy->notifyTeacher(
                (int)$row['teacher_id'],
                $violationId,
                'Student ' . $user['name'] . ' triggered an integrity flag: ' . $match['label']
            );
        }
    }

    json_response([
        'flagged'  => true,
        'severity' => $match['severity'],
        'response' => $refusal,
    ]);
}

// Check assignment/class-level rules
$schoolId = $user['school_id'] ?? null;
$classId  = null;
if ($assignId) {
    $stmt = $db->prepare('SELECT class_id FROM assignments WHERE id = ?');
    $stmt->execute([$assignId]);
    $row = $stmt->fetch();
    $classId = $row ? (int)$row['class_id'] : null;
}

$rules = $policy->getEffectiveRules($schoolId, $classId, $assignId);

if (!$policy->isModeAllowed($mode, $rules)) {
    json_response([
        'flagged'  => true,
        'severity' => 'medium',
        'response' => "This type of AI assistance ($mode) is not allowed for this assignment by your teacher's policy. Try a different type of support.",
    ]);
}

/* -------------------------------------------------------------------- */
/*  2. Build prompt                                                     */
/* -------------------------------------------------------------------- */
$systemPrompts = [
    'interpreter' => "You are an academic assignment interpreter. Your job is to help a student understand their assignment. Restate the prompt in simpler language, identify the assignment type, explain what the teacher is likely looking for, break the task into small steps, and ask guiding questions. Do NOT write the assignment for the student.",
    'planner'     => "You are an academic planner. Help the student break their assignment into manageable steps, create a work plan, and suggest milestones. Do NOT write any of the actual content — only provide structure and planning guidance.",
    'brainstorm'  => "You are a brainstorm coach. Ask the student guided questions to help them generate ideas, angles, or examples for their assignment. Encourage original thinking. Do NOT provide finished ideas — help them discover their own.",
    'outline'     => "You are an outline builder. Help the student structure their writing with an introduction, body, and conclusion. Suggest paragraph roles and claim/evidence/reasoning structure. Do NOT write the actual paragraphs.",
    'draft_coach' => "You are a draft coach. Review the student's writing and highlight weak areas. Suggest improvements for clarity, coherence, grammar, and argument strength without rewriting everything. Provide specific, actionable feedback.",
    'reasoning'   => "You are a reasoning checker. Identify unsupported claims, flag weak logic, and suggest evidence types to look for. Help the student strengthen their arguments without writing them.",
    'reflection'  => "You are a reflection coach. Ask the student why they made certain choices in their writing. Check whether they understand their own work. Prompt deeper thinking without providing answers.",
    'general'     => "You are an educational AI assistant. Help the student understand their work and improve their skills. NEVER write assignments, essays, or answers for them. Always guide, scaffold, and ask questions instead of giving direct content.",
];

$systemPrompt = $systemPrompts[$mode] ?? $systemPrompts['general'];
$systemPrompt .= "\n\nIMPORTANT: You must NEVER produce submit-ready academic work. Always maintain a coaching role.";

$enforcementLevel = $policy->getEnforcementLevel($rules);
if ($enforcementLevel === 'strict') {
    $systemPrompt .= "\n\nSTRICT MODE: Provide minimal content. Focus only on questions, structure suggestions, and clarification. Do not produce examples, model sentences, or sample arguments.";
} elseif ($enforcementLevel === 'supportive') {
    $systemPrompt .= "\n\nSUPPORTIVE MODE: You may provide more examples and modeling, but still never produce complete work.";
}

// Build context
$context = [];
if ($assignId) {
    $stmt = $db->prepare('SELECT title, instructions FROM assignments WHERE id = ?');
    $stmt->execute([$assignId]);
    $aRow = $stmt->fetch();
    if ($aRow) {
        $context['assignment'] = "Title: {$aRow['title']}\nInstructions: {$aRow['instructions']}";
    }
}
if ($docContent) {
    $context['document'] = mb_substr($docContent, 0, AI_MAX_DOCUMENT_CONTEXT_LENGTH);
}
if ($selection) {
    $context['selection'] = mb_substr($selection, 0, AI_MAX_SELECTION_LENGTH);
}

// Include recent conversation history
if ($docId) {
    $stmt = $db->prepare(
        'SELECT role, content FROM ai_conversations WHERE document_id = ? AND user_id = ? ORDER BY created_at DESC LIMIT 10'
    );
    $stmt->execute([$docId, $user['id']]);
    $history = array_reverse($stmt->fetchAll());
    $context['history'] = $history;
}

$userMessage = $message ?: "Please review the selected text and provide guidance.";

/* -------------------------------------------------------------------- */
/*  3. Call AI                                                          */
/* -------------------------------------------------------------------- */
$ai = new AIClient();
$result = $ai->chat($systemPrompt, $userMessage, $context);

if ($result['error']) {
    // Log error event
    $stmt = $db->prepare(
        'INSERT INTO ai_events (user_id, document_id, event_type, mode, request_text, response_text, created_at) VALUES (?, ?, "error", ?, ?, ?, NOW())'
    );
    $stmt->execute([$user['id'], $docId, $mode, $message, $result['error']]);
    json_response(['error' => 'AI service error. Please try again.'], 502);
}

/* -------------------------------------------------------------------- */
/*  4. Log conversation & events                                       */
/* -------------------------------------------------------------------- */
$tokensUsed = $result['usage']['total_tokens'] ?? null;

// Save user message to conversation
$stmt = $db->prepare(
    'INSERT INTO ai_conversations (document_id, user_id, mode, role, content, tokens_used, created_at) VALUES (?, ?, ?, "user", ?, NULL, NOW())'
);
$stmt->execute([$docId ?: 0, $user['id'], $mode, $userMessage]);

// Save assistant response to conversation
$stmt = $db->prepare(
    'INSERT INTO ai_conversations (document_id, user_id, mode, role, content, tokens_used, created_at) VALUES (?, ?, ?, "assistant", ?, ?, NOW())'
);
$stmt->execute([$docId ?: 0, $user['id'], $mode, $result['content'], $tokensUsed]);

// Log AI event
$stmt = $db->prepare(
    'INSERT INTO ai_events (user_id, document_id, event_type, mode, request_text, response_text, tokens_used, created_at) VALUES (?, ?, "request", ?, ?, ?, ?, NOW())'
);
$stmt->execute([$user['id'], $docId, $mode, $userMessage, $result['content'], $tokensUsed]);

json_response([
    'flagged'  => false,
    'response' => $result['content'],
    'mode'     => $mode,
]);
