<?php
declare(strict_types=1);

/**
 * Lebegő mini eszköztár — bal felső sarok, csak bejelentkezett adminoknak.
 *
 * @var list<array{
 *     href: string,
 *     title: string,
 *     aria?: string,
 *     icon: 'eye'|'copy'|'back'|'calendar'|'plus'|'edit'|'list'|'map'|'home'|string
 * }> $adminFloatTools
 * @var bool $adminFloatToolsRequireLogin Alapértelmezés: true
 */
$adminFloatTools = $adminFloatTools ?? [];
$adminFloatToolsRequireLogin = $adminFloatToolsRequireLogin ?? true;

if ($adminFloatToolsRequireLogin && !(function_exists('isLoggedIn') && isLoggedIn())) {
    return;
}

$adminFloatTools = array_values(array_filter(
    $adminFloatTools,
    static fn (mixed $btn): bool => is_array($btn)
        && isset($btn['href'], $btn['title'], $btn['icon'])
        && is_string($btn['href'])
        && $btn['href'] !== ''
        && is_string($btn['title'])
        && $btn['title'] !== ''
        && is_string($btn['icon'])
        && $btn['icon'] !== ''
));

if ($adminFloatTools === []) {
    return;
}

$adminFloatToolIcons = [
    'eye' => '<path stroke-linecap="round" stroke-linejoin="round" d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
    'copy' => '<rect x="9" y="9" width="13" height="13" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
    'back' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l-7-7 7-7"/>',
    'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path stroke-linecap="round" d="M16 2v4M8 2v4M3 10h18"/>',
    'plus' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>',
    'edit' => '<path stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
    'list' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13"/><circle cx="4" cy="6" r="1.5" fill="currentColor" stroke="none"/><circle cx="4" cy="12" r="1.5" fill="currentColor" stroke="none"/><circle cx="4" cy="18" r="1.5" fill="currentColor" stroke="none"/>',
    'map' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
    'home' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5 12 3l9 7.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M5 9.5V20h14V9.5"/>',
];
?>
<nav class="events-edit-float-tools" aria-label="Gyors műveletek">
    <?php foreach ($adminFloatTools as $btn): ?>
        <?php
        $href = (string) $btn['href'];
        $title = (string) $btn['title'];
        $aria = trim((string) ($btn['aria'] ?? $title));
        $icon = (string) $btn['icon'];
        $iconMarkup = $adminFloatToolIcons[$icon] ?? $adminFloatToolIcons['edit'];
        ?>
        <a
            href="<?= h($href) ?>"
            class="events-edit-float-tools__btn"
            title="<?= h($title) ?>"
            aria-label="<?= h($aria !== '' ? $aria : $title) ?>"
        >
            <svg class="events-edit-float-tools__icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><?= $iconMarkup ?></svg>
        </a>
    <?php endforeach; ?>
</nav>
