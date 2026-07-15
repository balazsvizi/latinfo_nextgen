<?php
declare(strict_types=1);

/**
 * Lebegő mini eszköztár — esemény szerkesztő (bal felső sarok).
 *
 * @var string $eventEditBackCalendarUrl Admin naptár
 * @var string $eventEditPublicCalendarUrl Nyilvános naptár (aktuális hónap)
 * @var string $eventEditCopyUrl Másolás
 * @var string|null $eventEditPreviewUrl Nyilvános megtekintés (közzétett esemény)
 */
$eventEditPreviewUrl = $eventEditPreviewUrl ?? null;

$adminFloatTools = [];
if ($eventEditPreviewUrl !== null && $eventEditPreviewUrl !== '') {
    $adminFloatTools[] = [
        'href' => $eventEditPreviewUrl,
        'title' => 'Nyilvános megtekintés',
        'aria' => 'Nyilvános megtekintés',
        'icon' => 'eye',
    ];
}
$adminFloatTools[] = [
    'href' => $eventEditCopyUrl,
    'title' => 'Esemény másolása',
    'aria' => 'Esemény másolása',
    'icon' => 'copy',
];
$adminFloatTools[] = [
    'href' => $eventEditBackCalendarUrl,
    'title' => 'Vissza az admin naptárhoz',
    'aria' => 'Vissza az admin naptárhoz',
    'icon' => 'back',
];
$adminFloatTools[] = [
    'href' => $eventEditPublicCalendarUrl,
    'title' => 'Nyilvános naptár megtekintése (aktuális hónap)',
    'aria' => 'Nyilvános naptár megtekintése az esemény hónapjában',
    'icon' => 'calendar',
];

$adminFloatToolsRequireLogin = false;
require __DIR__ . '/admin_float_tools.php';
