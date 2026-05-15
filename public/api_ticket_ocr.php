<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
@ini_set('display_errors', '0');

if (auth_user_id() === null) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autorizado'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metodo no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$token = (string)($_POST['csrf_token'] ?? '');
if (!csrf_verify($token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sesion invalida. Recarga e intenta de nuevo.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_FILES['ticket_image']) || !is_array($_FILES['ticket_image'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Falta la foto del ticket.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$file = $_FILES['ticket_image'];
$err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
if ($err !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No se pudo subir la imagen.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tmp = (string)($file['tmp_name'] ?? '');
$size = (int)($file['size'] ?? 0);

if ($tmp === '' || !is_uploaded_file($tmp)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Imagen invalida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($size <= 0 || $size > 10 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'La imagen debe pesar menos de 10 MB.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$info = @getimagesize($tmp);
$mime = is_array($info) && isset($info['mime']) ? (string)$info['mime'] : (string)($file['type'] ?? '');
if ($mime !== '' && str_contains($mime, ';')) {
    $mime = trim(explode(';', $mime, 2)[0]);
}

$allowed = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($mime, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Formato no soportado. Usa JPG, PNG o WebP.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$config = app_config();
$userId = (int)auth_user_id();

try {
    $ticket = ticket_ocr_extract_openai($config, $tmp, $mime);

    try {
        $pdo = db($config);
        $ticket = ticket_ocr_enrich_with_catalog($pdo, $userId, $ticket);
    } catch (Throwable $e) {
        $ticket['warnings'][] = 'No se pudo contrastar el PLU con el catalogo.';
    }

    if (count($ticket['items'] ?? []) === 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'No se detectaron productos en el ticket.', 'ticket' => $ticket], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok' => true, 'ticket' => $ticket], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('api_ticket_ocr.php error: ' . $e->getMessage());

    $env = (string)($config['app']['env'] ?? 'production');
    $rawMsg = (string)$e->getMessage();

    $safeMsg = 'No se pudo leer el ticket.';
    if (stripos($rawMsg, 'OPENAI_API_KEY') !== false) {
        $safeMsg = 'OCR no configurado (falta OPENAI_API_KEY).';
    } elseif (stripos($rawMsg, 'cURL no disponible') !== false) {
        $safeMsg = 'Tu hosting no permite OCR server-side (cURL no disponible).';
    }

    $msg = $env === 'production' ? $safeMsg : ('Error: ' . $rawMsg);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
}
