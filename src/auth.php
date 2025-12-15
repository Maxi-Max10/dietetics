<?php

declare(strict_types=1);

function auth_user_id(): ?int
{
    $id = $_SESSION['user_id'] ?? null;
    if (is_int($id) && $id > 0) {
        return $id;
    }
    return null;
}

function auth_require_login(): void
{
    if (auth_user_id() === null) {
        header('Location: /login.php');
        exit;
    }
}

function auth_login(PDO $pdo, string $email, string $password): bool
{
    $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !isset($user['id'], $user['password_hash'])) {
        return false;
    }

    if (!password_verify($password, (string)$user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    return true;
}

function auth_logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}
