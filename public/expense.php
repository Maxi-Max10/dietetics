<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

auth_require_login();

$config = app_config();
$appName = (string)($config['app']['name'] ?? 'Dietetics');
$userId = (int)auth_user_id();
$csrf = csrf_token();

$flash = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!csrf_verify($token)) {
        $error = 'Sesión inválida. Recargá e intentá de nuevo.';
    } else {
        $description = trim((string)($_POST['description'] ?? ''));
        $amount = (string)($_POST['amount'] ?? '');
        $currency = trim((string)($_POST['currency'] ?? 'ARS'));
        $date = trim((string)($_POST['entry_date'] ?? ''));

        try {
            $pdo = db($config);
            finance_create($pdo, $userId, 'expense', $description, $amount, $currency, $date);
            $flash = 'Egreso guardado.';
        } catch (Throwable $e) {
            error_log('expense.php error: ' . $e->getMessage());
            $error = ($config['app']['env'] ?? 'production') === 'production'
                ? 'No se pudo guardar el egreso.'
                : ('Error: ' . $e->getMessage());
        }
    }
}

$totals = [];
$rows = [];
try {
    $pdo = db($config);
    $totals = finance_total($pdo, $userId, 'expense');
    $rows = finance_list($pdo, $userId, 'expense', 50);
} catch (Throwable $e) {
    error_log('expense.php load error: ' . $e->getMessage());
    $error = $error !== '' ? $error : (
        ($config['app']['env'] ?? 'production') === 'production'
            ? 'No se pudieron cargar los egresos. ¿Ejecutaste el schema.sql?'
            : ('Error: ' . $e->getMessage())
    );
}

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($appName) ?> - Egresos</title>
  <link rel="icon" type="image/png" href="/logo.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --accent:#dc2626; --accent-rgb:220,38,38; --accent-dark:#b91c1c; --accent-2:#f97316; --accent-2-rgb:249,115,22; --ink:#0b1727; --muted:#6b7280; --card:rgba(255,255,255,.9); }
    body { font-family:'Space Grotesk','Segoe UI',sans-serif; background: radial-gradient(circle at 10% 20%, rgba(var(--accent-rgb),.16), transparent 35%), radial-gradient(circle at 90% 10%, rgba(var(--accent-2-rgb),.14), transparent 35%), linear-gradient(120deg,#f7fafc,#eef2ff); color:var(--ink); min-height:100vh; }
    .navbar-glass { background:rgba(255,255,255,.9); backdrop-filter:blur(12px); border:1px solid rgba(15,23,42,.06); box-shadow:0 10px 40px rgba(15,23,42,.08); }
    .navbar-glass .container { padding-left: calc(.75rem + env(safe-area-inset-left)); padding-right: calc(.75rem + env(safe-area-inset-right)); }
    .page-shell { padding:2.5rem 0; }
    .card-lift { background:var(--card); border:1px solid rgba(15,23,42,.06); box-shadow:0 18px 50px rgba(15,23,42,.07); border-radius:18px; }
    .card-header-clean { border-bottom:1px solid rgba(15,23,42,.06); }
    .pill { display:inline-flex; align-items:center; gap:.4rem; padding:.35rem .75rem; border-radius:999px; background:rgba(var(--accent-rgb),.1); color:var(--accent); font-weight:600; font-size:.9rem; }
    .action-btn { border-radius:12px; padding-inline:1.25rem; font-weight:600; }
    .btn-primary, .btn-primary:hover, .btn-primary:focus { background:linear-gradient(135deg,var(--accent),var(--accent-dark)); border:none; box-shadow:0 10px 30px rgba(var(--accent-rgb),.25); }
    .btn-outline-primary { border-color:var(--accent); color:var(--accent); }
    .btn-outline-primary:hover, .btn-outline-primary:focus { background:rgba(var(--accent-rgb),.1); color:var(--accent); border-color:var(--accent); }
    .table thead th { background:rgba(var(--accent-rgb),.08); border-bottom:none; font-weight:600; color:var(--ink); }
    .table td, .table th { border-color:rgba(148,163,184,.35); }
    .muted-label { color:var(--muted); font-weight:600; text-transform:uppercase; letter-spacing:.04em; font-size:.8rem; }
    @media (max-width:768px){ .page-shell{padding:1.5rem .75rem; padding-left:calc(.75rem + env(safe-area-inset-left)); padding-right:calc(.75rem + env(safe-area-inset-right));} .card-lift{border-radius:14px} }
    .navbar-logo { height:34px; width:auto; display:inline-block; }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-glass sticky-top">
  <div class="container py-2">
    <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-dark mb-0 h4 text-decoration-none" href="/dashboard">
      <img src="/logo.png" alt="Logo" class="navbar-logo">
      <span><?= e($appName) ?></span>
    </a>
    <div class="d-flex flex-wrap align-items-center gap-2 ms-auto justify-content-end">
      <span class="pill">Admin</span>
      <a class="btn btn-outline-primary btn-sm" href="/dashboard">Volver</a>
      <form method="post" action="/logout.php" class="d-flex">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <button type="submit" class="btn btn-outline-danger btn-sm">Salir</button>
      </form>
    </div>
  </div>
</nav>

<main class="container page-shell">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-10 col-xl-9">
      <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
          <p class="muted-label mb-1">Finanzas</p>
          <h1 class="h3 mb-0">Egresos</h1>
        </div>
        <span class="text-muted">Usuario #<?= e((string)$userId) ?></span>
      </div>

      <?php if ($flash !== ''): ?>
        <div class="alert alert-success" role="alert"><?= e($flash) ?></div>
      <?php endif; ?>
      <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <div class="card card-lift mb-4">
        <div class="card-header card-header-clean bg-white px-4 py-3">
          <p class="muted-label mb-1">Alta</p>
          <h2 class="h5 mb-0">Registrar egreso</h2>
        </div>
        <div class="card-body px-4 py-4">
          <form method="post" action="/expense">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label" for="description">Descripción</label>
                <input class="form-control" id="description" name="description" required>
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label" for="amount">Monto</label>
                <input class="form-control" id="amount" name="amount" inputmode="decimal" placeholder="0,00" required>
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label" for="currency">Moneda</label>
                <input class="form-control" id="currency" name="currency" value="ARS" maxlength="3">
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label" for="entry_date">Fecha</label>
                <input class="form-control" id="entry_date" name="entry_date" type="date" value="<?= e((new DateTimeImmutable('now'))->format('Y-m-d')) ?>">
              </div>
            </div>
            <div class="d-flex justify-content-end mt-3">
              <button class="btn btn-primary action-btn" type="submit">Guardar</button>
            </div>
          </form>
        </div>
      </div>

      <div class="card card-lift mb-4">
        <div class="card-header card-header-clean bg-white px-4 py-3">
          <p class="muted-label mb-1">Totales</p>
          <h2 class="h5 mb-0">Egresos acumulados</h2>
        </div>
        <div class="card-body px-4 py-4">
          <?php if (count($totals) === 0): ?>
            <div class="text-muted">Sin egresos registrados.</div>
          <?php else: ?>
            <div class="row g-3">
              <?php foreach ($totals as $cur => $cents): ?>
                <div class="col-12 col-md-6">
                  <div class="p-3 rounded-4 border bg-white">
                    <div class="muted-label">Total (<?= e((string)$cur) ?>)</div>
                    <div class="fs-4 fw-bold mt-1"><?= e(money_format_cents((int)$cents, (string)$cur)) ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card card-lift">
        <div class="card-header card-header-clean bg-white px-4 py-3">
          <p class="muted-label mb-1">Historial</p>
          <h2 class="h5 mb-0">Últimos egresos</h2>
        </div>
        <div class="card-body px-4 py-4">
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th style="width:120px">Fecha</th>
                  <th>Descripción</th>
                  <th style="width:180px" class="text-end">Monto</th>
                </tr>
              </thead>
              <tbody>
              <?php if (count($rows) === 0): ?>
                <tr><td colspan="3" class="text-muted">Sin resultados.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <td><?= e((string)$r['entry_date']) ?></td>
                    <td><?= e((string)$r['description']) ?></td>
                    <td class="text-end"><?= e(money_format_cents((int)$r['amount_cents'], (string)$r['currency'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </div>
</main>
</body>
</html>
