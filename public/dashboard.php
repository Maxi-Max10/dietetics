<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

auth_require_login();

$config = app_config();
$appName = (string)($config['app']['name'] ?? 'Dietetic');
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

    try {
      $pdo = db($config);
            $invoiceId = invoices_create($pdo, $userId, $customerName, $customerEmail, $detail, $items, 'ARS');
      $data = invoices_get($pdo, $invoiceId, $userId);
      $download = invoice_build_download($data);

      if ($action === 'download') {
        invoice_send_download($download);
        exit;
      }

      if ($action === 'email') {
        $subject = 'Factura #' . $invoiceId . ' - ' . $appName;
        $body = '<p>Hola ' . e($customerName) . ',</p><p>Adjuntamos tu factura.</p><p>Gracias.</p>';
        mail_send_with_attachment($config, $customerEmail, $customerName, $subject, $body, $download['bytes'], $download['filename'], $download['mime']);
        $flash = 'Factura enviada por email y guardada (ID ' . $invoiceId . ').';
      } else {
        $flash = 'Factura guardada (ID ' . $invoiceId . ').';
      }
    } catch (Throwable $e) {
      $error = ($config['app']['env'] ?? 'production') === 'production'
        ? 'No se pudo crear/enviar la factura.'
        : ('Error: ' . $e->getMessage());
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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
  <div class="container">
    <span class="navbar-brand mb-0 h1"><?= e($appName) ?></span>
    <form method="post" action="/logout.php" class="ms-auto">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <button type="submit" class="btn btn-outline-secondary btn-sm">Salir</button>
    </form>
  </div>
</nav>

<main class="container py-4">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-10">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 mb-0">Administración - Facturas</h1>
        <span class="text-muted small">Usuario #<?= e((string)$userId) ?></span>
      </div>

      <?php if ($flash !== ''): ?>
        <div class="alert alert-success" role="alert"><?= e($flash) ?></div>
      <?php endif; ?>
      <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <div class="card">
        <div class="card-body">
          <h2 class="h5 mb-3">Crear factura</h2>

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
              <div class="col-12">
                <label class="form-label" for="detail">Detalle</label>
                <textarea class="form-control" id="detail" name="detail" rows="3" placeholder="Observaciones / detalle..."></textarea>
              </div>
            </div>

            <hr class="my-4">

            <div class="d-flex align-items-center justify-content-between mb-2">
              <h3 class="h6 mb-0">Productos</h3>
              <button type="button" class="btn btn-outline-primary btn-sm" id="addItem">Agregar producto</button>
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

            <div class="d-flex gap-2 justify-content-end mt-3">
              <button class="btn btn-primary" type="submit" name="action" value="download">Guardar y descargar</button>
              <button class="btn btn-outline-primary" type="submit" name="action" value="email">Guardar y enviar por email</button>
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
</body>
</html>
