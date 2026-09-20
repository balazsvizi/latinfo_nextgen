<?php
declare(strict_types=1);

$path = __DIR__ . '/docs/mediaertek-modszertan.pdf';
if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'A módszertani PDF nem található.';
    exit;
}

$filesize = filesize($path);
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="latinfo-mediaertek-modszertan.pdf"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=86400');
if ($filesize !== false) {
    header('Content-Length: ' . (string) $filesize);
}
readfile($path);
exit;
