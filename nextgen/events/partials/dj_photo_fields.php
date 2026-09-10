<?php
declare(strict_types=1);

/**
 * DJ fotó feltöltő — wrapper a közös média partialhoz.
 *
 * @var string $djPhotoUrl
 * @var string $djPhotoPick
 * @var array<string, string>|null $profile
 */

$djMediaKind = 'photo';
$djMediaUrl = (string) ($djPhotoUrl ?? '');
$djMediaPick = (string) ($djPhotoPick ?? '');
$djMediaProfile = is_array($profile ?? null) ? $profile : [];
require __DIR__ . '/dj_media_fields.php';
