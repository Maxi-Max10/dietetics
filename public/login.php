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
          // Loguea el error real (útil en Hostinger: revisá "Errors" / "error_log").
          error_log('Login DB error: ' . $e->getMessage());

          $env = (string)($config['app']['env'] ?? 'production');
          $msg = (string)$e->getMessage();

          // Mensajes seguros para producción (sin revelar detalles sensibles).
          if (str_contains($msg, 'Falta configurar la base de datos')) {
            $error = 'Falta configurar la base de datos: creá `config.local.php` y completá DB_HOST/DB_NAME/DB_USER/DB_PASS.';
          } elseif (stripos($msg, 'Access denied') !== false) {
            $error = 'No se pudo conectar: usuario/contraseña de MySQL incorrectos (DB_USER/DB_PASS).';
          } elseif (stripos($msg, 'Unknown database') !== false) {
            $error = 'No se pudo conectar: el nombre de la base (DB_NAME) no existe o está mal.';
          } elseif (stripos($msg, 'getaddrinfo') !== false || stripos($msg, 'Name or service not known') !== false) {
            $error = 'No se pudo conectar: el host de MySQL (DB_HOST) es incorrecto.';
          } elseif (stripos($msg, 'Connection refused') !== false) {
            $error = 'No se pudo conectar: MySQL rechazó la conexión (host/puerto).';
          } else {
            $error = 'No se pudo conectar a la base de datos.';
          }

          if ($env !== 'production') {
            $error .= ' (Detalle: ' . $msg . ')';
          }
        }
    }
}

$appName = (string)($config['app']['name'] ?? 'Dietetics');
$csrf = csrf_token();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($appName) ?> - Login</title>
  <link rel="icon" type="image/png" href="/logo.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    .auth-shell { min-height: 100vh; }
    .auth-card { border-radius: 1.25rem; overflow: hidden; }
    .auth-left { min-height: 220px; }
    .auth-blob {
      width: min(360px, 80%);
      height: 240px;
      border-radius: 2.5rem;
      background: rgba(255, 255, 255, .18);
      transform: rotate(-6deg);
    }
    @media (min-width: 992px) {
      .auth-left { min-height: 520px; }
      .auth-blob { height: 360px; }
    }
  </style>
</head>
<body class="bg-body-tertiary">
  <div class="container auth-shell d-flex align-items-center py-4">
    <div class="row justify-content-center w-100">
      <div class="col-12 col-lg-9 col-xl-8">
        <div class="card shadow auth-card">
          <div class="row g-0">
            <div class="col-lg-5">
              <div class="auth-left bg-primary bg-gradient text-white position-relative d-flex align-items-center justify-content-center p-4">
                <div class="position-absolute auth-blob"></div>
                <div class="position-relative text-center">
                  <h2 class="h3 fw-semibold mb-2">Hola, Bienvenida!</h2>
                </div>
              </div>
            </div>

            <div class="col-lg-7">
              <div class="card-body p-4 p-lg-5">
                <h1 class="h3 fw-semibold mb-4 text-center">Login</h1>

                <?php if ($error !== ''): ?>
                  <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" action="/login.php" novalidate>
                  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

                  <div class="mb-3">
                    <div class="input-group">
                      <span class="input-group-text"><i class="bi bi-person"></i></span>
                      <input type="email" class="form-control" id="email" name="email" value="<?= e($email) ?>" placeholder="Email" autocomplete="username" required>
                    </div>
                  </div>

                  <div class="mb-2">
                    <div class="input-group">
                      <span class="input-group-text"><i class="bi bi-lock"></i></span>
                      <input type="password" class="form-control" id="password" name="password" placeholder="Password" autocomplete="current-password" required>
                    </div>
                  </div>

                  <div class="d-flex justify-content-end mb-3">
                    <a class="small text-decoration-none" href="#" onclick="return false;">Forgot password?</a>
                  </div>

                  <button type="submit" class="btn btn-primary w-100 py-2">Login</button>

                  <div class="text-center text-muted small mt-3">or login with social platforms</div>
                  <div class="d-flex justify-content-center gap-2 mt-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" title="Google" disabled><i class="bi bi-google"></i></button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" title="Facebook" disabled><i class="bi bi-facebook"></i></button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" title="GitHub" disabled><i class="bi bi-github"></i></button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" title="LinkedIn" disabled><i class="bi bi-linkedin"></i></button>
                  </div>

                  <p class="text-muted small mt-4 mb-0 text-center"><?= e($appName) ?></p>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
