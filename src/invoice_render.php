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
    body { font-family: Arial, sans-serif; color: #111; font-size: 12px; }
    .wrap { max-width: 800px; margin: 0 auto; padding: 24px; }
    .top { display: flex; justify-content: space-between; gap: 16px; }
    .h1 { font-size: 22px; font-weight: 700; margin: 0 0 4px; }
    .muted { color: #666; }
    table { width: 100%; border-collapse: collapse; margin-top: 16px; }
    th, td { border-bottom: 1px solid #e6e6e6; padding: 10px 8px; }
    th { text-align: left; background: #f6f7f8; }
    .total { text-align: right; font-size: 14px; font-weight: 700; margin-top: 14px; }
    .detail { margin-top: 14px; padding: 12px; background: #f6f7f8; border-radius: 8px; }
  </style>
</head>
<body>
  <div class=\"wrap\">
    <div class=\"top\">
      <div>
        <div class=\"h1\">Factura #{$id}</div>
        <div class=\"muted\">Fecha: " . htmlspecialchars($createdAt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</div>
      </div>
      <div style=\"text-align:right\">
        <div><strong>Cliente:</strong> " . htmlspecialchars($customerName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</div>
        <div class=\"muted\">" . htmlspecialchars($customerEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</div>
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

    <div class=\"total\">Total: " . htmlspecialchars($totalFormatted, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</div>
    {$detailHtml}
  </div>
</body>
</html>";
}
