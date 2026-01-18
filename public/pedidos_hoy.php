<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

auth_require_login();

$config = app_config();
$appName = (string)($config['app']['name'] ?? 'Dietetic');
$userId = (int)auth_user_id();
$csrf = csrf_token();

$flash = (string)($_SESSION['flash'] ?? '');
unset($_SESSION['flash']);
$error = '';

$viewId = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$statusFilter = trim((string)($_GET['status'] ?? ''));

$tz = new DateTimeZone('America/Argentina/Buenos_Aires');
$now = new DateTimeImmutable('now', $tz);
$start = $now->setTime(0, 0, 0);
$end = $start->modify('+1 day');
$todayLabel = $start->format('d/m/Y');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!csrf_verify($token)) {
        $error = 'Sesión inválida. Recargá e intentá de nuevo.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            $pdo = db($config);
            if ($action === 'update_status') {
                $orderId = (int)($_POST['order_id'] ?? 0);
                $status = (string)($_POST['status'] ?? '');
                orders_update_status($pdo, $userId, $orderId, $status);
                $_SESSION['flash'] = 'Estado actualizado.';
                $qs = [];
                if ($viewId > 0) {
                    $qs[] = 'view=' . rawurlencode((string)$viewId);
                }
                if ($statusFilter !== '') {
                    $qs[] = 'status=' . rawurlencode($statusFilter);
                }
                header('Location: /pedidos_hoy' . (count($qs) > 0 ? ('?' . implode('&', $qs)) : ''));
                exit;
            }

            throw new InvalidArgumentException('Acción inválida.');
        } catch (Throwable $e) {
            error_log('pedidos_hoy.php error: ' . $e->getMessage());
            $error = (($config['app']['env'] ?? 'production') === 'production') ? 'No se pudo procesar el pedido.' : ('Error: ' . $e->getMessage());
        }
    }
}

$rows = [];
$view = null;
$viewItems = [];

try {
    $pdo = db($config);

    if ($viewId > 0) {
        $g = orders_get($pdo, $userId, $viewId);
        $view = $g['order'] ?? null;
        $viewItems = $g['items'] ?? [];
    }

    $rows = orders_list_between($pdo, $userId, $start, $end, $statusFilter, 300);
} catch (Throwable $e) {
    error_log('pedidos_hoy.php load error: ' . $e->getMessage());
    $rows = [];
    if ($error === '') {
        $error = (($config['app']['env'] ?? 'production') === 'production')
            ? 'No se pudieron cargar los pedidos. ¿Ejecutaste el schema.sql?'
            : ('Error: ' . $e->getMessage());
    }
}

$badge = function (string $status): string {
    return match ($status) {
        'new' => 'bg-warning text-dark',
        'confirmed' => 'bg-primary',
        'fulfilled' => 'bg-success',
        'cancelled' => 'bg-danger',
        default => 'bg-secondary',
    };
};

$label = function (string $status): string {
    return match ($status) {
        'new' => 'Nuevo',
        'confirmed' => 'Confirmado',
        'fulfilled' => 'Entregado',
        'cancelled' => 'Cancelado',
        default => $status,
    };
};

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pedidos de hoy</title>
  <link rel="icon" type="image/png" href="/logo.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="stylesheet" href="/brand.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --accent:#463B1E; --accent-rgb:70,59,30; --accent-dark:#2f2713; --accent-2:#96957E; --accent-2-rgb:150,149,126; --ink:#241e10; --muted:#6b6453; --card:rgba(255,255,255,.9); }
    body { font-family:'Space Grotesk','Segoe UI',sans-serif; background: radial-gradient(circle at 10% 20%, rgba(var(--accent-2-rgb),.22), transparent 38%), radial-gradient(circle at 90% 10%, rgba(var(--accent-rgb),.12), transparent 40%), linear-gradient(120deg,#fbfaf6,#E7E3D5); color:var(--ink); min-height:100vh; }
    .navbar-glass { background:rgba(255,255,255,.9); backdrop-filter:blur(12px); border:1px solid rgba(15,23,42,.06); box-shadow:0 10px 40px rgba(15,23,42,.08); }
    .page-shell { padding:2.5rem 0; }
    .card-lift { background:var(--card); border:1px solid rgba(15,23,42,.06); box-shadow:0 18px 50px rgba(15,23,42,.07); border-radius:18px; }
    .muted-label { color:var(--muted); font-weight:600; text-transform:uppercase; letter-spacing:.04em; font-size:.8rem; }
    .pill { display:inline-flex; align-items:center; gap:.4rem; padding:.35rem .75rem; border-radius:999px; background:rgba(var(--accent-rgb),.1); color:var(--accent); font-weight:600; font-size:.9rem; }
    .action-btn { border-radius:12px; padding-inline:1.25rem; font-weight:600; }
    .btn-primary, .btn-primary:hover, .btn-primary:focus { background:linear-gradient(135deg,var(--accent),var(--accent-dark)); border:none; box-shadow:0 10px 30px rgba(var(--accent-rgb),.25); }
    .table thead th { background:rgba(var(--accent-rgb),.08); border-bottom:none; font-weight:600; color:var(--ink); }
    .table td, .table th { border-color:rgba(148,163,184,.35); }
    @media (max-width:768px){ .page-shell{padding:1.5rem .75rem; padding-left:calc(.75rem + env(safe-area-inset-left)); padding-right:calc(.75rem + env(safe-area-inset-right));} .card-lift{border-radius:14px} }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-glass sticky-top">
  <div class="container py-2">
    <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-dark mb-0 h4 text-decoration-none" href="/dashboard" aria-label="<?= e($appName) ?>">
      <img src="/logo.png" alt="Logo" style="height:34px;width:auto;">
    </a>
    <div class="ms-auto d-flex align-items-center gap-2">
      <span class="pill">Pedidos hoy</span>
      <a class="btn btn-outline-primary btn-sm action-btn" href="/pedidos">Ver todos</a>
      <form method="post" action="/logout.php" class="d-flex">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <button type="submit" class="btn btn-outline-danger btn-sm action-btn">Salir</button>
      </form>
    </div>
  </div>
</nav>

<main class="container page-shell">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-10 col-xl-9">
      <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
          <p class="muted-label mb-1">Ventas</p>
          <h1 class="h3 mb-0">Pedidos de hoy <span class="text-muted" style="font-size:.9em;">(<?= e($todayLabel) ?>)</span></h1>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <a class="btn btn-outline-primary action-btn" href="/lista_precios">Abrir lista pública</a>
        </div>
      </div>

      <?php if ($flash !== ''): ?>
        <div class="alert alert-success"><?= e($flash) ?></div>
      <?php endif; ?>
      <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
      <?php endif; ?>

      <div class="card card-lift mb-4">
        <div class="card-body p-4">
          <form class="row g-2 align-items-end" method="get" action="/pedidos_hoy">
            <div class="col-12 col-md-4">
              <label class="form-label" for="status">Estado</label>
              <select class="form-select" id="status" name="status">
                <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>Todos</option>
                <option value="new" <?= $statusFilter === 'new' ? 'selected' : '' ?>>Nuevo</option>
                <option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>>Confirmado</option>
                <option value="fulfilled" <?= $statusFilter === 'fulfilled' ? 'selected' : '' ?>>Entregado</option>
                <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelado</option>
              </select>
            </div>
            <div class="col-12 col-md-3">
              <button class="btn btn-primary action-btn w-100" type="submit">Filtrar</button>
            </div>
          </form>
        </div>
      </div>

      <?php if (is_array($view)): ?>
        <div class="card card-lift mb-4">
          <div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
              <div>
                <p class="muted-label mb-1">Detalle</p>
                <h2 class="h5 mb-0">Pedido #<?= e((string)($view['id'] ?? '')) ?></h2>
              </div>
              <div class="d-flex align-items-center gap-2">
                <span class="badge <?= e($badge((string)($view['status'] ?? ''))) ?>"><?= e($label((string)($view['status'] ?? ''))) ?></span>
                <a class="btn btn-outline-primary btn-sm action-btn" href="/pedidos_hoy">Cerrar</a>
              </div>
            </div>

            <div class="mt-3 small text-muted">
              <div><strong>Cliente:</strong> <?= e((string)($view['customer_name'] ?? '')) ?></div>
              <?php if ((string)($view['customer_phone'] ?? '') !== ''): ?>
                <div><strong>Tel:</strong> <?= e((string)($view['customer_phone'] ?? '')) ?></div>
              <?php endif; ?>
              <?php if ((string)($view['customer_address'] ?? '') !== ''): ?>
                <div><strong>Dir:</strong> <?= e((string)($view['customer_address'] ?? '')) ?></div>
              <?php endif; ?>
              <?php if ((string)($view['notes'] ?? '') !== ''): ?>
                <div><strong>Notas:</strong> <?= e((string)($view['notes'] ?? '')) ?></div>
              <?php endif; ?>
              <div><strong>Fecha:</strong> <?= e((string)($view['created_at'] ?? '')) ?></div>
            </div>

            <div class="table-responsive mt-3">
              <table class="table align-middle">
                <thead>
                <tr>
                  <th>Item</th>
                  <th class="text-end">Cant.</th>
                  <th class="text-end">Precio</th>
                  <th class="text-end">Total</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($viewItems as $it): ?>
                  <tr>
                    <td><?= e((string)($it['description'] ?? '')) ?></td>
                    <td class="text-end"><?= e((string)($it['quantity'] ?? '')) ?></td>
                    <td class="text-end"><?= e(money_format_cents((int)($it['unit_price_cents'] ?? 0), (string)($view['currency'] ?? 'ARS'))) ?></td>
                    <td class="text-end fw-semibold"><?= e(money_format_cents((int)($it['line_total_cents'] ?? 0), (string)($view['currency'] ?? 'ARS'))) ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <div class="d-flex align-items-center justify-content-between mt-2">
              <div class="fw-semibold">Total</div>
              <div class="fw-bold"><?= e(money_format_cents((int)($view['total_cents'] ?? 0), (string)($view['currency'] ?? 'ARS'))) ?></div>
            </div>

            <form method="post" class="row g-2 mt-3">
              <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
              <input type="hidden" name="action" value="update_status">
              <input type="hidden" name="order_id" value="<?= e((string)($view['id'] ?? 0)) ?>">
              <div class="col-12 col-md-6">
                <label class="form-label" for="newStatus">Cambiar estado</label>
                <select class="form-select" id="newStatus" name="status">
                  <option value="new">Nuevo</option>
                  <option value="confirmed">Confirmado</option>
                  <option value="fulfilled">Entregado</option>
                  <option value="cancelled">Cancelado</option>
                </select>
              </div>
              <div class="col-12 col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary action-btn w-100">Guardar</button>
              </div>
            </form>
          </div>
        </div>
      <?php endif; ?>

      <div class="card card-lift">
        <div class="card-body p-4">
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
              <tr>
                <th>#</th>
                <th>Cliente</th>
                <th>Teléfono</th>
                <th>Hora</th>
                <th class="text-end">Total</th>
                <th>Estado</th>
                <th class="text-end"></th>
              </tr>
              </thead>
              <tbody>
              <?php if (count($rows) === 0): ?>
                <tr>
                  <td colspan="7" class="text-center text-muted py-4">No hay pedidos hoy.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <td><?= e((string)($r['id'] ?? '')) ?></td>
                    <td><?= e((string)($r['customer_name'] ?? '')) ?></td>
                    <td><?= e((string)($r['customer_phone'] ?? '')) ?></td>
                    <td><?= e(substr((string)($r['created_at'] ?? ''), 11, 5)) ?></td>
                    <td class="text-end fw-semibold"><?= e(money_format_cents((int)($r['total_cents'] ?? 0), (string)($r['currency'] ?? 'ARS'))) ?></td>
                    <td><span class="badge <?= e($badge((string)($r['status'] ?? ''))) ?>"><?= e($label((string)($r['status'] ?? ''))) ?></span></td>
                    <td class="text-end"><a class="btn btn-outline-primary btn-sm action-btn" href="/pedidos_hoy?view=<?= e((string)($r['id'] ?? 0)) ?>">Ver</a></td>
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
