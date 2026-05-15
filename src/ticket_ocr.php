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
    $key = (string)($config['ticket_ocr']['gemini_api_key'] ?? '');
    if ($key === '') {
        throw new RuntimeException('Falta GEMINI_API_KEY en config.');
    }
    return $key;
}

function ticket_ocr_gemini_model(array $config): string
{
    $model = (string)($config['ticket_ocr']['gemini_model'] ?? '');
    return $model !== '' ? $model : 'gemini-3-flash-preview';
}

function ticket_ocr_extract(array $config, string $filePath, string $mimeType): array
{
    $geminiKey = (string)($config['ticket_ocr']['gemini_api_key'] ?? '');
    if ($geminiKey === '') {
        throw new RuntimeException('Falta GEMINI_API_KEY en config.');
    }

    return ticket_ocr_extract_gemini($config, $filePath, $mimeType);
}

function ticket_ocr_schema(): array
{
    return [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['sale_date', 'sale_time', 'items', 'total', 'confidence', 'warnings', 'raw_text'],
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
                    'required' => ['plu', 'name', 'quantity', 'unit', 'unit_price', 'line_total', 'confidence'],
                    'properties' => [
                        'plu' => [
                            'type' => 'string',
                            'description' => 'Codigo PLU numerico del producto. Vacio si no se ve.',
                        ],
                        'name' => [
                            'type' => 'string',
                            'description' => 'Nombre o descripcion impresa del producto. Vacio si no se ve.',
                        ],
                        'quantity' => [
                            'type' => 'number',
                            'description' => 'Cantidad vendida. Para peso en gramos, usar gramos. Para kg, usar kg.',
                        ],
                        'unit' => [
                            'type' => 'string',
                            'enum' => ['', 'u', 'g', 'kg', 'ml', 'l'],
                            'description' => 'Unidad de la cantidad.',
                        ],
                        'unit_price' => [
                            'type' => 'number',
                            'description' => 'Precio base en ARS por unidad/kg/l si esta disponible; 0 si no se ve.',
                        ],
                        'line_total' => [
                            'type' => 'number',
                            'description' => 'Importe total del producto en ARS; 0 si no se ve.',
                        ],
                        'confidence' => [
                            'type' => 'number',
                            'description' => 'Confianza de 0 a 1 para este producto.',
                        ],
                    ],
                ],
            ],
            'total' => [
                'type' => 'number',
                'description' => 'Total general del ticket en ARS; 0 si no se ve.',
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
        'Extrae fecha y hora de venta, todos los productos, PLU, nombre si aparece, cantidad/peso vendido, precio unitario/base, importe por producto y total general.',
        'Los PLU suelen estar entre corchetes o antes del nombre, por ejemplo "[203] Pistachos".',
        'Cuando el ticket dice algo como "5000g / Kg x 2450$", usa quantity=5000, unit="g", unit_price=2450 y line_total=12250.',
        'Si la cantidad esta en kg, usa unit="kg"; si es por unidad, unit="u".',
        'Todos los importes deben ser numeros en pesos argentinos, sin simbolo $, sin separadores de miles.',
        'La fecha debe salir en ISO YYYY-MM-DD y la hora en HH:MM:SS. Si el mes aparece como MAY, interpretalo como mayo.',
        'No inventes datos. Si un campo no se puede leer, usa "" o 0 y agrega una advertencia breve.',
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

    $payload = ticket_ocr_gemini_payload($mimeType, base64_encode($bytes), true);
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
}

function ticket_ocr_gemini_payload(string $mimeType, string $base64Image, bool $structured): array
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
            'maxOutputTokens' => 2500,
        ],
    ];

    if ($structured) {
        $payload['generationConfig']['responseFormat'] = [
            'text' => [
                'mimeType' => 'application/json',
                'schema' => ticket_ocr_gemini_schema(),
            ],
        ];
    }

    return $payload;
}

function ticket_ocr_gemini_request(string $apiKey, string $model, array $payload): array
{
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('No se pudo iniciar cURL.');
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        throw new RuntimeException('No se pudo preparar el pedido de OCR con Gemini.');
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $apiKey,
        ],
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
    foreach (($data['items'] ?? []) as $rawItem) {
        if (!is_array($rawItem)) {
            continue;
        }

        $plu = preg_replace('/\D+/', '', (string)($rawItem['plu'] ?? ''));
        $plu = is_string($plu) ? ltrim($plu, '0') : '';

        $name = ticket_ocr_clean_text((string)($rawItem['name'] ?? ''));
        $quantity = ticket_ocr_number($rawItem['quantity'] ?? 0);
        $unit = ticket_ocr_normalize_unit((string)($rawItem['unit'] ?? ''));
        $unitPrice = ticket_ocr_number($rawItem['unit_price'] ?? 0);
        $lineTotal = ticket_ocr_number($rawItem['line_total'] ?? 0);

        if ($quantity <= 0 && $lineTotal <= 0 && $unitPrice <= 0 && $plu === '' && $name === '') {
            continue;
        }

        if ($name === '' && $plu !== '') {
            $name = 'PLU ' . $plu;
        }
        if ($name === '') {
            $name = 'Producto ticket';
        }
        if ($unit === '') {
            $unit = 'u';
        }
        if ($lineTotal <= 0 && $quantity > 0 && $unitPrice > 0) {
            $lineTotal = ticket_ocr_compute_line_total($quantity, $unit, $unitPrice);
        }
        if ($unitPrice <= 0 && $quantity > 0 && $lineTotal > 0) {
            $unitPrice = ticket_ocr_compute_base_price($lineTotal, $quantity, $unit);
        }

        $items[] = [
            'plu' => $plu,
            'name' => $name,
            'quantity' => $quantity > 0 ? $quantity : 1.0,
            'unit' => $unit,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'confidence' => max(0.0, min(1.0, ticket_ocr_number($rawItem['confidence'] ?? 0))),
            'product_id' => null,
            'catalog_name' => '',
            'catalog_price' => 0.0,
        ];
    }

    $total = ticket_ocr_number($data['total'] ?? 0);
    if ($total <= 0 && count($items) > 0) {
        $total = array_reduce($items, static fn(float $sum, array $item): float => $sum + (float)$item['line_total'], 0.0);
    }

    $warnings = [];
    foreach (($data['warnings'] ?? []) as $warning) {
        $warning = ticket_ocr_clean_text((string)$warning);
        if ($warning !== '') {
            $warnings[] = $warning;
        }
    }

    return [
        'sale_date' => ticket_ocr_normalize_date((string)($data['sale_date'] ?? '')),
        'sale_time' => ticket_ocr_normalize_time((string)($data['sale_time'] ?? '')),
        'items' => $items,
        'total' => round($total, 2),
        'confidence' => max(0.0, min(1.0, ticket_ocr_number($data['confidence'] ?? 0))),
        'warnings' => $warnings,
        'raw_text' => ticket_ocr_clean_text((string)($data['raw_text'] ?? '')),
    ];
}

function ticket_ocr_enrich_with_catalog(PDO $pdo, int $userId, array $ticket): array
{
    if (!catalog_supports_table($pdo)) {
        return $ticket;
    }

    foreach (($ticket['items'] ?? []) as $idx => $item) {
        if (!is_array($item)) {
            continue;
        }
        $plu = (int)($item['plu'] ?? 0);
        if ($plu <= 0) {
            continue;
        }

        try {
            $product = catalog_get($pdo, $userId, $plu);
        } catch (Throwable $e) {
            continue;
        }

        $ticket['items'][$idx]['product_id'] = (int)($product['id'] ?? 0);
        $ticket['items'][$idx]['catalog_name'] = (string)($product['name'] ?? '');
        $ticket['items'][$idx]['catalog_price'] = round(((int)($product['price_cents'] ?? 0)) / 100, 2);

        $currentName = trim((string)($ticket['items'][$idx]['name'] ?? ''));
        if ($currentName === '' || preg_match('/^PLU\s+\d+$/i', $currentName) === 1 || $currentName === 'Producto ticket') {
            $ticket['items'][$idx]['name'] = (string)($product['name'] ?? $currentName);
        }

        $catalogPriceCents = (int)($product['price_cents'] ?? 0);
        if ((float)($ticket['items'][$idx]['unit_price'] ?? 0) <= 0 && $catalogPriceCents > 0) {
            $ticket['items'][$idx]['unit_price'] = round($catalogPriceCents / 100, 2);
            $ticket['items'][$idx]['line_total'] = ticket_ocr_compute_line_total(
                (float)$ticket['items'][$idx]['quantity'],
                (string)$ticket['items'][$idx]['unit'],
                (float)$ticket['items'][$idx]['unit_price']
            );
        }
    }

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
        'g', 'gr', 'gramo', 'gramos' => 'g',
        'kg', 'kilo', 'kilos', 'kgs' => 'kg',
        'ml', 'mililitro', 'mililitros' => 'ml',
        'l', 'lt', 'lts', 'litro', 'litros' => 'l',
        default => '',
    };
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
