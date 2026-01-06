<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
@ini_set('display_errors', '0');

if (auth_user_id() === null) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autorizado'], JSON_UNESCAPED_UNICODE);
    exit;
}

$config = app_config();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$token = (string)($_POST['csrf_token'] ?? '');
if (!csrf_verify($token)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Sesión inválida. Recargá e intentá de nuevo.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_FILES['audio']) || !is_array($_FILES['audio'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Falta audio'], JSON_UNESCAPED_UNICODE);
    exit;
}

$file = $_FILES['audio'];
$err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
if ($err !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No se pudo subir el audio'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tmp = (string)($file['tmp_name'] ?? '');
$mime = (string)($file['type'] ?? 'audio/webm');
$size = (int)($file['size'] ?? 0);

if ($tmp === '' || !is_uploaded_file($tmp)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Audio inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($size <= 0 || $size > 12 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'El audio es demasiado grande'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $text = speech_transcribe_openai($config, $tmp, $mime, 'es');
    echo json_encode(['ok' => true, 'text' => $text], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('api_speech_to_text.php error: ' . $e->getMessage());
    $msg = ($config['app']['env'] ?? 'production') === 'production'
        ? 'No se pudo transcribir el audio.'
        : ('Error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
}
