<?php
declare(strict_types=1);

/**
 * DJ logó feltöltő — wrapper a közös média partialhoz.
 *
 * @var string $djLogoUrl
 * @var string $djLogoPick
 * @var array<string, string>|null $profile
 */

$djMediaKind = 'logo';
$djMediaUrl = (string) ($djLogoUrl ?? '');
$djMediaPick = (string) ($djLogoPick ?? '');
$djMediaProfile = is_array($profile ?? null) ? $profile : [];
require __DIR__ . '/dj_media_fields.php';
