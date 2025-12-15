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

    // PDF si existe Dompdf
    if (class_exists('Dompdf\\Dompdf')) {
        $dompdf = new Dompdf\Dompdf([
            'isRemoteEnabled' => true,
        ]);
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
