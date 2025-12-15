<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

auth_require_login();

$config = app_config();
$appName = (string)($config['app']['name'] ?? 'Dietetic');
$userId = (int)auth_user_id();
$csrf = csrf_token();

$period = (string)($_GET['period'] ?? 'day');

try {
    $pdo = db($config);
    $p = sales_period($period);
    $summary = sales_summary($pdo, $userId, $p['start'], $p['end']);
    $rows = sales_list($pdo, $userId, $p['start'], $p['end']);
} catch (Throwable $e) {
    $p = sales_period('day');
    $summary = [];
    $rows = [];
    $error = ($config['app']['env'] ?? 'production') === 'production'
        ? 'No se pudieron cargar las ventas.'
        : ('Error: ' . $e->getMessage());
}

function sales_period_link(string $key): string
{
    return '/sales.php?period=' . urlencode($key);
}

function sales_active(string $current, string $key): string
{
    return $current === $key ? 'btn-primary' : 'btn-outline-primary';
}

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($appName) ?> - Ventas</title>
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

    .stat-card {
      background: rgba(15, 118, 110, 0.06);
      border: 1px solid rgba(15, 23, 42, 0.06);
      border-radius: 16px;
      padding: 1rem 1.25rem;
    }

    @media (max-width: 768px) {
      .page-shell {
        padding: 1.5rem 0;
      }

      .card-lift {
        border-radius: 14px;
      }
    }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-glass sticky-top">
  <div class="container py-2">
    <a class="navbar-brand fw-bold text-dark mb-0 h4 text-decoration-none" href="/dashboard.php"><?= e($appName) ?></a>
    <div class="d-flex align-items-center gap-2 ms-auto">
      <span class="pill">Admin</span>
      <a class="btn btn-outline-primary btn-sm" href="/dashboard.php">Volver</a>
      <form method="post" action="/logout.php" class="d-flex">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <button type="submit" class="btn btn-outline-secondary btn-sm">Salir</button>
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
          <h1 class="h3 mb-0">Ventas</h1>
          <div class="text-muted mt-1"><?= e($p['label']) ?></div>
        </div>
        <span class="text-muted">Usuario #<?= e((string)$userId) ?></span>
      </div>

      <?php if (!empty($error ?? '')): ?>
        <div class="alert alert-danger" role="alert"><?= e((string)$error) ?></div>
      <?php endif; ?>

      <div class="card card-lift mb-4">
        <div class="card-header card-header-clean bg-white px-4 py-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
          <div>
            <p class="muted-label mb-1">Rango</p>
            <h2 class="h5 mb-0">Seleccionar período</h2>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-sm action-btn <?= e(sales_active($p['key'], 'day')) ?>" href="<?= e(sales_period_link('day')) ?>">Día</a>
            <a class="btn btn-sm action-btn <?= e(sales_active($p['key'], 'week')) ?>" href="<?= e(sales_period_link('week')) ?>">Semana</a>
            <a class="btn btn-sm action-btn <?= e(sales_active($p['key'], 'month')) ?>" href="<?= e(sales_period_link('month')) ?>">Mes</a>
            <a class="btn btn-sm action-btn <?= e(sales_active($p['key'], 'year')) ?>" href="<?= e(sales_period_link('year')) ?>">Año</a>
          </div>
        </div>
        <div class="card-body px-4 py-4">
          <?php if (count($summary) === 0): ?>
            <div class="text-muted">No hay ventas en este período.</div>
          <?php else: ?>
            <div class="row g-3">
              <?php foreach ($summary as $s): ?>
                <div class="col-12 col-md-6">
                  <div class="stat-card">
                    <div class="d-flex align-items-start justify-content-between">
                      <div>
                        <div class="muted-label">Total (<?= e($s['currency']) ?>)</div>
                        <div class="fs-4 fw-bold mt-1"><?= e(money_format_cents((int)$s['total_cents'], (string)$s['currency'])) ?></div>
                        <div class="text-muted mt-1"><?= e((string)$s['count']) ?> facturas</div>
                      </div>
                      <span class="badge text-bg-light border"><?= e($p['key']) ?></span>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card card-lift">
        <div class="card-header card-header-clean bg-white px-4 py-3">
          <p class="muted-label mb-1">Detalle</p>
          <h2 class="h5 mb-0">Ventas del período</h2>
        </div>
        <div class="card-body px-4 py-4">
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th style="width:90px">#</th>
                  <th>Cliente</th>
                  <th style="width:180px" class="text-end">Total</th>
                  <th style="width:200px">Fecha</th>
                </tr>
              </thead>
              <tbody>
              <?php if (count($rows) === 0): ?>
                <tr>
                  <td colspan="4" class="text-muted">Sin resultados.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <td><?= e((string)$r['id']) ?></td>
                    <td><?= e((string)$r['customer_name']) ?></td>
                    <td class="text-end"><?= e(money_format_cents((int)$r['total_cents'], (string)$r['currency'])) ?></td>
                    <td><?= e((string)$r['created_at']) ?></td>
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
