<?php

declare(strict_types=1);

/**
 * @param array{invoice:array, items:array<int, array>} $data
 */
function invoice_render_html(array $data): string
{
    $invoice = $data['invoice'];
    $items = $data['items'];

    $id = (int)($invoice['id'] ?? 0);
    $customerName = (string)($invoice['customer_name'] ?? '');
    $customerEmail = (string)($invoice['customer_email'] ?? '');
    $detail = (string)($invoice['detail'] ?? '');
    $currency = (string)($invoice['currency'] ?? 'USD');
    $totalCents = (int)($invoice['total_cents'] ?? 0);
    $createdAt = (string)($invoice['created_at'] ?? '');

    // Logo embebido (data URI) para que funcione en email y PDF.
    $logoHtml = '';
    $logoPath = __DIR__ . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'logo.jpg';
    if (is_file($logoPath)) {
      $bytes = @file_get_contents($logoPath);
      if (is_string($bytes) && $bytes !== '') {
        $logoBase64 = base64_encode($bytes);
        $logoHtml = '<img class="logo" alt="Logo" src="data:image/jpeg;base64,' . $logoBase64 . '">';
      }
    }

    $rowsHtml = '';
    foreach ($items as $item) {
        $desc = htmlspecialchars((string)($item['description'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $qty = (string)($item['quantity'] ?? '1.00');
        $unit = money_format_cents((int)($item['unit_price_cents'] ?? 0), $currency);
        $line = money_format_cents((int)($item['line_total_cents'] ?? 0), $currency);

        $rowsHtml .= "<tr>";
        $rowsHtml .= "<td>{$desc}</td>";
        $rowsHtml .= "<td style=\"text-align:right\">" . htmlspecialchars($qty, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td>";
        $rowsHtml .= "<td style=\"text-align:right\">" . htmlspecialchars($unit, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td>";
        $rowsHtml .= "<td style=\"text-align:right\">" . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td>";
        $rowsHtml .= "</tr>";
    }

    $detailHtml = $detail !== ''
        ? '<div class="detail"><strong>Detalle:</strong><br>' . nl2br(htmlspecialchars($detail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</div>'
        : '';

    $totalFormatted = money_format_cents($totalCents, $currency);

    return "<!doctype html>
<html lang=\"es\">
<head>
  <meta charset=\"utf-8\">
  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">
  <title>Factura #{$id}</title>
  <style>
    :root { --primary: #0d6efd; --text: #111; --muted: #667085; --line: #e6e6e6; --bg: #f6f7f8; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; color: var(--text); font-size: 12px; }
    .wrap { max-width: 820px; margin: 0 auto; padding: 24px; }
    .card { border: 1px solid var(--line); border-radius: 14px; overflow: hidden; }
    .header { background: linear-gradient(90deg, var(--primary), #5aa2ff); color: #fff; padding: 18px 20px; }
    .header-row { display: table; width: 100%; }
    .header-left, .header-right { display: table-cell; vertical-align: middle; }
    .header-right { text-align: right; }
    .logo { height: 42px; width: auto; border-radius: 8px; background: rgba(255,255,255,.15); padding: 6px; }
    .title { font-size: 18px; font-weight: 700; margin: 0; }
    .subtitle { margin: 4px 0 0; opacity: .9; }
    .body { padding: 18px 20px; }
    .meta { display: table; width: 100%; margin-bottom: 14px; }
    .meta-left, .meta-right { display: table-cell; vertical-align: top; }
    .meta-right { text-align: right; }
    .muted { color: var(--muted); }
    .badge { display: inline-block; padding: 4px 8px; border-radius: 999px; background: rgba(13,110,253,.10); color: #0b5ed7; font-weight: 600; font-size: 11px; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { border-bottom: 1px solid var(--line); padding: 10px 8px; }
    th { text-align: left; background: var(--bg); font-weight: 700; }
    tbody tr:nth-child(even) td { background: #fcfcfd; }
    .totals { margin-top: 12px; display: table; width: 100%; }
    .totals-left, .totals-right { display: table-cell; vertical-align: top; }
    .totals-right { text-align: right; }
    .total-box { display: inline-block; min-width: 240px; border: 1px solid var(--line); border-radius: 12px; padding: 12px 14px; background: #fff; }
    .total-label { font-size: 11px; color: var(--muted); margin: 0 0 6px; }
    .total-value { font-size: 16px; font-weight: 800; margin: 0; }
    .detail { margin-top: 14px; padding: 12px; background: var(--bg); border-radius: 10px; border: 1px solid var(--line); }
    .footer { padding: 12px 20px 18px; color: var(--muted); font-size: 11px; }
  </style>
</head>
<body>
  <div class=\"wrap\">
    <div class=\"card\">
      <div class=\"header\">
        <div class=\"header-row\">
          <div class=\"header-left\">
            {$logoHtml}
          </div>
          <div class=\"header-right\">
            <div class=\"title\">Factura</div>
            <div class=\"subtitle\">#{$id}</div>
          </div>
        </div>
      </div>

      <div class=\"body\">
        <div class=\"meta\">
          <div class=\"meta-left\">
            <div class=\"muted\">Facturar a</div>
            <div style=\"font-weight:700; font-size: 13px\">" . htmlspecialchars($customerName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</div>
            <div class=\"muted\">" . htmlspecialchars($customerEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</div>
          </div>
          <div class=\"meta-right\">
            <div class=\"badge\">" . htmlspecialchars(strtoupper($currency), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</div>
            <div class=\"muted\" style=\"margin-top:6px\">Fecha</div>
            <div style=\"font-weight:700\">" . htmlspecialchars($createdAt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</div>
          </div>
        </div>

        <table>
          <thead>
            <tr>
              <th>Producto</th>
              <th style=\"text-align:right\">Cantidad</th>
              <th style=\"text-align:right\">Precio</th>
              <th style=\"text-align:right\">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            {$rowsHtml}
          </tbody>
        </table>

        <div class=\"totals\">
          <div class=\"totals-left\">{$detailHtml}</div>
          <div class=\"totals-right\">
            <div class=\"total-box\">
              <p class=\"total-label\">Total</p>
              <p class=\"total-value\">" . htmlspecialchars($totalFormatted, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>
            </div>
          </div>
        </div>
      </div>

      <div class=\"footer\">Documento generado automáticamente.</div>
    </div>
  </div>
</body>
</html>";
}
