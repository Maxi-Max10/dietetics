<?php

declare(strict_types=1);

// Carga opcional de secrets locales (NO versionar).
$localConfigPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config.local.php';
if (is_file($localConfigPath)) {
    /** @noinspection PhpIncludeInspection */
    require_once $localConfigPath;
}

/**
 * Config por defecto: usa variables de entorno o valores definidos en config.local.php.
 * En Hostinger, lo más simple es crear config.local.php con estos define().
 */
return [
    'app' => [
        'name' => getenv('APP_NAME') ?: (defined('APP_NAME') ? APP_NAME : 'Dietetic'),
        'env' => getenv('APP_ENV') ?: (defined('APP_ENV') ? APP_ENV : 'production'),
        'base_url' => getenv('APP_BASE_URL') ?: (defined('APP_BASE_URL') ? APP_BASE_URL : ''),
    ],
    'db' => [
        'host' => getenv('DB_HOST') ?: (defined('DB_HOST') ? DB_HOST : 'localhost'),
        'name' => getenv('DB_NAME') ?: (defined('DB_NAME') ? DB_NAME : ''),
        'user' => getenv('DB_USER') ?: (defined('DB_USER') ? DB_USER : ''),
        'pass' => getenv('DB_PASS') ?: (defined('DB_PASS') ? DB_PASS : ''),
        'charset' => getenv('DB_CHARSET') ?: (defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4'),
    ],
    'security' => [
        'session_name' => getenv('SESSION_NAME') ?: (defined('SESSION_NAME') ? SESSION_NAME : 'dietetic_session'),
    ],
    'speech' => [
        'openai_api_key' => getenv('OPENAI_API_KEY') ?: (defined('OPENAI_API_KEY') ? OPENAI_API_KEY : ''),
        'openai_transcribe_model' => getenv('OPENAI_TRANSCRIBE_MODEL') ?: (defined('OPENAI_TRANSCRIBE_MODEL') ? OPENAI_TRANSCRIBE_MODEL : 'whisper-1'),
    ],
];
