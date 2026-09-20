<?php
declare(strict_types=1);

$preferred = __DIR__ . '/docs/mediaertek-modszertan-v2.pdf';
$fallback = __DIR__ . '/docs/mediaertek-modszertan.pdf';
$path = (is_file($preferred) && is_readable($preferred)) ? $preferred : $fallback;
$downloadName = str_ends_with(str_replace('\\', '/', $path), 'mediaertek-modszertan-v2.pdf')
    ? 'latinfo-mediaertek-modszertan-v2.pdf'
    : 'latinfo-mediaertek-modszertan.pdf';

if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'A módszertani PDF nem található.';
    exit;
}

$filesize = filesize($path);
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $downloadName . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=86400');
if ($filesize !== false) {
    header('Content-Length: ' . (string) $filesize);
}
readfile($path);
exit;
