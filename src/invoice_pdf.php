<?php

declare(strict_types=1);

/**
 * Devuelve un array con:
 * - bytes: string
 * - filename: string
 * - mime: string
 *
 * Si Dompdf está instalado, genera PDF. Si no, devuelve HTML descargable.
 */
function invoice_build_download(array $data): array
{
    $invoiceId = (int)($data['invoice']['id'] ?? 0);
    $html = invoice_render_html($data);

    // PDF usando plantilla (FPDI) si está disponible.
    if (function_exists('invoice_build_pdf_from_template')) {
        try {
            return invoice_build_pdf_from_template($data);
        } catch (Throwable $e) {
            error_log('Invoice template PDF error: ' . $e->getMessage());
            // Continúa a otros métodos
        }
    }

    // PDF si existe Dompdf
    if (class_exists('Dompdf\\Dompdf')) {
        // Render especial para PDF: usar rutas locales para assets (logo).
        $html = invoice_render_html($data, ['asset_mode' => 'file']);

        $options = new Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        // Permite leer archivos locales dentro del proyecto.
        $options->setChroot(dirname(__DIR__));

        $dompdf = new Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $output = $dompdf->output();

        return [
            'bytes' => $output,
            'filename' => 'factura-' . $invoiceId . '.pdf',
            'mime' => 'application/pdf',
        ];
    }

    // Fallback: HTML descargable
    return [
        'bytes' => $html,
        'filename' => 'factura-' . $invoiceId . '.html',
        'mime' => 'text/html; charset=UTF-8',
    ];
}

function invoice_send_download(array $download): void
{
    header('Content-Type: ' . $download['mime']);
    header('Content-Disposition: attachment; filename="' . $download['filename'] . '"');
    header('X-Content-Type-Options: nosniff');
    echo $download['bytes'];
}
