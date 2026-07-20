<?php
declare(strict_types=1);
/** @var array<string, mixed> $r */
/** @var string $editBase */
/** @var array<int, list<array{id: int, name: string, color: string}>> $categoriesByEventId */
/** @var array<int, list<array{id: int, name: string}>> $tagsByEventId */
/** @var array<int, list<array{id: int, name: string}>> $djsByEventId */
/** @var array<int, list<array{id: int, name: string}>> $mainStylesByEventId */
/** @var array<int, list<array{id: int, name: string}>> $supplementaryStylesByEventId */
/** @var bool $tagsAvailable */
/** @var bool $djsAvailable */
/** @var bool $stylesAvailable */

$eid = (int) $r['id'];
$edit = $editBase . $eid;
$st = (string) ($r['event_status'] ?? '');
$badgeClass = events_post_status_badge_class($st);
?>
<tr>
    <td class="events-td-actions">
        <div class="events-action-icons">
            <a href="<?= h($edit) ?>" class="events-icon-action" title="Szerkesztés" aria-label="Szerkesztés">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </a>
            <?php if (($r['event_status'] ?? '') === events_public_post_status()): ?>
                <a href="<?= h(events_megjelenit_url((string) $r['event_slug'])) ?>" class="events-icon-action" title="Nyilvános megtekintés (új lap)" aria-label="Nyilvános megtekintés új lapon" target="_blank" rel="noopener">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>
                </a>
            <?php endif; ?>
        </div>
    </td>
    <td><a class="events-cell-edit" href="<?= h($edit) ?>"><?= h(events_admin_format_datum_cell($r)) ?></a></td>
    <td><a class="events-cell-edit" href="<?= h($edit) ?>"><?= ($r['organizer_name'] ?? '') !== '' ? h((string) $r['organizer_name']) : '–' ?></a></td>
    <td><a class="events-cell-edit" href="<?= h($edit) ?>"><?= h((string) $r['event_name']) ?></a></td>
    <td class="events-td-meta">
        <?php
        $eventCats = $categoriesByEventId[$eid] ?? [];
        $eventTags = $tagsAvailable ? ($tagsByEventId[$eid] ?? []) : [];
        $eventDjs = $djsAvailable ? ($djsByEventId[$eid] ?? []) : [];
        $eventMainStyles = $stylesAvailable ? ($mainStylesByEventId[$eid] ?? []) : [];
        $eventSupplementaryStyles = $stylesAvailable ? ($supplementaryStylesByEventId[$eid] ?? []) : [];
        $hasMeta = $eventCats !== [] || $eventTags !== [] || $eventDjs !== [] || $eventMainStyles !== [] || $eventSupplementaryStyles !== [];
        ?>
        <?php if (!$hasMeta): ?>
            <span class="events-admin-meta-empty">–</span>
        <?php else: ?>
            <span class="events-admin-meta-cell">
                <?php if ($eventCats !== []): ?>
                    <span class="events-admin-meta-group">
                        <span class="events-admin-category-list" role="list">
                            <?php foreach ($eventCats as $catItem): ?>
                                <span class="events-admin-category-chip" role="listitem">
                                    <span class="events-category-color-chip__dot" style="background: <?= h($catItem['color']) ?>;" aria-hidden="true"></span>
                                    <?= h($catItem['name']) ?>
                                </span>
                            <?php endforeach; ?>
                        </span>
                    </span>
                <?php endif; ?>
                <?php if ($eventTags !== []): ?>
                    <span class="events-admin-meta-group">
                        <span class="events-admin-meta-emoji" aria-hidden="true" title="Címkék">🏷️</span>
                        <span class="events-admin-tag-list" role="list">
                            <?php foreach ($eventTags as $tagItem): ?>
                                <span class="events-admin-tag-chip" role="listitem"><?= h($tagItem['name']) ?></span>
                            <?php endforeach; ?>
                        </span>
                    </span>
                <?php endif; ?>
                <?php if ($eventMainStyles !== []): ?>
                    <span class="events-admin-meta-group">
                        <span class="events-admin-meta-emoji" aria-hidden="true" title="Fő stílusok">🎵</span>
                        <span class="events-admin-style-list" role="list">
                            <?php foreach ($eventMainStyles as $styleItem): ?>
                                <span class="events-admin-style-chip events-admin-style-chip--main" role="listitem"><?= h($styleItem['name']) ?></span>
                            <?php endforeach; ?>
                        </span>
                    </span>
                <?php endif; ?>
                <?php if ($eventSupplementaryStyles !== []): ?>
                    <span class="events-admin-meta-group">
                        <span class="events-admin-meta-emoji" aria-hidden="true" title="Kiegészítő stílusok">✨</span>
                        <span class="events-admin-style-list" role="list">
                            <?php foreach ($eventSupplementaryStyles as $styleItem): ?>
                                <span class="events-admin-style-chip events-admin-style-chip--supplementary" role="listitem"><?= h($styleItem['name']) ?></span>
                            <?php endforeach; ?>
                        </span>
                    </span>
                <?php endif; ?>
                <?php if ($eventDjs !== []): ?>
                    <span class="events-admin-meta-group">
                        <span class="events-admin-meta-emoji" aria-hidden="true" title="DJ-k">🎧</span>
                        <span class="events-admin-dj-list" role="list">
                            <?php foreach ($eventDjs as $djItem): ?>
                                <a
                                    class="events-admin-dj-chip events-admin-dj-chip--link"
                                    role="listitem"
                                    href="<?= h(events_url('tags.php?open_tag=') . (int) $djItem['id']) ?>"
                                    target="_blank"
                                    rel="noopener"
                                ><?= h($djItem['name']) ?></a>
                            <?php endforeach; ?>
                        </span>
                    </span>
                <?php endif; ?>
            </span>
        <?php endif; ?>
    </td>
    <td>
        <a class="events-cell-edit events-cell-edit--badge" href="<?= h($edit) ?>">
            <span class="event-status-badge <?= h($badgeClass) ?>"><?= h(events_post_status_label($st)) ?></span>
        </a>
    </td>
    <?php
    $metricEditUrl = $edit;
    $metricTitle = 'Naptár előnézet';
    $metricCounts = events_view_metric_counts_from_row($r, 'naptar_elonezetek');
    require __DIR__ . '/admin_metric_count_cells.php';
    $metricTitle = 'Eseményoldal';
    $metricCounts = events_view_metric_counts_from_row($r, 'megtekintesek');
    require __DIR__ . '/admin_metric_count_cells.php';
    ?>
    <td><a class="events-cell-edit" href="<?= h($edit) ?>"><?= $eid ?></a></td>
</tr>
