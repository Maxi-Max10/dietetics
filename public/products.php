<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

auth_require_login();

$config = app_config();
$appName = (string)($config['app']['name'] ?? 'Dietetic');
$userId = (int)auth_user_id();
$csrf = csrf_token();

$period = (string)($_GET['period'] ?? 'day');
$q = trim((string)($_GET['q'] ?? ''));
$format = strtolower(trim((string)($_GET['format'] ?? '')));
$limit = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

function products_build_url(array $params): string
{
  $period = (string)($params['period'] ?? '');

  $path = '/products';
  if ($period !== '') {
    $path .= '/' . rawurlencode($period);
  }

    $clean = [];
    foreach ($params as $k => $v) {
      if ($k === 'period' || $k === 'limit') continue;
      if ($v === null) continue;
      $v = (string)$v;
      if ($v === '') continue;
      $clean[$k] = $v;
    }
  return $path . (count($clean) ? ('?' . http_build_query($clean)) : '');
}

function btn_active(string $current, string $key): string
{
    return $current === $key ? 'btn-primary' : 'btn-outline-primary';
}

try {
    $pdo = db($config);
    $p = sales_period($period);
  $rows = reports_products_list($pdo, $userId, $p['start'], $p['end'], $q, $limit, $page);

  // Para paginación: obtener el total de productos (sin LIMIT)
  $totalRowsStmt = $pdo->prepare(
    'SELECT COUNT(DISTINCT CONCAT(ii.description, ":", COALESCE(ii.unit, ""), ":", inv.currency)) as total
     FROM invoice_items ii
     INNER JOIN invoices inv ON inv.id = ii.invoice_id
     WHERE inv.created_by = :user_id AND inv.created_at >= :start AND inv.created_at < :end' . ($q !== '' ? ' AND (ii.description LIKE :q)' : '')
  );
  $params = [
    'user_id' => $userId,
    'start' => $p['start']->format('Y-m-d H:i:s'),
    'end' => $p['end']->format('Y-m-d H:i:s'),
  ];
  if ($q !== '') {
    $params['q'] = '%' . $q . '%';
  }
  $totalRowsStmt->execute($params);
  $totalRows = (int)($totalRowsStmt->fetchColumn() ?: 0);
  $totalPages = max(1, (int)ceil($totalRows / $limit));

    if (in_array($format, ['csv', 'xml', 'xlsx'], true)) {
        try {
            $stamp = date('Ymd-His');
            $base = 'productos-' . $p['key'] . '-' . $stamp;

            $headers = ['description', 'currency', 'quantity_sum', 'invoices_count', 'total_cents'];

            if ($format === 'csv') {
                reports_export_csv($headers, $rows, $base . '.csv');
                exit;
            }

            if ($format === 'xml') {
                reports_export_xml('products', 'product', $rows, [
                    'period' => $p['key'],
                    'start' => $p['start']->format('Y-m-d H:i:s'),
                    'end' => $p['end']->format('Y-m-d H:i:s'),
                    'q' => $q,
                ], $base . '.xml');
                exit;
            }

            reports_export_xlsx('Productos', $headers, $rows, $base . '.xlsx');
            exit;
        } catch (Throwable $exportError) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=UTF-8');
            $msg = ($config['app']['env'] ?? 'production') === 'production'
                ? 'No se pudo generar el reporte.'
                : ('Error: ' . $exportError->getMessage());
            echo $msg;
            exit;
        }
    }

    $error = '';
} catch (Throwable $e) {
    $p = sales_period('day');
    $rows = [];
    $error = ($config['app']['env'] ?? 'production') === 'production'
        ? 'No se pudieron cargar los productos.'
        : ('Error: ' . $e->getMessage());
}

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($appName) ?> - Productos vendidos</title>
  <link rel="icon" type="image/png" href="/logo.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --accent:#059669; --accent-rgb:5,150,105; --accent-dark:#047857; --accent-2:#f59e0b; --accent-2-rgb:245,158,11; --ink:#0b1727; --muted:#6b7280; --card:rgba(255,255,255,.9); }
    body { font-family:'Space Grotesk','Segoe UI',sans-serif; background: radial-gradient(circle at 10% 20%, rgba(var(--accent-rgb),.18), transparent 35%), radial-gradient(circle at 90% 10%, rgba(var(--accent-2-rgb),.18), transparent 35%), linear-gradient(120deg,#f7fafc,#eef2ff); color:var(--ink); min-height:100vh; }
    .navbar-glass { background:rgba(255,255,255,.9); backdrop-filter:blur(12px); border:1px solid rgba(15,23,42,.06); box-shadow:0 10px 40px rgba(15,23,42,.08); }

    .navbar-glass .container {
      padding-left: calc(.75rem + env(safe-area-inset-left));
      padding-right: calc(.75rem + env(safe-area-inset-right));
    }
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
<body>
<nav class="navbar navbar-expand-lg navbar-glass sticky-top">
  <div class="container py-2">
    <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-dark mb-0 h4 text-decoration-none" href="/dashboard">
      <img src="/logo.png" alt="Logo" class="navbar-logo">
      <span><?= e($appName) ?></span>
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
          <a class="nav-link-pill is-active" href="/products" aria-current="page">Productos</a>
          <a class="nav-link-pill" href="/catalogo">Catálogo</a>
          <a class="nav-link-pill" href="/income">Ingresos</a>
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
      <h5 class="offcanvas-title mb-0" id="appNavOffcanvasLabel"><?= e($appName) ?></h5>
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

<main class="container page-shell" id="liveMain">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-10 col-xl-9">
      <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
          <p class="muted-label mb-1">Panel</p>
          <h1 class="h3 mb-0">Productos vendidos</h1>
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
            <h2 class="h5 mb-0">Seleccionar período</h2>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-sm action-btn <?= e(btn_active($p['key'], 'day')) ?>" href="<?= e(products_build_url(['period' => 'day', 'q' => $q])) ?>">Día</a>
            <a class="btn btn-sm action-btn <?= e(btn_active($p['key'], 'week')) ?>" href="<?= e(products_build_url(['period' => 'week', 'q' => $q])) ?>">Semana</a>
            <a class="btn btn-sm action-btn <?= e(btn_active($p['key'], 'month')) ?>" href="<?= e(products_build_url(['period' => 'month', 'q' => $q])) ?>">Mes</a>
            <a class="btn btn-sm action-btn <?= e(btn_active($p['key'], 'year')) ?>" href="<?= e(products_build_url(['period' => 'year', 'q' => $q])) ?>">Año</a>
          </div>
        </div>
        <div class="card-body px-4 py-4">
          <form id="liveSearchForm" method="get" action="/products" class="d-flex flex-column flex-md-row gap-2 align-items-md-center justify-content-between">
            <input type="hidden" name="period" value="<?= e($p['key']) ?>">
            <div class="d-flex gap-2 flex-grow-1">
              <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Buscar por producto" aria-label="Buscar">
            </div>
            <div class="d-flex flex-wrap gap-2">
              <a class="btn btn-outline-secondary btn-sm" href="<?= e(products_build_url(['period' => $p['key'], 'q' => $q, 'format' => 'csv'])) ?>">CSV</a>
              <a class="btn btn-outline-secondary btn-sm" href="<?= e(products_build_url(['period' => $p['key'], 'q' => $q, 'format' => 'xml'])) ?>">XML</a>
              <a class="btn btn-outline-secondary btn-sm" href="<?= e(products_build_url(['period' => $p['key'], 'q' => $q, 'format' => 'xlsx'])) ?>">XLSX</a>
            </div>
          </form>
        </div>
      </div>

      <div class="card card-lift">
        <div class="card-header card-header-clean bg-white px-4 py-3">
          <p class="muted-label mb-1">Detalle</p>
          <h2 class="h5 mb-0">Productos del período</h2>
        </div>
        <div class="card-body px-4 py-4">
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Producto</th>
                  <th style="width:110px" class="text-end">Cantidad</th>
                  
                  <th style="width:180px" class="text-end">Total</th>
                </tr>
              </thead>
              <tbody>
              <?php if (count($rows) === 0): ?>
                <tr><td colspan="4" class="text-muted">Sin resultados.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <td><?= e((string)$r['description']) ?> <span class="text-muted">(<?= e((string)$r['currency']) ?>)</span></td>
                    <td class="text-end"><?= e(number_format((float)$r['quantity_sum'], 2, ',', '.')) ?> <span class="text-muted"><?= e((string)($r['unit'] ?? '')) ?></span></td>
                    
                    <td class="text-end"><?= e(money_format_cents((int)$r['total_cents'], (string)$r['currency'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <!-- Paginación -->
        <?php if ($totalPages > 1): ?>
        <nav aria-label="Paginación de productos">
          <ul class="pagination justify-content-center mt-4">
            <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
              <a class="page-link" href="<?= e(products_build_url(['period' => $p['key'], 'q' => $q, 'page' => $page - 1])) ?>">Anterior</a>
            </li>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <li class="page-item<?= $i === $page ? ' active' : '' ?>">
                <a class="page-link" href="<?= e(products_build_url(['period' => $p['key'], 'q' => $q, 'page' => $i])) ?>"><?= $i ?></a>
              </li>
            <?php endfor; ?>
            <li class="page-item<?= $page >= $totalPages ? ' disabled' : '' ?>">
              <a class="page-link" href="<?= e(products_build_url(['period' => $p['key'], 'q' => $q, 'page' => $page + 1])) ?>">Siguiente</a>
            </li>
          </ul>
        </nav>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
(() => {
  const normalize = (value) => {
    const s = (value || '').toString().trim().toLowerCase();
    return s.normalize ? s.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : s;
  };

  const wireLiveFilter = () => {
    const form = document.getElementById('liveSearchForm');
    const main = document.getElementById('liveMain');
    if (!form || !main) return;

    const qInput = form.querySelector('input[name="q"]');
    const table = main.querySelector('table');
    const tbody = table && table.tBodies && table.tBodies[0];
    if (!(qInput instanceof HTMLInputElement) || !tbody) return;

    const allRows = Array.from(tbody.rows).filter((r) => !r.hasAttribute('data-live-empty') && !r.hasAttribute('data-live-no-results'));
    const colCount = (table.tHead && table.tHead.rows[0]) ? table.tHead.rows[0].cells.length : (allRows[0] ? allRows[0].cells.length : 1);

    let noRow = tbody.querySelector('tr[data-live-no-results]');
    if (!noRow) {
      noRow = document.createElement('tr');
      noRow.setAttribute('data-live-no-results', '1');
      const td = document.createElement('td');
      td.colSpan = colCount;
      td.className = 'text-muted';
      td.textContent = 'Sin resultados.';
      noRow.appendChild(td);
      noRow.style.display = 'none';
      tbody.appendChild(noRow);
    }

    const apply = () => {
      const q = normalize(qInput.value);
      let shown = 0;
      for (const row of allRows) {
        const haystack = normalize(row.textContent);
        const match = q === '' || haystack.includes(q);
        row.style.display = match ? '' : 'none';
        if (match) shown++;
      }
      noRow.style.display = (shown === 0 && q !== '') ? '' : 'none';
    };

    qInput.addEventListener('input', apply);
    apply();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', wireLiveFilter);
  } else {
    wireLiveFilter();
  }
})();
</script>
</body>
</html>
