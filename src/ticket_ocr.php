<?php

declare(strict_types=1);

/**
 * OCR/IA para tickets de balanza.
 */

function ticket_ocr_openai_api_key(array $config): string
{
    $key = (string)($config['ticket_ocr']['openai_api_key'] ?? '');
    if ($key === '') {
        $key = (string)($config['speech']['openai_api_key'] ?? '');
    }
    if ($key === '') {
        throw new RuntimeException('Falta OPENAI_API_KEY en config.');
    }
    return $key;
}

function ticket_ocr_openai_model(array $config): string
{
    $model = (string)($config['ticket_ocr']['openai_model'] ?? '');
    return $model !== '' ? $model : 'gpt-4.1-mini';
}

function ticket_ocr_gemini_api_key(array $config): string
{
    $key = ticket_ocr_clean_api_key((string)($config['ticket_ocr']['gemini_api_key'] ?? ''));
    if ($key === '') {
        foreach (['GEMINI_API_KEY', 'GOOGLE_API_KEY', 'GOOGLE_AI_API_KEY', 'GOOGLE_GENAI_API_KEY'] as $name) {
            $candidate = getenv($name);
            if (!is_string($candidate) || $candidate === '') {
                $candidate = defined($name) ? (string)constant($name) : '';
            }
            $candidate = ticket_ocr_clean_api_key($candidate);
            if ($candidate !== '') {
                $key = $candidate;
                break;
            }
        }
    }
    if ($key === '') {
        throw new RuntimeException('Falta GEMINI_API_KEY en config.');
    }
    if (str_starts_with($key, '{') || str_contains($key, '-----BEGIN')) {
        throw new RuntimeException('GEMINI_API_KEY no debe ser JSON de service account ni certificado; usa una API key de Google AI Studio.');
    }
    return $key;
}

function ticket_ocr_clean_api_key(string $key): string
{
    $key = trim($key);
    $key = trim($key, " \t\n\r\0\x0B\"'");
    if (stripos($key, 'Bearer ') === 0) {
        $key = trim(substr($key, 7));
    }
    if (str_contains($key, '=')) {
        $parts = explode('=', $key);
        $last = trim((string)end($parts));
        if ($last !== '') {
            $key = $last;
        }
    }
    return trim($key);
}

function ticket_ocr_mask_key(string $key): string
{
    $key = ticket_ocr_clean_api_key($key);
    $len = strlen($key);
    if ($len === 0) {
        return '';
    }
    if ($len <= 8) {
        return str_repeat('*', $len);
    }
    return substr($key, 0, 4) . str_repeat('*', max(4, $len - 8)) . substr($key, -4);
}

function ticket_ocr_gemini_model(array $config): string
{
    $model = (string)($config['ticket_ocr']['gemini_model'] ?? '');
    return $model !== '' ? $model : 'gemini-3-flash-preview';
}

function ticket_ocr_extract(array $config, string $filePath, string $mimeType): array
{
    return ticket_ocr_extract_gemini($config, $filePath, $mimeType);
}

function ticket_ocr_schema(): array
{
    return [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['sale_date', 'sale_time', 'items', 'confidence', 'warnings', 'raw_text'],
        'properties' => [
            'sale_date' => [
                'type' => 'string',
                'description' => 'Fecha de venta en formato YYYY-MM-DD. Vacio si no se ve.',
            ],
            'sale_time' => [
                'type' => 'string',
                'description' => 'Hora de venta en formato HH:MM:SS. Vacio si no se ve.',
            ],
            'items' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['plu', 'cantidad', 'unidad', 'confidence'],
                    'properties' => [
                        'plu' => [
                            'type' => 'string',
                            'description' => 'Codigo PLU numerico tomado solo del numero entre corchetes al inicio del articulo. Vacio si no se ve.',
                        ],
                        'cantidad' => [
                            'type' => 'number',
                            'description' => 'Cantidad vendida impresa como peso o unidad. Ejemplo: para 0.245kg devolver 0.245.',
                        ],
                        'unidad' => [
                            'type' => 'string',
                            'enum' => ['', 'u', 'g', 'kg', 'ml', 'l'],
                            'description' => 'Unidad exacta de la cantidad leida del ticket.',
                        ],
                        'confidence' => [
                            'type' => 'number',
                            'description' => 'Confianza de 0 a 1 para este producto.',
                        ],
                    ],
                ],
            ],
            'confidence' => [
                'type' => 'number',
                'description' => 'Confianza global de 0 a 1.',
            ],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Dudas relevantes de lectura. Array vacio si no hay.',
            ],
            'raw_text' => [
                'type' => 'string',
                'description' => 'Texto principal reconocido del ticket, breve y sin inventar.',
            ],
        ],
    ];
}

function ticket_ocr_gemini_schema(): array
{
    $schema = ticket_ocr_schema();
    ticket_ocr_strip_schema_descriptions($schema);
    return $schema;
}

function ticket_ocr_strip_schema_descriptions(array &$schema): void
{
    unset($schema['description']);
    foreach ($schema as &$value) {
        if (is_array($value)) {
            ticket_ocr_strip_schema_descriptions($value);
        }
    }
}

function ticket_ocr_prompt(): string
{
    return implode("\n", [
        'Lee la foto de un ticket de balanza de comercio argentino y devolve solo JSON segun el esquema.',
        'Extrae fecha y hora de venta si se ven y TODOS los productos, pero de cada producto extrae UNICAMENTE PLU, cantidad vendida y unidad.',
        'Toma el PLU solo del numero entre corchetes al inicio de cada articulo, por ejemplo "[ 203]". No uses codigos de barras ni datos de la etiqueta de codigo de barras.',
        'Toma la cantidad vendida del valor impreso como peso o unidad, por ejemplo "0.245kg", "245g" o "1u".',
        'No extraigas ni calcules precio unitario, importe por producto, subtotal ni total general: esos datos se resolveran despues consultando la base de datos por PLU.',
        'El ticket puede ser largo y tener varios bloques de producto. Cada producto suele empezar con un PLU entre corchetes o parentesis, por ejemplo "[203] - Pistachos".',
        'Despues de cada producto suele aparecer una linea con peso/cantidad, precio por kg/unidad e importe: por ejemplo "0.245kg 50000$/kg = 12250$". Para ese caso devuelve cantidad=0.245 y unidad="kg"; ignora 50000 y 12250.',
        'Si el ticket dice "ARTICULOS: 6", intenta devolver 6 items; si no podes leer alguno, agrega warning.',
        'Ignora los codigos de barras y los numeros largos impresos debajo de cada producto. No uses codigos de barras como PLU, cantidad ni importe.',
        'Los PLU reales son cortos (normalmente 1 a 5 digitos) y estan cerca del nombre del producto.',
        'Si la cantidad esta en kg, usa unidad="kg"; si esta en gramos usa unidad="g"; si es por unidad usa unidad="u".',
        'La fecha debe salir en ISO YYYY-MM-DD y la hora en HH:MM:SS. Si el mes aparece como MAY, interpretalo como mayo.',
        'No inventes datos. Si un campo no se puede leer, usa "" o 0 y agrega una advertencia breve.',
        'Devuelve items con objetos {plu, cantidad, unidad, confidence}. Si falta PLU, cantidad o unidad, mantenlo en el objeto y agrega warnings para revision manual.',
        'Objeto JSON requerido: {"sale_date":"","sale_time":"","items":[{"plu":"","cantidad":0,"unidad":"kg","confidence":0}],"confidence":0,"warnings":[],"raw_text":""}.',
        'JSON obligatorio.',
    ]);
}

function ticket_ocr_extract_gemini(array $config, string $filePath, string $mimeType): array
{
    $apiKey = ticket_ocr_gemini_api_key($config);
    $model = ticket_ocr_gemini_model($config);

    if (!is_file($filePath)) {
        throw new RuntimeException('Imagen no encontrada.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('cURL no disponible en este hosting.');
    }

    $bytes = file_get_contents($filePath);
    if ($bytes === false || $bytes === '') {
        throw new RuntimeException('No se pudo leer la imagen.');
    }

    $base64Image = base64_encode($bytes);
    $errors = [];
    foreach (['modern_schema', 'legacy_schema', 'prompt_json'] as $mode) {
        try {
            $payload = ticket_ocr_gemini_payload($mimeType, $base64Image, $mode);
            $decoded = ticket_ocr_gemini_request($apiKey, $model, $payload);
            $text = ticket_ocr_gemini_response_text($decoded);
            if ($text === '') {
                throw new RuntimeException('Gemini no devolvio texto util.');
            }

            $data = json_decode($text, true);
            if (!is_array($data)) {
                throw new RuntimeException('Gemini devolvio una respuesta que no es JSON valido.');
            }

            return ticket_ocr_normalize_result($data);
        } catch (Throwable $e) {
            $errors[] = '[' . $mode . '] ' . $e->getMessage();
            if (!ticket_ocr_gemini_can_retry((string)$e->getMessage())) {
                throw $e;
            }
        }
    }

    throw new RuntimeException('Gemini OCR fallo: ' . implode(' | ', array_slice($errors, 0, 3)));
}

function ticket_ocr_gemini_payload(string $mimeType, string $base64Image, string $mode): array
{
    $payload = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    [
                        'text' => ticket_ocr_prompt(),
                    ],
                    [
                        'inline_data' => [
                            'mime_type' => $mimeType,
                            'data' => $base64Image,
                        ],
                    ],
                ],
            ],
        ],
        'generationConfig' => [
            'temperature' => 0,
            'maxOutputTokens' => 4000,
        ],
    ];

    if ($mode === 'modern_schema') {
        $payload['generationConfig']['responseFormat'] = [
            'text' => [
                'mimeType' => 'application/json',
                'schema' => ticket_ocr_gemini_schema(),
            ],
        ];
    } elseif ($mode === 'legacy_schema') {
        $payload['generationConfig']['responseMimeType'] = 'application/json';
        $payload['generationConfig']['responseSchema'] = ticket_ocr_gemini_schema();
    }

    return $payload;
}

function ticket_ocr_gemini_can_retry(string $message): bool
{
    $m = strtolower($message);
    if (str_contains($m, 'api key') || str_contains($m, 'permission_denied') || str_contains($m, 'quota') || str_contains($m, 'billing') || str_contains($m, 'resource_exhausted')) {
        return false;
    }
    if (str_contains($m, 'unknown name') || str_contains($m, 'invalid json payload') || str_contains($m, 'invalid_argument')) {
        return true;
    }
    if (str_contains($m, 'responseformat') || str_contains($m, 'responsemimetype') || str_contains($m, 'responseschema') || str_contains($m, 'schema')) {
        return true;
    }
    if (str_contains($m, 'json valido') || str_contains($m, 'no devolvio texto')) {
        return true;
    }
    return false;
}

function ticket_ocr_gemini_request(string $apiKey, string $model, array $payload): array
{
    $apiKey = ticket_ocr_clean_api_key($apiKey);
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('No se pudo iniciar cURL.');
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        throw new RuntimeException('No se pudo preparar el pedido de OCR con Gemini.');
    }

    $headers = [
        'Content-Type: application/json',
        'x-goog-api-key: ' . $apiKey,
    ];
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host !== '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https';
        $headers[] = 'Referer: ' . $scheme . '://' . $host . '/';
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $json,
    ]);

    $body = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $body === '') {
        throw new RuntimeException('No hubo respuesta de Gemini OCR.' . ($err !== '' ? (' ' . $err) : ''));
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Respuesta invalida de Gemini OCR.');
    }

    if ($status >= 400) {
        $msg = '';
        if (isset($decoded['error']['message']) && is_string($decoded['error']['message'])) {
            $msg = $decoded['error']['message'];
        }
        throw new RuntimeException($msg !== '' ? ('Gemini: ' . $msg) : 'Error de Gemini OCR.');
    }

    return $decoded;
}

function ticket_ocr_gemini_diagnostic(array $config): array
{
    $raw = (string)($config['ticket_ocr']['gemini_api_key'] ?? '');
    $source = $raw !== '' ? 'config.ticket_ocr.gemini_api_key' : '';
    if ($raw === '') {
        foreach (['GEMINI_API_KEY', 'GOOGLE_API_KEY', 'GOOGLE_AI_API_KEY', 'GOOGLE_GENAI_API_KEY'] as $name) {
            $candidate = getenv($name);
            if (!is_string($candidate) || $candidate === '') {
                $candidate = defined($name) ? (string)constant($name) : '';
            }
            if ($candidate !== '') {
                $raw = $candidate;
                $source = $name;
                break;
            }
        }
    }

    $key = ticket_ocr_clean_api_key($raw);
    $model = ticket_ocr_gemini_model($config);
    $out = [
        'provider' => 'gemini',
        'model' => $model,
        'key_source' => $source,
        'key_present' => $key !== '',
        'key_length' => strlen($key),
        'key_mask' => ticket_ocr_mask_key($key),
        'curl_available' => function_exists('curl_init'),
        'ok' => false,
        'message' => '',
    ];

    if ($key === '') {
        $out['message'] = 'No se encontro GEMINI_API_KEY.';
        return $out;
    }
    if (str_starts_with($key, '{') || str_contains($key, '-----BEGIN')) {
        $out['message'] = 'La key parece JSON/certificado. Gemini API necesita una API key de Google AI Studio.';
        return $out;
    }
    if (!function_exists('curl_init')) {
        $out['message'] = 'cURL no esta disponible en el hosting.';
        return $out;
    }

    try {
        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => 'Respond only with OK.'],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0,
                'maxOutputTokens' => 8,
            ],
        ];
        $decoded = ticket_ocr_gemini_request($key, $model, $payload);
        $text = ticket_ocr_gemini_response_text($decoded);
        $out['ok'] = true;
        $out['message'] = $text !== '' ? $text : 'Gemini respondio correctamente.';
    } catch (Throwable $e) {
        $out['message'] = $e->getMessage();
    }

    return $out;
}

function ticket_ocr_gemini_response_text(array $decoded): string
{
    $chunks = [];
    foreach (($decoded['candidates'] ?? []) as $candidate) {
        if (!is_array($candidate)) {
            continue;
        }
        $content = $candidate['content'] ?? [];
        if (!is_array($content)) {
            continue;
        }
        foreach (($content['parts'] ?? []) as $part) {
            if (is_array($part) && isset($part['text'])) {
                $chunks[] = (string)$part['text'];
            }
        }
    }

    $text = trim(implode("\n", $chunks));
    if ($text !== '') {
        return ticket_ocr_extract_json_text($text);
    }
    return '';
}

function ticket_ocr_extract_json_text(string $text): string
{
    $text = trim($text);
    if (str_starts_with($text, '```')) {
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/', '', $text) ?? $text;
        $text = trim($text);
    }
    return $text;
}

function ticket_ocr_extract_openai(array $config, string $filePath, string $mimeType): array
{
    $apiKey = ticket_ocr_openai_api_key($config);
    $model = ticket_ocr_openai_model($config);

    if (!is_file($filePath)) {
        throw new RuntimeException('Imagen no encontrada.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('cURL no disponible en este hosting.');
    }

    $bytes = file_get_contents($filePath);
    if ($bytes === false || $bytes === '') {
        throw new RuntimeException('No se pudo leer la imagen.');
    }

    $payload = [
        'model' => $model,
        'input' => [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'input_text',
                        'text' => ticket_ocr_prompt(),
                    ],
                    [
                        'type' => 'input_image',
                        'image_url' => 'data:' . $mimeType . ';base64,' . base64_encode($bytes),
                    ],
                ],
            ],
        ],
        'text' => [
            'format' => [
                'type' => 'json_schema',
                'name' => 'scale_ticket_sale',
                'strict' => true,
                'schema' => ticket_ocr_schema(),
            ],
        ],
        'max_output_tokens' => 1800,
    ];

    $decoded = ticket_ocr_openai_request($apiKey, $payload);
    $text = ticket_ocr_response_text($decoded);
    if ($text === '') {
        throw new RuntimeException('La IA no devolvio texto util.');
    }

    $data = json_decode($text, true);
    if (!is_array($data)) {
        throw new RuntimeException('La IA devolvio una respuesta que no es JSON valido.');
    }

    return ticket_ocr_normalize_result($data);
}

function ticket_ocr_openai_request(string $apiKey, array $payload): array
{
    $ch = curl_init('https://api.openai.com/v1/responses');
    if ($ch === false) {
        throw new RuntimeException('No se pudo iniciar cURL.');
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        throw new RuntimeException('No se pudo preparar el pedido de OCR.');
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => $json,
    ]);

    $body = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $body === '') {
        throw new RuntimeException('No hubo respuesta del servicio OCR.' . ($err !== '' ? (' ' . $err) : ''));
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Respuesta invalida del servicio OCR.');
    }

    if ($status >= 400) {
        $msg = '';
        if (isset($decoded['error']['message']) && is_string($decoded['error']['message'])) {
            $msg = $decoded['error']['message'];
        }
        throw new RuntimeException($msg !== '' ? $msg : 'Error del servicio OCR.');
    }

    return $decoded;
}

function ticket_ocr_response_text(array $decoded): string
{
    $direct = trim((string)($decoded['output_text'] ?? ''));
    if ($direct !== '') {
        return $direct;
    }

    $chunks = [];
    foreach (($decoded['output'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }
        foreach (($item['content'] ?? []) as $content) {
            if (!is_array($content)) {
                continue;
            }
            $type = (string)($content['type'] ?? '');
            if (($type === 'output_text' || $type === 'text') && isset($content['text'])) {
                $chunks[] = (string)$content['text'];
            }
        }
    }

    return trim(implode("\n", $chunks));
}

function ticket_ocr_normalize_result(array $data): array
{
    $items = [];
    $rawItems = $data['items'] ?? $data['productos'] ?? [];
    if (!is_array($rawItems)) {
        $rawItems = [];
    }

    foreach ($rawItems as $rawItem) {
        if (!is_array($rawItem)) {
            continue;
        }

        $plu = preg_replace('/\D+/', '', (string)($rawItem['plu'] ?? ''));
        $plu = is_string($plu) ? ltrim($plu, '0') : '';

        $name = ticket_ocr_clean_text((string)($rawItem['name'] ?? ''));
        $unitRaw = $rawItem['unidad'] ?? $rawItem['unit'] ?? '';
        $unit = ticket_ocr_normalize_unit((string)$unitRaw);
        $quantityRaw = $rawItem['cantidad'] ?? $rawItem['quantity'] ?? 0;
        $quantity = ticket_ocr_quantity_number($quantityRaw, $unit);

        if ($quantity <= 0 && $plu === '' && $name === '' && $unit === '') {
            continue;
        }

        if ($name === '' && $plu !== '') {
            $name = 'PLU ' . $plu;
        }
        if ($name === '') {
            $name = 'Producto ticket';
        }
        $items[] = [
            'plu' => $plu,
            'name' => $name,
            'quantity' => $quantity,
            'unit' => $unit,
            'cantidad' => $quantity,
            'unidad' => $unit,
            'unit_price' => 0.0,
            'line_total' => 0.0,
            'confidence' => max(0.0, min(1.0, ticket_ocr_number($rawItem['confidence'] ?? 0))),
            'product_id' => null,
            'catalog_name' => '',
            'catalog_price' => 0.0,
            'catalog_unit' => '',
            'needs_review' => false,
            'review_reasons' => [],
        ];
    }

    $total = array_reduce($items, static fn(float $sum, array $item): float => $sum + (float)$item['line_total'], 0.0);

    $warnings = [];
    foreach (($data['warnings'] ?? []) as $warning) {
        $warning = ticket_ocr_clean_text((string)$warning);
        if ($warning !== '') {
            $warnings[] = $warning;
        }
    }

    $ticket = [
        'sale_date' => ticket_ocr_normalize_date((string)($data['sale_date'] ?? '')),
        'sale_time' => ticket_ocr_normalize_time((string)($data['sale_time'] ?? '')),
        'items' => $items,
        'total' => round($total, 2),
        'confidence' => max(0.0, min(1.0, ticket_ocr_number($data['confidence'] ?? 0))),
        'warnings' => $warnings,
        'raw_text' => ticket_ocr_clean_text((string)($data['raw_text'] ?? '')),
    ];

    return ticket_ocr_sync_minimal_products($ticket);
}

function ticket_ocr_enrich_with_catalog(PDO $pdo, int $userId, array $ticket): array
{
    if (!catalog_supports_table($pdo)) {
        $ticket['warnings'][] = 'No se encontro la tabla del catalogo para resolver PLU y precios.';
        $ticket['needs_review'] = true;
        return $ticket;
    }

    foreach (($ticket['items'] ?? []) as $idx => $item) {
        if (!is_array($item)) {
            continue;
        }

        $reasons = is_array($item['review_reasons'] ?? null) ? $item['review_reasons'] : [];
        $pluRaw = preg_replace('/\D+/', '', (string)($item['plu'] ?? ''));
        $pluRaw = is_string($pluRaw) ? ltrim($pluRaw, '0') : '';
        $plu = (int)$pluRaw;
        $unit = ticket_ocr_normalize_unit((string)($item['unit'] ?? $item['unidad'] ?? ''));
        $quantity = ticket_ocr_quantity_number($item['quantity'] ?? $item['cantidad'] ?? 0, $unit);

        if ($plu <= 0) {
            $reasons[] = 'No se pudo leer el PLU.';
            $ticket['items'][$idx]['needs_review'] = true;
            $ticket['items'][$idx]['review_reasons'] = ticket_ocr_unique_warnings($reasons);
            continue;
        }
        if ($quantity <= 0) {
            $reasons[] = 'No se pudo leer la cantidad vendida.';
        }
        if ($unit === '') {
            $reasons[] = 'No se pudo leer la unidad de la cantidad.';
        }

        try {
            $product = catalog_get($pdo, $userId, $plu);
        } catch (Throwable $e) {
            $reasons[] = 'El PLU ' . $plu . ' no existe en el catalogo.';
            $ticket['items'][$idx]['needs_review'] = true;
            $ticket['items'][$idx]['review_reasons'] = ticket_ocr_unique_warnings($reasons);
            continue;
        }

        $catalogUnit = ticket_ocr_normalize_unit((string)($product['unit'] ?? ''));
        if ($unit === '' && $catalogUnit !== '') {
            $unit = $catalogUnit;
        }

        $price = round(((int)($product['price_cents'] ?? 0)) / 100, 2);
        if ($price <= 0) {
            $reasons[] = 'El PLU ' . $plu . ' no tiene precio configurado en el catalogo.';
        }
        if ($unit !== '' && $catalogUnit !== '' && !ticket_ocr_units_are_compatible($unit, $catalogUnit)) {
            $reasons[] = 'La unidad leida (' . $unit . ') no coincide con la unidad del catalogo (' . $catalogUnit . ').';
        }

        $ticket['items'][$idx]['product_id'] = (int)($product['id'] ?? 0);
        $ticket['items'][$idx]['catalog_name'] = (string)($product['name'] ?? '');
        $ticket['items'][$idx]['catalog_price'] = $price;
        $ticket['items'][$idx]['catalog_unit'] = $catalogUnit;
        $ticket['items'][$idx]['name'] = (string)($product['name'] ?? ('PLU ' . $plu));
        $ticket['items'][$idx]['quantity'] = $quantity;
        $ticket['items'][$idx]['unit'] = $unit;
        $ticket['items'][$idx]['cantidad'] = $quantity;
        $ticket['items'][$idx]['unidad'] = $unit;
        $ticket['items'][$idx]['unit_price'] = $price;

        if ($quantity > 0 && $unit !== '' && $price > 0 && ($catalogUnit === '' || ticket_ocr_units_are_compatible($unit, $catalogUnit))) {
            $ticket['items'][$idx]['line_total'] = ticket_ocr_compute_line_total($quantity, $unit, $price);
        } else {
            $ticket['items'][$idx]['line_total'] = 0.0;
        }

        $ticket['items'][$idx]['needs_review'] = count($reasons) > 0;
        $ticket['items'][$idx]['review_reasons'] = ticket_ocr_unique_warnings($reasons);
    }

    $ticket['total'] = round(array_reduce(
        is_array($ticket['items'] ?? null) ? $ticket['items'] : [],
        static fn(float $sum, array $item): float => $sum + ticket_ocr_number($item['line_total'] ?? 0),
        0.0
    ), 2);

    $ticket['needs_review'] = false;
    foreach (($ticket['items'] ?? []) as $idx => $item) {
        if (!is_array($item)) {
            continue;
        }
        $reasons = is_array($item['review_reasons'] ?? null) ? $item['review_reasons'] : [];
        foreach ($reasons as $reason) {
            $ticket['warnings'][] = 'Item ' . ((int)$idx + 1) . ': ' . (string)$reason;
        }
        if (!empty($item['needs_review'])) {
            $ticket['needs_review'] = true;
        }
    }
    $ticket['warnings'] = ticket_ocr_unique_warnings($ticket['warnings'] ?? []);

    return ticket_ocr_sync_minimal_products($ticket);
}

function ticket_ocr_unit_group(string $unit): string
{
    $unit = ticket_ocr_normalize_unit($unit);
    return match ($unit) {
        'g', 'kg' => 'mass',
        'ml', 'l' => 'volume',
        'u' => 'count',
        default => '',
    };
}

function ticket_ocr_units_are_compatible(string $readUnit, string $catalogUnit): bool
{
    $readGroup = ticket_ocr_unit_group($readUnit);
    $catalogGroup = ticket_ocr_unit_group($catalogUnit);
    return $readGroup !== '' && $catalogGroup !== '' && $readGroup === $catalogGroup;
}

function ticket_ocr_unique_warnings(array $warnings): array
{
    $out = [];
    foreach ($warnings as $warning) {
        $warning = ticket_ocr_clean_text((string)$warning);
        if ($warning !== '' && !in_array($warning, $out, true)) {
            $out[] = $warning;
        }
    }
    return $out;
}

function ticket_ocr_sync_minimal_products(array $ticket): array
{
    $products = [];
    foreach (($ticket['items'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $products[] = [
            'plu' => (string)($item['plu'] ?? ''),
            'cantidad' => ticket_ocr_quantity_number(
                $item['quantity'] ?? $item['cantidad'] ?? 0,
                ticket_ocr_normalize_unit((string)($item['unit'] ?? $item['unidad'] ?? ''))
            ),
            'unidad' => ticket_ocr_normalize_unit((string)($item['unit'] ?? $item['unidad'] ?? '')),
        ];
    }
    $ticket['productos'] = $products;
    return $ticket;
}

function ticket_ocr_clean_text(string $value): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?: $value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, 500, 'UTF-8');
    }
    return substr($value, 0, 500);
}

function ticket_ocr_number(mixed $value): float
{
    if (is_int($value) || is_float($value)) {
        $n = (float)$value;
        return is_finite($n) ? $n : 0.0;
    }

    $s = trim((string)$value);
    if ($s === '') {
        return 0.0;
    }
    $s = str_replace(['$', ' '], '', $s);
    $hasDot = str_contains($s, '.');
    $hasComma = str_contains($s, ',');
    if ($hasDot && $hasComma) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif ($hasComma) {
        $s = str_replace(',', '.', $s);
    } elseif (preg_match('/^\d{1,3}(?:\.\d{3})+$/', $s) === 1) {
        $s = str_replace('.', '', $s);
    }
    $s = preg_replace('/[^0-9.\-]/', '', $s);
    if (!is_string($s) || $s === '' || $s === '-' || !is_numeric($s)) {
        return 0.0;
    }

    $n = (float)$s;
    return is_finite($n) ? $n : 0.0;
}

function ticket_ocr_normalize_unit(string $unit): string
{
    $u = strtolower(trim($unit));
    $u = str_replace(['.', ' '], '', $u);

    return match ($u) {
        '', 'cant', 'cantidad' => '',
        'u', 'un', 'uni', 'unidad', 'unidades', 'und' => 'u',
        'g', 'gr', 'grs', 'gramo', 'gramos' => 'g',
        'kg', 'kilo', 'kilos', 'kgs' => 'kg',
        'ml', 'mililitro', 'mililitros' => 'ml',
        'l', 'lt', 'lts', 'litro', 'litros' => 'l',
        default => '',
    };
}

function ticket_ocr_quantity_number(mixed $value, string $unit): float
{
    if (is_int($value) || is_float($value)) {
        $n = (float)$value;
        return is_finite($n) ? $n : 0.0;
    }

    $s = trim((string)$value);
    if ($s === '') {
        return 0.0;
    }

    $s = str_replace(['$', ' '], '', $s);
    $unit = ticket_ocr_normalize_unit($unit);
    $hasDot = str_contains($s, '.');
    $hasComma = str_contains($s, ',');

    if (($unit === 'kg' || $unit === 'l') && $hasDot && !$hasComma) {
        // En cantidades de balanza, 1.025kg significa 1.025 kg, no 1025.
    } elseif ($hasDot && $hasComma) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif ($hasComma) {
        $s = str_replace(',', '.', $s);
    }

    $s = preg_replace('/[^0-9.\-]/', '', $s);
    if (!is_string($s) || $s === '' || $s === '-' || !is_numeric($s)) {
        return 0.0;
    }

    $n = (float)$s;
    return is_finite($n) ? $n : 0.0;
}

function ticket_ocr_compute_line_total(float $quantity, string $unit, float $basePrice): float
{
    if ($quantity <= 0 || $basePrice <= 0) {
        return 0.0;
    }
    $unit = ticket_ocr_normalize_unit($unit);
    if ($unit === 'g' || $unit === 'ml') {
        return round(($quantity / 1000.0) * $basePrice, 2);
    }
    return round($quantity * $basePrice, 2);
}

function ticket_ocr_compute_base_price(float $lineTotal, float $quantity, string $unit): float
{
    if ($lineTotal <= 0 || $quantity <= 0) {
        return 0.0;
    }
    $unit = ticket_ocr_normalize_unit($unit);
    if ($unit === 'g' || $unit === 'ml') {
        return round(($lineTotal * 1000.0) / $quantity, 2);
    }
    return round($lineTotal / $quantity, 2);
}

function ticket_ocr_normalize_date(string $value): string
{
    $value = trim($value);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
        return $value;
    }

    $upper = strtoupper($value);
    $monthMap = [
        'ENE' => '01', 'JAN' => '01',
        'FEB' => '02',
        'MAR' => '03',
        'ABR' => '04', 'APR' => '04',
        'MAY' => '05',
        'JUN' => '06',
        'JUL' => '07',
        'AGO' => '08', 'AUG' => '08',
        'SEP' => '09', 'SET' => '09',
        'OCT' => '10',
        'NOV' => '11',
        'DIC' => '12', 'DEC' => '12',
    ];

    if (preg_match('/^(\d{1,2})[\/\-. ]([A-Z]{3})[\/\-. ](\d{2,4})$/', $upper, $m) === 1) {
        $month = $monthMap[$m[2]] ?? '';
        if ($month !== '') {
            $year = (string)$m[3];
            if (strlen($year) === 2) {
                $year = '20' . $year;
            }
            return sprintf('%04d-%02d-%02d', (int)$year, (int)$month, (int)$m[1]);
        }
    }

    if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})$/', $value, $m) === 1) {
        $year = (string)$m[3];
        if (strlen($year) === 2) {
            $year = '20' . $year;
        }
        return sprintf('%04d-%02d-%02d', (int)$year, (int)$m[2], (int)$m[1]);
    }

    return '';
}

function ticket_ocr_normalize_time(string $value): string
{
    $value = trim($value);
    if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $value, $m) === 1) {
        return sprintf('%02d:%02d:%02d', (int)$m[1], (int)$m[2], isset($m[3]) ? (int)$m[3] : 0);
    }
    return '';
}
