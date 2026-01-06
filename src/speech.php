<?php

declare(strict_types=1);

function speech_transcribe_openai(array $config, string $filePath, string $mimeType = 'audio/webm', string $language = 'es'): string
{
    $apiKey = (string)($config['speech']['openai_api_key'] ?? '');
    if ($apiKey === '') {
        throw new RuntimeException('Falta OPENAI_API_KEY en config.');
    }

    $model = (string)($config['speech']['openai_transcribe_model'] ?? 'whisper-1');
    if ($model === '') {
        $model = 'whisper-1';
    }

    if (!is_file($filePath)) {
        throw new RuntimeException('Archivo de audio no encontrado.');
    }

    if (!function_exists('curl_init')) {
        throw new RuntimeException('cURL no disponible en este hosting.');
    }

    $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
    if ($ch === false) {
        throw new RuntimeException('No se pudo iniciar cURL.');
    }

    $postFields = [
        'model' => $model,
        'language' => $language,
        'response_format' => 'json',
        'file' => new CURLFile($filePath, $mimeType, basename($filePath)),
    ];

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => $postFields,
    ]);

    $body = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $body === '') {
        throw new RuntimeException('No hubo respuesta del servicio de transcripción.' . ($err !== '' ? (' ' . $err) : ''));
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Respuesta inválida del servicio de transcripción.');
    }

    if ($status >= 400) {
        $msg = '';
        if (isset($decoded['error']['message']) && is_string($decoded['error']['message'])) {
            $msg = $decoded['error']['message'];
        }
        throw new RuntimeException($msg !== '' ? $msg : 'Error de transcripción.');
    }

    $text = (string)($decoded['text'] ?? '');
    $text = trim($text);
    if ($text === '') {
        throw new RuntimeException('No se detectó texto en el audio.');
    }

    return $text;
}
