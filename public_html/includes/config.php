<?php
/**
 * AI Education App — Configuration
 */

// --- Database ---------------------------------------------------------------
// In production, set these via environment variables.
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'ai_education');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// --- Application ------------------------------------------------------------
define('APP_NAME', 'AI Education App');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost');
define('APP_ENV', getenv('APP_ENV') ?: 'development');

define('SESSION_LIFETIME', 7200);

// --- AI Provider ------------------------------------------------------------
// Use 'mock' for local dev without API keys. Set to 'abacus' or 'openai' in production.
define('AI_PROVIDER', getenv('AI_PROVIDER') ?: 'mock');
define('AI_API_KEY', getenv('AI_API_KEY') ?: '');
define('AI_API_URL', getenv('AI_API_URL') ?: 'https://api.abacus.ai/v1');
define('AI_DEFAULT_MODEL', getenv('AI_DEFAULT_MODEL') ?: 'gpt-4');
define('AI_MAX_TOKENS', 1024);
define('AI_TEMPERATURE', 0.7);
define('AI_MAX_DOCUMENT_CONTEXT_LENGTH', 8000);
define('AI_MAX_SELECTION_LENGTH', 2000);

// --- Rate Limiting ----------------------------------------------------------
define('AI_RATE_LIMIT', 30);
define('AI_RATE_WINDOW', 60);

// --- Documents --------------------------------------------------------------
define('AUTOSAVE_INTERVAL_MS', 5000);
define('DOCUMENT_VERSION_THROTTLE_SECONDS', 120);

// --- File Storage -----------------------------------------------------------
define('STORAGE_PATH', __DIR__ . '/../storage');
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);
