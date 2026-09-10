<?php
declare(strict_types=1);

/**
 * DJ fotó feltöltő — wrapper a közös média partialhoz.
 *
 * @var string $djPhotoUrl
 * @var string $djPhotoPick
 */

$djMediaKind = 'photo';
$djMediaUrl = (string) ($djPhotoUrl ?? '');
$djMediaPick = (string) ($djPhotoPick ?? '');
require __DIR__ . '/dj_media_fields.php';
