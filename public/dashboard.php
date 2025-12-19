<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

auth_require_login();

$showPreload = !empty($_SESSION['preload_dashboard']);
unset($_SESSION['preload_dashboard']);

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
    $action = (string)($_POST['action'] ?? '');
    $customerName = trim((string)($_POST['customer_name'] ?? ''));
    $customerEmail = trim((string)($_POST['customer_email'] ?? ''));
    $customerDni = trim((string)($_POST['customer_dni'] ?? ''));
    $detail = trim((string)($_POST['detail'] ?? ''));

    $descs = $_POST['item_description'] ?? [];
    $qtys = $_POST['item_quantity'] ?? [];
    $prices = $_POST['item_unit_price'] ?? [];

    $items = [];
    if (is_array($descs) && is_array($qtys) && is_array($prices)) {
      $count = min(count($descs), count($qtys), count($prices));
      for ($i = 0; $i < $count; $i++) {
        $desc = trim((string)$descs[$i]);
        $qty = (string)$qtys[$i];
        $price = (string)$prices[$i];
        if ($desc === '' && trim($qty) === '' && trim($price) === '') {
          continue;
        }
        $items[] = ['description' => $desc, 'quantity' => $qty, 'unit_price' => $price];
      }
    }

    $invoiceId = 0;
    try {
      $pdo = db($config);
      $invoiceId = invoices_create($pdo, $userId, $customerName, $customerEmail, $detail, $items, 'ARS', $customerDni);
    } catch (Throwable $e) {
      if ($e instanceof InvalidArgumentException) {
        $error = $e->getMessage();
      } else {
        $errorId = bin2hex(random_bytes(4));
        error_log('[invoice_create_error ' . $errorId . '] ' . get_class($e) . ': ' . $e->getMessage());
        $error = ($config['app']['env'] ?? 'production') === 'production'
          ? ('No se pudo guardar la factura. (código ' . $errorId . ')')
          : ('Error: ' . $e->getMessage());
      }
    }

    if ($error === '' && $invoiceId > 0) {
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
            $subject = 'Factura #' . $invoiceId . ' - ' . $appName;
            $body = '<p>Hola ' . e($customerName) . ',</p><p>Adjuntamos tu factura.</p><p>Gracias.</p>';
            mail_send_with_attachment($config, $customerEmail, $customerName, $subject, $body, $download['bytes'], $download['filename'], $download['mime']);
            $flash = 'Factura enviada por email y guardada (ID ' . $invoiceId . ').';
          } catch (Throwable $e) {
            $errorId = bin2hex(random_bytes(4));
            error_log('[invoice_mail_error ' . $errorId . '] ' . get_class($e) . ': ' . $e->getMessage());
            $error = ($config['app']['env'] ?? 'production') === 'production'
              ? ('La factura se guardó (ID ' . $invoiceId . ') pero no se pudo enviar el email. (código ' . $errorId . ')')
              : ('Error: ' . $e->getMessage());
          }
        } else {
          $flash = 'Factura guardada (ID ' . $invoiceId . ').';
        }
      }
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($appName) ?> - Dashboard</title>
  <link rel="icon" type="image/png" href="/logo.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --accent: #0f766e;
      --accent-2: #f97316;
      --ink: #0b1727;
      --muted: #6b7280;
      --card: rgba(255, 255, 255, 0.9);
    }

    body {
      font-family: 'Space Grotesk', 'Segoe UI', sans-serif;
      background: radial-gradient(circle at 10% 20%, rgba(15, 118, 110, 0.18), transparent 35%),
        radial-gradient(circle at 90% 10%, rgba(249, 115, 22, 0.18), transparent 35%),
        linear-gradient(120deg, #f7fafc, #eef2ff);
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

    .pill {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.35rem 0.75rem;
      border-radius: 999px;
      background: rgba(15, 118, 110, 0.1);
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
      background: linear-gradient(135deg, var(--accent), #115e59);
      border: none;
      box-shadow: 0 10px 30px rgba(15, 118, 110, 0.25);
    }

    .btn-outline-primary {
      border-color: var(--accent);
      color: var(--accent);
    }

    .btn-outline-primary:hover, .btn-outline-primary:focus {
      background: rgba(15, 118, 110, 0.1);
      color: var(--accent);
      border-color: var(--accent);
    }

    .table thead th {
      background: rgba(15, 118, 110, 0.08);
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
<body>
<?php if ($showPreload): ?>
  <div class="preload-overlay" id="preloadOverlay" aria-hidden="true">
    <img src="/logo.png" alt="Logo">
  </div>
<?php endif; ?>
<nav class="navbar navbar-expand-lg navbar-glass sticky-top">
  <div class="container py-2">
    <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-dark mb-0 h4 text-decoration-none" href="/dashboard">
      <img src="/logo.png" alt="Logo" class="navbar-logo">
      <span><?= e($appName) ?></span>
    </a>
    <div class="d-flex flex-wrap align-items-center gap-2 ms-auto justify-content-end">
      <span class="pill">Admin</span>
      <a class="btn btn-outline-primary btn-sm d-inline-flex align-items-center" href="/sales">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="me-1" aria-hidden="true">
          <rect x="2" y="9" width="2" height="5" rx="0.5" />
          <rect x="7" y="6" width="2" height="8" rx="0.5" />
          <rect x="12" y="3" width="2" height="11" rx="0.5" />
        </svg>
        Ventas
      </a>
      <a class="btn btn-outline-primary btn-sm d-inline-flex align-items-center" href="/customers">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="me-1" aria-hidden="true">
          <circle cx="6" cy="5" r="2" />
          <circle cx="11" cy="6" r="1.6" />
          <path d="M2.5 14c0-2.3 1.9-4 4-4s4 1.7 4 4" />
          <path d="M9.2 14c.2-1.7 1.6-3 3.3-3 1.8 0 3 1.2 3 3" />
        </svg>
        Clientes
      </a>
      <a class="btn btn-outline-primary btn-sm d-inline-flex align-items-center" href="/products">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="me-1" aria-hidden="true">
          <rect x="2.5" y="4.5" width="11" height="9" rx="1" />
          <path d="M2.5 7.5h11" />
          <path d="M6 4.5v3" />
        </svg>
        Productos
      </a>

      <a class="btn btn-outline-primary btn-sm d-inline-flex align-items-center" href="/income">
        Ingresos
      </a>
      <a class="btn btn-outline-primary btn-sm d-inline-flex align-items-center" href="/expense">
        Egresos
      </a>
      <a class="btn btn-outline-primary btn-sm d-inline-flex align-items-center" href="/stock">
        Stock
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

<main class="container page-shell">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-10 col-xl-9">
      <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
          <p class="muted-label mb-1">Panel</p>
          <h1 class="h3 mb-0">Administración de facturas</h1>
        </div>
        <span class="text-muted">Usuario #<?= e((string)$userId) ?></span>
      </div>

      <?php if ($flash !== ''): ?>
        <div class="alert alert-success" role="alert"><?= e($flash) ?></div>
      <?php endif; ?>
      <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <div class="card card-lift">
        <div class="card-header card-header-clean bg-white px-4 py-3 d-flex align-items-center justify-content-between">
          <div>
            <p class="muted-label mb-1">Nueva factura</p>
            <h2 class="h5 mb-0">Crear y enviar</h2>
          </div>
          <span class="badge text-bg-light border">Protegido con CSRF</span>
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
                <label class="form-label" for="customer_email">Email del cliente</label>
                <input class="form-control" id="customer_email" name="customer_email" type="email" required>
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label" for="customer_dni">DNI del cliente (opcional)</label>
                <input class="form-control" id="customer_dni" name="customer_dni" inputmode="numeric" autocomplete="off">
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
                    <th style="width:140px">Cantidad</th>
                    <th style="width:160px">Precio</th>
                    <th style="width:60px"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td><input class="form-control" name="item_description[]" required></td>
                    <td><input class="form-control" name="item_quantity[]" value="1" inputmode="decimal" required></td>
                    <td><input class="form-control" name="item_unit_price[]" value="0" inputmode="decimal" required></td>
                    <td><button type="button" class="btn btn-outline-danger btn-sm" data-remove>×</button></td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="d-flex flex-wrap gap-2 justify-content-end mt-3">
              <button class="btn btn-primary action-btn" type="submit" name="action" value="download">Guardar y descargar</button>
              <button class="btn btn-outline-primary action-btn" type="submit" name="action" value="email">Guardar y enviar por email</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</main>

<script>
  (function () {
    const addBtn = document.getElementById('addItem');
    const table = document.getElementById('itemsTable');
    const tbody = table.querySelector('tbody');

    function addRow() {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><input class="form-control" name="item_description[]" required></td>
        <td><input class="form-control" name="item_quantity[]" value="1" inputmode="decimal" required></td>
        <td><input class="form-control" name="item_unit_price[]" value="0" inputmode="decimal" required></td>
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
