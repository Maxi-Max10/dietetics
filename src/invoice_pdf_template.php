<?php

declare(strict_types=1);

/**
 * Genera un PDF usando `src/pdf/boceto.pdf` como fondo y escribiendo encima.
 * Requiere FPDI (setasign/fpdi) instalado via Composer.
 */
function invoice_build_pdf_from_template(array $data): array
{
    // FPDI se apoya en FPDF (clase global FPDF). En algunos hostings, FPDI puede estar instalado
    // pero faltar setasign/fpdf, lo que causa un fatal error al cargar FPDI.
    if (!class_exists('FPDF', false)) {
        $fpdfPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'setasign' . DIRECTORY_SEPARATOR . 'fpdf' . DIRECTORY_SEPARATOR . 'fpdf.php';
        if (is_file($fpdfPath)) {
            /** @noinspection PhpIncludeInspection */
            require_once $fpdfPath;
        }
    }

    if (!class_exists('FPDF', false)) {
        throw new RuntimeException('Falta FPDF (setasign/fpdf). Instalá dependencias con Composer y asegurá vendor/setasign/fpdf/fpdf.php.');
    }

    if (!class_exists('setasign\\Fpdi\\Fpdi')) {
        throw new RuntimeException('FPDI no está instalado (setasign/fpdi).');
    }

    $invoice = $data['invoice'];
    $items = $data['items'];

    $invoiceId = (int)($invoice['id'] ?? 0);
    $customerName = (string)($invoice['customer_name'] ?? '');
    $customerEmail = (string)($invoice['customer_email'] ?? '');
    $customerDni = (string)($invoice['customer_dni'] ?? '');
    $detail = (string)($invoice['detail'] ?? '');
    $currency = (string)($invoice['currency'] ?? 'ARS');
    $totalCents = (int)($invoice['total_cents'] ?? 0);
    $createdAt = (string)($invoice['created_at'] ?? '');

    $createdAtLabel = $createdAt;
    try {
        if ($createdAt !== '') {
            $dt = new DateTimeImmutable($createdAt);
            $createdAtLabel = $dt->format('d/m/Y');
        }
    } catch (Throwable $e) {
        // Si falla el parseo, mantenemos el string original.
    }

    $templatePath = __DIR__ . DIRECTORY_SEPARATOR . 'pdf' . DIRECTORY_SEPARATOR . 'boceto.pdf';
    if (!is_file($templatePath)) {
        throw new RuntimeException('No se encontró el PDF plantilla: ' . $templatePath);
    }

    $pdf = new setasign\Fpdi\Fpdi();
    $pdf->SetAutoPageBreak(true, 18);

    $pageCount = $pdf->setSourceFile($templatePath);
    $tplId = $pdf->importPage(1);
    $size = $pdf->getTemplateSize($tplId);

    $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
    $pdf->AddPage($orientation, [$size['width'], $size['height']]);
    $pdf->useTemplate($tplId, 0, 0, $size['width'], $size['height'], true);

    $toPdfText = static function (string $text): string {
        // FPDF no soporta UTF-8 nativo; convertimos a ISO-8859-1.
        // Evitar caracteres típicamente conflictivos.
        $text = str_replace(['…', '€'], ['...', 'EUR'], $text);

        if (function_exists('iconv')) {
            $out = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
            if (is_string($out) && $out !== '') {
                return $out;
            }
        }

        // Fallback: dejar solo ASCII imprimible.
        $clean = @preg_replace('/[^\x0A\x0D\x20-\x7E]/', '?', $text);
        return is_string($clean) ? $clean : $text;
    };

    $trimDesc = static function (string $text, int $maxChars = 60): string {
        // Evita depender de mbstring en hosting.
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text, 'UTF-8') > $maxChars) {
                return mb_substr($text, 0, $maxChars - 3, 'UTF-8') . '...';
            }
            return $text;
        }
        if (strlen($text) > $maxChars) {
            return substr($text, 0, $maxChars - 3) . '...';
        }
        return $text;
    };

    $debugGrid = (getenv('INVOICE_PDF_DEBUG') === '1') || (defined('INVOICE_PDF_DEBUG') && INVOICE_PDF_DEBUG);

    // Coordenadas (mm) - ajustadas en base a una hoja A4 típica.
    // Tip: en FPDI/FPDF el origen (0,0) es arriba-izquierda.
    $marginX = 18;
    $xLeft = $marginX;
    $xRight = $size['width'] - $marginX;

    // Zonas típicas (relativas): bloque cliente a la izquierda, meta (nro/fecha) arriba a la derecha, tabla al medio.
    $yCustomer = 52;
    $yTable = 92;

    $xMeta = max($xLeft, $xRight - 65);
    $yMeta = 34;

    // Debug grid (para calibrar posiciones sobre tu boceto)
    if ($debugGrid) {
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->SetTextColor(160, 160, 160);
        $pdf->SetFont('Helvetica', '', 7);
        for ($x = 0; $x <= (int)$size['width']; $x += 10) {
            $pdf->Line($x, 0, $x, $size['height']);
            $pdf->SetXY($x + 1, 2);
            $pdf->Cell(0, 3, (string)$x, 0, 0);
        }
        for ($y = 0; $y <= (int)$size['height']; $y += 10) {
            $pdf->Line(0, $y, $size['width'], $y);
            $pdf->SetXY(2, $y + 1);
            $pdf->Cell(0, 3, (string)$y, 0, 0);
        }
        $pdf->SetTextColor(20, 20, 20);
    }

    // Meta (número/fecha) - arriba a la derecha
    $pdf->SetTextColor(20, 20, 20);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetXY($xMeta, $yMeta);
    $pdf->Cell(65, 6, $toPdfText('N° ' . $invoiceId), 0, 1, 'R');

    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetXY($xMeta, $yMeta + 7);
    $pdf->Cell(65, 6, $toPdfText('Fecha: ' . $createdAtLabel), 0, 1, 'R');

    // Cliente (izquierda)
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetXY($xLeft, $yCustomer);
    $pdf->Cell(0, 6, $toPdfText('Cliente:'), 0, 1);

    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetXY($xLeft, $yCustomer + 6);
    $pdf->Cell(0, 6, $toPdfText($customerName), 0, 1);
    $pdf->SetXY($xLeft, $yCustomer + 12);
    $pdf->Cell(0, 6, $toPdfText($customerEmail), 0, 1);

    if (trim($customerDni) !== '') {
        $pdf->SetXY($xLeft, $yCustomer + 18);
        $pdf->Cell(0, 6, $toPdfText('DNI: ' . $customerDni), 0, 1);
    }

    // Tabla de items
    $y = $yTable;
    $pdf->SetXY($xLeft, $y);
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetFillColor(245, 247, 250);

    // Anchos de columnas
    // Nota: calculamos todo en enteros (mm) y asignamos el remanente a la última columna
    // para evitar desfasajes visuales por redondeo.
    $usableW = (int)round((float)$size['width'] - ($marginX * 2));
    $colDesc = (int)floor($usableW * 0.60);
    $colQty  = (int)floor($usableW * 0.12);
    $colUnit = (int)floor($usableW * 0.14);
    $colSub  = max(0, $usableW - $colDesc - $colQty - $colUnit);

    $drawTableHeader = static function (setasign\Fpdi\Fpdi $pdf, int $colDesc, int $colQty, int $colUnit, int $colSub, callable $toPdfText): void {
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetFillColor(245, 247, 250);
        $pdf->Cell($colDesc, 8, $toPdfText('Producto'), 1, 0, 'L', true);
        $pdf->Cell($colQty, 8, $toPdfText('Cant.'), 1, 0, 'R', true);
        $pdf->Cell($colUnit, 8, $toPdfText('Precio'), 1, 0, 'R', true);
        $pdf->Cell($colSub, 8, $toPdfText('Subtotal'), 1, 1, 'R', true);
        $pdf->SetFont('Helvetica', '', 10);
    };

    $drawTableHeader($pdf, $colDesc, $colQty, $colUnit, $colSub, $toPdfText);
    foreach ($items as $item) {
        $desc = (string)($item['description'] ?? '');
        $qty = (string)($item['quantity'] ?? '1.00');
        $unit = money_format_cents((int)($item['unit_price_cents'] ?? 0), $currency);
        $sub = money_format_cents((int)($item['line_total_cents'] ?? 0), $currency);

        $y = $pdf->GetY();
        if ($y > ($size['height'] - 40)) {
            // Nueva página usando la misma plantilla
            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($tplId, 0, 0, $size['width'], $size['height'], true);
            if ($debugGrid) {
                $pdf->SetDrawColor(220, 220, 220);
                $pdf->SetTextColor(160, 160, 160);
                $pdf->SetFont('Helvetica', '', 7);
                for ($x = 0; $x <= (int)$size['width']; $x += 10) {
                    $pdf->Line($x, 0, $x, $size['height']);
                    $pdf->SetXY($x + 1, 2);
                    $pdf->Cell(0, 3, (string)$x, 0, 0);
                }
                for ($yLine = 0; $yLine <= (int)$size['height']; $yLine += 10) {
                    $pdf->Line(0, $yLine, $size['width'], $yLine);
                    $pdf->SetXY(2, $yLine + 1);
                    $pdf->Cell(0, 3, (string)$yLine, 0, 0);
                }
                $pdf->SetTextColor(20, 20, 20);
            }
            $pdf->SetXY($xLeft, $yTable);
            $drawTableHeader($pdf, $colDesc, $colQty, $colUnit, $colSub, $toPdfText);
        }

        $pdf->Cell($colDesc, 7, $toPdfText($trimDesc($desc, 60)), 1, 0, 'L');
        $pdf->Cell($colQty, 7, $toPdfText($qty), 1, 0, 'R');
        $pdf->Cell($colUnit, 7, $toPdfText($unit), 1, 0, 'R');
        $pdf->Cell($colSub, 7, $toPdfText($sub), 1, 1, 'R');
    }

    // Total
    $pdf->Ln(4);
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->Cell($colDesc + $colQty + $colUnit, 8, $toPdfText('TOTAL'), 0, 0, 'R');
    $pdf->Cell($colSub, 8, $toPdfText(money_format_cents($totalCents, $currency)), 0, 1, 'R');

    // Detalle
    if ($detail !== '') {
        $pdf->Ln(2);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->MultiCell(0, 5, $toPdfText('Detalle: ' . $detail), 0, 'L');
    }

    $bytes = $pdf->Output('S');

    return [
        'bytes' => $bytes,
        'filename' => 'factura-' . $invoiceId . '.pdf',
        'mime' => 'application/pdf',
        'generator' => 'template-fpdi',
    ];
}
