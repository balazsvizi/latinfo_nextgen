<?php
declare(strict_types=1);

/**
 * Szűrő panel a publikus főoldalon.
 * Inline változatban (klasszikus naptár) a nyitógomb a hónap lapozó mellé kerül,
 * a panel törzse desktopon legördülőként nyílik a fejléc alatt.
 * A szívecske szűrés az inline nyitógomb bal oldalán is megjelenik,
 * és a megnyitott panelben is megmarad.
 *
 * @var string $view cal|mcal|list|map
 * @var array<string, string> $D
 * @var bool $filtersActive
 * @var bool $filtersPanelOpen
 * @var string $filterClearUrl
 * @var bool $filtersPanelInline
 * @var array<string, mixed> $filters
 */
$filtersPanelInline = !empty($filtersPanelInline);
$favoritesFilterAlsoOutside = $filtersPanelInline && !empty($filters['favoritesAvailable']);
$filtersSummaryActive = !empty($filtersActive);
$panelClass = 'home-public__filters-panel';
if ($view === 'mcal') {
    $panelClass .= ' home-public__filters-panel--mcal';
}
if ($filtersPanelInline) {
    $panelClass .= ' home-public__filters-panel--inline';
}
?>
<?php if ($filtersPanelInline): ?>
<div class="home-public__filters-inline-tools">
    <?php if ($favoritesFilterAlsoOutside): ?>
        <div class="home-public__filters-favorites-slot">
            <?php
            $favoritesFilterBtnExtraClass = 'events-filter-favorites-btn--toolbar';
            $favoritesFilterRenderInput = true;
            $favoritesFilterPrimaryIds = true;
            require __DIR__ . '/public_filter_favorites_btn.php';
            ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
<details class="<?= h($panelClass) ?>" id="home-filters-panel"<?= $filtersPanelOpen ? ' open' : '' ?>>
    <summary class="home-public__filters-summary">
        <span class="home-public__filters-summary-text"><?= h((string) $D['filters_toggle']) ?></span>
        <?php if ($filtersSummaryActive): ?>
            <span class="home-public__filters-meta">
                <span class="home-public__filters-badge"><?= h((string) $D['filters_active_badge']) ?></span>
                <a href="<?= h($filterClearUrl) ?>" class="home-public__clear-filters" onclick="event.stopPropagation();"><?= h((string) $D['clear_filters']) ?></a>
            </span>
        <?php endif; ?>
    </summary>
    <div class="home-public__filters-body">
        <?php
        $hideMapDateFiltersInPanel = ($view === 'map');
        require __DIR__ . '/public_event_filters.php';
        ?>
    </div>
</details>
<?php if ($filtersPanelInline): ?>
</div>
<?php endif; ?>
