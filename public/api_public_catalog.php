<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$config = app_config();

try {
    if (((string)($config['public_catalog']['enabled'] ?? '1')) === '0') {
        http_response_code(404);
        exit;
    }

    $pdo = db($config);

    if (!catalog_supports_table($pdo)) {
        throw new RuntimeException('No se encontró la tabla del catálogo.');
    }

    $ownerId = orders_public_catalog_owner_id($pdo, $config);

    $q = trim((string)($_GET['q'] ?? ''));
    $items = catalog_list($pdo, $ownerId, $q, 500);

    $out = [];
    foreach ($items as $r) {
        $unit = trim((string)($r['unit'] ?? ''));
        $imagePath = trim((string)($r['image_path'] ?? ''));
        $priceFormatted = money_format_cents((int)($r['price_cents'] ?? 0), (string)($r['currency'] ?? 'ARS'));
        $out[] = [
            'id' => (int)($r['id'] ?? 0),
            'name' => (string)($r['name'] ?? ''),
            'description' => (string)($r['description'] ?? ''),
            'image_path' => $imagePath,
            'image_url' => $imagePath !== '' ? catalog_image_url($imagePath) : '',
            'unit' => $unit,
            'price_cents' => (int)($r['price_cents'] ?? 0),
            'currency' => (string)($r['currency'] ?? 'ARS'),
            'price_formatted' => $priceFormatted,
            'price_label' => $priceFormatted . ($unit !== '' ? (' / ' . $unit) : ''),
        ];
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'items' => $out], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    error_log('api_public_catalog.php error: ' . $e->getMessage());
    $msg = (($config['app']['env'] ?? 'production') === 'production')
        ? 'No se pudo cargar la lista de precios.'
        : ('Error: ' . $e->getMessage());

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
