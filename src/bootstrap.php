<?php

declare(strict_types=1);

$config = require __DIR__ . DIRECTORY_SEPARATOR . 'config.php';

// Sesión segura (ajustable según hosting).
$sessionName = (string)($config['security']['session_name'] ?? 'dietetic_session');
if ($sessionName !== '') {
    session_name($sessionName);
}

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'csrf.php';

function app_config(): array
{
    static $configCache = null;
    if (is_array($configCache)) {
        return $configCache;
    }
    $configCache = require __DIR__ . DIRECTORY_SEPARATOR . 'config.php';
    return $configCache;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
