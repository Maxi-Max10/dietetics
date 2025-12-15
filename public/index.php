<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

if (auth_user_id() !== null) {
    header('Location: /dashboard.php');
    exit;
}

header('Location: /login.php');
exit;
