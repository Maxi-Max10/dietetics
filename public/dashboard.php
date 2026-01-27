<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

auth_require_login();

$showPreload = !empty($_SESSION['preload_dashboard']);
unset($_SESSION['preload_dashboard']);

$config = app_config();
$appName = (string)($config['app']['name'] ?? 'Dietetic');
$userId = (int)auth_user_id();
$csrf = csrf_token();

$newOrdersCount = 0;
try {
  $pdoNav = db($config);
  $newOrdersCount = orders_count_new($pdoNav, $userId);
} catch (Throwable $e) {
  error_log('dashboard.php nav count error: ' . $e->getMessage());
  $newOrdersCount = 0;
}

$flash = '';
$error = '';

$tzAr = new DateTimeZone('America/Argentina/Buenos_Aires');
$todayPeriod = sales_period('day', $tzAr);

$kpiIncomeText = '—';
$kpiSalesCount = 0;
$kpiTopProducts = [];

try {
  $pdoKpi = db($config);

  $summary = sales_summary($pdoKpi, $userId, $todayPeriod['start'], $todayPeriod['end']);
  $kpiSalesCount = array_sum(array_map(static fn(array $r): int => (int)($r['count'] ?? 0), $summary));

  $incomeCents = 0;
  $incomeCurrency = 'ARS';
  if (count($summary) > 0) {
    // Si hay múltiples monedas, mostramos ARS si existe; si no, la primera.
    foreach ($summary as $row) {
      $cur = strtoupper((string)($row['currency'] ?? 'ARS'));
      if ($cur === 'ARS') {
        $incomeCents = (int)($row['total_cents'] ?? 0);
        $incomeCurrency = 'ARS';
        break;
      }
    }

    if ($incomeCents === 0) {
      $first = $summary[0];
      $incomeCents = (int)($first['total_cents'] ?? 0);
      $incomeCurrency = strtoupper((string)($first['currency'] ?? 'ARS'));
    }
  }

  if ($kpiSalesCount > 0 || $incomeCents > 0) {
    $kpiIncomeText = money_format_cents($incomeCents, $incomeCurrency);
  }

  $stmtTop = $pdoKpi->prepare(
    'SELECT ii.description AS description,
            COALESCE(SUM(ii.quantity), 0) AS qty
     FROM invoice_items ii
     INNER JOIN invoices i ON i.id = ii.invoice_id
     WHERE i.created_by = :user_id
       AND i.created_at >= :start
       AND i.created_at < :end
     GROUP BY ii.description
     ORDER BY qty DESC
     LIMIT 3'
  );

  $stmtTop->execute([
    'user_id' => $userId,
    'start' => $todayPeriod['start']->format('Y-m-d H:i:s'),
    'end' => $todayPeriod['end']->format('Y-m-d H:i:s'),
  ]);

  foreach ($stmtTop->fetchAll() as $r) {
    $kpiTopProducts[] = [
      'description' => (string)($r['description'] ?? ''),
      'qty' => (float)($r['qty'] ?? 0),
    ];
  }
} catch (Throwable $e) {
  error_log('[dashboard_kpi_error] ' . get_class($e) . ': ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $isAjax = ((string)($_POST['ajax'] ?? '') === '1')
    || (isset($_SERVER['HTTP_ACCEPT']) && is_string($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

  $token = (string)($_POST['csrf_token'] ?? '');
  if (!csrf_verify($token)) {
    $error = 'Sesión inválida. Recargá e intentá de nuevo.';
    if ($isAjax) {
      http_response_code(400);
      header('Content-Type: application/json');
      echo json_encode(['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
      exit;
    }
  } else {
    $action = (string)($_POST['action'] ?? '');
    $customerName = trim((string)($_POST['customer_name'] ?? ''));
    $customerPhone = trim((string)($_POST['customer_phone'] ?? ''));
    $customerEmail = trim((string)($_POST['customer_email'] ?? ''));
    $customerDni = trim((string)($_POST['customer_dni'] ?? ''));
    $customerAddress = trim((string)($_POST['customer_address'] ?? ''));
    $detail = trim((string)($_POST['detail'] ?? ''));

    $descs = $_POST['item_description'] ?? [];
    $qtys = $_POST['item_quantity'] ?? [];
    $units = $_POST['item_unit'] ?? [];
    $prices = $_POST['item_unit_price'] ?? [];

    $items = [];
    if (is_array($descs) && is_array($qtys) && is_array($prices) && is_array($units)) {
      $count = min(count($descs), count($qtys), count($prices), count($units));
      for ($i = 0; $i < $count; $i++) {
        $desc = trim((string)$descs[$i]);
        $qty = (string)$qtys[$i];
        $unit = (string)$units[$i];
        $price = (string)$prices[$i];
        if ($desc === '' && trim($qty) === '' && trim($price) === '') {
          continue;
        }
        $items[] = ['description' => $desc, 'quantity' => $qty, 'unit' => $unit, 'unit_price' => $price];
      }
    }

    $invoiceId = 0;
    $validationError = false;
    try {
      $pdo = db($config);
      $invoiceId = invoices_create($pdo, $userId, $customerName, $customerEmail, $detail, $items, 'ARS', $customerDni, $customerPhone, $customerAddress);
    } catch (Throwable $e) {
      if ($e instanceof InvalidArgumentException) {
        $validationError = true;
        $error = $e->getMessage();
      } else {
        $errorId = bin2hex(random_bytes(4));
        error_log('[invoice_create_error ' . $errorId . '] ' . get_class($e) . ': ' . $e->getMessage());
        $error = ($config['app']['env'] ?? 'production') === 'production'
          ? ('No se pudo guardar la factura. (código ' . $errorId . ')')
          : ('Error: ' . $e->getMessage());
      }
    }

    // Modo AJAX: no renderizar HTML, devolver JSON para que el front actualice la gráfica sin recargar.
    if ($isAjax) {
      if ($error !== '' || $invoiceId <= 0) {
        http_response_code($validationError ? 400 : 500);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $error !== '' ? $error : 'No se pudo guardar la factura.'], JSON_UNESCAPED_UNICODE);
        exit;
      }

      header('Content-Type: application/json');
      echo json_encode(['ok' => true, 'invoice_id' => $invoiceId, 'action' => $action], JSON_UNESCAPED_UNICODE);
      exit;
    }

    if ($error === '' && $invoiceId > 0) {
      if ($action === 'download' || $action === 'email') {
        $download = null;
        try {
          $data = invoices_get($pdo, $invoiceId, $userId);
          $download = invoice_build_download($data);

          if (!is_array($download) || (string)($download['mime'] ?? '') !== 'application/pdf') {
            $errorId = bin2hex(random_bytes(4));
            error_log('[invoice_pdf_error ' . $errorId . '] Download mime inesperado: ' . (string)($download['mime'] ?? '')); 
            $error = 'La factura se guardó (ID ' . $invoiceId . ') pero no se pudo generar el PDF. (código ' . $errorId . ')';
            $download = null;
          }
        } catch (Throwable $e) {
          $errorId = bin2hex(random_bytes(4));
          error_log('[invoice_pdf_error ' . $errorId . '] ' . get_class($e) . ': ' . $e->getMessage());
          $error = ($config['app']['env'] ?? 'production') === 'production'
            ? ('La factura se guardó (ID ' . $invoiceId . ') pero no se pudo generar el PDF. (código ' . $errorId . ')')
            : ('Error: ' . $e->getMessage());
        }

        if ($error === '' && $download !== null) {
          if ($action === 'download') {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
            invoice_send_download($download);
            exit;
          }

          if ($action === 'email') {
            try {
              $tzAr = new DateTimeZone('America/Argentina/Buenos_Aires');
              $hourAr = (int)(new DateTimeImmutable('now', $tzAr))->format('H');
              $greeting = ($hourAr < 12) ? 'Buenos días' : (($hourAr < 20) ? 'Buenas tardes' : 'Buenas noches');
              $subject = 'Factura #' . $invoiceId . ' - ' . $appName;
              $body = '<p>' . $greeting . ' ' . e($customerName) . ',</p><p>Adjuntamos tu factura.</p><p>Gracias por tu compra.</p>';
              mail_send_with_attachment($config, $customerEmail, $customerName, $subject, $body, $download['bytes'], $download['filename'], $download['mime']);
              $flash = 'Factura enviada por email y guardada (ID ' . $invoiceId . ').';
            } catch (Throwable $e) {
              $errorId = bin2hex(random_bytes(4));
              error_log('[invoice_mail_error ' . $errorId . '] ' . get_class($e) . ': ' . $e->getMessage());
              $error = ($config['app']['env'] ?? 'production') === 'production'
                ? ('La factura se guardó (ID ' . $invoiceId . ') pero no se pudo enviar el email. (código ' . $errorId . ')')
                : ('Error: ' . $e->getMessage());
            }
          }
        }
      } else {
        $flash = 'Factura guardada (ID ' . $invoiceId . ').';
      }
    }
  }
}

$modal = null;
if ($error !== '') {
  $modal = ['type' => 'danger', 'title' => 'Error', 'message' => $error];
} elseif ($flash !== '') {
  $modal = ['type' => 'success', 'title' => 'Listo', 'message' => $flash];
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard</title>
  <link rel="icon" type="image/png" href="/logo.png">
  <link rel="preload" as="image" href="/preload.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="stylesheet" href="/brand.css?v=20260118-2">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --accent: #463B1E;
      --accent-rgb: 70, 59, 30;
      --accent-2: #96957E;
      --ink: #241e10;
      --muted: #6b6453;
      --card: rgba(255, 255, 255, 0.9);

      /* Colores por vista (para que cada botón del navbar represente su sección) */
      --view-sales: #96957E;
      --view-sales-rgb: 150, 149, 126;
      --view-customers: #96957E;
      --view-customers-rgb: 150, 149, 126;
      --view-products: #96957E;
      --view-products-rgb: 150, 149, 126;
      --view-income: #96957E;
      --view-income-rgb: 150, 149, 126;
      --view-expense: #96957E;
      --view-expense-rgb: 150, 149, 126;
      --view-stock: #96957E;
      --view-stock-rgb: 150, 149, 126;
    }

    body {
      font-family: 'Space Grotesk', 'Segoe UI', sans-serif;
      background: radial-gradient(circle at 10% 20%, rgba(150, 149, 126, 0.22), transparent 38%),
        radial-gradient(circle at 90% 10%, rgba(70, 59, 30, 0.12), transparent 40%),
        linear-gradient(120deg, #fbfaf6, #E7E3D5);
      color: var(--ink);
      min-height: 100vh;
    }

    .navbar-glass {
      background: rgba(255, 255, 255, 0.9);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(15, 23, 42, 0.06);
      box-shadow: 0 10px 40px rgba(15, 23, 42, 0.08);
    }

    .navbar-glass .container {
      padding-left: calc(.75rem + env(safe-area-inset-left));
      padding-right: calc(.75rem + env(safe-area-inset-right));
    }

    .nav-toggle-btn {
      border-radius: 12px;
      font-weight: 600;
    }

    .offcanvas-nav .list-group-item {
      border: 1px solid rgba(15, 23, 42, 0.06);
      border-radius: 14px;
      margin-bottom: .6rem;
      background: rgba(255, 255, 255, 0.85);
      box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
    }

    .offcanvas-nav .list-group-item:active {
      transform: translateY(1px);
    }

    .page-shell {
      padding: 2.5rem 0;
    }

    .card-lift {
      background: var(--card);
      border: 1px solid rgba(15, 23, 42, 0.06);
      box-shadow: 0 18px 50px rgba(15, 23, 42, 0.07);
      border-radius: 18px;
    }

    .card-header-clean {
      border-bottom: 1px solid rgba(15, 23, 42, 0.06);
    }

    .kpi-card {
      --kpi: var(--accent);
      --kpi-rgb: var(--accent-rgb);
      border-top: 4px solid var(--kpi);
      background: linear-gradient(180deg, rgba(var(--kpi-rgb), 0.10), var(--card));
    }

    .kpi-card--income { --kpi: var(--view-income); --kpi-rgb: var(--view-income-rgb); }
    .kpi-card--top { --kpi: var(--view-products); --kpi-rgb: var(--view-products-rgb); }
    .kpi-card--count { --kpi: var(--view-sales); --kpi-rgb: var(--view-sales-rgb); }

    .pill {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.35rem 0.75rem;
      border-radius: 999px;
      background: rgba(var(--accent-rgb), 0.10);
      color: var(--accent);
      font-weight: 600;
      font-size: 0.9rem;
    }

    .action-btn {
      border-radius: 12px;
      padding-inline: 1.25rem;
      font-weight: 600;
    }

    .btn-primary, .btn-primary:hover, .btn-primary:focus {
      background: linear-gradient(135deg, var(--accent), #2f2713);
      border: none;
      box-shadow: 0 10px 30px rgba(var(--accent-rgb), 0.25);
    }

    .btn-outline-primary {
      border-color: var(--accent);
      color: var(--accent);
    }

    .btn-outline-primary:hover, .btn-outline-primary:focus {
      background: rgba(var(--accent-rgb), 0.10);
      color: var(--accent);
      border-color: var(--accent);
    }

    /* Navbar: cada botón con el color de su vista */
    .nav-view-btn.btn-outline-primary {
      --nav-accent: var(--accent);
      --nav-accent-rgb: var(--accent-rgb);
      border-color: var(--nav-accent);
      color: var(--nav-accent);
      background: rgba(var(--nav-accent-rgb), 0.10);
    }

    .nav-view-btn.btn-outline-primary:hover,
    .nav-view-btn.btn-outline-primary:focus {
      background: rgba(var(--nav-accent-rgb), 0.16);
      color: var(--nav-accent);
      border-color: var(--nav-accent);
    }

    .nav-view-btn--sales { --nav-accent: var(--view-sales); --nav-accent-rgb: var(--view-sales-rgb); }
    .nav-view-btn--customers { --nav-accent: var(--view-customers); --nav-accent-rgb: var(--view-customers-rgb); }
    .nav-view-btn--products { --nav-accent: var(--view-products); --nav-accent-rgb: var(--view-products-rgb); }
    .nav-view-btn--income { --nav-accent: var(--view-income); --nav-accent-rgb: var(--view-income-rgb); }
    .nav-view-btn--expense { --nav-accent: var(--view-expense); --nav-accent-rgb: var(--view-expense-rgb); }
    .nav-view-btn--stock { --nav-accent: var(--view-stock); --nav-accent-rgb: var(--view-stock-rgb); }

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
      background: linear-gradient(135deg, var(--accent), #2f2713);
      color: #fff;
      box-shadow: 0 12px 30px rgba(var(--accent-rgb), 0.28);
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

    .table thead th {
      background: rgba(var(--accent-rgb), 0.08);
      border-bottom: none;
      font-weight: 600;
      color: var(--ink);
    }

    .table td, .table th {
      border-color: rgba(148, 163, 184, 0.35);
    }

    .muted-label {
      color: var(--muted);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      font-size: 0.8rem;
    }

    .chart-shell {
      position: relative;
      width: 100%;
      height: 240px;
    }

    @media (max-width: 576px) {
      .chart-shell {
        height: 300px;
      }
    }

    @media (max-width: 768px) {
      .page-shell {
        padding: 1.5rem .75rem;
        padding-left: calc(.75rem + env(safe-area-inset-left));
        padding-right: calc(.75rem + env(safe-area-inset-right));
      }

      .card-lift {
        border-radius: 14px;
      }
    }

    .navbar-logo {
      height: 34px;
      width: auto;
      display: inline-block;
    }

    .preload-overlay {
      position: fixed;
      inset: 0;
      z-index: 2000;
      background: rgba(255, 255, 255, .92);
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 1;
      transition: opacity .35s ease;
    }

    .preload-overlay.is-hide {
      opacity: 0;
      pointer-events: none;
    }

    .preload-overlay img {
      height: 96px;
      width: auto;
      transform-origin: 50% 50%;
      will-change: transform;
      animation: preload-spin 7s cubic-bezier(.45, 0, .55, 1) infinite;
    }

    .catalog-suggest-box {
      position: absolute;
      z-index: 2000;
      background: #fff;
      border: 1px solid rgba(15, 23, 42, 0.12);
      border-radius: 12px;
      box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
      max-height: 260px;
      overflow-y: auto;
      padding: 6px;
      display: none;
    }

    .catalog-suggest-box .list-group-item {
      border: 0;
      border-radius: 8px;
      padding: .5rem .65rem;
    }

    .catalog-suggest-box .list-group-item:hover,
    .catalog-suggest-box .list-group-item:focus {
      background: rgba(15, 23, 42, 0.06);
    }

    @keyframes preload-spin {
      0% { transform: rotate(0deg); }
      40% { transform: rotate(360deg); }
      60% { transform: rotate(360deg); }
      100% { transform: rotate(720deg); }
    }

    @media (prefers-reduced-motion: reduce) {
      .preload-overlay { transition: none; }
      .preload-overlay img { animation: none; }
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
<?php if ($showPreload): ?>
  <div class="preload-overlay" id="preloadOverlay" aria-hidden="true">
    <img src="/preload.png" alt="Cargando">
  </div>
<?php endif; ?>

<?php if (is_array($modal)): ?>
  <div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content card-lift" style="border-radius: 18px;">
        <div class="modal-header text-bg-<?= e((string)$modal['type']) ?>" style="border-top-left-radius: 18px; border-top-right-radius: 18px;">
          <h5 class="modal-title mb-0"><?= e((string)$modal['title']) ?></h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div><?= e((string)$modal['message']) ?></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-primary action-btn" data-bs-dismiss="modal">Aceptar</button>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

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
          <a class="nav-link-pill is-active" href="/dashboard" aria-current="page">Dashboard</a>
          <a class="nav-link-pill" href="/sales">Ventas</a>
          <a class="nav-link-pill" href="/customers">Clientes</a>
          <a class="nav-link-pill" href="/products">Productos</a>
          <a class="nav-link-pill" href="/catalogo">Catálogo</a>
          <a class="nav-link-pill" href="/pedidos">Pedidos<?php if ($newOrdersCount > 0): ?><span class="badge rounded-pill text-bg-danger ms-1"><?= e((string)$newOrdersCount) ?></span><?php endif; ?></a>
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
      <h5 class="offcanvas-title mb-0 visually-hidden" id="appNavOffcanvasLabel"><?= e($appName) ?></h5>
      <span class="pill ms-1">Admin</span>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
  </div>
  <div class="offcanvas-body">
    <div class="list-group offcanvas-nav">
      <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="/sales">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <rect x="2" y="9" width="2" height="5" rx="0.5" />
          <rect x="7" y="6" width="2" height="8" rx="0.5" />
          <rect x="12" y="3" width="2" height="11" rx="0.5" />
        </svg>
        Ventas
      </a>
      <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="/customers">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="6" cy="5" r="2" />
          <circle cx="11" cy="6" r="1.6" />
          <path d="M2.5 14c0-2.3 1.9-4 4-4s4 1.7 4 4" />
          <path d="M9.2 14c.2-1.7 1.6-3 3.3-3 1.8 0 3 1.2 3 3" />
        </svg>
        Clientes
      </a>
      <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="/products">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <rect x="2.5" y="4.5" width="11" height="9" rx="1" />
          <path d="M2.5 7.5h11" />
          <path d="M6 4.5v3" />
        </svg>
        Productos
      </a>
      <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="/catalogo">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M3 3.5h10" />
          <path d="M3 6.5h10" />
          <path d="M3 9.5h10" />
          <path d="M3 12.5h10" />
        </svg>
        Catálogo
      </a>
      <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="/pedidos">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M3 3.5h10" />
          <path d="M3 6.5h10" />
          <path d="M3 9.5h7" />
          <path d="M11.5 10.5 13 12l2-3" />
        </svg>
        Pedidos
        <?php if ($newOrdersCount > 0): ?>
          <span class="ms-auto badge rounded-pill text-bg-danger"><?= e((string)$newOrdersCount) ?></span>
        <?php endif; ?>
      </a>
      <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="/income">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M3 11V6" />
          <path d="M6.5 11V4" />
          <path d="M10 11V7" />
          <path d="M13.5 11V5" />
        </svg>
        Ingresos
      </a>
      <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="/expense">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M12.5 4.5 6 11" />
          <path d="M8 5H4.5C3.7 5 3 5.7 3 6.5V10c0 .8.7 1.5 1.5 1.5H8" />
          <path d="M11.5 11H9" />
        </svg>
        Egresos
      </a>
      <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="/stock">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M3 5.5 8 3l5 2.5v5L8 13l-5-2.5v-5Z" />
          <path d="M8 3v10" />
          <path d="M3 5.5 8 8l5-2.5" />
        </svg>
        Stock
      </a>
    </div>

    <div class="mt-3">
      <form method="post" action="/logout.php" class="d-flex">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <button type="submit" class="btn btn-outline-danger w-100 d-inline-flex align-items-center justify-content-center">
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
</div>

<main class="container page-shell">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-10 col-xl-9">
      <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
          <p class="muted-label mb-1">Panel</p>
          <h1 class="h3 mb-0">Administración de facturas</h1>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <!-- Sección de gráfica Ingreso vs Egreso -->
        <div class="col-12">
          <div class="card card-lift mb-4">
            <div class="card-header card-header-clean bg-white px-4 py-3 d-flex align-items-center justify-content-between">
              <div>
                <p class="muted-label mb-1">Gráfica</p>
                <h2 class="h5 mb-0">Ingreso vs Egreso</h2>
              </div>
            </div>
            <div class="card-body px-4 py-4">
              <form id="filterForm" class="row g-3 mb-3">
                <div class="col-md-5">
                  <label for="startDate" class="form-label">Desde</label>
                  <input type="date" class="form-control" id="startDate" name="startDate" required>
                </div>
                <div class="col-md-5">
                  <label for="endDate" class="form-label">Hasta</label>
                  <input type="date" class="form-control" id="endDate" name="endDate" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                  <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                </div>
              </form>
              <div class="chart-shell">
                <canvas id="incomeExpenseChart"></canvas>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="card card-lift h-100 kpi-card kpi-card--income">
            <div class="card-body px-4 py-3">
              <p class="muted-label mb-1">Ingresos del día</p>
              <div class="d-flex align-items-baseline justify-content-between gap-3">
                <div class="h4 mb-0"><?= e($kpiIncomeText) ?></div>
                <span class="text-muted small"><?= e($todayPeriod['start']->format('d/m/Y')) ?></span>
              </div>
              <div class="text-muted small mt-1"><?= e((string)$kpiSalesCount) ?> ventas</div>
            </div>
          </div>
        </div>

        <div class="col-12 col-md-4">
          <div class="card card-lift h-100 kpi-card kpi-card--top">
            <div class="card-body px-4 py-3">
              <p class="muted-label mb-1">Más vendidos del día</p>
              <?php if (count($kpiTopProducts) === 0): ?>
                <div class="text-muted">—</div>
              <?php else: ?>
                <div class="vstack gap-1">
                  <?php foreach ($kpiTopProducts as $p): ?>
                    <div class="d-flex justify-content-between gap-3">
                      <div class="text-truncate" style="max-width: 70%">
                        <?= e((string)($p['description'] ?? '')) ?>
                      </div>
                      <div class="text-muted">
                        <?= e(rtrim(rtrim(number_format((float)($p['qty'] ?? 0), 2, '.', ''), '0'), '.')) ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="col-12 col-md-4">
          <div class="card card-lift h-100 kpi-card kpi-card--count">
            <div class="card-body px-4 py-3">
              <p class="muted-label mb-1">Ventas realizadas</p>
              <div class="h2 mb-0"><?= e((string)$kpiSalesCount) ?></div>
              <div class="text-muted small mt-1">Hoy</div>
            </div>
          </div>
        </div>
      </div>

      <div class="card card-lift">
        <div class="card-header card-header-clean bg-white px-4 py-3 d-flex align-items-center justify-content-between">
          <div>
            <p class="muted-label mb-1">Nueva factura</p>
            <h2 class="h5 mb-0">Crear y enviar</h2>
          </div>
        </div>
        <div class="card-body px-4 py-4">

          <form method="post" action="/dashboard.php" id="invoiceForm">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

            <div class="row g-3">
              <div class="col-12 col-md-6">
                <label class="form-label" for="customer_name">Nombre del cliente</label>
                <input class="form-control" id="customer_name" name="customer_name" required>
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label" for="customer_phone">Teléfono del cliente (opcional)</label>
                <input class="form-control" id="customer_phone" name="customer_phone" inputmode="tel" autocomplete="tel">
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label" for="customer_email">Email del cliente (opcional)</label>
                <input class="form-control" id="customer_email" name="customer_email" type="email" autocomplete="email">
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label" for="customer_dni">DNI del cliente (opcional)</label>
                <input class="form-control" id="customer_dni" name="customer_dni" inputmode="numeric" autocomplete="off">
              </div>
              <div class="col-12">
                <label class="form-label" for="customer_address">Domicilio del cliente (opcional)</label>
                <input class="form-control" id="customer_address" name="customer_address" maxlength="255" autocomplete="street-address">
              </div>
              <div class="col-12">
                <label class="form-label" for="detail">Detalle</label>
                <textarea class="form-control" id="detail" name="detail" rows="3" placeholder="Observaciones / detalle..."></textarea>
              </div>
            </div>

            <hr class="my-4">

            <div class="d-flex align-items-center justify-content-between mb-2">
              <h3 class="h6 mb-0">Productos</h3>
              <button type="button" class="btn btn-outline-primary btn-sm action-btn" id="addItem">Agregar producto</button>
            </div>

            <div class="table-responsive">
              <table class="table align-middle" id="itemsTable">
                <thead>
                  <tr>
                    <th>Producto</th>
                    <th style="width:220px">Cantidad</th>
                    <th style="width:130px">Precio base</th>
                    <th style="width:130px">Total</th>
                    <th style="width:60px"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>
                      <input class="form-control" name="item_description[]" autocomplete="off" placeholder="Buscar producto" required>
                    </td>
                    <td>
                      <div class="d-flex gap-2">
                        <input class="form-control" name="item_quantity[]" value="1" inputmode="decimal" required style="max-width: 110px">
                        <select class="form-select" name="item_unit[]" required style="max-width: 110px">
                          <option value="u">u</option>
                          <option value="g">g</option>
                          <option value="kg">kg</option>
                          <option value="ml">ml</option>
                          <option value="l">l</option>
                        </select>
                      </div>
                    </td>
                    <td>
                      <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input class="form-control" name="item_unit_price[]" type="number" min="0.01" step="0.01" inputmode="decimal" placeholder="Precio base (u/kg/l)" required>
                      </div>
                    </td>
                    <td><input class="form-control" type="text" readonly placeholder="$0.00" data-total></td>
                    <td><button type="button" class="btn btn-outline-danger btn-sm" data-remove>×</button></td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="d-flex flex-wrap gap-2 justify-content-end mt-3">
              <button class="btn btn-primary action-btn" type="submit" name="action" value="download">Guardar y descargar</button>
              <button class="btn btn-outline-primary action-btn" type="submit" name="action" value="save">Guardar</button>
              <!--<button class="btn btn-outline-primary action-btn" type="submit" name="action" value="email">Guardar y enviar por email</button>-->
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
  // Lógica para la gráfica de ingreso vs egreso
  document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('incomeExpenseChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    let chart;

    const isMobile = window.matchMedia && window.matchMedia('(max-width: 576px)').matches;

    function compactDateLabel(label) {
      if (typeof label !== 'string') return label;
      const m = label.match(/^(\d{4})-(\d{2})-(\d{2})$/);
      if (!m) return label;
      return `${m[3]}/${m[2]}`;
    }

    function fetchData(start, end) {
      return fetch(`/api_income_expense.php?start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`)
        .then(res => {
          if (!res.ok) throw new Error(`HTTP ${res.status}`);
          return res.json();
        });
    }

    function renderChart(data) {
      if (chart) chart.destroy();
      chart = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: data.labels,
          datasets: [
            {
              label: 'Ingresos',
              data: data.ingresos,
              backgroundColor: 'rgba(22, 163, 74, 0.7)',
              borderColor: 'rgba(22, 163, 74, 1)',
              borderWidth: 1
            },
            {
              label: 'Egresos',
              data: data.egresos,
              backgroundColor: 'rgba(220, 38, 38, 0.7)',
              borderColor: 'rgba(220, 38, 38, 1)',
              borderWidth: 1
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { position: 'top' },
            title: { display: true, text: 'Ingresos vs Egresos' }
          },
          scales: {
            x: {
              ticks: {
                autoSkip: true,
                maxTicksLimit: isMobile ? 6 : 12,
                maxRotation: 0,
                minRotation: 0,
                callback: function (value) {
                  const raw = this.getLabelForValue(value);
                  return compactDateLabel(raw);
                }
              }
            },
            y: {
              beginAtZero: true
            }
          }
        }
      });
    }

    function refreshChart() {
      const start = document.getElementById('startDate')?.value;
      const end = document.getElementById('endDate')?.value;
      if (!start || !end) return;
      fetchData(start, end).then(renderChart).catch(err => {
        console.error('No se pudo cargar la data del gráfico', err);
      });
    }

    // Exponer para que otras acciones (como crear factura) refresquen la gráfica.
    window.refreshIncomeExpenseChart = refreshChart;

    document.getElementById('filterForm').addEventListener('submit', function (e) {
      e.preventDefault();
      const start = document.getElementById('startDate').value;
      const end = document.getElementById('endDate').value;
      fetchData(start, end).then(renderChart).catch(err => {
        console.error('No se pudo cargar la data del gráfico', err);
      });
    });

    // Inicializar con rango actual (fecha local, no UTC)
    const toLocalISODate = (d) => {
      const year = d.getFullYear();
      const month = String(d.getMonth() + 1).padStart(2, '0');
      const day = String(d.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
    };
    const today = toLocalISODate(new Date());
    document.getElementById('startDate').value = today;
    document.getElementById('endDate').value = today;
    fetchData(today, today).then(renderChart).catch(err => {
      console.error('No se pudo cargar la data del gráfico', err);
    });
  });
</script>

<script>
  // Crear factura sin recargar: POST por fetch y refrescar gráfica al terminar.
  document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('invoiceForm');
    if (!form) return;

    // Iframe oculto para descargar el PDF sin navegar fuera del dashboard.
    let dlFrame = document.querySelector('iframe[name="invoiceDownloadFrame"]');
    if (!dlFrame) {
      dlFrame = document.createElement('iframe');
      dlFrame.name = 'invoiceDownloadFrame';
      dlFrame.style.display = 'none';
      document.body.appendChild(dlFrame);
    }

    form.addEventListener('submit', function (e) {
      // Si Chart.js no está disponible o fetch no existe, dejamos el submit normal.
      if (!window.fetch || !window.FormData) return;

      e.preventDefault();
      const submitter = e.submitter;
      const action = submitter && submitter.name === 'action' ? submitter.value : (form.querySelector('button[name="action"]')?.value || 'download');

      const fd = new FormData(form);
      fd.set('ajax', '1');
      fd.set('action', action);

      fetch('/dashboard.php', {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body: fd
      })
        .then(res => res.json())
        .then(payload => {
          if (!payload || payload.ok !== true) {
            console.error('No se pudo crear la factura', payload);
            return;
          }

          // Refrescar la gráfica con el rango actual.
          if (typeof window.refreshIncomeExpenseChart === 'function') {
            window.refreshIncomeExpenseChart();
          }

          // Descargar sin recargar.
          if (payload.action === 'download' && payload.invoice_id) {
            dlFrame.src = `/invoice_download.php?id=${encodeURIComponent(payload.invoice_id)}`;
          }
        })
        .catch(err => {
          console.error('Error creando la factura (AJAX)', err);
        });
    });
  });
</script>

<?php if (is_array($modal)): ?>
<script>
  (function () {
    if (!window.bootstrap) return;
    var el = document.getElementById('statusModal');
    if (!el) return;
    var modal = new bootstrap.Modal(el);
    modal.show();
  })();
</script>
<?php endif; ?>

<script>
  (function () {
    const addBtn = document.getElementById('addItem');
    const table = document.getElementById('itemsTable');
    const tbody = table.querySelector('tbody');

    function addRow() {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>
          <input class="form-control" name="item_description[]" autocomplete="off" placeholder="Buscar producto" required>
        </td>
        <td>
          <div class="d-flex gap-2">
            <input class="form-control" name="item_quantity[]" value="1" inputmode="decimal" required style="max-width: 110px">
            <select class="form-select" name="item_unit[]" required style="max-width: 110px">
              <option value="u">u</option>
              <option value="g">g</option>
              <option value="kg">kg</option>
              <option value="ml">ml</option>
              <option value="l">l</option>
            </select>
          </div>
        </td>
        <td>
          <div class="input-group">
            <span class="input-group-text">$</span>
            <input class="form-control" name="item_unit_price[]" type="number" min="0.01" step="0.01" inputmode="decimal" placeholder="Precio base (u/kg/l)" required>
          </div>
        </td>
        <td><input class="form-control" type="text" readonly placeholder="$0.00" data-total></td>
        <td><button type="button" class="btn btn-outline-danger btn-sm" data-remove>×</button></td>
      `;
      tbody.appendChild(tr);
    }

    addBtn.addEventListener('click', addRow);

    table.addEventListener('click', function (e) {
      const btn = e.target.closest('[data-remove]');
      if (!btn) return;
      const row = btn.closest('tr');
      if (!row) return;
      if (tbody.querySelectorAll('tr').length <= 1) return;
      row.remove();
    });
  })();
</script>
<script>
  (function () {
    const table = document.getElementById('itemsTable');
    if (!table) return;

    const itemByName = new Map();
    let debounceTimer = 0;
    let inflight = null;
    let lastQuery = '';
    let activeInput = null;
    const maxSuggestions = 12;
    let allItems = [];
    let loadedAll = false;
    const unitLabels = {
      u: 'u',
      g: 'g',
      kg: 'kg',
      ml: 'ml',
      l: 'l'
    };

    const suggestBox = document.createElement('div');
    suggestBox.className = 'catalog-suggest-box list-group';
    document.body.appendChild(suggestBox);

    function normalizeName(name) {
      return (name || '').trim().toLowerCase();
    }

    function unitOptionsFor(unitKey) {
      switch (unitKey) {
        case 'g':
        case 'kg':
          return ['g', 'kg'];
        case 'ml':
        case 'l':
          return ['ml', 'l'];
        case 'u':
          return ['u'];
        default:
          return ['u', 'g', 'kg', 'ml', 'l'];
      }
    }

    function renderUnitOptions(select, unitKey) {
      if (!select) return;
      const allowed = unitOptionsFor(unitKey);
      const current = select.value;
      select.innerHTML = allowed.map(u => `<option value="${u}">${unitLabels[u] || u}</option>`).join('');

      if (allowed.includes(current)) {
        select.value = current;
      } else if (unitKey && allowed.includes(unitKey)) {
        select.value = unitKey;
      } else if (allowed.length > 0) {
        select.value = allowed[0];
      }
    }

    function escapeHtml(str) {
      return String(str)
        .replaceAll('&', '&amp;')
        .replaceAll('"', '&quot;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;');
    }

    function buildOptions(items) {
      itemByName.clear();
      const list = [];

      for (const it of items || []) {
        const name = String(it.name || '').trim();
        if (!name) continue;
        itemByName.set(normalizeName(name), it);
        list.push({ name });
      }

      allItems = list;
      renderSuggestions(list);
    }

    function filterLocal(query) {
      const q = normalizeName(query);
      if (!q) return allItems;
      return allItems.filter(it => normalizeName(it.name).includes(q));
    }

    function positionSuggestBox(input) {
      if (!input) return;
      const rect = input.getBoundingClientRect();
      const left = rect.left + window.scrollX;
      const top = rect.bottom + window.scrollY + 6;
      suggestBox.style.left = `${left}px`;
      suggestBox.style.top = `${top}px`;
      suggestBox.style.width = `${rect.width}px`;
    }

    function hideSuggestions() {
      suggestBox.style.display = 'none';
      suggestBox.innerHTML = '';
    }

    function showSuggestions() {
      if (!activeInput) return;
      positionSuggestBox(activeInput);
      suggestBox.style.display = 'block';
    }

    function renderSuggestions(list) {
      if (!activeInput) return;
      suggestBox.innerHTML = '';

      const items = (list || []).slice(0, maxSuggestions);
      if (items.length === 0) {
        hideSuggestions();
        return;
      }

      for (const it of items) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'list-group-item list-group-item-action';
        btn.textContent = it.name;
        btn.addEventListener('mousedown', function (e) {
          e.preventDefault();
          if (!activeInput) return;
          activeInput.value = it.name;
          activeInput.dispatchEvent(new Event('change', { bubbles: true }));
          hideSuggestions();
        });
        suggestBox.appendChild(btn);
      }

      showSuggestions();
    }

    function fetchSuggestions(query) {
      const q = String(query || '').trim();
      if (q === lastQuery && suggestBox.innerHTML !== '') {
        return;
      }
      lastQuery = q;

      if (inflight && typeof inflight.abort === 'function') {
        inflight.abort();
      }
      inflight = new AbortController();

      const url = `/api_catalog_suggest.php?q=${encodeURIComponent(q)}&limit=5000`;
      return fetch(url, { headers: { 'Accept': 'application/json' }, signal: inflight.signal })
        .then(res => {
          if (!res.ok) throw new Error(`HTTP ${res.status}`);
          return res.json();
        })
        .then(payload => {
          const items = payload && Array.isArray(payload.items) ? payload.items : [];
          buildOptions(items);
        })
        .catch(err => {
          if (err && err.name === 'AbortError') return;
          console.warn('No se pudieron cargar productos del catálogo', err);
        });
    }

    function loadAllCatalog() {
      if (loadedAll) return Promise.resolve();
      const url = `/api_catalog_suggest.php?q=&limit=5000`;
      return fetch(url, { headers: { 'Accept': 'application/json' } })
        .then(res => {
          if (!res.ok) throw new Error(`HTTP ${res.status}`);
          return res.json();
        })
        .then(payload => {
          const items = payload && Array.isArray(payload.items) ? payload.items : [];
          buildOptions(items);
          loadedAll = true;
        })
        .catch(err => {
          console.warn('No se pudieron cargar productos del catálogo', err);
        });
    }

    function scheduleFetch(query) {
      window.clearTimeout(debounceTimer);
      debounceTimer = window.setTimeout(() => {
        if (loadedAll) {
          renderSuggestions(filterLocal(query));
        } else {
          fetchSuggestions(query);
        }
      }, 180);
    }

    function maybeFillPriceForRow(descInput) {
      const row = descInput.closest('tr');
      if (!row) return;
      const priceInput = row.querySelector('input[name="item_unit_price[]"]');
      if (!priceInput) return;
      const unitSelect = row.querySelector('select[name="item_unit[]"]');

      const key = normalizeName(descInput.value);
      if (!key) {
        renderUnitOptions(unitSelect, '');
        priceInput.value = '';
        if (typeof window.recalculateInvoiceRow === 'function') {
          window.recalculateInvoiceRow(row);
        }
        return;
      }

      const item = itemByName.get(key);
      if (!item) return;

      const unitKey = String(item.unit || '').trim();
      renderUnitOptions(unitSelect, unitKey);

      const price = Number(item.price);
      if (!Number.isFinite(price) || price <= 0) return;
      priceInput.value = price.toFixed(2);

      if (typeof window.recalculateInvoiceRow === 'function') {
        window.recalculateInvoiceRow(row);
      }
    }

    table.addEventListener('focusin', function (e) {
      const el = e.target;
      if (!(el instanceof HTMLInputElement)) return;
      if (el.name !== 'item_description[]') return;
      activeInput = el;
      positionSuggestBox(el);
      if (!loadedAll) {
        loadAllCatalog().finally(() => {
          renderSuggestions(filterLocal(el.value));
        });
      } else {
        renderSuggestions(filterLocal(el.value));
      }
    });

    table.addEventListener('input', function (e) {
      const el = e.target;
      if (!(el instanceof HTMLInputElement)) return;
      if (el.name !== 'item_description[]') return;
      activeInput = el;
      positionSuggestBox(el);
      if (loadedAll) {
        renderSuggestions(filterLocal(el.value));
      } else {
        scheduleFetch(el.value);
      }
    });

    table.addEventListener('change', function (e) {
      const el = e.target;
      if (!(el instanceof HTMLInputElement)) return;
      if (el.name !== 'item_description[]') return;
      maybeFillPriceForRow(el);
      hideSuggestions();
    });

    document.addEventListener('click', function (e) {
      if (!activeInput) return;
      if (e.target === activeInput || suggestBox.contains(e.target)) return;
      hideSuggestions();
    });

    window.addEventListener('scroll', function () {
      if (!activeInput) return;
      positionSuggestBox(activeInput);
    }, { passive: true });

    window.addEventListener('resize', function () {
      if (!activeInput) return;
      positionSuggestBox(activeInput);
    });
  })();
</script>
<script>
  // Calcular automáticamente el precio total según cantidad, unidad y precio base
  (function () {
    const table = document.getElementById('itemsTable');
    if (!table) return;

    const moneyFormatter = new Intl.NumberFormat('es-AR', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });

    function calculateTotal(row) {
      const qtyInput = row.querySelector('input[name="item_quantity[]"]');
      const unitSelect = row.querySelector('select[name="item_unit[]"]');
      const priceInput = row.querySelector('input[name="item_unit_price[]"]');
      const totalInput = row.querySelector('input[data-total]');

      if (!qtyInput || !unitSelect || !priceInput || !totalInput) return;

      const qty = parseFloat(qtyInput.value) || 0;
      const unit = unitSelect.value;
      const basePrice = parseFloat(priceInput.value) || 0;

      if (qty <= 0 || basePrice <= 0) {
        totalInput.value = '';
        return;
      }

      let total = 0;

      // Asumimos que el precio base es por kg/litro/unidad
      switch (unit) {
        case 'kg':
        case 'l':
        case 'u':
          // Precio directo
          total = qty * basePrice;
          break;
        case 'g':
          // Convertir gramos a kg
          total = (qty / 1000) * basePrice;
          break;
        case 'ml':
          // Convertir ml a litros
          total = (qty / 1000) * basePrice;
          break;
        default:
          total = qty * basePrice;
      }

      totalInput.value = '$' + moneyFormatter.format(total);
    }

    function recalculateRow(row) {
      if (!row) return;
      calculateTotal(row);
    }

    window.recalculateInvoiceRow = recalculateRow;

    // Escuchar cambios en cantidad, unidad y precio
    table.addEventListener('input', function (e) {
      const el = e.target;
      if (!(el instanceof HTMLInputElement)) return;
      if (el.name !== 'item_quantity[]' && el.name !== 'item_unit_price[]') return;
      
      const row = el.closest('tr');
      recalculateRow(row);
    });

    table.addEventListener('change', function (e) {
      const el = e.target;
      if (el instanceof HTMLSelectElement && el.name === 'item_unit[]') {
        const row = el.closest('tr');
        recalculateRow(row);
      }
    });

    // Calcular totales iniciales
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(recalculateRow);
  })();
</script>
<script>
  (function () {
    var el = document.getElementById('preloadOverlay');
    if (!el) return;

    function hide() {
      el.classList.add('is-hide');
      window.setTimeout(function () {
        if (el && el.parentNode) el.parentNode.removeChild(el);
      }, 450);
    }

    if (document.readyState === 'complete') {
      window.setTimeout(hide, 7000);
    } else {
      window.addEventListener('load', function () {
        window.setTimeout(hide, 7000);
      }, { once: true });
    }
  })();
</script>
</body>
</html>
