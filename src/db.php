<?php

declare(strict_types=1);

function db(array $config): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = $config['db'] ?? [];

    $host = (string)($db['host'] ?? 'localhost');
    $name = (string)($db['name'] ?? '');
    $user = (string)($db['user'] ?? '');
    $pass = (string)($db['pass'] ?? '');
    $charset = (string)($db['charset'] ?? 'utf8mb4');

    if ($name === '' || $user === '') {
        throw new RuntimeException('Falta configurar la base de datos (DB_NAME/DB_USER).');
    }

    $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);

    // Alinea la zona horaria de la sesión MySQL con Argentina (UTC-3).
    // Esto evita que "Hoy" se corte por UTC y queden datos afuera a la noche.
    try {
        $pdo->exec("SET time_zone = '-03:00'");
    } catch (Throwable $e) {
        // Si el hosting no lo permite, no bloqueamos la app.
    }

    return $pdo;
}
