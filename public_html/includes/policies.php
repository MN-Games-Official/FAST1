<?php
/**
 * AI Education App — Policy Engine
 *
 * Handles anti-cheating detection, rule enforcement, and integrity logging.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

class PolicyEngine
{
    /* ------------------------------------------------------------------ */
    /*  Cheating-pattern detection                                        */
    /* ------------------------------------------------------------------ */

    /**
     * Patterns that strongly indicate a request for direct answers.
     * Each entry is [regex_pattern, severity, label].
     */
    private const CHEAT_PATTERNS = [
        ['/\b(write|compose|create|generate|produce)\s+(my|the|an?|this)\s+(essay|paper|report|response|assignment|paragraph|answer)/i', 'high', 'direct_generation'],
        ['/\b(give|tell)\s+me\s+the\s+answer/i', 'high', 'direct_answer'],
        ['/\bdo\s+(this|it|my\s+\w+)\s+for\s+me\b/i', 'high', 'do_for_me'],
        ['/\banswer\s+(these|this|my)\s+(question|prompt|exam)/i', 'high', 'answer_request'],
        ['/\bcomplete\s+(my|the|this)\s+(exam|test|quiz|assignment)/i', 'high', 'exam_completion'],
        ['/\bmake\s+(this|it)\s+sound\s+human/i', 'medium', 'humanise_request'],
        ['/\b(pretend|act\s+as\s+if)\s+you\s+are\s+me\b/i', 'high', 'impersonation'],
        ['/\bgenerate\s+final\s+version\b/i', 'medium', 'final_version'],
        ['/\bgive\s+me\s+a\s+model\s+(response|answer|essay)/i', 'medium', 'model_response'],
        ['/\bwrite\s+the\s+entire\b/i', 'high', 'full_write'],
        ['/\bjust\s+(give|write|provide)\s+(me\s+)?(the\s+)?(whole|full|complete)\b/i', 'high', 'full_output'],
    ];

    /**
     * Check whether a user message triggers any cheating pattern.
     *
     * @return array{flagged: bool, matches: array<array{pattern: string, severity: string, label: string}>}
     */
    public function detectCheating(string $message): array
    {
        $matches = [];
        foreach (self::CHEAT_PATTERNS as [$pattern, $severity, $label]) {
            if (preg_match($pattern, $message)) {
                $matches[] = ['pattern' => $pattern, 'severity' => $severity, 'label' => $label];
            }
        }
        return ['flagged' => count($matches) > 0, 'matches' => $matches];
    }

    /* ------------------------------------------------------------------ */
    /*  Assignment-level / class-level rule checking                      */
    /* ------------------------------------------------------------------ */

    /**
     * Retrieve the effective policy rules for a given context.
     * Precedence: school → class → assignment → student accommodation.
     */
    public function getEffectiveRules(?int $schoolId, ?int $classId, ?int $assignmentId): array
    {
        $db = get_db();
        $rules = [];

        // School-level rules
        if ($schoolId) {
            $stmt = $db->prepare('SELECT * FROM policy_rules WHERE scope = "school" AND scope_id = ? AND is_active = 1');
            $stmt->execute([$schoolId]);
            $rules = array_merge($rules, $stmt->fetchAll());
        }
        // Class-level rules
        if ($classId) {
            $stmt = $db->prepare('SELECT * FROM policy_rules WHERE scope = "class" AND scope_id = ? AND is_active = 1');
            $stmt->execute([$classId]);
            $rules = array_merge($rules, $stmt->fetchAll());
        }
        // Assignment-level rules
        if ($assignmentId) {
            $stmt = $db->prepare('SELECT * FROM policy_rules WHERE scope = "assignment" AND scope_id = ? AND is_active = 1');
            $stmt->execute([$assignmentId]);
            $rules = array_merge($rules, $stmt->fetchAll());
        }

        return $rules;
    }

    /**
     * Check if a specific AI mode is allowed by rules.
     */
    public function isModeAllowed(string $mode, array $rules): bool
    {
        foreach ($rules as $rule) {
            if ($rule['rule_key'] === 'block_mode' && $rule['rule_value'] === $mode) {
                return false;
            }
            if ($rule['rule_key'] === 'allow_only_modes') {
                $allowed = array_map('trim', explode(',', $rule['rule_value']));
                if (!in_array($mode, $allowed, true)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Determine the enforcement level from rules (strict / balanced / supportive).
     * Defaults to 'balanced'.
     */
    public function getEnforcementLevel(array $rules): string
    {
        foreach (array_reverse($rules) as $rule) {
            if ($rule['rule_key'] === 'enforcement_level') {
                return $rule['rule_value'];
            }
        }
        return 'balanced';
    }

    /* ------------------------------------------------------------------ */
    /*  Integrity logging                                                 */
    /* ------------------------------------------------------------------ */

    /**
     * Log a policy violation.
     */
    public function logViolation(
        int    $userId,
        ?int   $documentId,
        string $flaggedRequest,
        string $policyTriggered,
        string $severity,
        ?int   $teacherId = null
    ): int {
        $db = get_db();
        $stmt = $db->prepare(
            'INSERT INTO policy_violations (user_id, document_id, flagged_request, policy_triggered, severity, teacher_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$userId, $documentId, $flaggedRequest, $policyTriggered, $severity, $teacherId]);
        return (int) $db->lastInsertId();
    }

    /**
     * Create a notification for a teacher about a policy violation.
     */
    public function notifyTeacher(int $teacherId, int $violationId, string $message): void
    {
        $db = get_db();
        $stmt = $db->prepare(
            'INSERT INTO notifications (user_id, type, reference_id, message, created_at) VALUES (?, "policy_violation", ?, ?, NOW())'
        );
        $stmt->execute([$teacherId, $violationId, $message]);
    }

    /* ------------------------------------------------------------------ */
    /*  Safe alternative response                                         */
    /* ------------------------------------------------------------------ */

    /**
     * Return a refusal message with an educational redirect.
     */
    public function getRefusalResponse(string $label): string
    {
        $alternatives = [
            'direct_generation' => "I can't write this assignment for you, but I can help you understand the prompt, build an outline, and improve your own draft. Would you like to start with understanding the assignment?",
            'direct_answer'     => "I'm here to help you learn, not provide direct answers. Let's work through this together — what part of the question is most confusing?",
            'do_for_me'         => "I can't do the work for you, but I can break it into smaller steps and guide you through each one. Want to start with the first step?",
            'answer_request'    => "Instead of giving you the answer, let me help you figure it out. What do you already know about this topic?",
            'exam_completion'   => "I can't help complete exams or tests. If you're studying, I can help you review concepts and practice your understanding.",
            'humanise_request'  => "I can help you improve your own writing to sound more natural. Would you like feedback on a specific paragraph?",
            'impersonation'     => "I need to work with you as your learning coach, not pretend to be you. Let's focus on helping you write your own response.",
            'final_version'     => "I can help you revise and improve your draft, but the final version should be your own work. Want me to review what you have so far?",
            'model_response'    => "Instead of a model response, let me help you build your own. We can start with an outline and work through each section together.",
            'full_write'        => "I can't write the entire piece for you. Let's start with understanding what's being asked, then work on an outline together.",
            'full_output'       => "I'm designed to help you learn, not to produce complete work. Let's break this down — what's the first thing you need to figure out?",
        ];

        return $alternatives[$label] ?? "I can't complete that request, but I can help you understand the assignment, plan your approach, and improve your writing. What would you like to work on?";
    }
}
