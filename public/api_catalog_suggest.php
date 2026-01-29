<?php
// Sugerencias de productos del catálogo para autocompletar en factura.
require_once __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

// Evitar que warnings/notices se impriman como HTML y rompan el JSON.
@ini_set('display_errors', '0');

if (auth_user_id() === null) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado'], JSON_UNESCAPED_UNICODE);
    exit;
}

$config = app_config();
$userId = (int)auth_user_id();

try {
    $pdo = db($config);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo conectar a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!catalog_supports_table($pdo)) {
    echo json_encode(['items' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$q = isset($_GET['q']) ? (string)$_GET['q'] : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$limit = max(1, min(5000, $limit));

try {
    $rows = catalog_list($pdo, $userId, $q, $limit);
    $items = [];
    foreach ($rows as $r) {
        $priceCents = (int)($r['price_cents'] ?? 0);
        $unitKey = '';
        $rawUnit = (string)($r['unit'] ?? '');
        if ($rawUnit !== '') {
            try {
                $unitKey = catalog_normalize_unit($rawUnit);
                if ($unitKey === 'un') {
                    $unitKey = 'u';
                }
            } catch (Throwable $e) {
                $unitKey = '';
            }
        }
        $items[] = [
            'id' => (int)($r['id'] ?? 0),
            'name' => (string)($r['name'] ?? ''),
            'description' => (string)($r['description'] ?? ''),
            'unit' => $unitKey,
            'price_cents' => $priceCents,
            'price' => $priceCents / 100,
            'currency' => (string)($r['currency'] ?? 'ARS'),
        ];
    }

    echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudieron obtener sugerencias'], JSON_UNESCAPED_UNICODE);
}
