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

$accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
$wantsJson = (
  ((string)($_GET['ajax'] ?? '') === '1')
  || ((string)($_POST['ajax'] ?? '') === '1')
  || (stripos($accept, 'application/json') !== false)
);

$contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');
$jsonBody = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && stripos($contentType, 'application/json') !== false) {
  $raw = file_get_contents('php://input');
  $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
  if (is_array($decoded)) {
    $jsonBody = $decoded;
  }
}

$q = trim((string)($_GET['q'] ?? ''));
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

$edit = null;

// API JSON (para carga dinámica y buscador en vivo)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $wantsJson) {
  try {
    $pdo = db($config);
    if (!catalog_supports_table($pdo)) {
      throw new RuntimeException('No se encontró la tabla del catálogo. ¿Ejecutaste el schema.sql?');
    }

    $qAjax = trim((string)($_GET['q'] ?? ''));
    $items = catalog_list($pdo, $userId, $qAjax, 300);
    $out = [];
    foreach ($items as $r) {
      $out[] = [
        'id' => (int)($r['id'] ?? 0),
        'name' => (string)($r['name'] ?? ''),
        'price_cents' => (int)($r['price_cents'] ?? 0),
        'currency' => (string)($r['currency'] ?? 'ARS'),
        'price_formatted' => money_format_cents((int)($r['price_cents'] ?? 0), (string)($r['currency'] ?? 'ARS')),
      ];
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'items' => $out], JSON_UNESCAPED_UNICODE);
    exit;
  } catch (Throwable $e) {
    error_log('catalogo.php ajax load error: ' . $e->getMessage());
    $msg = ($config['app']['env'] ?? 'production') === 'production'
      ? 'No se pudo cargar el catálogo.'
      : ('Error: ' . $e->getMessage());

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $data = array_merge($_POST, $jsonBody);
  $token = (string)($data['csrf_token'] ?? '');
    if (!csrf_verify($token)) {
    $error = 'Sesión inválida. Recargá e intentá de nuevo.';
    if ($wantsJson) {
      http_response_code(400);
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
      exit;
    }
    } else {
    $action = (string)($data['action'] ?? '');
    $returnQ = trim((string)($data['q'] ?? $q));

        try {
            $pdo = db($config);

            if (!catalog_supports_table($pdo)) {
                throw new RuntimeException('No se encontró la tabla del catálogo. ¿Ejecutaste el schema.sql?');
            }

            if ($action === 'create') {
              $name = trim((string)($data['name'] ?? ''));
              $price = (string)($data['price'] ?? '0');
              $currency = trim((string)($data['currency'] ?? 'ARS'));

                catalog_create($pdo, $userId, $name, $price, $currency);
              if ($wantsJson) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'message' => 'Producto agregado al catálogo.'], JSON_UNESCAPED_UNICODE);
                exit;
              }

              $_SESSION['flash'] = 'Producto agregado al catálogo.';
              header('Location: /catalogo' . ($returnQ !== '' ? ('?q=' . rawurlencode($returnQ)) : ''));
              exit;
            }

            if ($action === 'update') {
              $id = (int)($data['id'] ?? 0);
              $name = trim((string)($data['name'] ?? ''));
              $price = (string)($data['price'] ?? '0');
              $currency = trim((string)($data['currency'] ?? 'ARS'));

                catalog_update($pdo, $userId, $id, $name, $price, $currency);
              if ($wantsJson) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'message' => 'Producto actualizado.'], JSON_UNESCAPED_UNICODE);
                exit;
              }

              $_SESSION['flash'] = 'Producto actualizado.';
              header('Location: /catalogo' . ($returnQ !== '' ? ('?q=' . rawurlencode($returnQ)) : ''));
              exit;
            }

            if ($action === 'delete') {
              $id = (int)($data['id'] ?? 0);
                catalog_delete($pdo, $userId, $id);
              if ($wantsJson) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'message' => 'Producto eliminado.'], JSON_UNESCAPED_UNICODE);
                exit;
              }

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

          if ($wantsJson) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
            exit;
          }
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
  <meta name="csrf-token" content="<?= e($csrf) ?>">
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
      <div id="catalogClientSuccess" class="alert alert-success d-none" role="alert"></div>
      <div id="catalogClientError" class="alert alert-danger d-none" role="alert"></div>

      <div class="card card-lift mb-4">
        <div class="card-header card-header-clean bg-white px-4 py-3">
          <p class="muted-label mb-1" id="catalogFormModeLabel"><?= $edit ? 'Editar' : 'Nuevo' ?></p>
          <h2 class="h5 mb-0" id="catalogFormModeTitle"><?= $edit ? 'Modificar producto' : 'Agregar producto' ?></h2>
        </div>
        <div class="card-body px-4 py-4">
          <form method="post" action="/catalogo" class="row g-3" id="catalogForm">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="q" id="catalogFormQ" value="<?= e($q) ?>">
            <input type="hidden" name="action" id="catalogAction" value="<?= $edit ? 'update' : 'create' ?>">
            <input type="hidden" name="id" id="catalogId" value="<?= $edit ? e((string)$edit['id']) : '' ?>">

            <div class="col-12 col-md-6">
              <label class="form-label" for="name">Producto</label>
              <div class="input-group">
                <input class="form-control" id="name" name="name" value="<?= e($defaultName) ?>" required>
                <button class="btn btn-outline-secondary" type="button" id="catalogVoiceBtn">Voz</button>
              </div>
              <input class="d-none" type="file" id="catalogVoiceFile" accept="audio/*" capture>
              <div class="form-text">Podés decir: “arroz integral, precio 1500 pesos”. Si tu navegador no soporta voz, usá el micrófono del teclado (dictado) con el cursor en el campo.</div>
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
              <a class="btn btn-outline-secondary action-btn <?= $edit ? '' : 'd-none' ?>" id="catalogCancel" href="/catalogo<?= $q !== '' ? ('?q=' . rawurlencode($q)) : '' ?>">Cancelar</a>
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
          <form method="get" action="/catalogo" class="d-flex flex-column flex-md-row gap-2 align-items-md-center justify-content-between mb-3" id="catalogSearchForm">
            <div class="d-flex gap-2 flex-grow-1">
              <input class="form-control" id="catalogSearch" name="q" value="<?= e($q) ?>" placeholder="Buscar por producto" aria-label="Buscar" autocomplete="off">
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
              <tbody id="catalogTbody">
              <?php if (count($rows) === 0): ?>
                <tr><td colspan="3" class="text-muted">Sin resultados.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <td><?= e((string)$r['name']) ?></td>
                    <td class="text-end"><?= e(money_format_cents((int)$r['price_cents'], (string)$r['currency'])) ?></td>
                    <td class="text-end">
                      <div class="d-inline-flex gap-2">
                        <a
                          class="btn btn-outline-primary btn-sm js-edit"
                          href="/catalogo?edit=<?= e((string)$r['id']) ?><?= $q !== '' ? ('&q=' . rawurlencode($q)) : '' ?>"
                          data-id="<?= e((string)$r['id']) ?>"
                          data-name="<?= e((string)$r['name']) ?>"
                          data-price="<?= e(number_format(((int)$r['price_cents']) / 100, 2, '.', '')) ?>"
                          data-currency="<?= e((string)$r['currency']) ?>"
                        >Editar</a>
                        <button type="button" class="btn btn-outline-danger btn-sm js-delete" data-id="<?= e((string)$r['id']) ?>">Eliminar</button>
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

<script>
(() => {
  const endpoint = '/catalogo?ajax=1';

  const clientSuccess = document.getElementById('catalogClientSuccess');
  const clientError = document.getElementById('catalogClientError');
  const tbody = document.getElementById('catalogTbody');
  const searchForm = document.getElementById('catalogSearchForm');
  const searchInput = document.getElementById('catalogSearch');
  const form = document.getElementById('catalogForm');
  const formQ = document.getElementById('catalogFormQ');
  const actionInput = document.getElementById('catalogAction');
  const idInput = document.getElementById('catalogId');
  const nameInput = document.getElementById('name');
  const priceInput = document.getElementById('price');
  const currencyInput = document.getElementById('currency');
  const cancelLink = document.getElementById('catalogCancel');
  const modeLabel = document.getElementById('catalogFormModeLabel');
  const modeTitle = document.getElementById('catalogFormModeTitle');
  const voiceBtn = document.getElementById('catalogVoiceBtn');
  const voiceFile = document.getElementById('catalogVoiceFile');

  const defaultVoiceLabel = voiceBtn ? (voiceBtn.textContent || 'Voz') : 'Voz';

  const hideMsg = (el) => { if (!el) return; el.classList.add('d-none'); el.textContent = ''; };
  const showMsg = (el, msg) => { if (!el) return; el.textContent = msg; el.classList.remove('d-none'); };
  const clearMsgs = () => { hideMsg(clientSuccess); hideMsg(clientError); };

  const setCreateMode = () => {
    actionInput.value = 'create';
    idInput.value = '';
    modeLabel.textContent = 'Nuevo';
    modeTitle.textContent = 'Agregar producto';
    cancelLink.classList.add('d-none');
    nameInput.value = '';
    priceInput.value = '';
    currencyInput.value = 'ARS';
  };

  const setEditMode = (item) => {
    actionInput.value = 'update';
    idInput.value = String(item.id);
    modeLabel.textContent = 'Editar';
    modeTitle.textContent = 'Modificar producto';
    cancelLink.classList.remove('d-none');
    nameInput.value = item.name || '';
    priceInput.value = item.price || '';
    currencyInput.value = item.currency || 'ARS';
    nameInput.focus();
  };

  const csrfToken = () => {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  };

  const renderRows = (items) => {
    tbody.innerHTML = '';
    if (!items || items.length === 0) {
      const tr = document.createElement('tr');
      tr.innerHTML = '<td colspan="3" class="text-muted">Sin resultados.</td>';
      tbody.appendChild(tr);
      return;
    }

    for (const it of items) {
      const tr = document.createElement('tr');
      const priceRaw = (typeof it.price_cents === 'number')
        ? (it.price_cents / 100).toFixed(2)
        : '';
      tr.innerHTML = `
        <td>${escapeHtml(it.name || '')}</td>
        <td class="text-end">${escapeHtml(it.price_formatted || '')}</td>
        <td class="text-end">
          <div class="d-inline-flex gap-2">
            <a
              class="btn btn-outline-primary btn-sm js-edit"
              href="/catalogo?edit=${encodeURIComponent(String(it.id))}${searchInput.value ? ('&q=' + encodeURIComponent(searchInput.value)) : ''}"
              data-id="${escapeAttr(String(it.id))}"
              data-name="${escapeAttr(it.name || '')}"
              data-price="${escapeAttr(priceRaw)}"
              data-currency="${escapeAttr(it.currency || 'ARS')}"
            >Editar</a>
            <button type="button" class="btn btn-outline-danger btn-sm js-delete" data-id="${escapeAttr(String(it.id))}">Eliminar</button>
          </div>
        </td>
      `.trim();
      tbody.appendChild(tr);
    }
  };

  const fetchList = async (q) => {
    const url = endpoint + (q ? ('&q=' + encodeURIComponent(q)) : '');
    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
    const data = await res.json();
    if (!data || data.ok !== true) {
      throw new Error((data && data.error) ? data.error : 'No se pudo cargar el catálogo.');
    }
    return data.items || [];
  };

  const refresh = async () => {
    clearMsgs();
    formQ.value = searchInput.value || '';
    const items = await fetchList(searchInput.value || '');
    renderRows(items);
  };

  const postAction = async (payload) => {
    const res = await fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    if (!data || data.ok !== true) {
      throw new Error((data && data.error) ? data.error : 'No se pudo procesar el catálogo.');
    }
    return data;
  };

  const escapeHtml = (s) => String(s)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
  const escapeAttr = escapeHtml;

  const applyTranscriptToForm = (raw) => {
    const text = String(raw || '').trim();
    if (!text) return;

    const lower = text.toLowerCase();

    // Moneda
    let currency = 'ARS';
    if (/(\beuro\b|\beur\b)/i.test(lower)) currency = 'EUR';
    if (/(\bd[oó]lar\b|\busd\b)/i.test(lower)) currency = 'USD';
    if (/(\bars\b|\bpeso\b|\bpesos\b)/i.test(lower)) currency = 'ARS';

    // Precio
    let price = '';
    const mPrecio = lower.match(/precios?\s*[:\-]?\s*\$?\s*([0-9]+(?:[\.,][0-9]{1,2})?)/i);
    if (mPrecio && mPrecio[1]) {
      price = mPrecio[1].replace(',', '.');
    } else {
      const nums = lower.match(/([0-9]+(?:[\.,][0-9]{1,2})?)/g);
      if (nums && nums.length > 0) {
        price = String(nums[nums.length - 1]).replace(',', '.');
      }
    }

    // Nombre
    let name = text
      .replace(/\bprecios?\b\s*[:\-]?\s*\$?\s*[0-9]+(?:[\.,][0-9]{1,2})?/ig, '')
      .replace(/\b(d[oó]lar|usd|euro|eur|ars|peso|pesos)\b/ig, '')
      .replace(/[\$€]/g, '')
      .replace(/[,]+/g, ' ')
      .replace(/\s{2,}/g, ' ')
      .trim();

    if (name) nameInput.value = name;
    if (price) priceInput.value = price;
    if (currency) currencyInput.value = currency;

    showMsg(clientSuccess, 'Voz detectada: ' + text);
    if (!name) nameInput.focus();
    else if (!price) priceInput.focus();
  };

  const transcribeAudioFile = async (file) => {
    const fd = new FormData();
    fd.append('csrf_token', csrfToken());
    fd.append('audio', file);

    const res = await fetch('/api_speech_to_text.php', {
      method: 'POST',
      headers: { 'Accept': 'application/json' },
      body: fd,
    });

    const data = await res.json().catch(() => null);
    if (!data || data.ok !== true) {
      throw new Error((data && data.error) ? data.error : 'No se pudo transcribir el audio.');
    }
    return String(data.text || '').trim();
  };

  let recorder = null;
  let recChunks = [];

  const SpeechRecognitionCtor = window.SpeechRecognition || window.webkitSpeechRecognition;
  let recognizer = null;
  let isRecognizing = false;

  const supportsSpeechRecognition = () => {
    return !!SpeechRecognitionCtor;
  };

  const startOrStopDictation = async () => {
    clearMsgs();

    if (!supportsSpeechRecognition()) {
      throw new Error('Este navegador no soporta dictado directo.');
    }

    const setUi = (busy, listening) => {
      if (!voiceBtn) return;
      voiceBtn.disabled = !!busy;
      voiceBtn.textContent = listening ? 'Detener' : defaultVoiceLabel;
    };

    if (!recognizer) {
      recognizer = new SpeechRecognitionCtor();
      recognizer.lang = 'es-AR';
      recognizer.continuous = false;
      recognizer.interimResults = false;
      try { recognizer.maxAlternatives = 1; } catch (_) {}

      recognizer.onstart = () => {
        isRecognizing = true;
        setUi(false, true);
        showMsg(clientSuccess, 'Escuchando… hablá ahora y esperá la transcripción.');
      };

      recognizer.onerror = (ev) => {
        isRecognizing = false;
        setUi(false, false);
        const code = ev && ev.error ? String(ev.error) : '';
        const msg = code === 'not-allowed'
          ? 'Permiso de micrófono denegado. Habilitalo e intentá de nuevo.'
          : 'No se pudo usar el dictado. Probá con grabación o el micrófono del teclado.';
        showMsg(clientError, msg);
      };

      recognizer.onresult = (ev) => {
        const t = ev && ev.results && ev.results[0] && ev.results[0][0] && ev.results[0][0].transcript
          ? String(ev.results[0][0].transcript)
          : '';
        if (t.trim() !== '') {
          applyTranscriptToForm(t);
        }
      };

      recognizer.onend = () => {
        isRecognizing = false;
        setUi(false, false);
      };
    }

    if (isRecognizing) {
      try { recognizer.stop(); } catch (_) {}
      return;
    }

    setUi(true, false);
    try {
      recognizer.start();
    } catch (err) {
      isRecognizing = false;
      setUi(false, false);
      throw err;
    }
  };

  const supportsMediaRecorder = () => {
    return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.MediaRecorder);
  };

  const startOrStopRecording = async () => {
    clearMsgs();

    // Si el navegador no soporta grabación directa (muy común en iOS), abrimos selector de audio con capture.
    if (!supportsMediaRecorder()) {
      if (voiceFile) {
        voiceFile.value = '';
        voiceFile.click();
        return;
      }
      nameInput.focus();
      showMsg(clientSuccess, 'Usá el micrófono del teclado para dictar en “Producto”.');
      return;
    }

    if (recorder && recorder.state === 'recording') {
      recorder.stop();
      return;
    }

    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    recChunks = [];
    recorder = new MediaRecorder(stream);
    const prevLabel = voiceBtn ? voiceBtn.textContent : '';

    const setBusy = (busy, isRecording) => {
      if (!voiceBtn) return;
      voiceBtn.disabled = !!busy;
      voiceBtn.textContent = isRecording ? 'Detener' : (prevLabel || 'Voz');
    };

    recorder.ondataavailable = (e) => {
      if (e.data && e.data.size > 0) recChunks.push(e.data);
    };
    recorder.onerror = () => {
      setBusy(false, false);
      try { stream.getTracks().forEach(t => t.stop()); } catch (_) {}
      showMsg(clientError, 'No se pudo grabar. Permití el micrófono e intentá de nuevo.');
    };
    recorder.onstart = () => {
      setBusy(false, true);
      showMsg(clientSuccess, 'Grabando… tocá “Detener” para transcribir.');
    };
    recorder.onstop = async () => {
      setBusy(true, false);
      try { stream.getTracks().forEach(t => t.stop()); } catch (_) {}

      try {
        const blob = new Blob(recChunks, { type: recorder.mimeType || 'audio/webm' });
        const file = new File([blob], 'voz.webm', { type: blob.type || 'audio/webm' });
        const text = await transcribeAudioFile(file);
        applyTranscriptToForm(text);
      } catch (err) {
        showMsg(clientError, err && err.message ? err.message : String(err));
      } finally {
        setBusy(false, false);
      }
    };

    recorder.start();
  };

  // Búsqueda dinámica
  if (searchForm) {
    searchForm.addEventListener('submit', (e) => {
      e.preventDefault();
    });
  }

  let t = null;
  if (searchInput) {
    searchInput.addEventListener('input', () => {
      clearTimeout(t);
      t = setTimeout(() => {
        refresh().catch((err) => showMsg(clientError, err.message || String(err)));
      }, 150);
    });
  }

  // Crear / actualizar sin recargar
  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      clearMsgs();

      const payload = {
        csrf_token: csrfToken(),
        action: actionInput.value,
        id: idInput.value,
        name: nameInput.value,
        price: priceInput.value,
        currency: currencyInput.value,
        q: searchInput.value || '',
      };

      postAction(payload)
        .then((resp) => {
          showMsg(clientSuccess, resp.message || 'OK');
          setCreateMode();
          return refresh();
        })
        .catch((err) => showMsg(clientError, err.message || String(err)));
    });
  }

  // Editar / eliminar desde la tabla
  document.addEventListener('click', (e) => {
    const edit = e.target && e.target.closest ? e.target.closest('.js-edit') : null;
    if (edit) {
      e.preventDefault();
      clearMsgs();
      setEditMode({
        id: edit.getAttribute('data-id') || '',
        name: edit.getAttribute('data-name') || '',
        price: edit.getAttribute('data-price') || '',
        currency: edit.getAttribute('data-currency') || 'ARS',
      });
      return;
    }

    const del = e.target && e.target.closest ? e.target.closest('.js-delete') : null;
    if (del) {
      e.preventDefault();
      clearMsgs();
      const id = del.getAttribute('data-id') || '';
      if (!id) return;
      if (!confirm('¿Eliminar este producto del catálogo?')) return;

      postAction({
        csrf_token: csrfToken(),
        action: 'delete',
        id,
        q: searchInput.value || '',
      })
        .then((resp) => {
          showMsg(clientSuccess, resp.message || 'Producto eliminado.');
          if (idInput.value === id) setCreateMode();
          return refresh();
        })
        .catch((err) => showMsg(clientError, err.message || String(err)));
    }
  });

  // Cancelar edición sin recargar
  if (cancelLink) {
    cancelLink.addEventListener('click', (e) => {
      e.preventDefault();
      clearMsgs();
      setCreateMode();
    });
  }

  // Primer carga dinámica (mantiene HTML como fallback)
  refresh().catch(() => {
    // Si falla, queda el render server-side.
  });

  // Carga por voz
  if (voiceBtn) {
    voiceBtn.addEventListener('click', () => {
      (async () => {
        // Preferimos dictado directo (Web Speech API) cuando está disponible.
        // Si falla (permiso, error del navegador), caemos a grabación/subida.
        if (supportsSpeechRecognition()) {
          try {
            await startOrStopDictation();
            return;
          } catch (_) {
            // El error ya lo mostramos; intentamos el fallback.
          }
        }
        await startOrStopRecording();
      })().catch((err) => {
        showMsg(clientError, err && err.message ? err.message : String(err));
      });
    });
  }

  if (voiceFile) {
    voiceFile.addEventListener('change', () => {
      const f = voiceFile.files && voiceFile.files[0] ? voiceFile.files[0] : null;
      if (!f) return;
      clearMsgs();
      showMsg(clientSuccess, 'Transcribiendo audio…');
      transcribeAudioFile(f)
        .then((text) => applyTranscriptToForm(text))
        .catch((err) => showMsg(clientError, err && err.message ? err.message : String(err)));
    });
  }
})();
</script>
</body>
</html>
