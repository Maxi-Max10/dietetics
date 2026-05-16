<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

auth_require_login();

$config  = app_config();
$appName = (string)($config['app']['name'] ?? 'Dietetic');
$userId  = (int)auth_user_id();
$csrf    = csrf_token();

function caja_decimal(mixed $value): float
{
    if (is_int($value) || is_float($value)) {
        $n = (float)$value;
        return is_finite($n) ? $n : 0.0;
    }

    $s = trim((string)$value);
    if ($s === '') {
        return 0.0;
    }
    $s = str_replace(['$', ' '], '', $s);
    if (str_contains($s, '.') && str_contains($s, ',')) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif (str_contains($s, ',')) {
        $s = str_replace(',', '.', $s);
    }
    $s = preg_replace('/[^0-9.\-]/', '', $s);
    if (!is_string($s) || $s === '' || !is_numeric($s)) {
        return 0.0;
    }

    $n = (float)$s;
    return is_finite($n) ? $n : 0.0;
}

function caja_compute_line_total(float $quantity, string $unit, float $basePrice): float
{
    $unit = invoice_normalize_unit($unit);
    if ($quantity <= 0 || $basePrice <= 0) {
        return 0.0;
    }
    if ($unit === 'g' || $unit === 'ml') {
        return round(($quantity / 1000.0) * $basePrice, 2);
    }
    return round($quantity * $basePrice, 2);
}

function caja_base_price_from_total(float $lineTotal, float $quantity, string $unit): float
{
    $unit = invoice_normalize_unit($unit);
    if ($lineTotal <= 0 || $quantity <= 0) {
        return 0.0;
    }
    if ($unit === 'g' || $unit === 'ml') {
        return round(($lineTotal * 1000.0) / $quantity, 2);
    }
    return round($lineTotal / $quantity, 2);
}

function caja_parse_sale_datetime(string $value): ?DateTimeImmutable
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    $value = str_replace('T', ' ', $value);
    if (preg_match('/^(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2})(?::(\d{2}))?$/', $value, $m) !== 1) {
        return null;
    }
    $time = $m[2] . ':' . ($m[3] ?? '00');
    try {
        return new DateTimeImmutable($m[1] . ' ' . $time, new DateTimeZone(date_default_timezone_get()));
    } catch (Throwable $e) {
        return null;
    }
}

function caja_money_detail(float $amount): string
{
    return '$' . number_format($amount, 2, ',', '.');
}

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

    $token        = (string)($body['csrf_token'] ?? '');
    $currency     = 'ARS';
    $items        = $body['items'] ?? [];
    $ticketTotal  = caja_decimal($body['ticket_total'] ?? 0);
    $saleDateTime = caja_parse_sale_datetime((string)($body['sale_datetime'] ?? ''));

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
    $detailLines = ['Venta cargada desde ticket de balanza (IA/OCR).'];
    if ($saleDateTime !== null) {
        $detailLines[] = 'Fecha/hora ticket: ' . $saleDateTime->format('Y-m-d H:i:s');
    }
    if ($ticketTotal > 0) {
        $detailLines[] = 'Total ticket detectado: ' . caja_money_detail($ticketTotal);
    }
    $detailItemLines = [];

    foreach ($items as $it) {
        $plu = preg_replace('/\D+/', '', (string)($it['plu'] ?? ''));
        $plu = is_string($plu) ? ltrim($plu, '0') : '';

        $desc      = trim((string)($it['description'] ?? ''));
        $qty       = caja_decimal($it['quantity'] ?? 0);
        $unit      = invoice_normalize_unit((string)($it['unit'] ?? 'u'));
        $price     = caja_decimal($it['unit_price'] ?? 0);
        $lineTotal = caja_decimal($it['line_total'] ?? 0);

        if ($desc === '' && $plu !== '') {
            $desc = 'PLU ' . $plu;
        }

        if ($price <= 0 && $lineTotal > 0 && $qty > 0) {
            $price = caja_base_price_from_total($lineTotal, $qty, $unit);
        }

        if ($price > 0 && $lineTotal > 0) {
            $computed = caja_compute_line_total($qty, $unit, $price);
            if ($computed > 0 && abs($computed - $lineTotal) > 0.05) {
                $price = caja_base_price_from_total($lineTotal, $qty, $unit);
            }
        }

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

        $detailItemLines[] = ($plu !== '' ? ('PLU ' . $plu . ' - ') : '') . $desc
            . ' | Cantidad: ' . (string)$qty . ' ' . $unit
            . ' | Precio base: ' . caja_money_detail($price)
            . ($lineTotal > 0 ? (' | Importe: ' . caja_money_detail($lineTotal)) : '');
    }

    if (count($detailItemLines) > 0) {
        $detailLines[] = 'Productos detectados:';
        foreach ($detailItemLines as $line) {
            $detailLines[] = $line;
        }
    }

    try {
        $pdo = db($config);
        $invoiceId = invoices_create(
            $pdo,
            $userId,
            'Mostrador',   // cliente genérico para ventas rápidas
            '',            // email
            implode("\n", $detailLines),
            $invoiceItems,
            $currency,
            '',
            '',
            '',
            $saleDateTime
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
  <link rel="stylesheet" href="/brand.css?v=20260516">
  <link rel="stylesheet" href="/public/brand.css?v=20260516">
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

    .caja-page .caja-shell {
      display: block !important;
      min-height: auto !important;
      height: auto !important;
      margin: 0 !important;
      padding: 1.25rem 0 3rem !important;
      align-items: initial !important;
      justify-content: initial !important;
      place-items: initial !important;
      transform: none !important;
      top: auto !important;
    }

    .caja-page .caja-shell > .container {
      width: 100%;
      max-width: 1140px;
      margin-top: 0 !important;
      transform: none !important;
    }

    body.has-leaves-bg .bg-leaves {
      position: fixed;
      inset: 0;
      pointer-events: none;
      z-index: 0;
      overflow: hidden;
    }

    body.has-leaves-bg > :not(.bg-leaves):not(.preload-overlay):not(.modal):not(.modal-backdrop):not(.offcanvas):not(.offcanvas-backdrop) {
      position: relative;
      z-index: 1;
    }

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

    .ticket-upload {
      border: 2px dashed rgba(var(--accent-rgb), .25);
      border-radius: 16px;
      background:
        linear-gradient(135deg, rgba(255,255,255,.88), rgba(231,227,213,.52)),
        rgba(255,255,255,.64);
      padding: 1.25rem;
      box-shadow: inset 0 1px 0 rgba(255,255,255,.7);
    }

    .ticket-upload-row {
      align-items: end;
    }

    .ticket-ai-btn {
      position: relative;
      min-width: 230px;
      min-height: 52px;
      border: 0;
      border-radius: 16px;
      overflow: hidden;
      background: linear-gradient(135deg, #10b981, #047857 42%, var(--accent));
      color: #fff;
      box-shadow: 0 16px 36px rgba(16,185,129,.28), 0 8px 22px rgba(var(--accent-rgb),.18);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: .65rem;
      font-weight: 800;
      letter-spacing: .01em;
      transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
    }

    .ticket-ai-btn::before {
      content: "";
      position: absolute;
      inset: 0;
      background: linear-gradient(110deg, transparent 0%, rgba(255,255,255,.24) 42%, transparent 66%);
      transform: translateX(-120%);
      transition: transform .5s ease;
    }

    .ticket-ai-btn:hover:not(:disabled),
    .ticket-ai-btn:focus-visible:not(:disabled) {
      transform: translateY(-1px);
      box-shadow: 0 20px 44px rgba(16,185,129,.34), 0 10px 26px rgba(var(--accent-rgb),.22);
      color: #fff;
    }

    .ticket-ai-btn:hover:not(:disabled)::before,
    .ticket-ai-btn:focus-visible:not(:disabled)::before {
      transform: translateX(120%);
    }

    .ticket-ai-btn:disabled {
      background: linear-gradient(135deg, #96957E, #7d7b68);
      box-shadow: none;
      color: rgba(255,255,255,.9);
      opacity: .82;
    }

    .ticket-ai-btn.is-ready {
      animation: ticket-ready-pulse 1.8s ease-in-out infinite;
    }

    .ticket-ai-btn.is-loading {
      animation: none;
      filter: saturate(1.08);
    }

    .ticket-ai-icon {
      width: 34px;
      height: 34px;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: rgba(255,255,255,.18);
      border: 1px solid rgba(255,255,255,.28);
      font-size: .82rem;
      font-weight: 900;
      flex: 0 0 auto;
    }

    .ticket-ai-spinner {
      display: none;
      width: 18px;
      height: 18px;
      border-radius: 999px;
      border: 2px solid rgba(255,255,255,.4);
      border-top-color: #fff;
      animation: ticket-spin .75s linear infinite;
      flex: 0 0 auto;
    }

    .ticket-ai-btn.is-loading .ticket-ai-icon { display: none; }
    .ticket-ai-btn.is-loading .ticket-ai-spinner { display: inline-block; }

    .ticket-progress {
      display: none;
      border: 1px solid rgba(16,185,129,.22);
      border-radius: 14px;
      background: rgba(255,255,255,.74);
      padding: .75rem .85rem;
      box-shadow: 0 12px 28px rgba(16,185,129,.08);
    }

    .ticket-progress.is-visible {
      display: block;
    }

    .ticket-progress-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .75rem;
      color: var(--muted);
      font-size: .88rem;
      font-weight: 700;
      margin-bottom: .45rem;
    }

    .ticket-progress-track {
      height: 11px;
      border-radius: 999px;
      background: rgba(70,59,30,.10);
      overflow: hidden;
    }

    .ticket-progress-bar {
      position: relative;
      width: 0%;
      height: 100%;
      border-radius: inherit;
      background: linear-gradient(90deg, #34d399, #10b981, var(--accent));
      transition: width .28s ease;
      overflow: hidden;
    }

    .ticket-progress-bar::after {
      content: "";
      position: absolute;
      inset: 0;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,.42), transparent);
      animation: ticket-progress-shine 1.1s linear infinite;
    }

    @keyframes ticket-spin { to { transform: rotate(360deg); } }

    @keyframes ticket-ready-pulse {
      0%, 100% { box-shadow: 0 16px 36px rgba(16,185,129,.25), 0 8px 22px rgba(var(--accent-rgb),.16); }
      50% { box-shadow: 0 20px 46px rgba(16,185,129,.38), 0 10px 26px rgba(var(--accent-rgb),.22); }
    }

    @keyframes ticket-progress-shine {
      from { transform: translateX(-100%); }
      to { transform: translateX(100%); }
    }

    .ticket-preview {
      display: none;
      width: 100%;
      max-height: 260px;
      object-fit: contain;
      border-radius: 12px;
      background: rgba(15,23,42,.04);
      border: 1px solid rgba(15,23,42,.08);
    }

    .ticket-preview.is-visible { display: block; }

    .ticket-status {
      min-height: 1.3em;
      color: var(--muted);
    }

    .editable-cell {
      min-width: 96px;
    }

    .editable-cell--product {
      min-width: 190px;
    }

    .cart-input {
      min-width: 0;
      border-radius: 10px;
    }

    .confidence-chip {
      display: inline-flex;
      align-items: center;
      padding: .18rem .5rem;
      border-radius: 999px;
      background: rgba(15,23,42,.06);
      color: var(--muted);
      font-size: .78rem;
      font-weight: 650;
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
      .caja-page .caja-shell { padding: 1rem .75rem 2rem !important; }
      .card-lift { border-radius: 14px; }
      .ticket-ai-btn { width: 100%; min-width: 0; }
    }

    @media (max-width: 576px) {
      .nav-shell { display: none; }
    }
  </style>
</head>
<body class="has-leaves-bg caja-page">
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

<main class="caja-shell">
  <div class="container">
    <div class="row g-4">

      <!-- Panel izquierdo: ticket + venta editable -->
      <div class="col-12 col-lg-8">
        <div class="card card-lift">
          <div class="card-header card-header-clean bg-white px-4 py-3">
            <p class="muted-label mb-1">Modo caja</p>
            <h2 class="h5 mb-0">Ticket de balanza (OCR/IA visual)</h2>
          </div>
          <div class="card-body px-4 py-4">

            <div class="ticket-upload mb-3">
              <div class="row g-3 ticket-upload-row">
                <div class="col-12 col-md">
                  <label for="cajaTicketImage" class="form-label fw-semibold mb-1">Foto del ticket</label>
                  <input type="file" id="cajaTicketImage" class="form-control" accept="image/*" capture="environment">
                </div>
                <div class="col-12 col-lg-auto">
                  <button type="button" id="cajaAnalyzeTicket" class="btn ticket-ai-btn w-100" disabled>
                    <span class="ticket-ai-icon">IA</span>
                    <span class="ticket-ai-spinner" aria-hidden="true"></span>
                    <span class="ticket-ai-label">Leer ticket con IA</span>
                  </button>
                </div>
              </div>
              <div id="cajaTicketProgress" class="ticket-progress mt-3" aria-hidden="true">
                <div class="ticket-progress-head">
                  <span id="cajaTicketProgressText">Preparando lectura...</span>
                  <span id="cajaTicketProgressPct">0%</span>
                </div>
                <div class="ticket-progress-track">
                  <div id="cajaTicketProgressBar" class="ticket-progress-bar"></div>
                </div>
              </div>
              <img id="cajaTicketPreview" class="ticket-preview mt-3" alt="Vista previa del ticket">
              <div id="cajaTicketStatus" class="ticket-status small mt-2"></div>
            </div>

            <div id="cajaTicketWarnings" class="mb-3" style="display:none"></div>

            <!-- Tabla venta editable -->
            <div class="table-responsive">
              <table class="table align-middle" id="cajaCart">
                <thead>
                  <tr>
                    <th style="width:90px">PLU</th>
                    <th>Producto</th>
                    <th style="width:110px">Cant.</th>
                    <th style="width:60px">Un.</th>
                    <th style="width:130px">Precio base</th>
                    <th style="width:120px">Subtotal</th>
                    <th style="width:44px"></th>
                  </tr>
                </thead>
                <tbody id="cajaCartBody">
                  <tr id="cajaEmptyRow">
                    <td colspan="7" class="text-center text-muted py-4">
                      <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mb-2 d-block mx-auto opacity-50" aria-hidden="true">
                        <path d="M4 7h3l2-2h6l2 2h3v12H4z"/>
                        <circle cx="12" cy="13" r="3"/>
                      </svg>
                      Carga una foto para detectar productos
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="d-flex justify-content-end">
              <button type="button" id="cajaAddItem" class="btn btn-outline-primary btn-sm action-btn">
                Agregar producto
              </button>
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
              <div class="form-label fw-semibold">Moneda</div>
              <div class="form-control bg-light">ARS — Pesos</div>
              <input type="hidden" id="cajaCurrency" value="ARS">
            </div>

            <div class="mb-3">
              <label for="cajaSaleDatetime" class="form-label fw-semibold">Fecha/hora de venta</label>
              <input type="datetime-local" id="cajaSaleDatetime" class="form-control">
            </div>

            <hr class="my-3">

            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="text-muted">Ítems</span>
              <span id="cajaItemCount" class="fw-semibold">0</span>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="text-muted">Total detectado</span>
              <span id="cajaDetectedTotal" class="fw-semibold">-</span>
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
  const SAVE_URL = window.location.pathname || '/caja';

  const ticketInput      = document.getElementById('cajaTicketImage');
  const analyzeBtn       = document.getElementById('cajaAnalyzeTicket');
  const analyzeBtnLabel  = analyzeBtn ? analyzeBtn.querySelector('.ticket-ai-label') : null;
  const ticketPreview    = document.getElementById('cajaTicketPreview');
  const ticketStatus     = document.getElementById('cajaTicketStatus');
  const ticketWarnings   = document.getElementById('cajaTicketWarnings');
  const ticketProgress   = document.getElementById('cajaTicketProgress');
  const progressBar      = document.getElementById('cajaTicketProgressBar');
  const progressText     = document.getElementById('cajaTicketProgressText');
  const progressPct      = document.getElementById('cajaTicketProgressPct');
  const addItemBtn       = document.getElementById('cajaAddItem');
  const cartBody         = document.getElementById('cajaCartBody');
  const emptyRow         = document.getElementById('cajaEmptyRow');
  const itemCountEl      = document.getElementById('cajaItemCount');
  const detectedTotalEl  = document.getElementById('cajaDetectedTotal');
  const totalDisplay     = document.getElementById('cajaTotalDisplay');
  const saleDatetimeEl   = document.getElementById('cajaSaleDatetime');
  const confirmBtn       = document.getElementById('cajaConfirm');
  const clearBtn         = document.getElementById('cajaClear');
  const saleResult       = document.getElementById('cajaSaleResult');
  const currencyEl       = document.getElementById('cajaCurrency');

  const money = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  // ── Cart state ──────────────────────────────────────────────────────────────
  // Cada ítem: { description, quantity, unit, unit_price, line_total }
  const cart = [];
  let detectedTicketTotal = 0;
  let previewUrl = '';
  let progressTimer = 0;
  let progressValue = 0;

  function setAnalyzeButton(text, loading) {
    if (analyzeBtnLabel) analyzeBtnLabel.textContent = text;
    analyzeBtn.classList.toggle('is-loading', !!loading);
  }

  function setTicketProgress(percent, text) {
    progressValue = Math.max(0, Math.min(100, Math.round(percent)));
    if (ticketProgress) {
      ticketProgress.classList.add('is-visible');
      ticketProgress.setAttribute('aria-hidden', 'false');
    }
    if (progressBar) progressBar.style.width = progressValue + '%';
    if (progressPct) progressPct.textContent = progressValue + '%';
    if (progressText && text) progressText.textContent = text;
  }

  function resetTicketProgress() {
    window.clearInterval(progressTimer);
    progressTimer = 0;
    progressValue = 0;
    if (progressBar) progressBar.style.width = '0%';
    if (progressPct) progressPct.textContent = '0%';
    if (progressText) progressText.textContent = 'Preparando lectura...';
    if (ticketProgress) {
      ticketProgress.classList.remove('is-visible');
      ticketProgress.setAttribute('aria-hidden', 'true');
    }
  }

  function startTicketProgress(text) {
    window.clearInterval(progressTimer);
    setTicketProgress(8, text || 'Preparando lectura...');
    progressTimer = window.setInterval(function () {
      if (progressValue < 42) {
        setTicketProgress(progressValue + 4, 'Subiendo foto al OCR...');
      } else if (progressValue < 76) {
        setTicketProgress(progressValue + 2, 'Leyendo datos del ticket...');
      } else if (progressValue < 91) {
        setTicketProgress(progressValue + 1, 'Armando venta editable...');
      }
    }, 420);
  }

  function finishTicketProgress(ok) {
    window.clearInterval(progressTimer);
    progressTimer = 0;
    setTicketProgress(100, ok ? 'Ticket procesado.' : 'Lectura finalizada.');
    window.setTimeout(resetTicketProgress, ok ? 850 : 1300);
  }

  function computeLineTotal(quantity, unit, unitPrice) {
    if (unit === 'g' || unit === 'ml') {
      return (quantity / 1000) * unitPrice;
    }
    return quantity * unitPrice;
  }

  function computeBasePrice(lineTotal, quantity, unit) {
    if (lineTotal <= 0 || quantity <= 0) return 0;
    if (unit === 'g' || unit === 'ml') {
      return (lineTotal * 1000) / quantity;
    }
    return lineTotal / quantity;
  }

  function cartTotal() {
    return cart.reduce(function (sum, it) { return sum + it.line_total; }, 0);
  }

  function updateSummary() {
    const total = cartTotal();
    itemCountEl.textContent = cart.length;
    totalDisplay.textContent = '$' + money.format(total);
    detectedTotalEl.textContent = detectedTicketTotal > 0 ? ('$' + money.format(detectedTicketTotal)) : '-';
    detectedTotalEl.classList.toggle('text-danger', detectedTicketTotal > 0 && Math.abs(total - detectedTicketTotal) > 0.10);
    confirmBtn.disabled = cart.length === 0;
    clearBtn.style.display = cart.length === 0 ? 'none' : '';
    emptyRow.style.display = cart.length === 0 ? '' : 'none';
  }

  function renderCart() {
    cartBody.querySelectorAll('tr:not(#cajaEmptyRow)').forEach(function (r) { r.remove(); });
    cart.forEach(function (item, idx) { addCartRow(item, idx); });
    updateSummary();
  }

  function addCartRow(item, idx) {
    const tr = document.createElement('tr');
    tr.dataset.index = String(idx);
    cartBody.insertBefore(tr, emptyRow);

    const confidence = item.confidence > 0
      ? '<div class="confidence-chip mt-1">' + Math.round(item.confidence * 100) + '% IA</div>'
      : '';

    tr.innerHTML =
      '<td class="editable-cell"><input type="text" class="form-control form-control-sm cart-input" data-field="plu" value="' + escAttr(item.plu || '') + '" inputmode="numeric"></td>' +
      '<td class="editable-cell editable-cell--product"><input type="text" class="form-control form-control-sm cart-input" data-field="description" value="' + escAttr(item.description) + '">' + confidence + '</td>' +
      '<td class="editable-cell"><input type="number" min="0" step="0.001" class="form-control form-control-sm cart-input text-end" data-field="quantity" value="' + escAttr(formatInputNumber(item.quantity)) + '"></td>' +
      '<td><select class="form-select form-select-sm cart-input" data-field="unit">' + unitOptions(item.unit) + '</select></td>' +
      '<td class="editable-cell"><input type="number" min="0" step="0.01" class="form-control form-control-sm cart-input text-end" data-field="unit_price" value="' + escAttr(formatInputNumber(item.unit_price)) + '"></td>' +
      '<td class="editable-cell"><input type="number" min="0" step="0.01" class="form-control form-control-sm cart-input text-end fw-semibold" data-field="line_total" value="' + escAttr(formatInputNumber(item.line_total)) + '"></td>' +
      '<td><button type="button" class="btn btn-outline-danger btn-sm" data-remove aria-label="Quitar">&times;</button></td>';

    tr.classList.add('row-scanned');
    setTimeout(function () { tr.classList.remove('row-scanned'); }, 900);
  }

  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function escAttr(str) {
    return escHtml(str).replace(/'/g, '&#039;');
  }

  function toNumber(value) {
    const n = Number(String(value || '').replace(',', '.'));
    return Number.isFinite(n) ? n : 0;
  }

  function formatInputNumber(value) {
    const n = Number(value || 0);
    if (!Number.isFinite(n)) return '0';
    return String(Math.round(n * 1000) / 1000).replace(/\.0+$/, '');
  }

  function normalizeUnit(unit) {
    const u = String(unit || '').toLowerCase().trim();
    if (['g', 'kg', 'ml', 'l', 'u'].includes(u)) return u;
    if (['gr', 'grs', 'gramo', 'gramos'].includes(u)) return 'g';
    if (['kilo', 'kilos'].includes(u)) return 'kg';
    if (['lt', 'lts', 'litro', 'litros'].includes(u)) return 'l';
    if (['un', 'uni', 'unidad', 'unidades'].includes(u)) return 'u';
    return 'u';
  }

  function unitOptions(current) {
    const unit = normalizeUnit(current);
    return ['u', 'g', 'kg', 'ml', 'l'].map(function (opt) {
      return '<option value="' + opt + '"' + (opt === unit ? ' selected' : '') + '>' + opt + '</option>';
    }).join('');
  }

  function normalizeTicketItem(raw) {
    const unit = normalizeUnit(raw.unit);
    const quantity = toNumber(raw.quantity);
    let lineTotal = toNumber(raw.line_total);
    let unitPrice = toNumber(raw.unit_price);

    if (unitPrice <= 0 && lineTotal > 0) {
      unitPrice = computeBasePrice(lineTotal, quantity, unit);
    }
    if (lineTotal <= 0 && unitPrice > 0) {
      lineTotal = computeLineTotal(quantity, unit, unitPrice);
    }
    if ((unit === 'kg' || unit === 'l') && quantity > 50 && unitPrice > 0 && lineTotal > 0) {
      const expectedQuantity = lineTotal / unitPrice;
      if (expectedQuantity > 0 && Math.abs(expectedQuantity - (quantity / 1000)) < 0.01) {
        quantity = Math.round(quantity) / 1000;
      }
    }

    const plu = String(raw.plu || '').replace(/\D/g, '');
    const name = String(raw.name || raw.catalog_name || '').trim() || (plu ? ('PLU ' + plu) : 'Producto ticket');

    return {
      plu: plu,
      description: name,
      quantity: quantity,
      unit: unit,
      unit_price: Math.round(unitPrice * 100) / 100,
      line_total: Math.round(lineTotal * 100) / 100,
      confidence: Math.max(0, Math.min(1, toNumber(raw.confidence))),
    };
  }

  function applyDetectedTicket(ticket, statusMessage) {
    const items = Array.isArray(ticket.items) ? ticket.items : [];
    cart.length = 0;
    items.forEach(function (raw) { cart.push(normalizeTicketItem(raw)); });
    detectedTicketTotal = toNumber(ticket.total);

    if (ticket.sale_date && ticket.sale_time) {
      saleDatetimeEl.value = String(ticket.sale_date) + 'T' + String(ticket.sale_time).slice(0, 5);
    }

    const warnings = Array.isArray(ticket.warnings) ? ticket.warnings.slice() : [];
    cart.forEach(function (item, idx) {
      if (!item.plu || item.quantity <= 0 || !item.unit) {
        warnings.push('Item ' + String(idx + 1) + ' necesita revision: falta PLU, cantidad o unidad.');
      }
    });

    const total = cartTotal();
    if (detectedTicketTotal > 0 && Math.abs(total - detectedTicketTotal) > 0.10) {
      warnings.push('El total detectado no coincide con la suma editable. Revisalo antes de confirmar.');
    }
    showWarnings(warnings);
    renderCart();
    showTicketStatus(statusMessage || 'Ticket leido. Revisa y edita la venta antes de confirmar.', 'success');
  }

  function loadScriptOnce(src) {
    return new Promise(function (resolve, reject) {
      const existing = document.querySelector('script[src="' + src + '"]');
      if (existing) {
        existing.addEventListener('load', resolve, { once: true });
        existing.addEventListener('error', reject, { once: true });
        if (window.Tesseract) resolve();
        return;
      }
      const script = document.createElement('script');
      script.src = src;
      script.async = true;
      script.onload = resolve;
      script.onerror = reject;
      document.head.appendChild(script);
    });
  }

  function parseMoney(raw) {
    let s = String(raw || '').replace(/[^\d.,]/g, '');
    if (!s) return 0;
    if (s.includes('.') && s.includes(',')) {
      s = s.replace(/\./g, '').replace(',', '.');
    } else if (s.includes(',')) {
      s = s.replace(',', '.');
    } else if (/^\d{1,3}(?:\.\d{3})+$/.test(s) && !s.startsWith('0.')) {
      s = s.replace(/\./g, '');
    }
    const n = Number(s);
    return Number.isFinite(n) ? n : 0;
  }

  function parseQuantity(raw) {
    let s = String(raw || '').replace(/[^\d.,]/g, '');
    if (!s) return 0;
    if (s.includes(',') && !s.includes('.')) {
      s = s.replace(',', '.');
    } else if (s.includes('.') && s.includes(',')) {
      s = s.replace(/\./g, '').replace(',', '.');
    }
    const n = Number(s);
    return Number.isFinite(n) ? n : 0;
  }

  function parseDateToIso(text) {
    const months = {
      ene: '01', jan: '01', feb: '02', mar: '03', abr: '04', apr: '04',
      may: '05', jun: '06', jul: '07', ago: '08', aug: '08', sep: '09',
      set: '09', oct: '10', nov: '11', dic: '12', dec: '12'
    };
    const m = String(text || '').toLowerCase().match(/(\d{1,2})[\/\-. ]?([a-z]{3}|\d{1,2})[\/\-. ]?(\d{2,4})/i);
    if (!m) return '';
    const day = String(m[1]).padStart(2, '0');
    const month = months[m[2]] || String(m[2]).padStart(2, '0');
    let year = String(m[3]);
    if (year.length === 2) year = '20' + year;
    return year + '-' + month + '-' + day;
  }

  function parseTime(text) {
    const m = String(text || '').match(/([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?/);
    if (!m) return '';
    return String(m[1]).padStart(2, '0') + ':' + m[2] + ':' + (m[3] || '00');
  }

  function parseTicketTextLegacy(text) {
    const source = String(text || '');
    const compact = source.replace(/\s+/g, ' ');
    const totalMatch = compact.match(/total\s*\$?\s*([0-9][0-9.,]*)/i);
    let total = totalMatch ? parseMoney(totalMatch[1]) : 0;

    if (total <= 0) {
      const amounts = Array.from(compact.matchAll(/\$?\s*([0-9]{3,}(?:[.,][0-9]{1,2})?)/g))
        .map(function (m) { return parseMoney(m[1]); })
        .filter(function (n) { return n > 0; });
      if (amounts.length) total = Math.max.apply(null, amounts);
    }

    const pluMatch = compact.match(/(?:\[\s*|\b)(\d{2,5})(?:\s*\])?\s*[-: ]+\s*([A-Za-zÁÉÍÓÚÑáéíóúñ][A-Za-zÁÉÍÓÚÑáéíóúñ ]{2,40})?/);
    const betterPluMatch = compact.match(/(?:plu|ic\.?plu)?\D{0,14}[\[(]?\s*(\d{2,5})\s*[\])]?\s*[-:]\s*([A-Za-z][A-Za-z .]{2,40})?/i)
      || compact.match(/[\[(]\s*(\d{2,5})\s*[\])]\s*[-:]\s*([A-Za-z][A-Za-z .]{2,40})?/i);
    const matchedPlu = betterPluMatch || pluMatch;
    const plu = matchedPlu ? String(matchedPlu[1]).replace(/^0+/, '') : '';
    let name = matchedPlu && matchedPlu[2] ? matchedPlu[2].trim() : '';
    name = name.replace(/\b(cantidad|precio|unit|importe|total)\b.*$/i, '').trim();
    if (!name && /pistach/i.test(compact)) name = 'Pistachos';
    if (!name) name = plu ? ('PLU ' + plu) : 'Producto ticket';

    let quantity = 1;
    let unit = 'u';
    const qtyMatch = compact.match(/([0-9]+(?:[.,][0-9]+)?)\s*(kg|kilo|g|gr|gramos|ml|l)\b/i);
    if (qtyMatch) {
      quantity = parseMoney(qtyMatch[1]);
      unit = normalizeUnit(qtyMatch[2]);
    }

    let unitPrice = 0;
    const unitPriceMatch = compact.match(/(?:x|por|\/)\s*\$?\s*([0-9][0-9.,]*)\s*(?:\/?\s*(kg|kilo|g|gr|ml|l))?/i)
      || compact.match(/\$?\s*([0-9][0-9.,]*)\s*\$?\s*\/\s*(kg|kilo|g|gr|ml|l)/i);
    if (unitPriceMatch) {
      unitPrice = parseMoney(unitPriceMatch[1]);
    }

    if (unitPrice <= 0 && total > 0) {
      unitPrice = computeBasePrice(total, quantity, unit);
    }

    const lineTotal = total > 0 ? total : computeLineTotal(quantity, unit, unitPrice);
    return {
      sale_date: parseDateToIso(source),
      sale_time: parseTime(source),
      items: [{
        plu: plu,
        name: name,
        quantity: quantity,
        unit: unit,
        unit_price: unitPrice,
        line_total: lineTotal,
        confidence: 0.55,
      }],
      total: lineTotal,
      confidence: 0.55,
      warnings: ['Lectura local del navegador: revisa los campos antes de confirmar.'],
      raw_text: source.slice(0, 500),
    };
  }

  function cleanTicketProductName(name) {
    let s = String(name || '')
      .replace(/[_|]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
    s = s.replace(/\b(cantidad|precio|unit|importe|total|vendedor|descripcion)\b.*$/i, '').trim();
    s = s.replace(/\b[0-9]+(?:[.,][0-9]+)?\s*(kg|kilo|g|gr|gramos|ml|l)\b.*$/i, '').trim();
    s = s.replace(/\b[0-9]{3,}\s*\$?.*$/i, '').trim();
    return s;
  }

  function plausibleTicketAmount(value, total) {
    return value > 0 && value < 10000000 && (total <= 0 || value <= Math.max(total * 1.15, total + 1000));
  }

  function parseTicketItemBlock(plu, headerName, block, total) {
    const name = cleanTicketProductName(headerName) || (plu ? ('PLU ' + plu) : 'Producto ticket');
    let quantity = 1;
    let unit = 'u';
    const qtyMatch = block.match(/([0-9]+(?:[.,][0-9]+)?)\s*(kg|kilo|kilos|g|gr|grs|gramos|ml|l)\b/i);
    if (qtyMatch) {
      quantity = parseQuantity(qtyMatch[1]);
      unit = normalizeUnit(qtyMatch[2]);
    }

    let unitPrice = 0;
    const unitPriceMatch = block.match(/([0-9]{2,}(?:[.,][0-9]{3})*(?:[.,][0-9]{1,2})?)\s*\$?\s*\/\s*(kg|kilo|kilos|g|gr|grs|gramos|ml|l|u|un)\b/i)
      || block.match(/(?:x|por)\s*\$?\s*([0-9]{2,}(?:[.,][0-9]{3})*(?:[.,][0-9]{1,2})?)/i);
    if (unitPriceMatch) {
      unitPrice = parseMoney(unitPriceMatch[1]);
    }

    let lineTotal = 0;
    const afterPrice = unitPriceMatch && typeof unitPriceMatch.index === 'number'
      ? block.slice(unitPriceMatch.index + unitPriceMatch[0].length)
      : block;
    const totalCandidates = Array.from(afterPrice.matchAll(/(?:^|[^\d])([0-9]{3,6}(?:[.,][0-9]{3})*(?:[.,][0-9]{1,2})?)\s*\$?/g))
      .map(function (m) { return parseMoney(m[1]); })
      .filter(function (n) { return plausibleTicketAmount(n, total) && Math.abs(n - unitPrice) > 0.01; });
    if (totalCandidates.length) {
      lineTotal = totalCandidates[0];
    }
    if (lineTotal <= 0 && quantity > 0 && unitPrice > 0) {
      lineTotal = computeLineTotal(quantity, unit, unitPrice);
    }
    if (unitPrice <= 0 && quantity > 0 && lineTotal > 0) {
      unitPrice = computeBasePrice(lineTotal, quantity, unit);
    }

    return {
      plu: plu,
      name: name,
      quantity: quantity > 0 ? quantity : 1,
      unit: unit,
      unit_price: unitPrice,
      line_total: lineTotal,
      confidence: lineTotal > 0 ? 0.62 : 0.42,
    };
  }

  function parseTicketText(text) {
    const source = String(text || '');
    const compact = source.replace(/\s+/g, ' ');
    const totalMatches = Array.from(compact.matchAll(/\btotal\b\s*\$?\s*([0-9][0-9.,]*)/ig));
    let total = totalMatches.length ? parseMoney(totalMatches[totalMatches.length - 1][1]) : 0;

    if (total <= 0) {
      const amounts = Array.from(compact.matchAll(/\$?\s*([0-9]{3,6}(?:[.,][0-9]{3})*(?:[.,][0-9]{1,2})?)/g))
        .map(function (m) { return parseMoney(m[1]); })
        .filter(function (n) { return n > 0 && n < 10000000; });
      if (amounts.length) total = Math.max.apply(null, amounts);
    }

    const headerMatches = [];
    const headerRegex = /[\[(]\s*(\d{1,5})\s*[\])]\s*[-:]\s*([^\n\r]{0,90})/gi;
    let match;
    while ((match = headerRegex.exec(source)) !== null) {
      headerMatches.push({
        index: match.index,
        plu: String(match[1] || '').replace(/^0+/, ''),
        name: match[2] || '',
      });
    }

    const items = [];
    headerMatches.forEach(function (h, idx) {
      const next = headerMatches[idx + 1] ? headerMatches[idx + 1].index : source.length;
      const block = source.slice(h.index, next);
      const item = parseTicketItemBlock(h.plu, h.name, block, total);
      if (item.line_total > 0 || item.plu || item.name) {
        items.push(item);
      }
    });

    if (items.length === 0) {
      const fallbackMatch = compact.match(/(?:plu|ic\.?plu)?\D{0,14}[\[(]?\s*(\d{2,5})\s*[\])]?\s*[-:]\s*([A-Za-z][A-Za-z .]{2,40})?/i);
      const plu = fallbackMatch ? String(fallbackMatch[1]).replace(/^0+/, '') : '';
      let name = fallbackMatch && fallbackMatch[2] ? cleanTicketProductName(fallbackMatch[2]) : '';
      if (!name && /pistach/i.test(compact)) name = 'Pistachos';
      const item = parseTicketItemBlock(plu, name || (plu ? ('PLU ' + plu) : 'Producto ticket'), compact, total);
      if (item.line_total <= 0 && total > 0) {
        item.line_total = total;
        item.unit_price = computeBasePrice(total, item.quantity, item.unit);
      }
      items.push(item);
    }

    const itemsSum = items.reduce(function (sum, it) { return sum + toNumber(it.line_total); }, 0);
    const articleMatch = compact.match(/articulos?\s*:?\s*(\d{1,3})/i);
    const warnings = ['Lectura local del navegador: revisa los campos antes de confirmar.'];
    if (articleMatch && items.length !== Number(articleMatch[1])) {
      warnings.push('El ticket dice ' + articleMatch[1] + ' articulos y la lectura local detecto ' + items.length + '.');
    }
    if (total > 0 && itemsSum > 0 && Math.abs(total - itemsSum) > 1) {
      warnings.push('El total del ticket no coincide exactamente con la suma detectada.');
    }

    return {
      sale_date: parseDateToIso(source),
      sale_time: parseTime(source),
      items: items,
      total: total > 0 ? total : itemsSum,
      confidence: items.length > 1 ? 0.62 : 0.52,
      warnings: warnings,
      raw_text: source.slice(0, 900),
    };
  }

  function parseScaleBarcode(code) {
    const barcode = String(code || '').replace(/\D/g, '');
    if (barcode.length !== 13) return null;
    const prefix = barcode.slice(0, 2);
    if (prefix === '20' || prefix === '21') {
      return {
        plu: String(Number(barcode.slice(2, 6))),
        total: Number(barcode.slice(6, 12)),
      };
    }
    if (prefix === '22') {
      return {
        plu: '',
        total: Number(barcode.slice(6, 12)),
      };
    }
    if (barcode[0] === '0') {
      return {
        plu: String(Number(barcode.slice(1, 4))),
        total: Number(barcode.slice(4, 12)),
      };
    }
    return null;
  }

  function detectBarcodesFromFile(file) {
    if (!('BarcodeDetector' in window) || !window.createImageBitmap) {
      return Promise.resolve(null);
    }
    return createImageBitmap(file)
      .then(function (bitmap) {
        const detector = new BarcodeDetector({ formats: ['ean_13', 'code_128'] });
        return detector.detect(bitmap);
      })
      .then(function (codes) {
        const parsed = (codes || []).map(function (c) { return parseScaleBarcode(c.rawValue); }).filter(Boolean);
        if (!parsed.length) return null;
        if (parsed.length > 1) return null;
        const item = parsed.find(function (p) { return p.plu; }) || parsed[0];
        const total = parsed.reduce(function (max, p) { return Math.max(max, p.total || 0); }, item.total || 0);
        return {
          sale_date: '',
          sale_time: '',
          items: [{
            plu: item.plu || '',
            name: item.plu ? ('PLU ' + item.plu) : 'Ticket balanza',
            quantity: 1,
            unit: 'u',
            unit_price: total,
            line_total: total,
            confidence: 0.50,
          }],
          total: total,
          confidence: 0.50,
          warnings: ['Lectura local por codigo detectado en la foto: completa peso/precio si hace falta.'],
          raw_text: '',
        };
      })
      .catch(function () { return null; });
  }

  function runLocalTicketRead(file, serverError) {
    showTicketStatus('Gemini no esta disponible. Intentando lectura local...', 'warning');
    setTicketProgress(Math.max(progressValue, 64), 'Cargando OCR local...');
    return loadScriptOnce('https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js')
      .then(function () {
        if (!window.Tesseract) throw new Error('OCR local no disponible');
        setTicketProgress(Math.max(progressValue, 70), 'Reconociendo texto del ticket...');
        return window.Tesseract.recognize(file, 'eng', {
          logger: function (m) {
            if (m && m.status === 'recognizing text' && m.progress) {
              setTicketProgress(70 + Math.round(m.progress * 22), 'OCR local ' + Math.round(m.progress * 100) + '%...');
              showTicketStatus('Lectura local OCR ' + Math.round(m.progress * 100) + '%...', 'warning');
            }
          },
        });
      })
      .then(function (result) {
        setTicketProgress(Math.max(progressValue, 94), 'Interpretando importe y productos...');
        const text = result && result.data ? String(result.data.text || '') : '';
        const ticket = parseTicketText(text);
        if (!ticket.items.length || toNumber(ticket.total) <= 0) {
          throw new Error('OCR local sin importes');
        }
        ticket.warnings.unshift('Gemini respondio: ' + serverError);
        return ticket;
      });
  }

  function showTicketStatus(message, type) {
    ticketStatus.className = 'ticket-status small mt-2' + (type ? (' text-' + type) : '');
    ticketStatus.textContent = message || '';
  }

  function showWarnings(warnings) {
    if (!warnings || warnings.length === 0) {
      ticketWarnings.style.display = 'none';
      ticketWarnings.innerHTML = '';
      return;
    }
    ticketWarnings.style.display = '';
    ticketWarnings.innerHTML = '<div class="alert alert-warning py-2 mb-0 rounded-3">'
      + warnings.map(escHtml).join('<br>') + '</div>';
  }

  function clearCart() {
    cart.length = 0;
    detectedTicketTotal = 0;
    renderCart();
    showWarnings([]);
  }

  // ── Eliminar ítem ────────────────────────────────────────────────────────────
  cartBody.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-remove]');
    if (!btn) return;
    const tr  = btn.closest('tr');
    const idx = Number(tr && tr.dataset ? tr.dataset.index : -1);
    if (idx < 0) return;
    cart.splice(idx, 1);
    renderCart();
  });

  cartBody.addEventListener('input', updateItemFromControl);
  cartBody.addEventListener('change', updateItemFromControl);

  function updateItemFromControl(e) {
    const control = e.target.closest('[data-field]');
    if (!control) return;
    const tr = control.closest('tr');
    const idx = Number(tr && tr.dataset ? tr.dataset.index : -1);
    const item = cart[idx];
    if (!item) return;

    const field = control.dataset.field;
    if (field === 'plu') {
      item.plu = String(control.value || '').replace(/\D/g, '');
      control.value = item.plu;
    } else if (field === 'description') {
      item.description = String(control.value || '').trim();
    } else if (field === 'unit') {
      item.unit = normalizeUnit(control.value);
      item.line_total = computeLineTotal(item.quantity, item.unit, item.unit_price);
      const lineInput = tr.querySelector('[data-field="line_total"]');
      if (lineInput) lineInput.value = formatInputNumber(item.line_total);
    } else if (field === 'line_total') {
      item.line_total = toNumber(control.value);
      item.unit_price = computeBasePrice(item.line_total, item.quantity, item.unit);
      const priceInput = tr.querySelector('[data-field="unit_price"]');
      if (priceInput) priceInput.value = formatInputNumber(item.unit_price);
    } else {
      item[field] = toNumber(control.value);
      item.line_total = computeLineTotal(item.quantity, item.unit, item.unit_price);
      const lineInput = tr.querySelector('[data-field="line_total"]');
      if (lineInput) lineInput.value = formatInputNumber(item.line_total);
    }

    updateSummary();
  }

  addItemBtn.addEventListener('click', function () {
    cart.push({
      plu: '',
      description: 'Producto ticket',
      quantity: 1,
      unit: 'u',
      unit_price: 0,
      line_total: 0,
      confidence: 0,
    });
    renderCart();
  });

  // ── Limpiar carrito ──────────────────────────────────────────────────────────
  clearBtn.addEventListener('click', function () {
    clearCart();
    saleResult.style.display = 'none';
  });

  // ── Escaneo ──────────────────────────────────────────────────────────────────
  ticketInput.addEventListener('change', function () {
    const file = ticketInput.files && ticketInput.files[0] ? ticketInput.files[0] : null;
    analyzeBtn.disabled = !file;
    analyzeBtn.classList.toggle('is-ready', !!file);
    analyzeBtn.classList.remove('is-loading');
    setAnalyzeButton('Leer ticket con IA', false);
    resetTicketProgress();
    showWarnings([]);
    saleResult.style.display = 'none';

    if (previewUrl) {
      URL.revokeObjectURL(previewUrl);
      previewUrl = '';
    }

    if (!file) {
      ticketPreview.classList.remove('is-visible');
      ticketPreview.removeAttribute('src');
      showTicketStatus('', '');
      return;
    }

    previewUrl = URL.createObjectURL(file);
    ticketPreview.src = previewUrl;
    ticketPreview.classList.add('is-visible');
    showTicketStatus('Foto lista. Toca el boton verde para leer el ticket.', 'success');
  });

  analyzeBtn.addEventListener('click', function () {
    const file = ticketInput.files && ticketInput.files[0] ? ticketInput.files[0] : null;
    if (!file) return;

    const formData = new FormData();
    formData.append('csrf_token', CSRF);
    formData.append('ticket_image', file);

    let readOk = false;
    analyzeBtn.disabled = true;
    analyzeBtn.classList.remove('is-ready');
    setAnalyzeButton('Analizando...', true);
    startTicketProgress('Preparando foto...');
    showTicketStatus('Analizando ticket con IA/OCR...', '');
    showWarnings([]);

    fetch('/api_ticket_ocr.php', {
      method: 'POST',
      headers: { 'Accept': 'application/json' },
      body: formData,
    })
      .then(function (res) {
        setTicketProgress(Math.max(progressValue, 52), 'Esperando respuesta de IA...');
        return res.json();
      })
      .then(function (data) {
        if (!data.ok) {
          const errorMessage = data.error || 'No se pudo leer el ticket.';
          return runLocalTicketRead(file, errorMessage)
            .then(function (ticket) {
              applyDetectedTicket(ticket, 'Lectura local cargada. Revisa y edita la venta antes de confirmar.');
              readOk = true;
            })
            .catch(function () {
              showTicketStatus(errorMessage + ' La lectura local tampoco pudo completarse.', 'danger');
              if (data.ticket && data.ticket.warnings) showWarnings(data.ticket.warnings);
            });
        }

        setTicketProgress(Math.max(progressValue, 92), 'Cargando venta editable...');
        applyDetectedTicket(data.ticket || {}, 'Ticket leido. Revisa y edita la venta antes de confirmar.');
        readOk = true;
      })
      .catch(function () {
        return runLocalTicketRead(file, 'Error de red al leer el ticket.')
          .then(function (ticket) {
            applyDetectedTicket(ticket, 'Lectura local cargada. Revisa y edita la venta antes de confirmar.');
            readOk = true;
          })
          .catch(function () {
            showTicketStatus('Error de red al leer el ticket. La lectura local tampoco pudo completarse.', 'danger');
          });
      })
      .finally(function () {
        analyzeBtn.disabled = !(ticketInput.files && ticketInput.files[0]);
        analyzeBtn.classList.toggle('is-ready', !!(ticketInput.files && ticketInput.files[0]));
        setAnalyzeButton('Leer ticket con IA', false);
        finishTicketProgress(readOk);
      });
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
      sale_datetime: saleDatetimeEl.value,
      ticket_total: detectedTicketTotal,
      items      : cart.map(function (it) {
        return {
          plu         : it.plu,
          description : it.description,
          quantity    : it.quantity,
          unit        : it.unit,
          unit_price  : it.unit_price,
          line_total  : it.line_total,
        };
      }),
    };

    fetch(SAVE_URL, {
      method  : 'POST',
      headers : { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body    : JSON.stringify(payload),
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data.ok) {
          // Mostrar éxito, limpiar carrito
          showResult('success', data.message || 'Venta guardada');
          clearCart();
        } else {
          showResult('danger', data.error || 'Error al guardar la venta');
          confirmBtn.disabled = false;
        }
        confirmBtn.textContent = 'Confirmar venta';
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
})();
</script>
</body>
</html>
