<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

auth_require_login();

$config = app_config();
$userId = (int)auth_user_id();

$invoiceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($invoiceId <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'ID inválido.';
    exit;
}

try {
    $pdo = db($config);
    $data = invoices_get($pdo, $invoiceId, $userId);
    $download = invoice_build_download($data);
    invoice_send_download($download);
    exit;
} catch (Throwable $e) {
    error_log('[invoice_download_error] ' . get_class($e) . ': ' . $e->getMessage());
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'No se pudo descargar la factura.';
    exit;
}
