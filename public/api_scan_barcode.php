<?php
// Lookup de producto a partir del codigo de barras EAN-13 de balanza.
// La balanza usada en el local imprime importes en pesos enteros: 003640 => $3640.
require_once __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

@ini_set('display_errors', '0');

function scale_amount_to_cents(string $digits): int
{
    return max(0, (int)$digits) * 100;
}

/**
 * @return array{type:string,candidates?:array<int,array{product_id:int,price_cents:int,format:string}>,price_cents?:int,format:string}|null
 */
function scale_parse_barcode(string $barcode): ?array
{
    $prefix = substr($barcode, 0, 2);

    if (in_array($prefix, ['20', '21'], true)) {
        return [
            'type' => 'item',
            'format' => 'prefix_20_21_plu4_amount6',
            'candidates' => [[
                'product_id' => (int)substr($barcode, 2, 4),
                'price_cents' => scale_amount_to_cents(substr($barcode, 6, 6)),
                'format' => 'prefix_20_21_plu4_amount6',
            ]],
        ];
    }

    if ($prefix === '22') {
        return [
            'type' => 'ticket_total',
            'format' => 'prefix_22_ticket_total',
            'price_cents' => scale_amount_to_cents(substr($barcode, 6, 6)),
        ];
    }

    if ($barcode[0] === '0') {
        return [
            'type' => 'item',
            'format' => 'leading_zero_item',
            'candidates' => [
                [
                    'product_id' => (int)substr($barcode, 1, 3),
                    'price_cents' => scale_amount_to_cents(substr($barcode, 4, 8)),
                    'format' => 'leading_zero_plu3_amount8',
                ],
                [
                    'product_id' => (int)substr($barcode, 1, 4),
                    'price_cents' => scale_amount_to_cents(substr($barcode, 5, 7)),
                    'format' => 'leading_zero_plu4_amount7',
                ],
                [
                    'product_id' => (int)substr($barcode, 1, 5),
                    'price_cents' => scale_amount_to_cents(substr($barcode, 6, 6)),
                    'format' => 'leading_zero_plu5_amount6',
                ],
            ],
        ];
    }

    return null;
}

if (auth_user_id() === null) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autorizado'], JSON_UNESCAPED_UNICODE);
    exit;
}

$barcodeRaw = isset($_GET['barcode']) ? trim((string)$_GET['barcode']) : '';
$barcode = preg_replace('/\D+/', '', $barcodeRaw);
$barcode = is_string($barcode) ? $barcode : '';

if (!preg_match('/^\d{13}$/', $barcode)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'El codigo no es una etiqueta de balanza valida (EAN-13 de 13 digitos)'], JSON_UNESCAPED_UNICODE);
    exit;
}

$parsed = scale_parse_barcode($barcode);
if ($parsed === null) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'El codigo no usa un formato de balanza reconocido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($parsed['type'] ?? '') === 'ticket_total') {
    $priceCents = (int)($parsed['price_cents'] ?? 0);
    if ($priceCents <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'El total del ticket de balanza es invalido'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'ok'                  => true,
        'product_id'          => null,
        'name'                => 'Ticket balanza',
        'unit'                => 'u',
        'price_cents'         => $priceCents,
        'price'               => round($priceCents / 100, 2),
        'catalog_price_cents' => 0,
        'catalog_price'       => 0,
        'currency'            => 'ARS',
        'barcode_type'        => 'ticket_total',
        'barcode_format'      => (string)($parsed['format'] ?? ''),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$config = app_config();
$userId = (int) auth_user_id();

try {
    $pdo = db($config);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error de conexion a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!catalog_supports_table($pdo)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Catalogo no disponible'], JSON_UNESCAPED_UNICODE);
    exit;
}

$product = null;
$productId = 0;
$priceCents = 0;
$barcodeFormat = (string)($parsed['format'] ?? '');
$triedPlus = [];

foreach (($parsed['candidates'] ?? []) as $candidate) {
    $candidateProductId = (int)($candidate['product_id'] ?? 0);
    $candidatePriceCents = (int)($candidate['price_cents'] ?? 0);
    if ($candidateProductId <= 0 || $candidatePriceCents <= 0) {
        continue;
    }

    $triedPlus[] = $candidateProductId;

    try {
        $product = catalog_get($pdo, $userId, $candidateProductId);
        $productId = $candidateProductId;
        $priceCents = $candidatePriceCents;
        $barcodeFormat = (string)($candidate['format'] ?? $barcodeFormat);
        break;
    } catch (Throwable $e) {
        if (!($e instanceof InvalidArgumentException) && $e->getMessage() !== 'Producto no encontrado.') {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Error al buscar el producto'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

if (!is_array($product)) {
    $triedPlus = array_values(array_unique($triedPlus));
    $label = count($triedPlus) > 0 ? implode(', ', $triedPlus) : 'desconocido';
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => "PLU {$label} no encontrado en el catalogo"], JSON_UNESCAPED_UNICODE);
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
    'price_cents'         => $priceCents,
    'price'               => round($priceCents / 100, 2),
    'catalog_price_cents' => $catalogPriceCents,
    'catalog_price'       => round($catalogPriceCents / 100, 2),
    'currency'            => (string) ($product['currency'] ?? 'ARS'),
    'barcode_type'        => 'item',
    'barcode_format'      => $barcodeFormat,
], JSON_UNESCAPED_UNICODE);
