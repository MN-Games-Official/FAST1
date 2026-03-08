<?php
/**
 * AI Education App — AI Client Abstraction
 *
 * Provides a provider-agnostic interface for sending prompts to AI models.
 * Supports Abacus.AI, OpenAI-compatible APIs, and a local mock for development.
 */

require_once __DIR__ . '/config.php';

class AIClient
{
    private string $provider;
    private string $apiKey;
    private string $apiUrl;
    private string $model;

    public function __construct()
    {
        $this->provider = AI_PROVIDER;
        $this->apiKey   = AI_API_KEY;
        $this->apiUrl   = AI_API_URL;
        $this->model    = AI_DEFAULT_MODEL;
    }

    /**
     * Send a chat-style request to the AI provider.
     *
     * @param  string $systemPrompt  System-level instructions.
     * @param  string $userMessage   The user's message / selected text / question.
     * @param  array  $context       Additional context (assignment, document text, etc.).
     * @return array  ['content' => string, 'usage' => array|null, 'error' => string|null]
     */
    public function chat(string $systemPrompt, string $userMessage, array $context = []): array
    {
        $messages = [];
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];

        if (!empty($context['assignment'])) {
            $messages[] = ['role' => 'system', 'content' => "Assignment context:\n" . $context['assignment']];
        }
        if (!empty($context['document'])) {
            $messages[] = ['role' => 'system', 'content' => "Student's current document:\n" . $context['document']];
        }
        if (!empty($context['selection'])) {
            $messages[] = ['role' => 'system', 'content' => "Selected text:\n" . $context['selection']];
        }
        if (!empty($context['history'])) {
            foreach ($context['history'] as $msg) {
                $messages[] = $msg;
            }
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        return match ($this->provider) {
            'abacus'  => $this->sendAbacus($messages),
            'openai'  => $this->sendOpenAI($messages),
            'mock'    => $this->sendMock($messages),
            default   => ['content' => '', 'usage' => null, 'error' => 'Unknown AI provider.'],
        };
    }

    /* --------------------------------------------------------------------- */
    /*  Provider Implementations                                             */
    /* --------------------------------------------------------------------- */

    private function sendAbacus(array $messages): array
    {
        return $this->sendOpenAICompatible($this->apiUrl . '/chat/completions', $messages);
    }

    private function sendOpenAI(array $messages): array
    {
        return $this->sendOpenAICompatible('https://api.openai.com/v1/chat/completions', $messages);
    }

    /**
     * Generic OpenAI-compatible HTTP call (works for Abacus, OpenAI, etc.).
     */
    private function sendOpenAICompatible(string $url, array $messages): array
    {
        $payload = json_encode([
            'model'       => $this->model,
            'messages'    => $messages,
            'max_tokens'  => AI_MAX_TOKENS,
            'temperature' => AI_TEMPERATURE,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 60,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['content' => '', 'usage' => null, 'error' => 'cURL error: ' . $err];
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200 || !isset($data['choices'][0]['message']['content'])) {
            $msg = $data['error']['message'] ?? 'Unknown AI API error.';
            return ['content' => '', 'usage' => null, 'error' => $msg];
        }

        return [
            'content' => $data['choices'][0]['message']['content'],
            'usage'   => $data['usage'] ?? null,
            'error'   => null,
        ];
    }

    /**
     * Mock provider for local development without API keys.
     */
    private function sendMock(array $messages): array
    {
        $lastUser = '';
        foreach (array_reverse($messages) as $m) {
            if ($m['role'] === 'user') {
                $lastUser = $m['content'];
                break;
            }
        }

        $content = "**Mock AI Response**\n\n"
            . "I received your request. In production this would be handled by the configured AI provider.\n\n"
            . "Your message: \"" . mb_substr($lastUser, 0, 200) . "\"";

        return ['content' => $content, 'usage' => null, 'error' => null];
    }
}
