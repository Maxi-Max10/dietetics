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

function ticket_ocr_safe_error(string $rawMsg): string
{
    $msg = strtolower($rawMsg);

    if (str_contains($msg, 'gemini_api_key')) {
        return 'OCR con Gemini no configurado (falta GEMINI_API_KEY).';
    }
    if (str_contains($msg, 'api key not valid') || str_contains($msg, 'api_key_invalid') || str_contains($msg, 'permission_denied')) {
        return 'La API key de Gemini no es valida o no tiene permisos. Revisa GEMINI_API_KEY.';
    }
    if (str_contains($msg, 'generativelanguage.googleapis.com') || str_contains($msg, 'no hubo respuesta de gemini')) {
        return 'El hosting no pudo conectarse con Gemini. Revisa conectividad saliente/Firewall del hosting.';
    }
    if (str_contains($msg, 'gemini') && (str_contains($msg, 'model') || str_contains($msg, 'not found') || str_contains($msg, 'not supported'))) {
        return 'El modelo Gemini configurado no esta disponible. Proba GEMINI_TICKET_OCR_MODEL=gemini-2.5-flash.';
    }
    if (str_contains($msg, 'requested entity was not found')) {
        return 'El modelo Gemini configurado no existe o no esta disponible para tu API key. Proba GEMINI_TICKET_OCR_MODEL=gemini-2.5-flash.';
    }
    if (str_contains($msg, 'unknown name') || str_contains($msg, 'invalid json payload') || str_contains($msg, 'responseformat') || str_contains($msg, 'responsemimetype') || str_contains($msg, 'responseschema')) {
        return 'Gemini rechazo el formato del pedido OCR. Actualiza los archivos del OCR y proba de nuevo.';
    }
    if (str_contains($msg, 'gemini') && (str_contains($msg, 'quota') || str_contains($msg, 'billing') || str_contains($msg, 'resource_exhausted'))) {
        return 'La cuenta de Gemini no tiene credito/cupo disponible para usar OCR.';
    }
    if (str_contains($msg, 'gemini ocr fallo')) {
        return 'Gemini rechazo el pedido OCR luego de varios reintentos. Proba GEMINI_TICKET_OCR_MODEL=gemini-2.5-flash o revisa error_log.';
    }
    if (str_contains($msg, 'gemini devolvio') || str_contains($msg, 'gemini no devolvio')) {
        return 'Gemini no pudo devolver una lectura usable. Proba con una foto mas nitida y centrada.';
    }
    if (str_contains($msg, 'openai_api_key')) {
        return 'No hay proveedor OCR configurado. Agrega GEMINI_API_KEY o OPENAI_API_KEY.';
    }
    if (str_contains($msg, 'incorrect api key') || str_contains($msg, 'invalid api key') || str_contains($msg, 'invalid_api_key')) {
        return 'La API key de OpenAI no es valida. Revisa OPENAI_API_KEY en config.local.php.';
    }
    if (str_contains($msg, 'insufficient_quota') || str_contains($msg, 'quota') || str_contains($msg, 'billing') || str_contains($msg, 'credit')) {
        return 'La cuenta de OpenAI no tiene credito/cupo disponible para usar OCR.';
    }
    if (str_contains($msg, 'model') && (str_contains($msg, 'not found') || str_contains($msg, 'does not exist') || str_contains($msg, 'access'))) {
        return 'El modelo de OCR configurado no esta disponible para esa cuenta. Proba OPENAI_TICKET_OCR_MODEL=gpt-4o-mini.';
    }
    if (str_contains($msg, 'rate limit') || str_contains($msg, 'rate_limit')) {
        return 'OpenAI limito temporalmente las consultas. Espera un momento y proba de nuevo.';
    }
    if (str_contains($msg, 'curl no disponible')) {
        return 'Tu hosting no permite OCR server-side (cURL no disponible).';
    }
    if (str_contains($msg, 'could not resolve host') || str_contains($msg, 'failed to connect') || str_contains($msg, 'timed out') || str_contains($msg, 'operation timed out')) {
        return 'El hosting no pudo conectarse con OpenAI. Revisa conectividad saliente/Firewall del hosting.';
    }
    if (str_contains($msg, 'certificate') || str_contains($msg, 'ssl')) {
        return 'El hosting no pudo validar la conexion SSL con OpenAI. Revisa certificados/cURL del servidor.';
    }
    if (str_contains($msg, 'image') && (str_contains($msg, 'too large') || str_contains($msg, 'size'))) {
        return 'La imagen es demasiado grande para OCR. Proba con una foto mas liviana.';
    }
    if (str_contains($msg, 'json_schema') || str_contains($msg, 'schema') || str_contains($msg, 'response_format')) {
        return 'El pedido de OCR no fue aceptado por OpenAI por el formato de respuesta. Actualiza el codigo del OCR.';
    }
    if (str_contains($msg, 'no devolvio texto') || str_contains($msg, 'json valido')) {
        return 'La IA no pudo devolver una lectura usable. Proba con una foto mas nítida y centrada.';
    }

    return 'No se pudo leer el ticket. Revisa el error_log del hosting para ver el detalle.';
}

try {
    $ticket = ticket_ocr_extract($config, $tmp, $mime);

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

    $safeMsg = ticket_ocr_safe_error($rawMsg);

    $msg = $env === 'production' ? $safeMsg : ('Error: ' . $rawMsg);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
}
