<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$config = app_config();

try {
    if (((string)($config['public_catalog']['enabled'] ?? '1')) === '0') {
        http_response_code(404);
        exit;
    }

    $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
    $wantsJson = (stripos($accept, 'application/json') !== false) || ((string)($_GET['ajax'] ?? '')) === '1' || ((string)($_POST['ajax'] ?? '')) === '1';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit;
    }

    $contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');
    $jsonBody = [];
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (is_array($decoded)) {
            $jsonBody = $decoded;
        }
    }

    $data = array_merge($_POST, $jsonBody);

    $token = (string)($data['csrf_token'] ?? '');
    if (!csrf_verify($token)) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Sesión inválida. Recargá e intentá de nuevo.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $customerName = (string)($data['customer_name'] ?? '');
    $customerPhone = (string)($data['customer_phone'] ?? '');
    $customerEmail = (string)($data['customer_email'] ?? '');
    $customerDni = (string)($data['customer_dni'] ?? '');
    $customerAddress = (string)($data['customer_address'] ?? '');
    $notes = (string)($data['notes'] ?? '');

    $items = $data['items'] ?? [];
    if (!is_array($items)) {
        $items = [];
    }

    $pdo = db($config);

    $ownerId = orders_public_catalog_owner_id($pdo, $config);
    $res = orders_create_public($pdo, $ownerId, $customerName, $customerPhone, $customerAddress, $notes, $items, $customerEmail, $customerDni);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'order_id' => (int)($res['order_id'] ?? 0),
        'total_cents' => (int)($res['total_cents'] ?? 0),
        'currency' => (string)($res['currency'] ?? 'ARS'),
        'total_formatted' => money_format_cents((int)($res['total_cents'] ?? 0), (string)($res['currency'] ?? 'ARS')),
        'message' => 'Pedido enviado. Te contactamos para confirmar.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    error_log('api_public_order.php error: ' . $e->getMessage());

    $rawMsg = $e->getMessage();
    $msg = (($config['app']['env'] ?? 'production') === 'production')
        ? 'No se pudo crear el pedido.'
        : ('Error: ' . $rawMsg);

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
