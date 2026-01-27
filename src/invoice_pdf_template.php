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
    $customerPhone = (string)($invoice['customer_phone'] ?? '');
    $customerDni = (string)($invoice['customer_dni'] ?? '');
    $customerAddress = (string)($invoice['customer_address'] ?? '');
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

    // La tabla suele venir ya dibujada en el boceto. Usamos un margen propio para
    // calzar dentro del recuadro y evitamos dibujar bordes/rellenos (no duplicar líneas).
    $tableMarginX = 22;
    $xTableLeft = $tableMarginX;
    $tableUsableW = (int)round((float)$size['width'] - ($tableMarginX * 2));

    $tableHeaderH = 8;
    $tableRowH = 8;
    $tableDrawBorders = true;
    $tableBorder = $tableDrawBorders ? 1 : 0;
    $tableHeaderFill = true;

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

    $yLine = $yCustomer + 6;
    $pdf->SetXY($xLeft, $yLine);
    $pdf->Cell(0, 6, $toPdfText($customerName), 0, 1);
    $yLine += 6;

    if (trim($customerPhone) !== '') {
        $pdf->SetXY($xLeft, $yLine);
        $pdf->Cell(0, 6, $toPdfText('Tel: ' . $customerPhone), 0, 1);
        $yLine += 6;
    }

    if (trim($customerEmail) !== '') {
        $pdf->SetXY($xLeft, $yLine);
        $pdf->Cell(0, 6, $toPdfText($customerEmail), 0, 1);
        $yLine += 6;
    }

    if (trim($customerDni) !== '') {
        $pdf->SetXY($xLeft, $yLine);
        $pdf->Cell(0, 6, $toPdfText('DNI: ' . $customerDni), 0, 1);
        $yLine += 6;
    }

    if (trim($customerAddress) !== '') {
        $pdf->SetXY($xLeft, $yLine);
        $pdf->Cell(0, 6, $toPdfText('Domicilio: ' . $customerAddress), 0, 1);
        $yLine += 6;
    }

    // Tabla de items
    $y = $yTable;
    $pdf->SetXY($xTableLeft, $y);
    // Importante: cuando Cell(..., ln=1) salta de línea, FPDF vuelve a X = margen izquierdo.
    // Ajustamos el margen izquierdo al inicio de la tabla para que header y filas queden alineados.
    $pdf->SetLeftMargin($xTableLeft);
    $pdf->SetRightMargin($tableMarginX);
    $pdf->SetX($xTableLeft);
    $pdf->SetFont('Helvetica', 'B', 10);

    // Anchos de columnas dentro del área útil de la tabla.
    // Nota: calculamos todo en enteros (mm) y asignamos el remanente a la última columna
    // para evitar desfasajes visuales por redondeo.
    $colDesc = (int)floor($tableUsableW * 0.70);
    $colQty  = (int)floor($tableUsableW * 0.14);
    $colSub  = max(0, $tableUsableW - $colDesc - $colQty);

    $drawTableHeader = static function (
        setasign\Fpdi\Fpdi $pdf,
        int $border,
        int $h,
        bool $fill,
        int $colDesc,
        int $colQty,
        int $colSub,
        callable $toPdfText
    ): void {
        $pdf->SetFont('Helvetica', 'B', 10);
        if ($fill) {
            $pdf->SetFillColor(245, 247, 250);
        }
        $pdf->Cell($colDesc, $h, $toPdfText('Producto'), $border, 0, 'L', $fill);
        $pdf->Cell($colQty, $h, $toPdfText('Cant.'), $border, 0, 'R', $fill);
        $pdf->Cell($colSub, $h, $toPdfText('Subtotal'), $border, 1, 'R', $fill);
        $pdf->SetFont('Helvetica', '', 10);
    };

    $drawTableHeader($pdf, $tableBorder, $tableHeaderH, $tableHeaderFill, $colDesc, $colQty, $colSub, $toPdfText);

    $formatQty = static function (string $qty): string {
        $qty = trim($qty);
        if ($qty === '') {
            return '0';
        }
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
        $desc = $capitalizeFirst((string)($item['description'] ?? ''));
        $qtyRaw = (string)($item['quantity'] ?? '1.00');
        $unitRaw = (string)($item['unit'] ?? '');
        $qty = $formatQty($qtyRaw);
        if ($unitRaw !== '') {
            $qty .= ' ' . $unitLabel($unitRaw);
        }
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
            $pdf->SetXY($xTableLeft, $yTable);
            $pdf->SetLeftMargin($xTableLeft);
            $pdf->SetRightMargin($tableMarginX);
            $pdf->SetX($xTableLeft);
            $drawTableHeader($pdf, $tableBorder, $tableHeaderH, $tableHeaderFill, $colDesc, $colQty, $colSub, $toPdfText);
        }

        $pdf->SetX($xTableLeft);
        $pdf->Cell($colDesc, $tableRowH, $toPdfText($trimDesc($desc, 60)), $tableBorder, 0, 'L');
        $pdf->Cell($colQty, $tableRowH, $toPdfText($qty), $tableBorder, 0, 'R');
        $pdf->Cell($colSub, $tableRowH, $toPdfText($sub), $tableBorder, 1, 'R');
    }

    // Total
    $pdf->Ln(4);
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->Cell($colDesc + $colQty, 8, $toPdfText('TOTAL'), 0, 0, 'R');
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
