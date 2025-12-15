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
    $name = (string)($db['name'] ?? 'u404968876_dietetics');
    $user = (string)($db['user'] ?? 'u404968876_dietetics');
    $pass = (string)($db['pass'] ?? 'Dietetics2025@');
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
    return $pdo;
}
