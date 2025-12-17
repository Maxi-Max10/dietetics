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
              $_SESSION['preload_dashboard'] = 1;
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
    html, body { overflow-x: hidden; width: 100%; }
    .auth-shell { min-height: 100vh; overflow-x: hidden; }
    .auth-card { border-radius: 1.25rem; overflow: hidden; width: 100%; }
    .auth-left { min-height: 220px; }
    .auth-right { min-height: 220px; }
    .auth-logo { height: 64px; width: auto; display: inline-block; }
    .auth-quotes { max-width: 22rem; }
    .auth-quote {
      opacity: .92;
      transition: opacity .35s ease;
    }
    .auth-quote.is-fading { opacity: .15; }
    .auth-blob {
      width: min(360px, 80%);
      height: 240px;
      border-radius: 2.5rem;
      background: rgba(255, 255, 255, .18);
      transform: rotate(-6deg);
    }

    .auth-float {
      position: absolute;
      border-radius: 999px;
      background: rgba(255, 255, 255, .16);
      filter: blur(.2px);
      animation: auth-float 22s cubic-bezier(.45, 0, .55, 1) infinite;
      pointer-events: none;
    }

    .auth-float.f1 { width: 140px; height: 140px; top: 12%; left: -40px; opacity: .55; animation-duration: 26s; }
    .auth-float.f2 { width: 90px; height: 90px; bottom: 18%; right: -22px; opacity: .45; animation-duration: 30s; animation-delay: -6s; }
    .auth-float.f3 { width: 56px; height: 56px; top: 22%; right: 18%; opacity: .35; animation-duration: 24s; animation-delay: -3s; }

    @keyframes auth-float {
      0%, 100% { transform: translate3d(0, 0, 0); }
      50% { transform: translate3d(10px, -26px, 0); }
    }

    @media (prefers-reduced-motion: reduce) {
      .auth-float { animation: none; }
      .auth-quote { transition: none; }
    }
    @media (min-width: 992px) {
      .auth-left { min-height: 520px; }
      .auth-right { min-height: 520px; }
      .auth-blob { height: 360px; }
    }
  </style>
</head>
<body class="bg-body-tertiary">
  <div class="container-fluid auth-shell d-flex align-items-center py-4 px-3">
    <div class="row justify-content-center w-100">
      <div class="col-12 col-lg-10 col-xl-9 col-xxl-8">
        <div class="card shadow auth-card">
          <div class="row g-0">
            <div class="col-lg-5">
              <div class="auth-left bg-success bg-gradient text-white position-relative d-flex align-items-center justify-content-center p-4">
                <div class="position-absolute auth-blob"></div>
                <div class="auth-float f1"></div>
                <div class="auth-float f2"></div>
                <div class="auth-float f3"></div>
                <div class="position-relative text-center">
                  <img src="/logo.png" alt="Logo" class="auth-logo mb-3">
                  <h2 class="h3 fw-semibold mb-2" id="greetingText">Hola, Bienvenida!</h2>
                  <div class="auth-quotes mx-auto mt-3">
                    <div class="small" style="opacity:.9" id="quoteText">“La constancia le gana al talento cuando el talento no es constante.”</div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-lg-7 auth-right d-flex align-items-center">
              <div class="card-body p-4 p-lg-5 w-100 d-flex flex-column justify-content-center">
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

                  <div class="mb-3"></div>

                  <button type="submit" class="btn btn-success w-100 py-2">Login</button>

                  <p class="text-muted small mt-4 mb-0 text-center"><?= e($appName) ?></p>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
<script>
  (function () {
    function getArgentinaHour() {
      try {
        var hourStr = new Intl.DateTimeFormat('es-AR', {
          hour: '2-digit',
          hour12: false,
          timeZone: 'America/Argentina/Buenos_Aires'
        }).format(new Date());

        var hour = Number(hourStr);
        return Number.isFinite(hour) ? hour : null;
      } catch (e) {
        return null;
      }
    }

    function greetingForHour(hour) {
      if (hour === null) return 'Hola, Bienvenida!';
      if (hour >= 5 && hour < 12) return 'Buenos días, Bienvenida!';
      if (hour >= 12 && hour < 20) return 'Buenas tardes, Bienvenida!';
      return 'Buenas noches, Bienvenida!';
    }

    var greetingEl = document.getElementById('greetingText');
    if (greetingEl) {
      greetingEl.textContent = greetingForHour(getArgentinaHour());
    }

    var quoteEl = document.getElementById('quoteText');
    if (!quoteEl) return;

    var quotes = [
      '“Hecho es mejor que perfecto.”',
      '“Lo que no se mide, no se mejora.”',
      '“Enfocate en el proceso: los resultados llegan.”',
      '“Vendé valor, no tiempo.”',
      '“Pequeños avances diarios crean grandes cambios.”',
      '“La disciplina construye lo que la motivación empieza.”'
    ];

    function getArgentinaDateKey() {
      try {
        return new Intl.DateTimeFormat('en-CA', {
          year: 'numeric',
          month: '2-digit',
          day: '2-digit',
          timeZone: 'America/Argentina/Buenos_Aires'
        }).format(new Date());
      } catch (e) {
        var d = new Date();
        var y = String(d.getFullYear());
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
      }
    }

    function simpleHash(s) {
      var h = 0;
      for (var j = 0; j < s.length; j++) {
        h = ((h << 5) - h) + s.charCodeAt(j);
        h |= 0;
      }
      return Math.abs(h);
    }

    var key = getArgentinaDateKey();
    var idx = quotes.length ? (simpleHash(key) % quotes.length) : 0;
    quoteEl.classList.add('auth-quote');
    quoteEl.textContent = quotes[idx] || '';
  })();
</script>
</body>
</html>
