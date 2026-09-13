<?php
declare(strict_types=1);

/**
 * Szűrő panel a publikus főoldalon.
 * Inline változatban (klasszikus naptár) a nyitógomb a hónap lapozó mellé kerül,
 * a panel törzse desktopon legördülőként nyílik a fejléc alatt.
 *
 * @var string $view cal|mcal|list|map
 * @var array<string, string> $D
 * @var bool $filtersActive
 * @var bool $filtersPanelOpen
 * @var string $filterClearUrl
 * @var bool $filtersPanelInline
 */
$filtersPanelInline = !empty($filtersPanelInline);
$panelClass = 'home-public__filters-panel';
if ($view === 'mcal') {
    $panelClass .= ' home-public__filters-panel--mcal';
}
if ($filtersPanelInline) {
    $panelClass .= ' home-public__filters-panel--inline';
}
?>
<details class="<?= h($panelClass) ?>" id="home-filters-panel"<?= $filtersPanelOpen ? ' open' : '' ?>>
    <summary class="home-public__filters-summary">
        <span class="home-public__filters-summary-text"><?= h((string) $D['filters_toggle']) ?></span>
        <?php if ($filtersActive): ?>
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
