<?php
declare(strict_types=1);

/**
 * Lebegő mini eszköztár — bal felső sarok, csak bejelentkezett adminoknak.
 *
 * @var list<array{
 *     href?: string,
 *     submit_form?: string,
 *     name?: string,
 *     value?: string,
 *     title: string,
 *     aria?: string,
 *     icon: 'eye'|'copy'|'back'|'calendar'|'plus'|'edit'|'list'|'map'|'home'|'save'|string,
 *     target?: string,
 *     rel?: string
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
    static function (mixed $btn): bool {
        if (!is_array($btn) || !isset($btn['title'], $btn['icon'])) {
            return false;
        }
        if (!is_string($btn['title']) || $btn['title'] === '') {
            return false;
        }
        if (!is_string($btn['icon']) || $btn['icon'] === '') {
            return false;
        }
        $href = isset($btn['href']) && is_string($btn['href']) ? $btn['href'] : '';
        $submitForm = isset($btn['submit_form']) && is_string($btn['submit_form']) ? $btn['submit_form'] : '';

        return $href !== '' || $submitForm !== '';
    }
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
    'save' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path stroke-linecap="round" stroke-linejoin="round" d="M17 21v-8H7v8M7 3v5h8"/>',
];
?>
<nav class="events-edit-float-tools" aria-label="Gyors műveletek">
    <?php foreach ($adminFloatTools as $btn): ?>
        <?php
        $title = (string) $btn['title'];
        $aria = trim((string) ($btn['aria'] ?? $title));
        $icon = (string) $btn['icon'];
        $iconMarkup = $adminFloatToolIcons[$icon] ?? $adminFloatToolIcons['edit'];
        $submitForm = trim((string) ($btn['submit_form'] ?? ''));
        if ($submitForm !== ''):
            $submitName = isset($btn['name']) && is_string($btn['name']) ? trim($btn['name']) : '';
            $submitValue = isset($btn['value']) && is_string($btn['value']) ? $btn['value'] : null;
            ?>
            <button
                type="submit"
                form="<?= h($submitForm) ?>"
                class="events-edit-float-tools__btn"
                title="<?= h($title) ?>"
                aria-label="<?= h($aria !== '' ? $aria : $title) ?>"
                <?= $submitName !== '' ? 'name="' . h($submitName) . '"' : '' ?>
                <?= $submitName !== '' && $submitValue !== null ? 'value="' . h($submitValue) . '"' : '' ?>
            >
                <svg class="events-edit-float-tools__icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><?= $iconMarkup ?></svg>
            </button>
            <?php
            continue;
        endif;
        $href = (string) ($btn['href'] ?? '');
        $target = trim((string) ($btn['target'] ?? ''));
        $rel = trim((string) ($btn['rel'] ?? ''));
        if ($target === '_blank' && $rel === '') {
            $rel = 'noopener noreferrer';
        }
        ?>
        <a
            href="<?= h($href) ?>"
            class="events-edit-float-tools__btn"
            title="<?= h($title) ?>"
            aria-label="<?= h($aria !== '' ? $aria : $title) ?>"
            <?= $target !== '' ? 'target="' . h($target) . '"' : '' ?>
            <?= $rel !== '' ? 'rel="' . h($rel) . '"' : '' ?>
        >
            <svg class="events-edit-float-tools__icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><?= $iconMarkup ?></svg>
        </a>
    <?php endforeach; ?>
</nav>
