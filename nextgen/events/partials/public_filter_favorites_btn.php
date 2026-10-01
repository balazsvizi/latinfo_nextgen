<?php
declare(strict_types=1);

/**
 * Kedvencek (szívecske) szűrő gomb a publikus esemény szűrőkhöz.
 *
 * @var array<string, mixed> $filters
 * @var array<string, string> $D
 * @var string|null $lang
 * @var string $favoritesFilterBtnExtraClass Extra CSS osztály(ok) a gombra
 * @var bool $favoritesFilterRenderInput Hidden f_favorites mező kirajzolása (csak egyszer legyen a formban)
 * @var bool $favoritesFilterPrimaryIds id="ev-f-favorites*" csak az első példányon
 */
if (empty($filters['favoritesAvailable'])) {
    return;
}

$favoritesFilterActive = !empty($filters['f_favorites']);
$favoritesFilterLabel = (string) ($D['filter_favorites'] ?? 'Szívecske');
$favoritesFilterApply = (string) ($D['filter_favorites_apply'] ?? 'Csak a kedvenceimet tartalmazó események');
$favoritesFilterClear = (string) ($D['filter_favorites_clear'] ?? 'Kedvencek szűrő kikapcsolása');
$favoritesFilterTitle = $favoritesFilterActive ? $favoritesFilterClear : $favoritesFilterApply;
$favoritesFilterLang = (isset($lang) && $lang === 'en') ? 'en' : 'hu';
$favoritesFilterBtnExtraClass = trim((string) ($favoritesFilterBtnExtraClass ?? ''));
$favoritesFilterRenderInput = !isset($favoritesFilterRenderInput) || !empty($favoritesFilterRenderInput);
$favoritesFilterPrimaryIds = !isset($favoritesFilterPrimaryIds) || !empty($favoritesFilterPrimaryIds);
$favoritesFilterBtnClass = 'events-filter-favorites-btn';
if ($favoritesFilterActive) {
    $favoritesFilterBtnClass .= ' is-active';
}
if ($favoritesFilterBtnExtraClass !== '') {
    $favoritesFilterBtnClass .= ' ' . $favoritesFilterBtnExtraClass;
}
?>
<?php if ($favoritesFilterRenderInput): ?>
<input type="hidden" name="f_favorites" id="ev-f-favorites" value="1"<?= $favoritesFilterActive ? '' : ' disabled' ?>>
<?php endif; ?>
<button
    type="button"
    class="<?= h($favoritesFilterBtnClass) ?>"
    <?php if ($favoritesFilterPrimaryIds): ?>id="ev-f-favorites-btn"<?php endif; ?>
    data-favorites-filter-btn
    data-logged-in="<?= !empty($filters['favoritesLoggedIn']) ? '1' : '0' ?>"
    data-has-favorites="<?= !empty($filters['favoritesHasAny']) ? '1' : '0' ?>"
    data-lang="<?= h($favoritesFilterLang) ?>"
    data-label-apply="<?= h($favoritesFilterApply) ?>"
    data-label-clear="<?= h($favoritesFilterClear) ?>"
    aria-pressed="<?= $favoritesFilterActive ? 'true' : 'false' ?>"
    aria-label="<?= h($favoritesFilterTitle) ?>"
    title="<?= h($favoritesFilterTitle) ?>"
>
    <span class="events-filter-favorites-btn__icon" aria-hidden="true">♥</span>
    <span class="visually-hidden"><?= h($favoritesFilterLabel) ?></span>
</button>
