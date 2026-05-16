<?php

declare(strict_types=1);

// Carga opcional de secrets locales (NO versionar).
$localConfigPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config.local.php';
if (is_file($localConfigPath)) {
    $localConfigSource = file_get_contents($localConfigPath);
    if (is_string($localConfigSource) && $localConfigSource !== '') {
        $matchCount = preg_match_all('/define\s*\(\s*[\'"]([A-Z0-9_]+)[\'"]\s*,\s*([\'"])(.*?)\2\s*\)\s*;?/s', $localConfigSource, $matches, PREG_SET_ORDER);
        if (is_int($matchCount) && $matchCount > 0) {
            foreach ($matches as $match) {
                $name = (string)($match[1] ?? '');
                $value = stripcslashes((string)($match[3] ?? ''));
                if ($name !== '' && !defined($name)) {
                    define($name, $value);
                }
            }
        }
    }
}

$cfgValue = static function (array $names, string $default = ''): string {
    foreach ($names as $name) {
        $env = getenv($name);
        if (is_string($env) && $env !== '') {
            return $env;
        }
        if (defined($name)) {
            return (string)constant($name);
        }
    }
    return $default;
};

/**
 * Config por defecto: usa variables de entorno o valores definidos en config.local.php.
 * En Hostinger, lo mas simple es crear config.local.php con estos define().
 */
return [
    'app' => [
        'name' => $cfgValue(['APP_NAME'], 'Dietetic'),
        'env' => $cfgValue(['APP_ENV'], 'production'),
        'base_url' => $cfgValue(['APP_BASE_URL']),
    ],
    'db' => [
        'host' => $cfgValue(['DB_HOST'], 'localhost'),
        'name' => $cfgValue(['DB_NAME']),
        'user' => $cfgValue(['DB_USER']),
        'pass' => $cfgValue(['DB_PASS']),
        'charset' => $cfgValue(['DB_CHARSET'], 'utf8mb4'),
    ],
    'security' => [
        'session_name' => $cfgValue(['SESSION_NAME'], 'dietetic_session'),
    ],
    'speech' => [
        'openai_api_key' => $cfgValue(['OPENAI_API_KEY']),
        'openai_transcribe_model' => $cfgValue(['OPENAI_TRANSCRIBE_MODEL'], 'whisper-1'),
    ],
    'ticket_ocr' => [
        'openai_api_key' => $cfgValue(['OPENAI_API_KEY']),
        'openai_model' => $cfgValue(['OPENAI_TICKET_OCR_MODEL'], 'gpt-4.1-mini'),
        'gemini_api_key' => $cfgValue(['GEMINI_API_KEY', 'GOOGLE_API_KEY', 'GOOGLE_AI_API_KEY', 'GOOGLE_GENAI_API_KEY']),
        'gemini_model' => $cfgValue(['GEMINI_TICKET_OCR_MODEL'], 'gemini-3-flash-preview'),
    ],
    'google_maps' => [
        'api_key' => $cfgValue(['GOOGLE_MAPS_API_KEY']),
    ],
    // Lista de precios publica (cliente) + pedidos para retiro.
    // PUBLIC_CATALOG_USER_ID: si tenes un solo usuario admin, podes dejarlo vacio y se toma el primero.
    'public_catalog' => [
        'enabled' => $cfgValue(['PUBLIC_CATALOG_ENABLED'], '1'),
        'user_id' => $cfgValue(['PUBLIC_CATALOG_USER_ID']),
    ],
];
