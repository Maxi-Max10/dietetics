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
    $ts = date('Ymd-His');

    try {
        $html = invoice_render_html($data);
    } catch (Throwable $e) {
        $html = '<h1>Error</h1><pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
    }

    // Si existe la plantilla, preferimos exigir ese camino para evitar confusiones
    // (en hosting suele faltar vendor/ y se cae silenciosamente a Dompdf/HTML).
    $templatePath = __DIR__ . DIRECTORY_SEPARATOR . 'pdf' . DIRECTORY_SEPARATOR . 'boceto.pdf';
    $hasTemplateFile = is_file($templatePath);
    $autoloadPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    $hasAutoload = is_file($autoloadPath);
    $hasFpdiClass = class_exists('setasign\\Fpdi\\Fpdi');

    // PDF usando plantilla (FPDI) si está disponible.
    if (function_exists('invoice_build_pdf_from_template')) {
        try {
            $download = invoice_build_pdf_from_template($data);
            // Forzar nombre único para evitar caché del navegador
            $download['filename'] = 'factura-' . $invoiceId . '-' . $ts . '.pdf';
            $download['generator'] = $download['generator'] ?? 'template-fpdi';
            return $download;
        } catch (Throwable $e) {
            error_log('Invoice template PDF error: ' . $e->getMessage());

            // Si la plantilla existe, no sigas con otros métodos: devolvé un error claro.
            if ($hasTemplateFile) {
                $msg = "No se pudo generar el PDF con la plantilla.\n\n" .
                    "Causas probables:\n" .
                    "- Falta instalar/subir dependencias de Composer (FPDI).\n" .
                    "- El hosting tiene una carpeta vendor/ pero NO incluye setasign/fpdi (vendor\\setasign\\...).\n\n" .
                    "Checklist rápido:\n" .
                    "- Plantilla existe: " . ($hasTemplateFile ? 'SI' : 'NO') . " (" . $templatePath . ")\n" .
                    "- Autoload existe: " . ($hasAutoload ? 'SI' : 'NO') . " (" . $autoloadPath . ")\n" .
                    "- Clase FPDI cargada (setasign\\Fpdi\\Fpdi): " . ($hasFpdiClass ? 'SI' : 'NO') . "\n\n" .
                    "Solución:\n" .
                    "1) Ejecutar 'composer install --no-dev --optimize-autoloader' en el proyecto\n" .
                    "2) Subir la carpeta vendor/ generada (completa) al mismo nivel que src/\n";
                return [
                    'bytes' => "<h1>Error de PDF (plantilla)</h1><pre>" . htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</pre>",
                    'filename' => 'factura-' . $invoiceId . '-' . $ts . '-ERROR.html',
                    'mime' => 'text/html; charset=UTF-8',
                    'generator' => 'template-error',
                ];
            }

            // Si no hay plantilla, continúa a otros métodos.
        }
    }

    // PDF si existe Dompdf
    if (class_exists('Dompdf\\Dompdf')) {
        try {
            // Render especial para PDF: usar rutas locales para assets (logo).
            $htmlPdf = invoice_render_html($data, ['asset_mode' => 'file']);

            $options = new Dompdf\Options();
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);
            // Permite leer archivos locales dentro del proyecto.
            $options->setChroot(dirname(__DIR__));

            $dompdf = new Dompdf\Dompdf($options);
            $dompdf->loadHtml($htmlPdf, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $output = $dompdf->output();

            return [
                'bytes' => $output,
                'filename' => 'factura-' . $invoiceId . '-' . $ts . '.pdf',
                'mime' => 'application/pdf',
                'generator' => 'dompdf',
            ];
        } catch (Throwable $e) {
            error_log('Invoice dompdf error: ' . $e->getMessage());
            // Fall back a HTML
        }
    }

    // Fallback: HTML descargable
    return [
        'bytes' => $html,
        'filename' => 'factura-' . $invoiceId . '-' . $ts . '.html',
        'mime' => 'text/html; charset=UTF-8',
        'generator' => 'html',
    ];
}

function invoice_send_download(array $download): void
{
    // Evita que el navegador cachee descargas y muestre un PDF viejo.
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: 0');

    header('Content-Type: ' . $download['mime']);
    header('Content-Disposition: attachment; filename="' . $download['filename'] . '"');
    header('X-Content-Type-Options: nosniff');
    if (!empty($download['generator'])) {
        header('X-Invoice-Generator: ' . (string)$download['generator']);
    }
    echo $download['bytes'];
}
