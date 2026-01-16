<?php

declare(strict_types=1);

// Sirve la imagen del preload sin exponer /src/ (bloqueado por .htaccess)
$path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'preload.png';

if (!is_file($path)) {
    http_response_code(404);
    exit;
}

$mtime = filemtime($path);
if (!is_int($mtime) || $mtime <= 0) {
    $mtime = time();
}

$size = filesize($path);
$etag = '"' . sha1($mtime . ':' . (is_int($size) ? $size : 0)) . '"';

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');

$ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
$ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';

if (is_string($ifNoneMatch) && trim($ifNoneMatch) === $etag) {
    http_response_code(304);
    exit;
}

if (is_string($ifModifiedSince)) {
    $since = strtotime($ifModifiedSince);
    if (is_int($since) && $since >= $mtime) {
        http_response_code(304);
        exit;
    }
}

readfile($path);
