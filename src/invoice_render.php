<?php

declare(strict_types=1);

/**
 * @param array{invoice:array, items:array<int, array>} $data
 */
function invoice_render_html(array $data, array $options = []): string
{
    $invoice = $data['invoice'];
    $items = $data['items'];

    $id = (int)($invoice['id'] ?? 0);
    $customerName = (string)($invoice['customer_name'] ?? '');
    $customerEmail = (string)($invoice['customer_email'] ?? '');
    $customerPhone = (string)($invoice['customer_phone'] ?? '');
    $customerAddress = (string)($invoice['customer_address'] ?? '');
    $detail = (string)($invoice['detail'] ?? '');
    $currency = (string)($invoice['currency'] ?? 'USD');
    $totalCents = (int)($invoice['total_cents'] ?? 0);
    $createdAt = (string)($invoice['created_at'] ?? '');

    $assetMode = (string)($options['asset_mode'] ?? 'data'); // data | file

    $path_to_file_url = static function (string $path): string {
      $real = realpath($path);
      if ($real === false) {
        $real = $path;
      }

      // Normalizar separadores para URL
      $real = str_replace('\\', '/', $real);

      // Si es ruta Windows tipo C:/...
      if (preg_match('/^[A-Za-z]:\//', $real) === 1) {
        return 'file:///' . str_replace(' ', '%20', $real);
      }

      // Linux/Unix
      if (!str_starts_with($real, '/')) {
        $real = '/' . $real;
      }
      return 'file://' . str_replace(' ', '%20', $real);
    };

    // Logo (data-uri para email/HTML; file:// para PDF con Dompdf si hace falta).
    $logoHtml = '';
    $logoPath = __DIR__ . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'logo.jpg';
    if (!is_file($logoPath)) {
      error_log('Invoice logo missing: ' . $logoPath);
    } elseif (!is_readable($logoPath)) {
      error_log('Invoice logo not readable: ' . $logoPath);
    } else {
      if ($assetMode === 'file') {
        $logoHtml = '<img class="logo" alt="Logo" src="' . htmlspecialchars($path_to_file_url($logoPath), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
      } else {
      $bytes = @file_get_contents($logoPath);
      if (!is_string($bytes) || $bytes === '') {
        error_log('Invoice logo empty/unreadable bytes: ' . $logoPath);
      } else {
        // Detectar MIME real
        $mime = 'image/jpeg';
        if (function_exists('finfo_open')) {
          $finfo = finfo_open(FILEINFO_MIME_TYPE);
          if ($finfo) {
            $detected = finfo_file($finfo, $logoPath);
            finfo_close($finfo);
            if (is_string($detected) && $detected !== '') {
              $mime = $detected;
            }
          }
        }

        // Si el logo es muy grande, algunos clientes/PDF pueden fallar.
        $maxBytes = 1024 * 1024 * 2; // 2MB
        if (strlen($bytes) > $maxBytes) {
          error_log('Invoice logo too large (' . strlen($bytes) . ' bytes): ' . $logoPath);
        }

        // Intentar re-encode con GD para mejorar compatibilidad (si está disponible)
        if ($mime === 'image/jpeg' && extension_loaded('gd') && function_exists('imagecreatefromstring')) {
          $img = @imagecreatefromstring($bytes);
          if ($img !== false && function_exists('imagesx') && function_exists('imagesy')) {
            $w = imagesx($img);
            $h = imagesy($img);
            $targetW = 320;
            $scale = ($w > $targetW) ? ($targetW / max(1, $w)) : 1.0;
            $newW = (int)max(1, round($w * $scale));
            $newH = (int)max(1, round($h * $scale));

            $dst = imagecreatetruecolor($newW, $newH);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);

            ob_start();
            imagepng($dst);
            $pngBytes = ob_get_clean();
            imagedestroy($dst);
            imagedestroy($img);

            if (is_string($pngBytes) && $pngBytes !== '') {
              $bytes = $pngBytes;
              $mime = 'image/png';
            }
          } elseif ($img !== false) {
            imagedestroy($img);
          }
        }

        $logoBase64 = base64_encode($bytes);
        $logoHtml = '<img class="logo" alt="Logo" src="data:' . htmlspecialchars($mime, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ';base64,' . $logoBase64 . '">';
      }
      }
    }

    $rowsHtml = '';
    $formatQty = static function (string $qty): string {
      $qty = trim($qty);
      if ($qty === '') {
        return '0';
      }
      // Compactar decimales tipo 1.00 -> 1
      if (str_contains($qty, '.')) {
        $qty = rtrim(rtrim($qty, '0'), '.');
      }
      return $qty;
    };

    $unitLabel = static function (string $unit): string {
      $u = strtolower(trim($unit));
      return match ($u) {
        'g' => 'g',
        'kg' => 'kg',
        'ml' => 'ml',
        'l' => 'l',
        default => 'cant.',
      };
    };

    $capitalizeFirst = static function (string $value): string {
      $value = trim($value);
      if ($value === '') {
        return '';
      }
      if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
        $first = mb_substr($value, 0, 1, 'UTF-8');
        $rest = mb_substr($value, 1, null, 'UTF-8');
        return mb_strtoupper($first, 'UTF-8') . $rest;
      }
      return strtoupper(substr($value, 0, 1)) . substr($value, 1);
    };

    foreach ($items as $item) {
      $descRaw = $capitalizeFirst((string)($item['description'] ?? ''));
      $desc = htmlspecialchars($descRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      $qtyRaw = (string)($item['quantity'] ?? '1.00');
      $unitRaw = (string)($item['unit'] ?? '');
      $qty = $formatQty($qtyRaw);
      if ($unitRaw !== '') {
        $qty .= ' ' . $unitLabel($unitRaw);
      }
        $line = money_format_cents((int)($item['line_total_cents'] ?? 0), $currency);

        $rowsHtml .= "<tr>";
        $rowsHtml .= "<td>{$desc}</td>";
        $rowsHtml .= "<td style=\"text-align:right\">" . htmlspecialchars($qty, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td>";
        $rowsHtml .= "<td style=\"text-align:right\">" . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td>";
        $rowsHtml .= "</tr>";
    }

    $detailHtml = $detail !== ''
        ? '<div class="detail"><strong>Detalle:</strong><br>' . nl2br(htmlspecialchars($detail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</div>'
        : '';

    $totalFormatted = money_format_cents($totalCents, $currency);

  $customerContactHtml = '';
  if (trim($customerPhone) !== '') {
    $customerContactHtml .= '<div class="muted">' . htmlspecialchars($customerPhone, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
  }
  if (trim($customerEmail) !== '') {
    $customerContactHtml .= '<div class="muted">' . htmlspecialchars($customerEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
  }
  if (trim($customerAddress) !== '') {
    $customerContactHtml .= '<div class="muted">' . htmlspecialchars($customerAddress, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
  }

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
            {$customerContactHtml}
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
