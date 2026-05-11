<?php
// Lookup de producto a partir del codigo de barras EAN-13 de balanza.
// Formato Systel/Cuora por item: 20/21 + PPPP (PLU, 4 digitos) + IIIIII (importe con 2 decimales) + C.
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

// Debe ser exactamente 13 digitos y usar un prefijo de item de balanza.
if (!preg_match('/^\d{13}$/', $barcode)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'El codigo no es una etiqueta de balanza valida (EAN-13 de 13 digitos)'], JSON_UNESCAPED_UNICODE);
    exit;
}

$prefix = substr($barcode, 0, 2);
if ($prefix === '22') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Ese codigo es el total del ticket y no trae PLU de producto. Escanea el codigo del item.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!in_array($prefix, ['20', '21'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'El codigo no usa prefijo de item de balanza esperado (20 o 21).'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Extraer ID de producto y precio total codificados.
// Qendra/balanza usa 6 digitos para importe con 2 decimales: 005940 => $59,40.
$productId  = (int) substr($barcode, 2, 4); // digitos 3-6
$priceCents = (int) substr($barcode, 6, 6); // digitos 7-12

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
