<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

auth_require_login();

$config = app_config();
$appName = (string)($config['app']['name'] ?? 'Dietetics');
$userId = (int)auth_user_id();
$csrf = csrf_token();

$flash = (string)($_SESSION['flash'] ?? '');
unset($_SESSION['flash']);
$error = '';

$q = trim((string)($_GET['q'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!csrf_verify($token)) {
        $error = 'Sesión inválida. Recargá e intentá de nuevo.';
    } else {
        $action = (string)($_POST['action'] ?? '');
    $returnQ = trim((string)($_POST['q'] ?? $q));

        try {
            $pdo = db($config);

            if ($action === 'create') {
                $name = trim((string)($_POST['name'] ?? ''));
                $sku = trim((string)($_POST['sku'] ?? ''));
                $unit = trim((string)($_POST['unit'] ?? ''));
                $qty = (string)($_POST['quantity'] ?? '0');

                stock_create_item($pdo, $userId, $name, $sku, $unit, $qty);
                $_SESSION['flash'] = 'Producto agregado al stock.';
                header('Location: /stock' . ($returnQ !== '' ? ('?q=' . rawurlencode($returnQ)) : ''));
                exit;
            } elseif ($action === 'adjust') {
                $itemId = (int)($_POST['item_id'] ?? 0);
                $delta = (string)($_POST['delta'] ?? '0');

                stock_adjust($pdo, $userId, $itemId, $delta);
                $_SESSION['flash'] = 'Stock actualizado.';
                header('Location: /stock' . ($returnQ !== '' ? ('?q=' . rawurlencode($returnQ)) : ''));
                exit;
            } else {
                throw new InvalidArgumentException('Acción inválida.');
            }
        } catch (Throwable $e) {
            error_log('stock.php error: ' . $e->getMessage());
            $error = ($config['app']['env'] ?? 'production') === 'production'
                ? 'No se pudo procesar el stock.'
                : ('Error: ' . $e->getMessage());
        }
    }
}

$rows = [];
try {
    $pdo = db($config);
    $rows = stock_list_items($pdo, $userId, $q, 120);
} catch (Throwable $e) {
    error_log('stock.php load error: ' . $e->getMessage());
    $error = $error !== '' ? $error : (
        ($config['app']['env'] ?? 'production') === 'production'
            ? 'No se pudo cargar el stock. ¿Ejecutaste el schema.sql?'
            : ('Error: ' . $e->getMessage())
    );
}

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($appName) ?> - Stock</title>
  <link rel="icon" type="image/png" href="/logo.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --accent:#7c3aed; --accent-rgb:124,58,237; --accent-dark:#6d28d9; --accent-2:#06b6d4; --accent-2-rgb:6,182,212; --ink:#0b1727; --muted:#6b7280; --card:rgba(255,255,255,.9); }
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
    <div class="col-12 col-lg-11 col-xl-10">
      <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
          <p class="muted-label mb-1">Inventario</p>
          <h1 class="h3 mb-0">Stock</h1>
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
          <h2 class="h5 mb-0">Agregar item</h2>
        </div>
        <div class="card-body px-4 py-4">
          <form method="post" action="/stock" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="q" value="<?= e($q) ?>">

            <div class="col-12 col-md-5">
              <label class="form-label" for="name">Nombre</label>
              <input class="form-control" id="name" name="name" required>
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label" for="sku">SKU (opcional)</label>
              <input class="form-control" id="sku" name="sku" maxlength="64">
            </div>
            <div class="col-12 col-md-2">
              <label class="form-label" for="unit">Unidad</label>
              <input class="form-control" id="unit" name="unit" placeholder="u / kg / lt" maxlength="24">
            </div>
            <div class="col-12 col-md-2">
              <label class="form-label" for="quantity">Cantidad</label>
              <input class="form-control" id="quantity" name="quantity" inputmode="decimal" value="0">
            </div>
            <div class="col-12 d-flex justify-content-end">
              <button class="btn btn-primary action-btn" type="submit">Guardar</button>
            </div>
          </form>
        </div>
      </div>

      <div class="card card-lift">
        <div class="card-header card-header-clean bg-white px-4 py-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
          <div>
            <p class="muted-label mb-1">Listado</p>
            <h2 class="h5 mb-0">Items</h2>
          </div>
          <form method="get" action="/stock" class="d-flex gap-2">
            <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre o SKU" aria-label="Buscar">
            <button class="btn btn-outline-primary btn-sm" type="submit">Buscar</button>
          </form>
        </div>
        <div class="card-body px-4 py-4">
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Producto</th>
                  <th style="width:150px">SKU</th>
                  <th style="width:120px">Unidad</th>
                  <th style="width:140px" class="text-end">Cantidad</th>
                  <th style="width:260px">Ajuste (+/-)</th>
                </tr>
              </thead>
              <tbody>
              <?php if (count($rows) === 0): ?>
                <tr><td colspan="5" class="text-muted">Sin resultados.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <td><?= e((string)$r['name']) ?></td>
                    <td class="text-muted"><?= e((string)$r['sku']) ?></td>
                    <td class="text-muted"><?= e((string)$r['unit']) ?></td>
                    <td class="text-end fw-semibold"><?= e((string)$r['quantity']) ?></td>
                    <td>
                      <form method="post" action="/stock" class="d-flex gap-2">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="adjust">
                        <input type="hidden" name="item_id" value="<?= e((string)$r['id']) ?>">
                        <input type="hidden" name="q" value="<?= e($q) ?>">
                        <input class="form-control form-control-sm" name="delta" inputmode="decimal" placeholder="Ej: 2 o -1" required>
                        <button class="btn btn-outline-primary btn-sm" type="submit">Aplicar</button>
                      </form>
                    </td>
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
