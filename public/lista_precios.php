<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$config = app_config();

if (((string)($config['public_catalog']['enabled'] ?? '1')) === '0') {
    http_response_code(404);
    exit;
}

$appName = (string)($config['app']['name'] ?? 'Dietetic');
$csrf = csrf_token();

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>Lista de precios</title>
  <link rel="icon" type="image/png" href="/logo.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="stylesheet" href="/brand.css?v=20260118-2">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --accent:#463B1E; --accent-rgb:70,59,30; --accent-dark:#2f2713; --accent-2:#96957E; --accent-2-rgb:150,149,126; --ink:#241e10; --muted:#6b6453; --card:rgba(255,255,255,.92); --glow:rgba(255,255,255,.6); }
    body { position: relative; font-family:'Space Grotesk','Segoe UI',sans-serif; background: radial-gradient(circle at 10% 20%, rgba(var(--accent-2-rgb),.22), transparent 38%), radial-gradient(circle at 90% 10%, rgba(var(--accent-rgb),.12), transparent 40%), linear-gradient(120deg,#fbfaf6,#E7E3D5); color:var(--ink); min-height:100vh; }

    /* Hojas de fondo (distintos tamaños y orientaciones) */
    .bg-leaves { position: fixed; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }
    .bg-leaf { position: absolute; background: url('/fondo.png') no-repeat center / contain; opacity: .12; filter: drop-shadow(0 18px 40px rgba(15,23,42,.08)); }

    /* Distribución por toda la pantalla */
    .bg-leaf.leaf-1  { width: 240px; height: 240px; left: -90px;  top: 80px;   transform: rotate(-18deg); opacity: .09; }
    .bg-leaf.leaf-2  { width: 320px; height: 320px; right: -140px; top: -110px; transform: rotate(22deg) scaleX(-1); opacity: .10; }
    .bg-leaf.leaf-3  { width: 210px; height: 210px; right: -70px;  top: 38%;    transform: rotate(145deg); opacity: .08; }
    .bg-leaf.leaf-4  { width: 280px; height: 280px; left: -140px;  top: 52%;    transform: rotate(95deg) scaleX(-1); opacity: .07; }
    .bg-leaf.leaf-5  { width: 180px; height: 180px; left: 14%;     top: -70px;  transform: rotate(-40deg); opacity: .06; }
    .bg-leaf.leaf-6  { width: 220px; height: 220px; left: 62%;     top: 18%;    transform: rotate(28deg); opacity: .07; }
    .bg-leaf.leaf-7  { width: 160px; height: 160px; right: 14%;    top: 58%;    transform: rotate(-120deg) scaleX(-1); opacity: .06; }
    .bg-leaf.leaf-8  { width: 260px; height: 260px; right: -110px; bottom: 80px; transform: rotate(35deg); opacity: .07; }
    .bg-leaf.leaf-9  { width: 200px; height: 200px; left: 10%;     bottom: 120px; transform: rotate(155deg); opacity: .06; }
    .bg-leaf.leaf-10 { width: 340px; height: 340px; left: -160px;  bottom: -140px; transform: rotate(75deg); opacity: .07; }
    .bg-leaf.leaf-11 { width: 190px; height: 190px; left: 46%;     bottom: -90px; transform: rotate(-10deg) scaleX(-1); opacity: .06; }
    .bg-leaf.leaf-12 { width: 230px; height: 230px; right: 34%;    bottom: 22%;  transform: rotate(110deg); opacity: .06; }
    @media (max-width: 576px) {
      /* En móvil reducimos un poco tamaños para que no tapen */
      .bg-leaf { opacity: .08; }
      .bg-leaf.leaf-2  { width: 260px; height: 260px; right: -140px; top: -140px; }
      .bg-leaf.leaf-10 { width: 260px; height: 260px; left: -150px; bottom: -150px; }
      .bg-leaf.leaf-6  { width: 170px; height: 170px; left: 58%; top: 22%; }
      .bg-leaf.leaf-12 { width: 180px; height: 180px; right: 26%; bottom: 18%; }
    }

    /* Asegura que el contenido quede por encima del fondo */
    nav.navbar, main, .mobile-cartbar { position: relative; z-index: 1; }
    .navbar-glass { background:rgba(255,255,255,.92); backdrop-filter:blur(14px); border:1px solid rgba(15,23,42,.06); box-shadow:0 12px 45px rgba(15,23,42,.1); }
    .page-shell { padding:2.5rem 0 2rem; }
    .card-lift { background:var(--card); border:1px solid rgba(15,23,42,.06); box-shadow:0 22px 60px rgba(15,23,42,.08); border-radius:22px; }
    .muted-label { color:var(--muted); font-weight:600; text-transform:uppercase; letter-spacing:.04em; font-size:.8rem; }
    .pill { display:inline-flex; align-items:center; gap:.4rem; padding:.35rem .75rem; border-radius:999px; background:rgba(var(--accent-rgb),.1); color:var(--accent); font-weight:600; font-size:.9rem; }
    .btn-primary, .btn-primary:hover, .btn-primary:focus { background:linear-gradient(135deg,var(--accent),var(--accent-dark)); border:none; box-shadow:0 12px 32px rgba(var(--accent-rgb),.28); }
    .action-btn { border-radius:12px; font-weight:600; }
    .table thead th { background:rgba(var(--accent-rgb),.08); border-bottom:none; font-weight:600; color:var(--ink); }
    .table td, .table th { border-color:rgba(148,163,184,.35); }
    .cart-sticky { position: sticky; top: 1rem; }
    .qty-input { width: 86px; }
    .small-help { color: var(--muted); font-size: .9rem; }
    @media (max-width: 992px) { .cart-sticky { position: static; } }

    .price-chip {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      padding: .3rem .6rem;
      border-radius: 999px;
      background: rgba(var(--accent-rgb), .12);
      border: 1px solid rgba(var(--accent-rgb), .22);
      color: var(--accent-dark);
      font-weight: 700;
      font-size: .95rem;
      white-space: nowrap;
    }

    .hero-card {
      position: relative;
      overflow: hidden;
      background: linear-gradient(135deg, rgba(var(--accent-rgb),.08), rgba(255,255,255,.95));
      border: 1px solid rgba(15,23,42,.06);
      box-shadow: 0 20px 60px rgba(15,23,42,.08);
      border-radius: 26px;
    }
    .hero-glow {
      position: absolute;
      width: 320px;
      height: 320px;
      background: radial-gradient(circle, rgba(var(--accent-rgb),.18), transparent 60%);
      top: -160px;
      right: -120px;
      filter: blur(2px);
    }
    .hero-badges .badge {
      border-radius: 999px;
      font-weight: 600;
      padding: .45rem .75rem;
      background: rgba(var(--accent-rgb),.12);
      color: var(--accent);
      border: 1px solid rgba(var(--accent-rgb),.2);
    }
    .search-wrap {
      background: rgba(255,255,255,.9);
      border-radius: 16px;
      border: 1px solid rgba(148,163,184,.35);
      padding: .65rem .8rem;
      box-shadow: 0 12px 30px rgba(15,23,42,.06);
    }
    .search-wrap input { border: none; box-shadow: none; }
    .section-divider { height: 1px; background: linear-gradient(90deg, transparent, rgba(148,163,184,.5), transparent); margin: 1.25rem 0; }
    .info-chip {
      display: inline-flex;
      align-items: center;
      gap: .45rem;
      border-radius: 999px;
      padding: .35rem .7rem;
      background: rgba(var(--accent-rgb),.08);
      color: var(--accent-dark);
      font-weight: 600;
      font-size: .85rem;
    }
    .table tbody tr { transition: transform .15s ease, box-shadow .15s ease; }
    .table tbody tr:hover { transform: translateY(-2px); box-shadow: 0 12px 30px rgba(15,23,42,.08); }

    .product-row {
      display: flex;
      align-items: center;
      gap: .75rem;
    }
    .product-thumb {
      width: 56px;
      height: 56px;
      border-radius: 14px;
      object-fit: cover;
      border: 1px solid rgba(15,23,42,.08);
      background: #fff;
      flex: 0 0 auto;
    }
    .product-thumb--empty {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: .72rem;
      font-weight: 700;
      color: var(--accent);
      background: rgba(var(--accent-rgb), .08);
      border: 1px dashed rgba(var(--accent-rgb), .28);
    }

    /* Mobile cart bar + offcanvas */
    .mobile-cartbar {
      position: fixed;
      left: 0;
      right: 0;
      bottom: 0;
      z-index: 1040; /* below offcanvas (1045), above content */
      background: rgba(255,255,255,.92);
      backdrop-filter: blur(12px);
      border-top: 1px solid rgba(148,163,184,.35);
      box-shadow: 0 -10px 30px rgba(15,23,42,.08);
      padding: .65rem 0;
    }
    .mobile-cartbar .btn { border-radius: 14px; font-weight: 700; }
    .mobile-cartbar .total { font-weight: 800; }

    @media (max-width: 576px) {
      body { padding-bottom: 84px; } /* evita que la barra tape contenido */
    }

    /* Mobile-first polish */
    @media (max-width: 576px) {
      .page-shell { padding: 1rem 0; }
      .navbar .container { padding-top: .5rem !important; padding-bottom: .5rem !important; }
      .card-body.p-4 { padding: 1rem !important; }
      .qty-input { width: 116px; }
      .hero-card { border-radius: 20px; }

      /* Table -> stacked cards */
      .table-mobile thead { display: none; }
      .table-mobile tbody tr {
        display: block;
        background: rgba(255,255,255,.7);
        border: 1px solid rgba(148,163,184,.35);
        border-radius: 16px;
        padding: .95rem;
        margin-bottom: .75rem;
        box-shadow: 0 10px 30px rgba(15,23,42,.06);
      }
      .table-mobile tbody td {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .85rem;
        border: none !important;
        padding: .35rem 0;
      }
      .table-mobile tbody td::before {
        content: attr(data-label);
        flex: 0 0 auto;
        color: var(--muted);
        font-weight: 700;
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .04em;
      }
      .table-mobile tbody td[data-label="Producto"] {
        display: block;
        padding-bottom: .55rem;
      }
      .table-mobile tbody td[data-label="Producto"]::before { display: none; }

      .table-mobile .product-thumb { width: 52px; height: 52px; border-radius: 12px; }

      .table-mobile .price-cell {
        justify-content: flex-start !important;
      }
      .table-mobile .price-chip {
        font-size: .9rem;
      }

      .table-mobile .qty-cell {
        align-items: center;
      }
      .table-mobile .qty-wrap {
        justify-content: flex-end !important;
        padding: .35rem .4rem;
        background: rgba(255,255,255,.8);
        border-radius: 12px;
        border: 1px solid rgba(148,163,184,.3);
        box-shadow: inset 0 1px 0 rgba(255,255,255,.6);
      }
      .table-mobile .qty-wrap select { max-width: 110px !important; }
      .table-mobile .qty-input { text-align: right; }

      .table-mobile .action-cell {
        padding-top: .6rem;
      }
      .table-mobile .action-btn { width: 100%; }
      .table-mobile .btn-sm {
        padding: .65rem 1rem;
        font-size: 1rem;
        border-radius: 14px;
      }

      .table-mobile .product-cell .fw-semibold {
        font-size: 1.02rem;
      }
      .table-mobile .product-cell .text-muted {
        font-size: .92rem;
      }

      .table-mobile tbody td.product-cell { order: 1; }
      .table-mobile tbody td.price-cell { order: 2; }
      .table-mobile tbody td.qty-cell { order: 3; }
      .table-mobile tbody td.action-cell { order: 4; }
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
    <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-dark mb-0 h5 text-decoration-none" href="/" aria-label="<?= e($appName) ?>">
      <img src="/logo.png" alt="Logo" style="height:34px;width:auto;">
      <span class="d-none d-sm-inline"><?= e($appName) ?></span>
    </a>
    <div class="ms-auto d-flex align-items-center gap-2">
      <span class="pill">Lista de precios</span>
    </div>
  </div>
</nav>

<main class="container page-shell">
  <div class="row g-3">
    <div class="col-12 col-lg-8">
      <div class="card hero-card mb-3">
        <div class="hero-glow" aria-hidden="true"></div>
        <div class="card-body p-4">
          <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
              <p class="muted-label mb-1">Bienvenida</p>
              <h1 class="h3 mb-1">Elegí, agregá y encargá en minutos</h1>
              <div class="small-help">Armá tu pedido con productos frescos y precios claros.</div>
              <div class="mt-3 d-flex flex-wrap gap-2 hero-badges">
                <span class="badge">Entrega rápida</span>
                <span class="badge">Pago al retirar</span>
              </div>
            </div>
            <div class="w-100 w-md-auto" style="max-width: 380px;">
              <label class="form-label mb-2 fw-semibold" for="searchInput">Buscar producto</label>
              <div class="search-wrap">
                <input class="form-control" id="searchInput" placeholder="Ej: granola, almendras, té" autocomplete="off">
              </div>
              <div class="mt-2 d-flex flex-wrap gap-2">
                <span class="info-chip">Sin mínimos de compra</span>
                <span class="info-chip">Precios actualizados</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card card-lift">
        <div class="card-body p-4">
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
              <p class="muted-label mb-1">Catálogo</p>
              <h2 class="h5 mb-0">Productos disponibles</h2>
            </div>
            <div class="small-help">Sumá los productos al carrito y completá tus datos.</div>
          </div>

          <div class="section-divider"></div>

          <div class="mt-3" id="loadError" style="display:none;">
            <div class="alert alert-danger mb-0" id="loadErrorText"></div>
          </div>

          <div class="table-responsive mt-3">
            <table class="table table-mobile align-middle">
              <thead>
                <tr>
                  <th>Producto</th>
                  <th class="text-end">Precio</th>
                  <th style="width: 140px;">Cantidad</th>
                  <th style="width: 120px;"></th>
                </tr>
              </thead>
              <tbody id="itemsTbody">
                <tr>
                  <td colspan="4" class="text-center text-muted py-4">Cargando lista…</td>
                </tr>
              </tbody>
            </table>
          </div>

        </div>
      </div>
    </div>

    <div class="col-12 col-lg-4 d-none d-lg-block">
      <div class="cart-sticky">
        <div class="card card-lift">
          <div class="card-body p-4">
            <p class="muted-label mb-1">Tu pedido</p>
            <h2 class="h5 mb-3">Carrito</h2>

            <div id="cartEmpty" class="text-muted">Todavía no agregaste productos.</div>
            <div id="cartList" class="list-group mb-3" style="display:none;"></div>

            <div class="d-flex align-items-center justify-content-between mb-3">
              <div class="fw-semibold">Total</div>
              <div class="fw-bold" id="cartTotal">$0,00</div>
            </div>

            <hr>

            <form id="orderForm" class="vstack gap-2" autocomplete="on">
              <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

              <div>
                <label class="form-label mb-1" for="customerName">Nombre</label>
                <input class="form-control" id="customerName" name="customer_name" required maxlength="190" autocomplete="name">
              </div>

              <div>
                <label class="form-label mb-1" for="customerPhone">Teléfono / WhatsApp</label>
                <input class="form-control" id="customerPhone" name="customer_phone" type="tel" inputmode="tel" maxlength="40" placeholder="Ej: 11 1234-5678" autocomplete="tel">
              </div>

              <div>
                <label class="form-label mb-1" for="customerEmail">Email (opcional)</label>
                <input class="form-control" id="customerEmail" name="customer_email" type="email" inputmode="email" maxlength="190" placeholder="Ej: cliente@mail.com" autocomplete="email">
              </div>

              <div>
                <label class="form-label mb-1" for="customerDni">DNI (opcional)</label>
                <input class="form-control" id="customerDni" name="customer_dni" inputmode="numeric" maxlength="32" placeholder="Ej: 12345678" autocomplete="off">
              </div>

              <div>
                <label class="form-label mb-1" for="customerAddress">Dirección (opcional)</label>
                <input class="form-control" id="customerAddress" name="customer_address" maxlength="255" autocomplete="street-address">
              </div>

              <div>
                <label class="form-label mb-1" for="notes">Notas (opcional)</label>
                <textarea class="form-control" id="notes" name="notes" rows="2" placeholder=""></textarea>
              </div>

              <div class="d-grid mt-2">
                <button type="submit" class="btn btn-primary action-btn" id="submitBtn" disabled>Enviar pedido</button>
              </div>

              <div id="orderMsg" class="small-help" style="display:none;"></div>
            </form>
          </div>
        </div>

        <div class="mt-3 small-help">
          <div class="fw-semibold">Importante</div>
          <div>Los precios pueden cambiar sin aviso.</div>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- Mobile bottom bar (shows current total + opens the order panel) -->
<div class="mobile-cartbar d-lg-none" aria-label="Carrito móvil">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between gap-2">
      <div style="min-width:0;">
        <div class="muted-label mb-0">Tu pedido</div>
        <div class="total" id="mobileCartTotal">$0,00</div>
      </div>
      <button class="btn btn-primary action-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#cartOffcanvas" aria-controls="cartOffcanvas" id="mobileCartBtn" disabled>
        Ver pedido <span class="badge text-bg-light ms-2" id="mobileCartCount">0</span>
      </button>
    </div>
  </div>
</div>

<!-- Offcanvas cart/order for mobile -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="cartOffcanvas" aria-labelledby="cartOffcanvasLabel">
  <div class="offcanvas-header">
    <div>
      <div class="muted-label">Tu pedido</div>
      <h2 class="offcanvas-title h5 mb-0" id="cartOffcanvasLabel">Carrito</h2>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
  </div>
  <div class="offcanvas-body">
    <div id="cartEmptyMobile" class="text-muted">Todavía no agregaste productos.</div>
    <div id="cartListMobile" class="list-group mb-3" style="display:none;"></div>

    <div class="d-flex align-items-center justify-content-between mb-3">
      <div class="fw-semibold">Total</div>
      <div class="fw-bold" id="cartTotalMobile">$0,00</div>
    </div>

    <hr>

    <form id="orderFormMobile" class="vstack gap-2" autocomplete="on">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

      <div>
        <label class="form-label mb-1" for="customerNameMobile">Nombre</label>
        <input class="form-control" id="customerNameMobile" name="customer_name" required maxlength="190" autocomplete="name">
      </div>

      <div>
        <label class="form-label mb-1" for="customerPhoneMobile">Teléfono / WhatsApp</label>
        <input class="form-control" id="customerPhoneMobile" name="customer_phone" type="tel" inputmode="tel" maxlength="40" placeholder="Ej: 11 1234-5678" autocomplete="tel">
      </div>

      <div>
        <label class="form-label mb-1" for="customerEmailMobile">Email (opcional)</label>
        <input class="form-control" id="customerEmailMobile" name="customer_email" type="email" inputmode="email" maxlength="190" placeholder="Ej: cliente@mail.com" autocomplete="email">
      </div>

      <div>
        <label class="form-label mb-1" for="customerDniMobile">DNI (opcional)</label>
        <input class="form-control" id="customerDniMobile" name="customer_dni" inputmode="numeric" maxlength="32" placeholder="Ej: 12345678" autocomplete="off">
      </div>

      <div>
        <label class="form-label mb-1" for="customerAddressMobile">Dirección (opcional)</label>
        <input class="form-control" id="customerAddressMobile" name="customer_address" maxlength="255" autocomplete="street-address">
      </div>

      <div>
        <label class="form-label mb-1" for="notesMobile">Notas (opcional)</label>
        <textarea class="form-control" id="notesMobile" name="notes" rows="2" placeholder=""></textarea>
      </div>

      <div class="d-grid mt-2">
        <button type="submit" class="btn btn-primary action-btn" id="submitBtnMobile" disabled>Enviar pedido</button>
      </div>

      <div id="orderMsgMobile" class="small-help" style="display:none;"></div>
    </form>

    <div class="mt-3 small-help">
      <div class="fw-semibold">Importante</div>
      <div>Los precios pueden cambiar sin aviso.</div>
    </div>
  </div>
</div>

<!-- Modal de confirmación de pedido -->
<div class="modal fade" id="orderConfirmModal" tabindex="-1" aria-labelledby="orderConfirmTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:22px; overflow:hidden;">
      <div class="p-4" style="background:linear-gradient(135deg, rgba(var(--accent-rgb),.08), rgba(255,255,255,1)); position:relative;">
        <img src="/img/fondo.png" alt="" aria-hidden="true" style="position:absolute; right:-22px; top:-18px; width:140px; opacity:.18; transform:rotate(10deg);">
        <div class="d-flex align-items-center gap-3">
          <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:56px;height:56px;background:rgba(var(--accent-rgb),.14);color:var(--accent); font-weight:700; font-size:1.2rem;">
            ✓
          </div>
          <div>
            <div class="pill">Confirmado</div>
            <h3 class="h5 mt-2 mb-1" id="orderConfirmTitle">¡Pedido enviado!</h3>
            <p class="mb-0" id="orderConfirmText">Tu pedido fue enviado correctamente. Podés pasar a retirarlo.</p>
          </div>
        </div>
      </div>
      <div class="modal-body p-4">
        <div class="d-flex flex-column gap-3">
          <div class="d-flex align-items-center justify-content-between">
            <div class="text-muted">Total</div>
            <div class="fw-bold" id="orderConfirmTotal"></div>
          </div>
          <div class="p-3" style="background:rgba(var(--accent-rgb),.06); border-radius:14px; border:1px solid rgba(var(--accent-rgb),.16);">
            <div class="fw-semibold mb-1">Horarios de retiro</div>
            <div class="small-help">Lunes a sábado 10:30-13:45 · 18:00-21:30</div>
          </div>
        </div>
      </div>
      <div class="modal-footer border-0 pt-0 px-4 pb-4">
        <button type="button" class="btn btn-primary action-btn" data-bs-dismiss="modal">Entendido</button>
      </div>
    </div>
  </div>
</div>

<script>
(() => {
  const itemsTbody = document.getElementById('itemsTbody');
  const searchInput = document.getElementById('searchInput');
  const loadError = document.getElementById('loadError');
  const loadErrorText = document.getElementById('loadErrorText');

  const cartEmpty = document.getElementById('cartEmpty');
  const cartList = document.getElementById('cartList');
  const cartTotal = document.getElementById('cartTotal');
  const submitBtn = document.getElementById('submitBtn');
  const orderForm = document.getElementById('orderForm');
  const orderMsg = document.getElementById('orderMsg');

  const cartEmptyMobile = document.getElementById('cartEmptyMobile');
  const cartListMobile = document.getElementById('cartListMobile');
  const cartTotalMobile = document.getElementById('cartTotalMobile');
  const submitBtnMobile = document.getElementById('submitBtnMobile');
  const orderFormMobile = document.getElementById('orderFormMobile');
  const orderMsgMobile = document.getElementById('orderMsgMobile');

  const mobileCartTotal = document.getElementById('mobileCartTotal');
  const mobileCartBtn = document.getElementById('mobileCartBtn');
  const mobileCartCount = document.getElementById('mobileCartCount');

  /** cart: productId -> { id, name, price_cents, currency, unit, qty_base, qty_display, qty_display_unit } */
  const cart = new Map();
  let lastCurrency = 'ARS';

  const fmtMoney = (cents, currency) => {
    const amount = (cents || 0) / 100;
    const symbol = (String(currency || 'ARS').toUpperCase() === 'ARS') ? '$' : '$';
    return symbol + amount.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  const unitOptionsFor = (baseUnit) => {
    const u = String(baseUnit || '').trim();
    if (u === 'kg') return ['g', 'kg'];
    if (u === 'l') return ['ml', 'l'];
    if (u === 'g') return ['g'];
    if (u === 'ml') return ['ml'];
    if (u === 'un') return ['un'];
    return [''];
  };

  const defaultQtyFor = (baseUnit) => {
    const u = String(baseUnit || '').trim();
    if (u === 'kg') return { unit: 'g', value: '100' };
    if (u === 'l') return { unit: 'ml', value: '100' };
    if (u === 'un') return { unit: 'un', value: '1' };
    if (u === 'g') return { unit: 'g', value: '100' };
    if (u === 'ml') return { unit: 'ml', value: '100' };
    return { unit: '', value: '1' };
  };

  const inputStepFor = (displayUnit) => {
    const u = String(displayUnit || '').trim();
    if (u === 'un') return { step: '1', min: '1' };
    if (u === 'g' || u === 'ml') return { step: '1', min: '1' };
    // kg / l (o vacío)
    return { step: '0.01', min: '0.01' };
  };

  const toBaseQty = (qtyDisplay, displayUnit, baseUnit) => {
    const q = Number(qtyDisplay);
    if (!Number.isFinite(q) || q <= 0) return null;

    const du = String(displayUnit || '').trim();
    const bu = String(baseUnit || '').trim();

    if (bu === 'kg') {
      if (du === 'g') return q / 1000;
      return q; // kg
    }
    if (bu === 'l') {
      if (du === 'ml') return q / 1000;
      return q; // l
    }

    // base ya es g/ml/un
    return q;
  };

  const renderCart = () => {
    let totalCents = 0;
    let currency = lastCurrency;

    const calc = () => {
      totalCents = 0;
      currency = lastCurrency;
      for (const it of cart.values()) {
        currency = it.currency || currency;
        lastCurrency = currency;
        const line = Math.round((it.price_cents || 0) * (it.qty_base || 0));
        totalCents += line;
      }
    };

    const renderInto = (emptyEl, listEl, totalEl, submitEl) => {
      if (!emptyEl || !listEl || !totalEl || !submitEl) return;

      if (cart.size === 0) {
        emptyEl.style.display = '';
        listEl.style.display = 'none';
        listEl.innerHTML = '';
        totalEl.textContent = fmtMoney(0, currency);
        submitEl.disabled = true;
        return;
      }

      emptyEl.style.display = 'none';
      listEl.style.display = '';
      listEl.innerHTML = '';

      for (const it of cart.values()) {
        const row = document.createElement('div');
        row.className = 'list-group-item d-flex align-items-start justify-content-between gap-2';
        const unitLabel = it.unit ? (' / ' + it.unit) : '';
        const qtyText = (it.qty_display_unit && it.qty_display_unit !== '')
          ? (String(it.qty_display) + ' ' + String(it.qty_display_unit))
          : String(it.qty_display);

        row.innerHTML = `
          <div class="me-2" style="min-width: 0;">
            <div class="fw-semibold text-truncate">${escapeHtml(it.name || '')}</div>
            <div class="text-muted" style="font-size:.9rem;">${escapeHtml(qtyText)} × ${escapeHtml(fmtMoney(it.price_cents, it.currency || currency) + unitLabel)}</div>
          </div>
          <button class="btn btn-sm btn-outline-danger" type="button" aria-label="Quitar">Quitar</button>
        `;
        row.querySelector('button').addEventListener('click', () => {
          cart.delete(it.id);
          renderCart();
        });
        listEl.appendChild(row);
      }

      totalEl.textContent = fmtMoney(totalCents, currency);
      submitEl.disabled = false;
    };

    calc();
    renderInto(cartEmpty, cartList, cartTotal, submitBtn);
    renderInto(cartEmptyMobile, cartListMobile, cartTotalMobile, submitBtnMobile);

    if (mobileCartTotal) mobileCartTotal.textContent = fmtMoney(totalCents, currency);
    if (mobileCartCount) mobileCartCount.textContent = String(cart.size);
    if (mobileCartBtn) mobileCartBtn.disabled = cart.size === 0;
  };

  const escapeHtml = (s) => String(s)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

  const escapeAttr = escapeHtml;

  const renderItems = (items) => {
    if (!Array.isArray(items) || items.length === 0) {
      itemsTbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No hay productos para mostrar.</td></tr>';
      return;
    }

    itemsTbody.innerHTML = '';
    for (const it of items) {
      const tr = document.createElement('tr');
      const name = it.name || '';
      const desc = it.description || '';
      const unit = String(it.unit || '').trim();
      const imageUrl = String(it.image_url || '').trim();
      const priceBase = it.price_formatted || fmtMoney(it.price_cents || 0, it.currency || 'ARS');
      const price = it.price_label || (priceBase + (unit ? (' / ' + unit) : ''));

      const opts = unitOptionsFor(unit);
      const def = defaultQtyFor(unit);
      const hasSelect = opts.length > 1;
      const qtyConfig = inputStepFor(def.unit);

      const unitSelectHtml = hasSelect
        ? `<select class="form-select form-select-sm" style="max-width: 86px;">
            ${opts.map(u => `<option value="${escapeHtml(u)}" ${u === def.unit ? 'selected' : ''}>${escapeHtml(u)}</option>`).join('')}
           </select>`
        : `<span class="text-muted" style="font-size:.9rem;">${escapeHtml(def.unit || unit || '')}</span>`;

      const thumbHtml = imageUrl
        ? `<img class="product-thumb" src="${escapeAttr(imageUrl)}" alt="">`
        : `<span class="product-thumb product-thumb--empty">IMG</span>`;

      tr.innerHTML = `
        <td data-label="Producto" class="product-cell">
          <div class="product-row">
            ${thumbHtml}
            <div>
              <div class="fw-semibold">${escapeHtml(name)}</div>
              ${desc ? `<div class="text-muted" style="font-size:.9rem;">${escapeHtml(desc)}</div>` : ''}
            </div>
          </div>
        </td>
        <td class="text-end fw-semibold price-cell" data-label="Precio"><span class="price-chip">${escapeHtml(price)}</span></td>
        <td data-label="Cantidad" class="qty-cell">
          <div class="d-flex align-items-center gap-2 justify-content-end qty-wrap">
            <input type="number" class="form-control qty-input" value="${escapeHtml(def.value)}" min="${escapeHtml(qtyConfig.min)}" step="${escapeHtml(qtyConfig.step)}">
            ${unitSelectHtml}
          </div>
        </td>
        <td class="text-end action-cell" data-label="">
          <button type="button" class="btn btn-outline-primary btn-sm action-btn">Agregar</button>
        </td>
      `;

      const qtyInput = tr.querySelector('input');
      const unitSelect = tr.querySelector('select');
      const btn = tr.querySelector('button');

      if (unitSelect) {
        unitSelect.addEventListener('change', () => {
          const chosen = String(unitSelect.value || '').trim();
          const cfg = inputStepFor(chosen);
          qtyInput.min = cfg.min;
          qtyInput.step = cfg.step;

          // Ajuste simple de defaults al cambiar unidad
          if (chosen === 'kg' || chosen === 'l') {
            if (qtyInput.value === '' || Number(qtyInput.value) > 10) qtyInput.value = '0.1';
          } else if (chosen === 'g' || chosen === 'ml') {
            if (qtyInput.value === '' || Number(qtyInput.value) < 1) qtyInput.value = '100';
          }
        });
      }

      btn.addEventListener('click', () => {
        const raw = String(qtyInput.value || '1').replace(',', '.');
        const qty = Number(raw);
        if (!Number.isFinite(qty) || qty <= 0) {
          qtyInput.focus();
          return;
        }

        const displayUnit = unitSelect ? String(unitSelect.value || '').trim() : (unit || '');
        const qtyBase = toBaseQty(qty, displayUnit, unit);
        if (qtyBase === null || !Number.isFinite(qtyBase) || qtyBase <= 0) {
          qtyInput.focus();
          return;
        }

        cart.set(Number(it.id), {
          id: Number(it.id),
          name: name,
          price_cents: Number(it.price_cents || 0),
          currency: String(it.currency || 'ARS'),
          unit: unit,
          qty_base: qtyBase,
          qty_display: qty,
          qty_display_unit: displayUnit,
        });
        renderCart();
      });

      itemsTbody.appendChild(tr);
    }
  };

  let abort = null;
  let catalogCache = [];
  const filterLocal = (items, q) => {
    const term = String(q || '').trim().toLowerCase();
    if (term === '') return items;
    return items.filter(it =>
      String(it.name || '').toLowerCase().includes(term) ||
      String(it.description || '').toLowerCase().includes(term)
    );
  };

  const load = async (q) => {
    if (abort) abort.abort();
    abort = new AbortController();

    loadError.style.display = 'none';

    const url = '/api_public_catalog.php' + (q ? ('?q=' + encodeURIComponent(q)) : '');
    let res;
    let data;
    try {
      res = await fetch(url, { headers: { 'Accept': 'application/json' }, signal: abort.signal });
      data = await res.json().catch(() => null);
    } catch (err) {
      if (err && err.name === 'AbortError') {
        return;
      }
    }

    if (!res || !res.ok || !data || data.ok !== true) {
      if (q && Array.isArray(catalogCache) && catalogCache.length > 0) {
        renderItems(filterLocal(catalogCache, q));
        return;
      }

      const msg = (data && data.error) ? data.error : 'No se pudo cargar la lista.';
      loadErrorText.textContent = msg;
      loadError.style.display = '';
      renderItems([]);
      return;
    }

    const items = data.items || [];
    if (!q) {
      catalogCache = items;
    }
    renderItems(items);
  };

  let t = null;
  searchInput.addEventListener('input', () => {
    clearTimeout(t);
    t = setTimeout(() => load(searchInput.value.trim()), 250);
  });

  const bindOrderForm = (formEl, msgEl, submitEl) => {
    if (!formEl || !msgEl || !submitEl) return;

    formEl.addEventListener('submit', async (ev) => {
      ev.preventDefault();

      msgEl.style.display = 'none';
      msgEl.textContent = '';

      if (cart.size === 0) return;

      const items = [];
      for (const it of cart.values()) {
        // Enviamos cantidad en la unidad base del precio (ej: kg o l) para que el servidor calcule bien.
        items.push({ product_id: it.id, quantity: String(it.qty_base) });
      }

      const csrfInput = formEl.querySelector('input[name="csrf_token"]');
      const payload = {
        ajax: 1,
        csrf_token: csrfInput ? csrfInput.value : '',
        customer_name: String(formEl.elements['customer_name']?.value || ''),
        customer_phone: String(formEl.elements['customer_phone']?.value || ''),
        customer_email: String(formEl.elements['customer_email']?.value || ''),
        customer_dni: String(formEl.elements['customer_dni']?.value || ''),
        customer_address: String(formEl.elements['customer_address']?.value || ''),
        notes: String(formEl.elements['notes']?.value || ''),
        items,
      };

      submitEl.disabled = true;
      const originalText = submitEl.textContent;
      submitEl.textContent = 'Enviando…';

      try {
        const res = await fetch('/api_public_order.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify(payload),
        });
        const data = await res.json().catch(() => null);

        if (!res.ok || !data || data.ok !== true) {
          const msg = (data && data.error) ? data.error : 'No se pudo enviar el pedido.';
          msgEl.textContent = msg;
          msgEl.style.display = '';
          submitEl.disabled = false;
          submitEl.textContent = originalText;
          return;
        }

        msgEl.textContent = (data.message || 'Pedido enviado.') + ' Total: ' + (data.total_formatted || '');
        msgEl.style.display = '';
        cart.clear();
        renderCart();
        submitEl.textContent = 'Enviado';

        // Si estamos en el offcanvas, lo cerramos.
        const ocEl = document.getElementById('cartOffcanvas');
        if (ocEl && window.bootstrap && window.bootstrap.Offcanvas) {
          const oc = window.bootstrap.Offcanvas.getInstance(ocEl) || window.bootstrap.Offcanvas.getOrCreateInstance(ocEl);
          oc.hide();
        }

        // Mostrar modal de confirmación
        const modalEl = document.getElementById('orderConfirmModal');
        const confirmText = document.getElementById('orderConfirmText');
        const confirmTotal = document.getElementById('orderConfirmTotal');
        if (confirmText) confirmText.textContent = 'Tu pedido fue enviado correctamente. Podés pasar a retirarlo.';
        if (confirmTotal) confirmTotal.textContent = (data.total_formatted ? ('Total: ' + data.total_formatted) : '');
        if (modalEl && window.bootstrap && window.bootstrap.Modal) {
          const modal = window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
          modal.show();
        }

      } catch (e) {
        msgEl.textContent = 'No se pudo enviar el pedido.';
        msgEl.style.display = '';
        submitEl.disabled = false;
        submitEl.textContent = originalText;
      }
    });
  };

  bindOrderForm(orderForm, orderMsg, submitBtn);
  bindOrderForm(orderFormMobile, orderMsgMobile, submitBtnMobile);

  renderCart();
  load('');
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
