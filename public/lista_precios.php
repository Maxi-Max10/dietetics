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
  <link rel="stylesheet" href="/brand.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --accent:#463B1E; --accent-rgb:70,59,30; --accent-dark:#2f2713; --accent-2:#96957E; --accent-2-rgb:150,149,126; --ink:#241e10; --muted:#6b6453; --card:rgba(255,255,255,.9); }
    body { font-family:'Space Grotesk','Segoe UI',sans-serif; background: radial-gradient(circle at 10% 20%, rgba(var(--accent-2-rgb),.22), transparent 38%), radial-gradient(circle at 90% 10%, rgba(var(--accent-rgb),.12), transparent 40%), linear-gradient(120deg,#fbfaf6,#E7E3D5); color:var(--ink); min-height:100vh; }
    .navbar-glass { background:rgba(255,255,255,.9); backdrop-filter:blur(12px); border:1px solid rgba(15,23,42,.06); box-shadow:0 10px 40px rgba(15,23,42,.08); }
    .page-shell { padding:2rem 0; }
    .card-lift { background:var(--card); border:1px solid rgba(15,23,42,.06); box-shadow:0 18px 50px rgba(15,23,42,.07); border-radius:18px; }
    .muted-label { color:var(--muted); font-weight:600; text-transform:uppercase; letter-spacing:.04em; font-size:.8rem; }
    .pill { display:inline-flex; align-items:center; gap:.4rem; padding:.35rem .75rem; border-radius:999px; background:rgba(var(--accent-rgb),.1); color:var(--accent); font-weight:600; font-size:.9rem; }
    .btn-primary, .btn-primary:hover, .btn-primary:focus { background:linear-gradient(135deg,var(--accent),var(--accent-dark)); border:none; box-shadow:0 10px 30px rgba(var(--accent-rgb),.25); }
    .action-btn { border-radius:12px; font-weight:600; }
    .table thead th { background:rgba(var(--accent-rgb),.08); border-bottom:none; font-weight:600; color:var(--ink); }
    .table td, .table th { border-color:rgba(148,163,184,.35); }
    .cart-sticky { position: sticky; top: 1rem; }
    .qty-input { width: 86px; }
    .small-help { color: var(--muted); font-size: .9rem; }
    @media (max-width: 992px) { .cart-sticky { position: static; } }
  </style>
</head>
<body>
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
      <div class="card card-lift">
        <div class="card-body p-4">
          <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3">
            <div>
              <p class="muted-label mb-1">Cliente</p>
              <h1 class="h4 mb-1">Encargá para retirar</h1>
              <div class="small-help">Seleccioná productos, armá tu pedido y lo confirmamos por teléfono/WhatsApp.</div>
            </div>
            <div class="w-100 w-md-auto" style="max-width: 360px;">
              <label class="form-label mb-1" for="searchInput">Buscar</label>
              <input class="form-control" id="searchInput" placeholder="Ej: yerba, azúcar, fideos..." autocomplete="off">
            </div>
          </div>

          <div class="mt-3" id="loadError" style="display:none;">
            <div class="alert alert-danger mb-0" id="loadErrorText"></div>
          </div>

          <div class="table-responsive mt-3">
            <table class="table align-middle">
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

    <div class="col-12 col-lg-4">
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
                <input class="form-control" id="customerName" name="customer_name" required maxlength="190">
              </div>

              <div>
                <label class="form-label mb-1" for="customerPhone">Teléfono / WhatsApp</label>
                <input class="form-control" id="customerPhone" name="customer_phone" maxlength="40" placeholder="Ej: 11 1234-5678">
              </div>

              <div>
                <label class="form-label mb-1" for="customerAddress">Dirección (opcional)</label>
                <input class="form-control" id="customerAddress" name="customer_address" maxlength="255">
              </div>

              <div>
                <label class="form-label mb-1" for="notes">Notas (opcional)</label>
                <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Ej: sin sal, cortar fiambre, etc."></textarea>
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
          <div>Los precios pueden cambiar sin aviso. El pedido queda “a confirmar”.</div>
        </div>
      </div>
    </div>
  </div>
</main>

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

    if (cart.size === 0) {
      cartEmpty.style.display = '';
      cartList.style.display = 'none';
      cartList.innerHTML = '';
      cartTotal.textContent = fmtMoney(0, currency);
      submitBtn.disabled = true;
      return;
    }

    cartEmpty.style.display = 'none';
    cartList.style.display = '';
    cartList.innerHTML = '';

    for (const it of cart.values()) {
      currency = it.currency || currency;
      lastCurrency = currency;
      const line = Math.round((it.price_cents || 0) * (it.qty_base || 0));
      totalCents += line;

      const row = document.createElement('div');
      row.className = 'list-group-item d-flex align-items-start justify-content-between gap-2';
      const unitLabel = it.unit ? (' / ' + it.unit) : '';
      const qtyText = (it.qty_display_unit && it.qty_display_unit !== '')
        ? (String(it.qty_display) + ' ' + String(it.qty_display_unit))
        : String(it.qty_display);
      row.innerHTML = `
        <div class="me-2" style="min-width: 0;">
          <div class="fw-semibold text-truncate">${escapeHtml(it.name || '')}</div>
          <div class="text-muted" style="font-size:.9rem;">${escapeHtml(qtyText)} × ${escapeHtml(fmtMoney(it.price_cents, currency) + unitLabel)}</div>
        </div>
        <button class="btn btn-sm btn-outline-danger" type="button" aria-label="Quitar">Quitar</button>
      `;
      row.querySelector('button').addEventListener('click', () => {
        cart.delete(it.id);
        renderCart();
      });
      cartList.appendChild(row);
    }

    cartTotal.textContent = fmtMoney(totalCents, currency);
    submitBtn.disabled = false;
  };

  const escapeHtml = (s) => String(s)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

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
      const priceBase = it.price_formatted || fmtMoney(it.price_cents || 0, it.currency || 'ARS');
      const price = it.price_label || (priceBase + (unit ? (' / ' + unit) : ''));

      const helperText = (unit === 'kg')
        ? 'Se cobra por kg. Si elegís g, se convierte automáticamente.'
        : (unit === 'l')
          ? 'Se cobra por litro. Si elegís ml, se convierte automáticamente.'
          : '';

      const opts = unitOptionsFor(unit);
      const def = defaultQtyFor(unit);
      const hasSelect = opts.length > 1;
      const qtyConfig = inputStepFor(def.unit);

      const unitSelectHtml = hasSelect
        ? `<select class="form-select form-select-sm" style="max-width: 86px;">
            ${opts.map(u => `<option value="${escapeHtml(u)}" ${u === def.unit ? 'selected' : ''}>${escapeHtml(u)}</option>`).join('')}
           </select>`
        : `<span class="text-muted" style="font-size:.9rem;">${escapeHtml(def.unit || unit || '')}</span>`;

      tr.innerHTML = `
        <td>
          <div class="fw-semibold">${escapeHtml(name)}</div>
          ${desc ? `<div class="text-muted" style="font-size:.9rem;">${escapeHtml(desc)}</div>` : ''}
        </td>
        <td class="text-end fw-semibold">${escapeHtml(price)}</td>
        <td>
          <div class="d-flex align-items-center gap-2 justify-content-end">
            <input type="number" class="form-control qty-input" value="${escapeHtml(def.value)}" min="${escapeHtml(qtyConfig.min)}" step="${escapeHtml(qtyConfig.step)}">
            ${unitSelectHtml}
          </div>
          ${helperText ? `<div class="text-muted" style="font-size:.8rem; margin-top:.35rem; text-align:right;">${escapeHtml(helperText)}</div>` : ''}
        </td>
        <td class="text-end">
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
  const load = async (q) => {
    if (abort) abort.abort();
    abort = new AbortController();

    loadError.style.display = 'none';

    const url = '/api_public_catalog.php' + (q ? ('?q=' + encodeURIComponent(q)) : '');
    const res = await fetch(url, { headers: { 'Accept': 'application/json' }, signal: abort.signal });
    const data = await res.json().catch(() => null);

    if (!res.ok || !data || data.ok !== true) {
      const msg = (data && data.error) ? data.error : 'No se pudo cargar la lista.';
      loadErrorText.textContent = msg;
      loadError.style.display = '';
      renderItems([]);
      return;
    }

    renderItems(data.items || []);
  };

  let t = null;
  searchInput.addEventListener('input', () => {
    clearTimeout(t);
    t = setTimeout(() => load(searchInput.value.trim()), 250);
  });

  orderForm.addEventListener('submit', async (ev) => {
    ev.preventDefault();

    orderMsg.style.display = 'none';
    orderMsg.textContent = '';

    if (cart.size === 0) {
      return;
    }

    const items = [];
    for (const it of cart.values()) {
      // Enviamos cantidad en la unidad base del precio (ej: kg o l) para que el servidor calcule bien.
      items.push({ product_id: it.id, quantity: String(it.qty_base) });
    }

    const payload = {
      ajax: 1,
      csrf_token: orderForm.querySelector('input[name="csrf_token"]').value,
      customer_name: document.getElementById('customerName').value,
      customer_phone: document.getElementById('customerPhone').value,
      customer_address: document.getElementById('customerAddress').value,
      notes: document.getElementById('notes').value,
      items,
    };

    submitBtn.disabled = true;
    submitBtn.textContent = 'Enviando…';

    try {
      const res = await fetch('/api_public_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json().catch(() => null);

      if (!res.ok || !data || data.ok !== true) {
        const msg = (data && data.error) ? data.error : 'No se pudo enviar el pedido.';
        orderMsg.textContent = msg;
        orderMsg.style.display = '';
        submitBtn.disabled = false;
        submitBtn.textContent = 'Enviar pedido';
        return;
      }

      orderMsg.textContent = (data.message || 'Pedido enviado.') + ' Total: ' + (data.total_formatted || '');
      orderMsg.style.display = '';
      cart.clear();
      renderCart();
      submitBtn.textContent = 'Enviado';

    } catch (e) {
      orderMsg.textContent = 'No se pudo enviar el pedido.';
      orderMsg.style.display = '';
      submitBtn.disabled = false;
      submitBtn.textContent = 'Enviar pedido';
    }
  });

  renderCart();
  load('');
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
