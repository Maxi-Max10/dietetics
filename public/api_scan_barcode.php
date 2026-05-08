<?php
// Lookup de producto a partir del codigo de barras EAN-13 de balanza.
// Formato: 20-29/02 + PPPPP (PLU/catalog_products.id, 5 digitos) + XXXXX (precio total) + C (verificador)
require_once __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

@ini_set('display_errors', '0');

if (auth_user_id() === null) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autorizado'], JSON_UNESCAPED_UNICODE);
    exit;
}

$barcode = isset($_GET['barcode']) ? trim((string)$_GET['barcode']) : '';
$mode = isset($_GET['mode']) ? strtolower(trim((string)$_GET['mode'])) : '';

// Debe ser exactamente 13 digitos y usar un prefijo tipico de balanza.
if (!preg_match('/^\d{13}$/', $barcode) || (!str_starts_with($barcode, '02') && $barcode[0] !== '2')) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'El codigo no es una etiqueta de balanza valida (EAN-13 con prefijo 2 o 02)'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Extraer ID de producto y precio total codificados
$productId  = (int) substr($barcode, 2, 5); // digitos 3-7
$priceCents = (int) substr($barcode, 7, 5); // digitos 8-12

$config = app_config();
$userId = (int) auth_user_id();

try {
    $pdo = db($config);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!catalog_supports_table($pdo)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Catálogo no disponible'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $product = catalog_get($pdo, $userId, $productId);
} catch (Throwable $e) {
    if (!($e instanceof InvalidArgumentException) && $e->getMessage() !== 'Producto no encontrado.') {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Error al buscar el producto'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($mode === 'caja' && $priceCents > 0) {
        echo json_encode([
            'ok'                  => true,
            'source'              => 'scale_ticket_total',
            'product_id'          => 0,
            'name'                => 'Ticket balanza',
            'unit'                => 'u',
            'price_cents'         => $priceCents,
            'price'               => round($priceCents / 100, 2),
            'catalog_price_cents' => 0,
            'catalog_price'       => 0,
            'currency'            => 'ARS',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => "PLU {$productId} no encontrado en el catalogo"], JSON_UNESCAPED_UNICODE);
    exit;
}

$unitKey = 'u';
$rawUnit = (string)($product['unit'] ?? '');
if ($rawUnit !== '') {
    try {
        $unitKey = catalog_normalize_unit($rawUnit);
        if ($unitKey === 'un') {
            $unitKey = 'u';
        }
    } catch (Throwable $e) {
        $unitKey = 'u';
    }
}

$catalogPriceCents = (int)($product['price_cents'] ?? 0);

echo json_encode([
    'ok'                  => true,
    'product_id'          => (int) $product['id'],
    'name'                => (string) $product['name'],
    'unit'                => $unitKey,
    'price_cents'         => $priceCents,           // importe total del barcode
    'price'               => round($priceCents / 100, 2),
    'catalog_price_cents' => $catalogPriceCents,    // precio base del catálogo (por kg/l/u)
    'catalog_price'       => round($catalogPriceCents / 100, 2),
    'currency'            => (string) ($product['currency'] ?? 'ARS'),
], JSON_UNESCAPED_UNICODE);
