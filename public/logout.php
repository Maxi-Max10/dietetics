<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard.php');
    exit;
}

$token = (string)($_POST['csrf_token'] ?? '');
if (!csrf_verify($token)) {
    header('Location: /dashboard.php');
    exit;
}

auth_logout();
header('Location: /login.php');
exit;
