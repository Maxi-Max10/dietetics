<?php

declare(strict_types=1);

$config = require __DIR__ . DIRECTORY_SEPARATOR . 'config.php';

// Zona horaria: en hosting suele estar en UTC. Forzamos Argentina para que "Hoy" y rangos sean correctos.
$appTz = getenv('APP_TIMEZONE') ?: (defined('APP_TIMEZONE') ? APP_TIMEZONE : 'America/Argentina/Buenos_Aires');
if (is_string($appTz) && $appTz !== '') {
    @date_default_timezone_set($appTz);
}

// Sesión segura (ajustable según hosting).
$sessionName = (string)($config['security']['session_name'] ?? 'dietetic_session');
if ($sessionName !== '') {
    session_name($sessionName);
}

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

// Endurecimiento básico de sesión/headers.
if (PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    // cookie_secure se setea en session_set_cookie_params según $https.
    if (!headers_sent()) {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(self), camera=()');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Expires: 0');
        if ($https) {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }
}

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

// Autoload de Composer (si existe en el hosting).
$autoload = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (is_file($autoload)) {
    /** @noinspection PhpIncludeInspection */
    require_once $autoload;
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'csrf.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'invoices.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'invoice_render.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'invoice_pdf.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'invoice_pdf_template.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'mailer.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'finance.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'catalog.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'speech.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'stock.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'sales.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'reports.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'orders.php';

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
