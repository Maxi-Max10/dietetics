<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

auth_require_login();

$config = app_config();
$appName = (string)($config['app']['name'] ?? 'Dietetic');
$userId = (int)auth_user_id();
$csrf = csrf_token();

$newOrdersCount = 0;
try {
  $pdoNav = db($config);
  $newOrdersCount = orders_count_new($pdoNav, $userId);
} catch (Throwable $e) {
  error_log('income.php nav count error: ' . $e->getMessage());
  $newOrdersCount = 0;
}

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
$legacyCutoff = invoices_legacy_cutoff_string();
$lineTotalSql = invoices_legacy_line_total_sql('inv', 'ii');

try {
  $pdo = db($config);

  $where = 'inv.created_by = :user_id AND inv.created_at >= :start AND inv.created_at < :end';
  $params = [
    'user_id' => $userId,
    'start' => $p['start']->format('Y-m-d H:i:s'),
    'end' => $p['end']->format('Y-m-d H:i:s'),
    'legacy_cutoff' => $legacyCutoff,
  ];

  if ($q !== '') {
    $where .= ' AND (ii.description LIKE :q OR inv.customer_name LIKE :q OR inv.customer_email LIKE :q)';
    $params['q'] = '%' . $q . '%';
  }

  $stmtTotals = $pdo->prepare(
    'SELECT inv.currency, COALESCE(SUM(' . $lineTotalSql . '), 0) AS total_cents
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
        ii.description, ii.quantity, COALESCE(ii.unit, "") AS unit, ' . $lineTotalSql . ' AS line_total_cents
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
  <title>Ingresos</title>
  <link rel="icon" type="image/png" href="/logo.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="stylesheet" href="/brand.css?v=20260118-2">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --accent:#463B1E; --accent-rgb:70,59,30; --accent-dark:#2f2713; --accent-2:#96957E; --accent-2-rgb:150,149,126; --ink:#241e10; --muted:#6b6453; --card:rgba(255,255,255,.9); }
    body { font-family:'Space Grotesk','Segoe UI',sans-serif; background: radial-gradient(circle at 10% 20%, rgba(var(--accent-2-rgb),.22), transparent 38%), radial-gradient(circle at 90% 10%, rgba(var(--accent-rgb),.12), transparent 40%), linear-gradient(120deg,#fbfaf6,#E7E3D5); color:var(--ink); min-height:100vh; }
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

    .nav-toggle-btn { border-radius:12px; font-weight:600; }
    .offcanvas-nav .list-group-item { border:1px solid rgba(15,23,42,.06); border-radius:14px; margin-bottom:.6rem; background:rgba(255,255,255,.85); box-shadow:0 10px 30px rgba(15,23,42,.06); }
    .offcanvas-nav .list-group-item:active { transform: translateY(1px); }

    .nav-shell {
      display: inline-flex;
      align-items: center;
      gap: .25rem;
      padding: .25rem;
      border-radius: 999px;
      background: rgba(15, 23, 42, .04);
      border: 1px solid rgba(15, 23, 42, .08);
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, .65);
    }

    .nav-link-pill {
      appearance: none;
      -webkit-appearance: none;
      display: inline-flex;
      align-items: center;
      gap: .45rem;
      padding: .45rem .85rem;
      border-radius: 999px;
      font-weight: 650;
      font-size: .92rem;
      line-height: 1;
      text-decoration: none;
      white-space: nowrap;
      cursor: pointer;
      color: rgba(15, 23, 42, .78);
      background: transparent;
      border: 1px solid transparent;
      transition: background .15s ease, box-shadow .15s ease, color .15s ease, transform .05s ease;
    }

    .nav-link-pill:hover,
    .nav-link-pill:focus {
      background: rgba(15, 23, 42, .06);
      color: var(--ink);
    }

    .nav-link-pill.is-active,
    .nav-link-pill[aria-current="page"] {
      background: linear-gradient(135deg, var(--accent), var(--accent-dark));
      color: #fff;
      box-shadow: 0 12px 30px rgba(var(--accent-rgb), .28);
    }

    .nav-link-pill:active { transform: translateY(1px); }

    .nav-link-pill--danger {
      color: #b91c1c;
      background: rgba(220, 38, 38, .08);
      border-color: rgba(220, 38, 38, .18);
    }

    .nav-link-pill--danger:hover,
    .nav-link-pill--danger:focus {
      background: rgba(220, 38, 38, .12);
      color: #991b1b;
    }
  </style>
</head>
<body class="has-leaves-bg">
<div class="bg-leaves" aria-hidden="true">
  <div class="bg-leaf leaf-1"></div>
  <div class="bg-leaf leaf-2"></div>
  <div class="bg-leaf leaf-3"></div>
  <div class="bg-leaf leaf-4"></div>
  <div class="bg-leaf leaf-5"></div>
  <div class="bg-leaf leaf-6"></div>
  <div class="bg-leaf leaf-7"></div>
  <div class="bg-leaf leaf-8"></div>
  <div class="bg-leaf leaf-9"></div>
  <div class="bg-leaf leaf-10"></div>
  <div class="bg-leaf leaf-11"></div>
  <div class="bg-leaf leaf-12"></div>
</div>
<nav class="navbar navbar-expand-lg navbar-glass sticky-top">
  <div class="container py-2">
    <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-dark mb-0 h4 text-decoration-none" href="/dashboard" aria-label="<?= e($appName) ?>">
      <img src="/logo.png" alt="Logo" class="navbar-logo">
    </a>
    <div class="ms-auto d-flex align-items-center gap-2 justify-content-end">
      <span class="pill d-none d-lg-inline-flex">Admin</span>

      <button class="btn btn-outline-primary btn-sm d-inline-flex align-items-center d-lg-none nav-toggle-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#appNavOffcanvas" aria-controls="appNavOffcanvas">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="me-1" aria-hidden="true">
          <path d="M2.5 4h11" />
          <path d="M2.5 8h11" />
          <path d="M2.5 12h11" />
        </svg>
        Menú
      </button>

      <div class="d-none d-lg-flex align-items-center gap-2 justify-content-end">
        <div class="nav-shell" role="navigation" aria-label="Secciones">
          <a class="nav-link-pill" href="/dashboard">Dashboard</a>
          <a class="nav-link-pill" href="/sales">Ventas</a>
          <a class="nav-link-pill" href="/customers">Clientes</a>
          <a class="nav-link-pill" href="/products">Productos</a>
          <a class="nav-link-pill" href="/catalogo">Catálogo</a>
          <a class="nav-link-pill" href="/pedidos">Pedidos<?php if ($newOrdersCount > 0): ?><span class="badge rounded-pill text-bg-danger ms-1"><?= e((string)$newOrdersCount) ?></span><?php endif; ?></a>
          <a class="nav-link-pill is-active" href="/income" aria-current="page">Ingresos</a>
          <a class="nav-link-pill" href="/expense">Egresos</a>
          <a class="nav-link-pill" href="/stock">Stock</a>
        </div>

        <form method="post" action="/logout.php" class="d-flex">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <button type="submit" class="nav-link-pill nav-link-pill--danger">Salir</button>
        </form>
      </div>
    </div>
  </div>
</nav>

<div class="offcanvas offcanvas-end" tabindex="-1" id="appNavOffcanvas" aria-labelledby="appNavOffcanvasLabel">
  <div class="offcanvas-header">
    <div class="d-flex align-items-center gap-2">
      <img src="/logo.png" alt="Logo" class="navbar-logo">
      <h5 class="offcanvas-title mb-0 visually-hidden" id="appNavOffcanvasLabel"><?= e($appName) ?></h5>
      <span class="pill ms-1">Admin</span>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
  </div>
  <div class="offcanvas-body">
    <div class="list-group offcanvas-nav">
      <a class="list-group-item list-group-item-action" href="/dashboard">Dashboard</a>
      <a class="list-group-item list-group-item-action" href="/sales">Ventas</a>
      <a class="list-group-item list-group-item-action" href="/customers">Clientes</a>
      <a class="list-group-item list-group-item-action" href="/products">Productos</a>
      <a class="list-group-item list-group-item-action" href="/catalogo">Catálogo</a>
      <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="/pedidos"><span>Pedidos</span><?php if ($newOrdersCount > 0): ?><span class="badge rounded-pill text-bg-danger"><?= e((string)$newOrdersCount) ?></span><?php endif; ?></a>
      <a class="list-group-item list-group-item-action" href="/income">Ingresos</a>
      <a class="list-group-item list-group-item-action" href="/expense">Egresos</a>
      <a class="list-group-item list-group-item-action" href="/stock">Stock</a>
    </div>

    <div class="mt-3">
      <form method="post" action="/logout.php" class="d-flex">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <button type="submit" class="btn btn-outline-danger w-100">Salir</button>
      </form>
    </div>
  </div>
</div>

<main class="container page-shell">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-10 col-xl-9">
      <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
          <p class="muted-label mb-1">Finanzas</p>
          <h1 class="h3 mb-0">Ingresos</h1>
          <div class="text-muted mt-1"><?= e($p['label']) ?></div>
        </div>
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
                    <?php
                      $qtyText = (string)($r['quantity'] ?? '');
                      $qtyNorm = str_replace(',', '.', $qtyText);
                      $qtyValue = is_numeric($qtyNorm) ? (float)$qtyNorm : null;
                      $fmtQty = static function (float $v): string {
                        $s = number_format($v, 3, '.', '');
                        return rtrim(rtrim($s, '0'), '.');
                      };
                      $unitRaw = trim((string)($r['unit'] ?? ''));
                      if ($unitRaw !== '') {
                        $unitKey = invoice_normalize_unit($unitRaw);
                        $unitLabel = match ($unitKey) {
                          'g' => 'g',
                          'kg' => 'kg',
                          'ml' => 'ml',
                          'l' => 'l',
                          default => 'u',
                        };
                        if ($qtyValue !== null && $qtyValue < 1) {
                          if ($unitKey === 'kg') {
                            $qtyText = $fmtQty($qtyValue * 1000);
                            $unitLabel = 'g';
                          } elseif ($unitKey === 'l') {
                            $qtyText = $fmtQty($qtyValue * 1000);
                            $unitLabel = 'ml';
                          }
                        }
                        $qtyText = trim($qtyText) !== '' ? ($qtyText . ' ' . $unitLabel) : $qtyText;
                      }
                    ?>
                    <td class="text-end"><?= e($qtyText) ?></td>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
