<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

auth_require_login();

$config  = app_config();
$appName = (string)($config['app']['name'] ?? 'Dietetic');
$userId  = (int)auth_user_id();
$csrf    = csrf_token();

// ── AJAX: guardar venta ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    @ini_set('display_errors', '0');

    $raw = (string)file_get_contents('php://input');
    $body = json_decode($raw, true);

    if (!is_array($body)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Cuerpo inválido'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $token    = (string)($body['csrf_token'] ?? '');
    $currency = strtoupper(trim((string)($body['currency'] ?? 'ARS')));
    $items    = $body['items'] ?? [];

    if (!csrf_verify($token)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Token de seguridad inválido. Recargá la página.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!is_array($items) || count($items) === 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'El carrito está vacío'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $invoiceItems = [];
    foreach ($items as $it) {
        $desc  = trim((string)($it['description'] ?? ''));
        $qty   = (float)($it['quantity'] ?? 0);
        $unit  = (string)($it['unit'] ?? 'u');
        $price = (float)($it['unit_price'] ?? 0);

        if ($desc === '' || $qty <= 0 || $price <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => "Ítem inválido: {$desc}"], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $invoiceItems[] = [
            'description' => $desc,
            'quantity'    => $qty,
            'unit'        => $unit,
            'unit_price'  => $price,
        ];
    }

    try {
        $pdo = db($config);
        $invoiceId = invoices_create(
            $pdo,
            $userId,
            'Mostrador',   // cliente genérico para ventas rápidas
            '',            // email
            'Venta rápida (caja)',
            $invoiceItems,
            $currency
        );

        echo json_encode([
            'ok'         => true,
            'invoice_id' => $invoiceId,
            'message'    => "Venta #{$invoiceId} guardada",
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ── Contador de pedidos para el nav ─────────────────────────────────────────
$newOrdersCount = 0;
try {
    $pdoNav = db($config);
    $newOrdersCount = orders_count_new($pdoNav, $userId);
} catch (Throwable $e) {
    // silencioso
}

?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Caja — <?= e($appName) ?></title>
  <link rel="icon" href="/logo.png" type="image/png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="stylesheet" href="/brand.css?v=20260423">
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
    }

    body {
      font-family: 'Space Grotesk', 'Segoe UI', sans-serif;
      background: radial-gradient(circle at 10% 20%, rgba(150,149,126,0.22), transparent 38%),
        radial-gradient(circle at 90% 10%, rgba(70,59,30,0.12), transparent 40%),
        linear-gradient(120deg, #fbfaf6, #E7E3D5);
      color: var(--ink);
      min-height: 100vh;
    }

    .navbar-glass {
      background: rgba(255,255,255,0.9);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(15,23,42,0.06);
      box-shadow: 0 10px 40px rgba(15,23,42,0.08);
    }

    .navbar-glass .container {
      padding-left: calc(.75rem + env(safe-area-inset-left));
      padding-right: calc(.75rem + env(safe-area-inset-right));
    }

    .nav-toggle-btn { border-radius: 12px; font-weight: 600; }

    .offcanvas-nav .list-group-item {
      border: 1px solid rgba(15,23,42,0.06);
      border-radius: 14px;
      margin-bottom: .6rem;
      background: rgba(255,255,255,0.85);
      box-shadow: 0 10px 30px rgba(15,23,42,0.06);
    }

    .page-shell { padding: 2.5rem 0; }

    .card-lift {
      background: var(--card);
      border: 1px solid rgba(15,23,42,0.06);
      box-shadow: 0 18px 50px rgba(15,23,42,0.07);
      border-radius: 18px;
    }

    .card-header-clean { border-bottom: 1px solid rgba(15,23,42,0.06); }

    .pill {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.35rem 0.75rem;
      border-radius: 999px;
      background: rgba(var(--accent-rgb),0.10);
      color: var(--accent);
      font-weight: 600;
      font-size: 0.9rem;
    }

    .action-btn { border-radius: 12px; padding-inline: 1.25rem; font-weight: 600; }

    .btn-primary, .btn-primary:hover, .btn-primary:focus {
      background: linear-gradient(135deg, var(--accent), #2f2713);
      border: none;
      box-shadow: 0 10px 30px rgba(var(--accent-rgb),0.25);
    }

    .btn-outline-primary { border-color: var(--accent); color: var(--accent); }
    .btn-outline-primary:hover, .btn-outline-primary:focus {
      background: rgba(var(--accent-rgb),0.10); color: var(--accent); border-color: var(--accent);
    }

    .nav-shell {
      display: inline-flex; align-items: center; gap: .25rem;
      padding: .25rem; border-radius: 999px;
      background: rgba(15,23,42,.04); border: 1px solid rgba(15,23,42,.08);
      box-shadow: inset 0 1px 0 rgba(255,255,255,.65);
    }

    .nav-link-pill {
      appearance: none; -webkit-appearance: none;
      display: inline-flex; align-items: center; gap: .45rem;
      padding: .45rem .85rem; border-radius: 999px;
      font-weight: 650; font-size: .92rem; line-height: 1;
      text-decoration: none; white-space: nowrap; cursor: pointer;
      color: rgba(15,23,42,.78); background: transparent;
      border: 1px solid transparent;
      transition: background .15s ease, box-shadow .15s ease, color .15s ease;
    }

    .nav-link-pill:hover, .nav-link-pill:focus {
      background: rgba(var(--accent-rgb),0.10);
      color: var(--accent); outline: none;
    }

    .nav-link-pill.is-active {
      background: var(--accent); color: #fff;
      box-shadow: 0 4px 12px rgba(var(--accent-rgb),0.30);
    }

    .nav-link-pill--danger { color: rgba(220,38,38,.85); }
    .nav-link-pill--danger:hover { background: rgba(220,38,38,.12); color: #991b1b; }

    .navbar-logo {
      height: 34px;
      width: auto;
      display: inline-block;
    }

    .muted-label {
      color: var(--muted); font-weight: 600; text-transform: uppercase;
      letter-spacing: 0.04em; font-size: 0.8rem;
    }

    .table thead th {
      background: rgba(var(--accent-rgb),0.08);
      border-bottom: none; font-weight: 600; color: var(--ink);
    }

    .table td, .table th { border-color: rgba(148,163,184,0.35); }

    /* Scan input destacado */
    .scan-input-wrap { position: relative; }
    .scan-input-wrap input {
      border-radius: 14px;
      font-size: 1.1rem;
      padding: .75rem 1rem .75rem 3rem;
      border: 2px solid rgba(var(--accent-rgb),.25);
      transition: border-color .2s;
    }
    .scan-input-wrap input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(var(--accent-rgb),.15);
    }
    .scan-input-wrap .scan-icon {
      position: absolute; left: 1rem; top: 50%; transform: translateY(-50%);
      color: var(--muted); pointer-events: none;
    }

    /* Total destacado */
    .total-pill {
      display: inline-flex; align-items: center; gap: .5rem;
      padding: .6rem 1.25rem; border-radius: 999px;
      background: rgba(var(--accent-rgb),.10);
      font-weight: 700; font-size: 1.2rem; color: var(--accent);
    }

    /* Feedback flash en fila */
    @keyframes row-flash { from { background: #d1fae5; } to { background: transparent; } }
    .row-scanned { animation: row-flash .8s ease forwards; }

    @media (max-width: 768px) {
      .page-shell { padding: 1.5rem .75rem; }
      .card-lift { border-radius: 14px; }
    }

    @media (max-width: 576px) {
      .nav-shell { display: none; }
    }
  </style>
</head>
<body class="has-leaves-bg">
<div class="bg-leaves" aria-hidden="true">
  <div class="bg-leaf leaf-1"></div><div class="bg-leaf leaf-2"></div>
  <div class="bg-leaf leaf-3"></div><div class="bg-leaf leaf-4"></div>
  <div class="bg-leaf leaf-5"></div><div class="bg-leaf leaf-6"></div>
  <div class="bg-leaf leaf-7"></div><div class="bg-leaf leaf-8"></div>
  <div class="bg-leaf leaf-9"></div><div class="bg-leaf leaf-10"></div>
  <div class="bg-leaf leaf-11"></div><div class="bg-leaf leaf-12"></div>
</div>

<!-- Nav -->
<nav class="navbar navbar-expand-lg navbar-glass sticky-top">
  <div class="container py-2">
    <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-dark mb-0 h4 text-decoration-none" href="/dashboard" aria-label="<?= e($appName) ?>">
      <img src="/logo.png" alt="Logo" class="navbar-logo">
    </a>
    <div class="ms-auto d-flex align-items-center gap-2 justify-content-end">
      <span class="pill d-none d-lg-inline-flex">Admin</span>

      <button class="btn btn-outline-primary btn-sm d-inline-flex align-items-center d-lg-none nav-toggle-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#appNavOffcanvas" aria-controls="appNavOffcanvas">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="me-1" aria-hidden="true">
          <path d="M2.5 4h11"/><path d="M2.5 8h11"/><path d="M2.5 12h11"/>
        </svg>
        Menú
      </button>

      <div class="d-none d-lg-flex align-items-center gap-2">
        <div class="nav-shell" role="navigation" aria-label="Secciones">
          <a class="nav-link-pill" href="/dashboard">Dashboard</a>
          <a class="nav-link-pill is-active" href="/caja" aria-current="page">Caja</a>
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

<!-- Offcanvas mobile -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="appNavOffcanvas" aria-labelledby="appNavOffcanvasLabel">
  <div class="offcanvas-header">
    <div class="d-flex align-items-center gap-2">
      <img src="/logo.png" alt="Logo" class="navbar-logo">
      <span class="pill ms-1">Admin</span>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
  </div>
  <div class="offcanvas-body">
    <div class="list-group offcanvas-nav">
      <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="/dashboard">Dashboard</a>
      <a class="list-group-item list-group-item-action d-flex align-items-center gap-2 active" href="/caja">Caja</a>
      <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="/sales">Ventas</a>
      <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="/customers">Clientes</a>
      <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="/products">Productos</a>
      <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="/catalogo">Catálogo</a>
      <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="/pedidos">Pedidos</a>
      <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="/income">Ingresos</a>
      <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="/expense">Egresos</a>
      <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="/stock">Stock</a>
    </div>
    <form method="post" action="/logout.php" class="mt-3">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <button type="submit" class="btn btn-outline-danger w-100 action-btn">Salir</button>
    </form>
  </div>
</div>

<main class="page-shell">
  <div class="container">
    <div class="row g-4">

      <!-- Panel izquierdo: escaneo + carrito -->
      <div class="col-12 col-lg-8">
        <div class="card card-lift">
          <div class="card-header card-header-clean bg-white px-4 py-3">
            <p class="muted-label mb-1">Modo caja</p>
            <h2 class="h5 mb-0">Escanear</h2>
          </div>
          <div class="card-body px-4 py-4">

            <!-- Campo de escaneo -->
            <div class="scan-input-wrap mb-2">
              <svg class="scan-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 5v2"/><path d="M3 19v-2"/><path d="M7 5h-.5A1.5 1.5 0 0 0 5 6.5V7"/><path d="M7 19h-.5A1.5 1.5 0 0 1 5 17.5V17"/>
                <path d="M21 5v2"/><path d="M21 19v-2"/><path d="M17 5h.5A1.5 1.5 0 0 1 19 6.5V7"/><path d="M17 19h.5A1.5 1.5 0 0 0 19 17.5V17"/>
                <line x1="7" y1="12" x2="7" y2="12"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="17" y1="12" x2="17" y2="12"/>
              </svg>
              <input type="text" id="cajaScanInput" class="form-control" placeholder="Ingresá o escaneá el código del ticket..." inputmode="numeric" autocomplete="off" autofocus>
            </div>
            <div id="cajaScanError" class="text-danger small mb-3" style="min-height:1.2em"></div>

            <!-- Tabla carrito -->
            <div class="table-responsive">
              <table class="table align-middle" id="cajaCart">
                <thead>
                  <tr>
                    <th>Producto</th>
                    <th style="width:110px">Cant.</th>
                    <th style="width:60px">Un.</th>
                    <th style="width:120px">Precio base</th>
                    <th style="width:120px">Subtotal</th>
                    <th style="width:44px"></th>
                  </tr>
                </thead>
                <tbody id="cajaCartBody">
                  <tr id="cajaEmptyRow">
                    <td colspan="6" class="text-center text-muted py-4">
                      <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mb-2 d-block mx-auto opacity-50" aria-hidden="true">
                        <path d="M3 5v2"/><path d="M3 19v-2"/><path d="M7 5h-.5A1.5 1.5 0 0 0 5 6.5V7"/><path d="M7 19h-.5A1.5 1.5 0 0 1 5 17.5V17"/>
                        <path d="M21 5v2"/><path d="M21 19v-2"/><path d="M17 5h.5A1.5 1.5 0 0 1 19 6.5V7"/><path d="M17 19h.5A1.5 1.5 0 0 0 19 17.5V17"/>
                        <line x1="7" y1="12" x2="7" y2="12"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="17" y1="12" x2="17" y2="12"/>
                      </svg>
                      Escaneá una etiqueta para empezar
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

          </div>
        </div>
      </div>

      <!-- Panel derecho: resumen + confirmar -->
      <div class="col-12 col-lg-4">
        <div class="card card-lift sticky-top" style="top: 80px">
          <div class="card-header card-header-clean bg-white px-4 py-3">
            <p class="muted-label mb-1">Resumen</p>
            <h2 class="h5 mb-0">Venta actual</h2>
          </div>
          <div class="card-body px-4 py-4">

            <div class="mb-3">
              <label class="form-label fw-semibold" for="cajaCurrency">Moneda</label>
              <select class="form-select" id="cajaCurrency">
                <option value="ARS" selected>ARS — Pesos</option>
                <option value="USD">USD — Dólares</option>
                <option value="EUR">EUR — Euros</option>
              </select>
            </div>

            <hr class="my-3">

            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="text-muted">Ítems</span>
              <span id="cajaItemCount" class="fw-semibold">0</span>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-4">
              <span class="fw-semibold">Total</span>
              <span class="total-pill" id="cajaTotalDisplay">$0,00</span>
            </div>

            <button type="button" id="cajaConfirm" class="btn btn-primary action-btn w-100 mb-2" disabled>
              Confirmar venta
            </button>
            <button type="button" id="cajaClear" class="btn btn-outline-danger action-btn w-100" style="display:none">
              Limpiar carrito
            </button>

            <!-- Feedback post-venta -->
            <div id="cajaSaleResult" class="mt-3" style="display:none"></div>

          </div>
        </div>
      </div>

    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
(function () {
  'use strict';

  const CSRF = <?= json_encode($csrf) ?>;

  const scanInput    = document.getElementById('cajaScanInput');
  const scanError    = document.getElementById('cajaScanError');
  const cartBody     = document.getElementById('cajaCartBody');
  const emptyRow     = document.getElementById('cajaEmptyRow');
  const itemCountEl  = document.getElementById('cajaItemCount');
  const totalDisplay = document.getElementById('cajaTotalDisplay');
  const confirmBtn   = document.getElementById('cajaConfirm');
  const clearBtn     = document.getElementById('cajaClear');
  const saleResult   = document.getElementById('cajaSaleResult');
  const currencyEl   = document.getElementById('cajaCurrency');

  const money = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  // ── Cart state ──────────────────────────────────────────────────────────────
  // Cada ítem: { description, quantity, unit, unit_price, line_total }
  const cart = [];

  function computeLineTotal(quantity, unit, unitPrice) {
    if (unit === 'g' || unit === 'ml') {
      return (quantity / 1000) * unitPrice;
    }
    return quantity * unitPrice;
  }

  function cartTotal() {
    return cart.reduce(function (sum, it) { return sum + it.line_total; }, 0);
  }

  function updateSummary() {
    const total = cartTotal();
    const cur   = currencyEl.value;
    itemCountEl.textContent = cart.length;
    totalDisplay.textContent = '$' + money.format(total);
    confirmBtn.disabled = cart.length === 0;
    clearBtn.style.display = cart.length === 0 ? 'none' : '';
    emptyRow.style.display = cart.length === 0 ? '' : 'none';
  }

  function addCartRow(item, tr) {
    // Crea o actualiza la fila de la tabla
    if (!tr) {
      tr = document.createElement('tr');
      cartBody.insertBefore(tr, emptyRow);
    }

    const unitLabel = item.unit === 'u' ? 'u'
      : item.unit === 'g'  ? 'g'
      : item.unit === 'kg' ? 'kg'
      : item.unit === 'ml' ? 'ml'
      : item.unit === 'l'  ? 'l'
      : item.unit;

    const qtyDisplay = Number.isInteger(item.quantity)
      ? item.quantity
      : parseFloat(item.quantity.toFixed(3));

    tr.innerHTML =
      '<td>' + escHtml(item.description) + '</td>' +
      '<td class="text-end">' + qtyDisplay + '</td>' +
      '<td>' + unitLabel + '</td>' +
      '<td class="text-end">$' + money.format(item.unit_price) + '</td>' +
      '<td class="text-end fw-semibold">$' + money.format(item.line_total) + '</td>' +
      '<td><button type="button" class="btn btn-outline-danger btn-sm" data-remove aria-label="Quitar">&times;</button></td>';

    tr.classList.add('row-scanned');
    setTimeout(function () { tr.classList.remove('row-scanned'); }, 900);

    return tr;
  }

  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  // ── Eliminar ítem ────────────────────────────────────────────────────────────
  cartBody.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-remove]');
    if (!btn) return;
    const tr  = btn.closest('tr');
    const idx = Array.from(cartBody.querySelectorAll('tr:not(#cajaEmptyRow)')).indexOf(tr);
    if (idx < 0) return;
    cart.splice(idx, 1);
    tr.remove();
    updateSummary();
  });

  // ── Limpiar carrito ──────────────────────────────────────────────────────────
  clearBtn.addEventListener('click', function () {
    cart.length = 0;
    cartBody.querySelectorAll('tr:not(#cajaEmptyRow)').forEach(function (r) { r.remove(); });
    updateSummary();
    saleResult.style.display = 'none';
    scanInput.focus();
  });

  // ── Escaneo ──────────────────────────────────────────────────────────────────
  let scanTimer = 0;

  function showScanError(msg) { scanError.textContent = msg; }
  function clearScanError()   { scanError.textContent = ''; }

  function processBarcode(raw) {
    const barcode = raw.replace(/\D/g, '');
    if (barcode.length !== 13 || barcode[0] !== '2') {
      showScanError('Código inválido: se esperan 13 dígitos con prefijo 2');
      return;
    }
    clearScanError();

    fetch('/api_scan_barcode.php?barcode=' + encodeURIComponent(barcode), {
      headers: { 'Accept': 'application/json' }
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (!data.ok) {
          showScanError(data.error || 'Producto no encontrado');
          return;
        }

        const unit           = data.unit || 'u';
        const totalCents     = data.price_cents;
        const catalogCents   = data.catalog_price_cents || 0;
        let quantity, unitPrice;

        if ((unit === 'g' || unit === 'kg' || unit === 'l' || unit === 'ml') && catalogCents > 0) {
          // Derivar cantidad a partir del importe total y el precio del catálogo
          if (unit === 'g') {
            quantity  = Math.round(totalCents * 1000 / catalogCents);
          } else if (unit === 'ml') {
            quantity  = Math.round(totalCents * 1000 / catalogCents);
          } else {
            quantity  = Math.round(totalCents / catalogCents * 1000) / 1000;
          }
          unitPrice = data.catalog_price;
        } else {
          quantity  = 1;
          unitPrice = data.price;
        }

        const lineTotal = computeLineTotal(quantity, unit, unitPrice);

        const item = {
          description : data.name,
          quantity    : quantity,
          unit        : unit,
          unit_price  : unitPrice,
          line_total  : lineTotal,
        };

        cart.push(item);
        addCartRow(item);
        updateSummary();
        scanInput.value = '';
        scanInput.focus();
      })
      .catch(function () {
        showScanError('Error de red al buscar el producto');
      });
  }

  scanInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      clearTimeout(scanTimer);
      processBarcode(scanInput.value);
    }
  });

  scanInput.addEventListener('input', function () {
    clearTimeout(scanTimer);
    const val = scanInput.value.replace(/\D/g, '');
    if (val.length >= 13) {
      scanTimer = setTimeout(function () { processBarcode(val); }, 80);
    }
  });

  // ── Confirmar venta ──────────────────────────────────────────────────────────
  confirmBtn.addEventListener('click', function () {
    if (cart.length === 0) return;

    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Guardando...';
    saleResult.style.display = 'none';

    const payload = {
      csrf_token : CSRF,
      currency   : currencyEl.value,
      items      : cart.map(function (it) {
        return {
          description : it.description,
          quantity    : it.quantity,
          unit        : it.unit,
          unit_price  : it.unit_price,
        };
      }),
    };

    fetch('/caja.php', {
      method  : 'POST',
      headers : { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body    : JSON.stringify(payload),
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data.ok) {
          // Mostrar éxito, limpiar carrito
          showResult('success', data.message || 'Venta guardada');
          cart.length = 0;
          cartBody.querySelectorAll('tr:not(#cajaEmptyRow)').forEach(function (r) { r.remove(); });
          updateSummary();
        } else {
          showResult('danger', data.error || 'Error al guardar la venta');
          confirmBtn.disabled = false;
        }
        confirmBtn.textContent = 'Confirmar venta';
        scanInput.focus();
      })
      .catch(function () {
        showResult('danger', 'Error de red. Intentá de nuevo.');
        confirmBtn.disabled = false;
        confirmBtn.textContent = 'Confirmar venta';
      });
  });

  function showResult(type, msg) {
    saleResult.style.display = '';
    saleResult.innerHTML =
      '<div class="alert alert-' + type + ' py-2 mb-0 rounded-3">' + escHtml(msg) + '</div>';
    if (type === 'success') {
      setTimeout(function () { saleResult.style.display = 'none'; }, 3000);
    }
  }

  updateSummary();
  scanInput.focus();
})();
</script>
</body>
</html>
