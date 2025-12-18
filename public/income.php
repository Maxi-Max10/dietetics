<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

auth_require_login();

$config = app_config();
$appName = (string)($config['app']['name'] ?? 'Dietetics');
$userId = (int)auth_user_id();
$csrf = csrf_token();

$error = '';

$period = (string)($_GET['period'] ?? 'day');
$q = trim((string)($_GET['q'] ?? ''));
$limit = 50;

function income_build_url(array $params): string
{
  $period = (string)($params['period'] ?? '');

  $path = '/income';
  if ($period !== '') {
    $path .= '/' . rawurlencode($period);
  }

  $clean = [];
  foreach ($params as $k => $v) {
    if ($k === 'period' || $k === 'limit') {
      continue;
    }
    if ($v === null) {
      continue;
    }
    $v = (string)$v;
    if ($v === '') {
      continue;
    }
    $clean[$k] = $v;
  }
  return $path . (count($clean) ? ('?' . http_build_query($clean)) : '');
}

function income_active(string $current, string $key): string
{
  return $current === $key ? 'btn-primary' : 'btn-outline-primary';
}

$totals = [];
$rows = [];
$p = sales_period($period);

try {
  $pdo = db($config);

  $where = 'inv.created_by = :user_id AND inv.created_at >= :start AND inv.created_at < :end';
  $params = [
    'user_id' => $userId,
    'start' => $p['start']->format('Y-m-d H:i:s'),
    'end' => $p['end']->format('Y-m-d H:i:s'),
  ];

  if ($q !== '') {
    $where .= ' AND (ii.description LIKE :q OR inv.customer_name LIKE :q OR inv.customer_email LIKE :q)';
    $params['q'] = '%' . $q . '%';
  }

  $stmtTotals = $pdo->prepare(
    'SELECT inv.currency, COALESCE(SUM(ii.line_total_cents), 0) AS total_cents
     FROM invoice_items ii
     INNER JOIN invoices inv ON inv.id = ii.invoice_id
     WHERE ' . $where . '
     GROUP BY inv.currency
     ORDER BY inv.currency ASC'
  );
  $stmtTotals->execute($params);
  $totalsRows = $stmtTotals->fetchAll();
  foreach ($totalsRows as $tr) {
    $totals[(string)($tr['currency'] ?? 'ARS')] = (int)($tr['total_cents'] ?? 0);
  }

  // LIMIT con entero validado: evitamos placeholders por compatibilidad MySQL/PDO.
  $stmt = $pdo->prepare(
    'SELECT inv.id AS invoice_id, inv.customer_name, inv.customer_email, inv.currency, inv.created_at,
        ii.description, ii.quantity, ii.line_total_cents
     FROM invoice_items ii
     INNER JOIN invoices inv ON inv.id = ii.invoice_id
     WHERE ' . $where . '
     ORDER BY inv.created_at DESC, inv.id DESC, ii.id DESC
     LIMIT ' . $limit
  );
  $stmt->execute($params);
  $rows = $stmt->fetchAll();
} catch (Throwable $e) {
  error_log('income.php load error: ' . $e->getMessage());
  $rows = [];
  $totals = [];
  $error = ($config['app']['env'] ?? 'production') === 'production'
    ? 'No se pudieron cargar los ingresos. ¿Ejecutaste el schema.sql?'
    : ('Error: ' . $e->getMessage());
}

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($appName) ?> - Ingresos</title>
  <link rel="icon" type="image/png" href="/logo.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --accent:#16a34a; --accent-rgb:22,163,74; --accent-dark:#15803d; --accent-2:#22c55e; --accent-2-rgb:34,197,94; --ink:#0b1727; --muted:#6b7280; --card:rgba(255,255,255,.9); }
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
          <h1 class="h3 mb-0">Ingresos</h1>
          <div class="text-muted mt-1"><?= e($p['label']) ?></div>
        </div>
        <span class="text-muted">Usuario #<?= e((string)$userId) ?></span>
      </div>

      <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <div class="card card-lift mb-4">
        <div class="card-header card-header-clean bg-white px-4 py-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
          <div>
            <p class="muted-label mb-1">Rango</p>
            <h2 class="h5 mb-0">Ingresos por ventas</h2>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-sm action-btn <?= e(income_active($p['key'], 'day')) ?>" href="<?= e(income_build_url(['period' => 'day', 'q' => $q])) ?>">Día</a>
            <a class="btn btn-sm action-btn <?= e(income_active($p['key'], 'week')) ?>" href="<?= e(income_build_url(['period' => 'week', 'q' => $q])) ?>">Semana</a>
            <a class="btn btn-sm action-btn <?= e(income_active($p['key'], 'month')) ?>" href="<?= e(income_build_url(['period' => 'month', 'q' => $q])) ?>">Mes</a>
            <a class="btn btn-sm action-btn <?= e(income_active($p['key'], 'year')) ?>" href="<?= e(income_build_url(['period' => 'year', 'q' => $q])) ?>">Año</a>
          </div>
        </div>
        <div class="card-body px-4 py-4">
          <form method="get" action="/income" class="d-flex flex-column flex-md-row gap-2 align-items-md-center justify-content-between">
            <input type="hidden" name="period" value="<?= e($p['key']) ?>">
            <div class="d-flex gap-2 flex-grow-1">
              <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Buscar por producto o cliente" aria-label="Buscar">
            </div>
            <div class="text-muted">Ingresos = suma de subtotales de items vendidos</div>
          </form>
        </div>
      </div>

      <div class="card card-lift mb-4">
        <div class="card-header card-header-clean bg-white px-4 py-3">
          <p class="muted-label mb-1">Totales</p>
          <h2 class="h5 mb-0">Ingresos acumulados</h2>
        </div>
        <div class="card-body px-4 py-4">
          <?php if (count($totals) === 0): ?>
            <div class="text-muted">Sin ingresos registrados.</div>
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
          <h2 class="h5 mb-0">Últimos productos vendidos</h2>
        </div>
        <div class="card-body px-4 py-4">
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th style="width:200px">Fecha</th>
                  <th style="width:90px">#</th>
                  <th>Producto</th>
                  <th style="width:110px" class="text-end">Cant.</th>
                  <th style="width:180px" class="text-end">Subtotal</th>
                </tr>
              </thead>
              <tbody>
              <?php if (count($rows) === 0): ?>
                <tr><td colspan="5" class="text-muted">Sin resultados.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <td><?= e((string)($r['created_at'] ?? '')) ?></td>
                    <td><?= e((string)($r['invoice_id'] ?? '')) ?></td>
                    <td><?= e((string)($r['description'] ?? '')) ?> <span class="text-muted">(<?= e((string)($r['currency'] ?? 'ARS')) ?>)</span></td>
                    <td class="text-end"><?= e((string)($r['quantity'] ?? '')) ?></td>
                    <td class="text-end"><?= e(money_format_cents((int)($r['line_total_cents'] ?? 0), (string)($r['currency'] ?? 'ARS'))) ?></td>
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
