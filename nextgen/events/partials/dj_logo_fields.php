<?php
declare(strict_types=1);

/**
 * DJ logó feltöltő — wrapper a közös média partialhoz.
 *
 * @var string $djLogoUrl
 * @var string $djLogoPick
 */

$djMediaKind = 'logo';
$djMediaUrl = (string) ($djLogoUrl ?? '');
$djMediaPick = (string) ($djLogoPick ?? '');
require __DIR__ . '/dj_media_fields.php';
