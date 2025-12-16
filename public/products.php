<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

auth_require_login();

$config = app_config();
$appName = (string)($config['app']['name'] ?? 'Dietetics');
$userId = (int)auth_user_id();
$csrf = csrf_token();

$period = (string)($_GET['period'] ?? 'day');
$q = trim((string)($_GET['q'] ?? ''));
$format = strtolower(trim((string)($_GET['format'] ?? '')));
$allowedLimits = [20, 50, 100, 120];
$limitRaw = (int)($_GET['limit'] ?? 20);
$limit = in_array($limitRaw, $allowedLimits, true) ? $limitRaw : 20;

function products_build_url(array $params): string
{
  $period = (string)($params['period'] ?? '');
  $limit = (string)($params['limit'] ?? '');

  $path = '/products';
  if ($period !== '') {
    $path .= '/' . rawurlencode($period);
    if ($limit !== '') {
      $path .= '/' . rawurlencode($limit);
    }
  }

    $clean = [];
    foreach ($params as $k => $v) {
    if (in_array($k, ['period', 'limit'], true)) continue;
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
  $rows = reports_products_list($pdo, $userId, $p['start'], $p['end'], $q, $limit);

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
                'limit' => (string)$limit,
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
    @media (max-width:768px){ .page-shell{padding:1.5rem 0} .card-lift{border-radius:14px} }

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
    <div class="d-flex align-items-center gap-2 ms-auto">
      <span class="pill">Admin</span>
      <a class="btn btn-outline-primary btn-sm d-inline-flex align-items-center" href="/dashboard">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="me-1" aria-hidden="true">
          <path d="M6.5 3.5 2.5 8l4 4.5" />
          <path d="M3 8h10.5" />
        </svg>
        Volver
      </a>
      <form method="post" action="/logout.php" class="d-flex">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <button type="submit" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="me-1" aria-hidden="true">
            <path d="M6 2.5H3.8c-.7 0-1.3.6-1.3 1.3v8.4c0 .7.6 1.3 1.3 1.3H6" />
            <path d="M10 11.5 13.5 8 10 4.5" />
            <path d="M13.5 8H6.2" />
          </svg>
          Salir
        </button>
      </form>
    </div>
  </div>
</nav>

<main class="container page-shell" id="liveMain">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-10 col-xl-9">
      <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
          <p class="muted-label mb-1">Panel</p>
          <h1 class="h3 mb-0">Productos vendidos</h1>
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
            <h2 class="h5 mb-0">Seleccionar período</h2>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-sm action-btn <?= e(btn_active($p['key'], 'day')) ?>" href="<?= e(products_build_url(['period' => 'day', 'q' => $q, 'limit' => (string)$limit])) ?>">Día</a>
            <a class="btn btn-sm action-btn <?= e(btn_active($p['key'], 'week')) ?>" href="<?= e(products_build_url(['period' => 'week', 'q' => $q, 'limit' => (string)$limit])) ?>">Semana</a>
            <a class="btn btn-sm action-btn <?= e(btn_active($p['key'], 'month')) ?>" href="<?= e(products_build_url(['period' => 'month', 'q' => $q, 'limit' => (string)$limit])) ?>">Mes</a>
            <a class="btn btn-sm action-btn <?= e(btn_active($p['key'], 'year')) ?>" href="<?= e(products_build_url(['period' => 'year', 'q' => $q, 'limit' => (string)$limit])) ?>">Año</a>
          </div>
        </div>
        <div class="card-body px-4 py-4">
          <form id="liveSearchForm" method="get" action="/products" class="d-flex flex-column flex-md-row gap-2 align-items-md-center justify-content-between">
            <input type="hidden" name="period" value="<?= e($p['key']) ?>">
            <div class="d-flex gap-2 flex-grow-1">
              <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Buscar por producto" aria-label="Buscar">
              <select class="form-select" name="limit" style="max-width: 140px" aria-label="Cantidad">
                <?php foreach ($allowedLimits as $opt): ?>
                  <option value="<?= e((string)$opt) ?>" <?= $opt === $limit ? 'selected' : '' ?>><?= e((string)$opt) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-outline-primary action-btn" type="submit">Buscar</button>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <a class="btn btn-outline-secondary btn-sm" href="<?= e(products_build_url(['period' => $p['key'], 'q' => $q, 'limit' => (string)$limit, 'format' => 'csv'])) ?>">CSV</a>
              <a class="btn btn-outline-secondary btn-sm" href="<?= e(products_build_url(['period' => $p['key'], 'q' => $q, 'limit' => (string)$limit, 'format' => 'xml'])) ?>">XML</a>
              <a class="btn btn-outline-secondary btn-sm" href="<?= e(products_build_url(['period' => $p['key'], 'q' => $q, 'limit' => (string)$limit, 'format' => 'xlsx'])) ?>">XLSX</a>
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
                  <th style="width:120px" class="text-end">Facturas</th>
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
                    <td class="text-end"><?= e(number_format((float)$r['quantity_sum'], 2, ',', '.')) ?></td>
                    <td class="text-end"><?= e((string)$r['invoices_count']) ?></td>
                    <td class="text-end"><?= e(money_format_cents((int)$r['total_cents'], (string)$r['currency'])) ?></td>
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
<script>
(() => {
  const DEBOUNCE_MS = 250;

  const buildUrlFromForm = (form) => {
    const action = form.getAttribute('action') || window.location.pathname;
    const url = new URL(action, window.location.origin);
    const params = new URLSearchParams(new FormData(form));
    url.search = params.toString();
    return url;
  };

  const wireLiveSearch = () => {
    const form = document.getElementById('liveSearchForm');
    const main = document.getElementById('liveMain');
    if (!form || !main) return;

    const qInput = form.querySelector('input[name="q"]');
    const limitSelect = form.querySelector('select[name="limit"]');

    let timer = null;
    let controller = null;

    const run = () => {
      if (timer) window.clearTimeout(timer);
      timer = window.setTimeout(async () => {
        const url = buildUrlFromForm(form);

        const focused = document.activeElement;
        const shouldRestoreFocus = focused === qInput;
        const selStart = qInput && typeof qInput.selectionStart === 'number' ? qInput.selectionStart : null;
        const selEnd = qInput && typeof qInput.selectionEnd === 'number' ? qInput.selectionEnd : null;

        if (controller) controller.abort();
        controller = new AbortController();

        try {
          const resp = await fetch(url.toString(), {
            signal: controller.signal,
            headers: { 'X-Requested-With': 'fetch' },
          });
          const html = await resp.text();
          const doc = new DOMParser().parseFromString(html, 'text/html');
          const newMain = doc.getElementById('liveMain');
          if (!newMain) return;

          main.replaceWith(newMain);
          history.replaceState(null, '', url.pathname + (url.search || ''));

          if (shouldRestoreFocus) {
            const newQ = document.querySelector('#liveSearchForm input[name="q"]');
            if (newQ instanceof HTMLInputElement) {
              newQ.focus();
              if (typeof selStart === 'number' && typeof selEnd === 'number') {
                try { newQ.setSelectionRange(selStart, selEnd); } catch (e) {}
              }
            }
          }

          wireLiveSearch();
        } catch (e) {
          if (e && e.name === 'AbortError') return;
        }
      }, DEBOUNCE_MS);
    };

    form.addEventListener('submit', (ev) => {
      ev.preventDefault();
      run();
    });
    if (qInput) qInput.addEventListener('input', run);
    if (limitSelect) limitSelect.addEventListener('change', run);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', wireLiveSearch);
  } else {
    wireLiveSearch();
  }
})();
</script>
</body>
</html>
