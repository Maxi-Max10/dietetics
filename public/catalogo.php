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
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

$edit = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!csrf_verify($token)) {
        $error = 'Sesión inválida. Recargá e intentá de nuevo.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $returnQ = trim((string)($_POST['q'] ?? $q));

        try {
            $pdo = db($config);

            if (!catalog_supports_table($pdo)) {
                throw new RuntimeException('No se encontró la tabla del catálogo. ¿Ejecutaste el schema.sql?');
            }

            if ($action === 'create') {
                $name = trim((string)($_POST['name'] ?? ''));
                $price = (string)($_POST['price'] ?? '0');
                $currency = trim((string)($_POST['currency'] ?? 'ARS'));

                catalog_create($pdo, $userId, $name, $price, $currency);
                $_SESSION['flash'] = 'Producto agregado al catálogo.';
                header('Location: /catalogo' . ($returnQ !== '' ? ('?q=' . rawurlencode($returnQ)) : ''));
                exit;
            }

            if ($action === 'update') {
                $id = (int)($_POST['id'] ?? 0);
                $name = trim((string)($_POST['name'] ?? ''));
                $price = (string)($_POST['price'] ?? '0');
                $currency = trim((string)($_POST['currency'] ?? 'ARS'));

                catalog_update($pdo, $userId, $id, $name, $price, $currency);
                $_SESSION['flash'] = 'Producto actualizado.';
                header('Location: /catalogo' . ($returnQ !== '' ? ('?q=' . rawurlencode($returnQ)) : ''));
                exit;
            }

            if ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                catalog_delete($pdo, $userId, $id);
                $_SESSION['flash'] = 'Producto eliminado.';
                header('Location: /catalogo' . ($returnQ !== '' ? ('?q=' . rawurlencode($returnQ)) : ''));
                exit;
            }

            throw new InvalidArgumentException('Acción inválida.');
        } catch (Throwable $e) {
            error_log('catalogo.php error: ' . $e->getMessage());
            $error = ($config['app']['env'] ?? 'production') === 'production'
                ? 'No se pudo procesar el catálogo.'
                : ('Error: ' . $e->getMessage());
        }
    }
}

$rows = [];
try {
    $pdo = db($config);

    if (!catalog_supports_table($pdo)) {
        throw new RuntimeException('No se encontró la tabla del catálogo. ¿Ejecutaste el schema.sql?');
    }

    if ($editId > 0) {
        $edit = catalog_get($pdo, $userId, $editId);
    }

    $rows = catalog_list($pdo, $userId, $q, 300);
} catch (Throwable $e) {
    error_log('catalogo.php load error: ' . $e->getMessage());
    $rows = [];
    $error = $error !== '' ? $error : (
        ($config['app']['env'] ?? 'production') === 'production'
            ? 'No se pudo cargar el catálogo. ¿Ejecutaste el schema.sql?'
            : ('Error: ' . $e->getMessage())
    );
}

$defaultName = is_array($edit) ? (string)($edit['name'] ?? '') : '';
$defaultCurrency = is_array($edit) ? (string)($edit['currency'] ?? 'ARS') : 'ARS';
$defaultPrice = '';
if (is_array($edit)) {
    $defaultPrice = number_format(((int)($edit['price_cents'] ?? 0)) / 100, 2, '.', '');
}

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($appName) ?> - Catálogo</title>
  <link rel="icon" type="image/png" href="/logo.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --accent:#059669; --accent-rgb:5,150,105; --accent-dark:#047857; --accent-2:#f59e0b; --accent-2-rgb:245,158,11; --ink:#0b1727; --muted:#6b7280; --card:rgba(255,255,255,.9); }
    body { font-family:'Space Grotesk','Segoe UI',sans-serif; background: radial-gradient(circle at 10% 20%, rgba(var(--accent-rgb),.18), transparent 35%), radial-gradient(circle at 90% 10%, rgba(var(--accent-2-rgb),.18), transparent 35%), linear-gradient(120deg,#f7fafc,#eef2ff); color:var(--ink); min-height:100vh; }
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
          <p class="muted-label mb-1">Productos</p>
          <h1 class="h3 mb-0">Catálogo</h1>
          <div class="text-muted mt-1">Lista de precios</div>
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
          <p class="muted-label mb-1"><?= $edit ? 'Editar' : 'Nuevo' ?></p>
          <h2 class="h5 mb-0"><?= $edit ? 'Modificar producto' : 'Agregar producto' ?></h2>
        </div>
        <div class="card-body px-4 py-4">
          <form method="post" action="/catalogo" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="q" value="<?= e($q) ?>">
            <?php if ($edit): ?>
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?= e((string)$edit['id']) ?>">
            <?php else: ?>
              <input type="hidden" name="action" value="create">
            <?php endif; ?>

            <div class="col-12 col-md-6">
              <label class="form-label" for="name">Producto</label>
              <input class="form-control" id="name" name="name" value="<?= e($defaultName) ?>" required>
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label" for="price">Precio</label>
              <input class="form-control" id="price" name="price" inputmode="decimal" placeholder="0.00" value="<?= e($defaultPrice) ?>" required>
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label" for="currency">Moneda</label>
              <select class="form-select" id="currency" name="currency">
                <?php foreach (['ARS', 'USD', 'EUR'] as $cur): ?>
                  <option value="<?= e($cur) ?>" <?= strtoupper($defaultCurrency) === $cur ? 'selected' : '' ?>><?= e($cur) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12 d-flex flex-wrap gap-2 justify-content-end">
              <?php if ($edit): ?>
                <a class="btn btn-outline-secondary action-btn" href="/catalogo<?= $q !== '' ? ('?q=' . rawurlencode($q)) : '' ?>">Cancelar</a>
              <?php endif; ?>
              <button type="submit" class="btn btn-primary action-btn"><?= $edit ? 'Guardar cambios' : 'Agregar' ?></button>
            </div>
          </form>
        </div>
      </div>

      <div class="card card-lift">
        <div class="card-header card-header-clean bg-white px-4 py-3">
          <p class="muted-label mb-1">Lista</p>
          <h2 class="h5 mb-0">Productos y precios</h2>
        </div>
        <div class="card-body px-4 py-4">
          <form method="get" action="/catalogo" class="d-flex flex-column flex-md-row gap-2 align-items-md-center justify-content-between mb-3">
            <div class="d-flex gap-2 flex-grow-1">
              <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Buscar por producto" aria-label="Buscar">
            </div>
            <button class="btn btn-outline-secondary btn-sm" type="submit">Buscar</button>
          </form>

          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Producto</th>
                  <th style="width:180px" class="text-end">Precio</th>
                  <th style="width:220px"></th>
                </tr>
              </thead>
              <tbody>
              <?php if (count($rows) === 0): ?>
                <tr><td colspan="3" class="text-muted">Sin resultados.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <td><?= e((string)$r['name']) ?></td>
                    <td class="text-end"><?= e(money_format_cents((int)$r['price_cents'], (string)$r['currency'])) ?></td>
                    <td class="text-end">
                      <div class="d-inline-flex gap-2">
                        <a class="btn btn-outline-primary btn-sm" href="/catalogo?edit=<?= e((string)$r['id']) ?><?= $q !== '' ? ('&q=' . rawurlencode($q)) : '' ?>">Editar</a>
                        <form method="post" action="/catalogo" class="d-inline">
                          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="id" value="<?= e((string)$r['id']) ?>">
                          <input type="hidden" name="q" value="<?= e($q) ?>">
                          <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar</button>
                        </form>
                      </div>
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
