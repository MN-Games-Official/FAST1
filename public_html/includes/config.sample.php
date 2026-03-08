<?php
/**
 * AI Education App — Configuration
 *
 * Copy this file to config.php and fill in your values.
 * config.php is loaded by the application; config.sample.php is the template.
 */

// --- Database ---------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'ai_education');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// --- Application ------------------------------------------------------------
define('APP_NAME', 'AI Education App');
define('APP_URL', 'http://localhost');
define('APP_ENV', 'development'); // 'production' or 'development'

// --- Session ----------------------------------------------------------------
define('SESSION_LIFETIME', 7200); // seconds

// --- AI Provider ------------------------------------------------------------
define('AI_PROVIDER', 'abacus');           // 'abacus' | 'openai' | 'mock'
define('AI_API_KEY', '');
define('AI_API_URL', 'https://api.abacus.ai/v1');
define('AI_DEFAULT_MODEL', 'gpt-4');
define('AI_MAX_TOKENS', 1024);
define('AI_TEMPERATURE', 0.7);

// --- Rate Limiting ----------------------------------------------------------
define('AI_RATE_LIMIT', 30);               // requests per window
define('AI_RATE_WINDOW', 60);              // window in seconds

// --- File Storage -----------------------------------------------------------
define('STORAGE_PATH', __DIR__ . '/../storage');
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10 MB
