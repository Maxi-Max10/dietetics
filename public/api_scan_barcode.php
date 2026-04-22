<?php
// Lookup de producto a partir del código de barras EAN-13 de balanza.
// Formato: 20 + PPPPP (ID producto, 5 dígitos) + XXXXX (precio total en centavos, 5 dígitos) + C (verificador)
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

// Debe ser exactamente 13 dígitos y empezar con "2" (prefijo de balanza)
if (!preg_match('/^\d{13}$/', $barcode) || $barcode[0] !== '2') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'El código no es una etiqueta de balanza válida (EAN-13 con prefijo 2)'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Extraer ID de producto y precio total codificados
$productId  = (int) substr($barcode, 2, 5); // dígitos 3-7
$priceCents = (int) substr($barcode, 7, 5); // dígitos 8-12

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
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error al buscar el producto'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($product === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => "PLU {$productId} no encontrado en el catálogo"], JSON_UNESCAPED_UNICODE);
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
