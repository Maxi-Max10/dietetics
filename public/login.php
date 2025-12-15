<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

if (auth_user_id() !== null) {
    header('Location: /dashboard.php');
    exit;
}

$config = app_config();
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $token = (string)($_POST['csrf_token'] ?? '');

    if (!csrf_verify($token)) {
        $error = 'Sesión inválida. Recargá e intentá de nuevo.';
    } elseif ($email === '' || $password === '') {
        $error = 'Completá email y contraseña.';
    } else {
        try {
            $pdo = db($config);
            $ok = auth_login($pdo, $email, $password);
            if ($ok) {
                header('Location: /dashboard.php');
                exit;
            }
            $error = 'Credenciales incorrectas.';
        } catch (Throwable $e) {
            $error = ($config['app']['env'] ?? 'production') === 'production'
                ? 'No se pudo conectar a la base de datos.'
                : ('DB error: ' . $e->getMessage());
        }
    }
}

$appName = (string)($config['app']['name'] ?? 'Dietetic');
$csrf = csrf_token();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($appName) ?> - Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body class="bg-light">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12 col-md-6 col-lg-4">
        <div class="card shadow-sm mt-5">
          <div class="card-body p-4">
            <h1 class="h4 mb-3">Ingresar</h1>

            <?php if ($error !== ''): ?>
              <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="/login.php" novalidate>
              <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

              <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" value="<?= e($email) ?>" autocomplete="username" required>
              </div>

              <div class="mb-3">
                <label for="password" class="form-label">Contraseña</label>
                <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
              </div>

              <button type="submit" class="btn btn-primary w-100">Entrar</button>
            </form>

            <p class="text-muted small mt-3 mb-0"><?= e($appName) ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
