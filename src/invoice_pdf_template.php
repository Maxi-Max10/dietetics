<?php

declare(strict_types=1);

/**
 * Genera un PDF usando `src/pdf/boceto.pdf` como fondo y escribiendo encima.
 * Requiere FPDI (setasign/fpdi) instalado via Composer.
 */
function invoice_build_pdf_from_template(array $data): array
{
    if (!class_exists('setasign\\Fpdi\\Fpdi')) {
        throw new RuntimeException('FPDI no está instalado.');
    }

    $invoice = $data['invoice'];
    $items = $data['items'];

    $invoiceId = (int)($invoice['id'] ?? 0);
    $customerName = (string)($invoice['customer_name'] ?? '');
    $customerEmail = (string)($invoice['customer_email'] ?? '');
    $detail = (string)($invoice['detail'] ?? '');
    $currency = (string)($invoice['currency'] ?? 'ARS');
    $totalCents = (int)($invoice['total_cents'] ?? 0);
    $createdAt = (string)($invoice['created_at'] ?? '');

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

    // Coordenadas (mm) - AJUSTAR según tu boceto.pdf
    // Tip: en FPDI/FPDF el origen (0,0) es arriba-izquierda.
    $xLeft = 18;
    $xRight = $size['width'] - 18;

    // Encabezado
    $pdf->SetFont('Helvetica', 'B', 14);
    $pdf->SetTextColor(20, 20, 20);
    $pdf->SetXY($xLeft, 20);
    $pdf->Cell(0, 7, 'Factura #' . $invoiceId, 0, 1);

    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetXY($xLeft, 28);
    $pdf->Cell(0, 6, 'Fecha: ' . $createdAt, 0, 1);

    // Cliente
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetXY($xLeft, 38);
    $pdf->Cell(0, 6, 'Cliente:', 0, 1);

    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetXY($xLeft, 44);
    $pdf->Cell(0, 6, $customerName, 0, 1);
    $pdf->SetXY($xLeft, 50);
    $pdf->Cell(0, 6, $customerEmail, 0, 1);

    // Tabla de items
    $y = 62;
    $pdf->SetXY($xLeft, $y);
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetFillColor(245, 247, 250);

    $colDesc = 100;
    $colQty = 25;
    $colUnit = 30;
    $colSub = 30;

    $pdf->Cell($colDesc, 8, 'Producto', 1, 0, 'L', true);
    $pdf->Cell($colQty, 8, 'Cant.', 1, 0, 'R', true);
    $pdf->Cell($colUnit, 8, 'Precio', 1, 0, 'R', true);
    $pdf->Cell($colSub, 8, 'Subtotal', 1, 1, 'R', true);

    $pdf->SetFont('Helvetica', '', 10);
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
            $pdf->SetFont('Helvetica', '', 10);
        }

        $pdf->Cell($colDesc, 7, mb_strimwidth($desc, 0, 60, '…', 'UTF-8'), 1, 0, 'L');
        $pdf->Cell($colQty, 7, $qty, 1, 0, 'R');
        $pdf->Cell($colUnit, 7, $unit, 1, 0, 'R');
        $pdf->Cell($colSub, 7, $sub, 1, 1, 'R');
    }

    // Total
    $pdf->Ln(4);
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->Cell($colDesc + $colQty + $colUnit, 8, 'TOTAL', 0, 0, 'R');
    $pdf->Cell($colSub, 8, money_format_cents($totalCents, $currency), 0, 1, 'R');

    // Detalle
    if ($detail !== '') {
        $pdf->Ln(2);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->MultiCell(0, 5, 'Detalle: ' . $detail, 0, 'L');
    }

    $bytes = $pdf->Output('S');

    return [
        'bytes' => $bytes,
        'filename' => 'factura-' . $invoiceId . '.pdf',
        'mime' => 'application/pdf',
    ];
}
