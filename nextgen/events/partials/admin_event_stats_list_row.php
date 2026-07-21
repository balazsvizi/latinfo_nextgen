<?php
declare(strict_types=1);
/** @var array<string, mixed> $r */
/** @var string $editBase */

$eid = (int) $r['id'];
$edit = $editBase . $eid;
$st = (string) ($r['event_status'] ?? '');
$badgeClass = events_post_status_badge_class($st);
$organizerName = trim((string) ($r['organizer_name'] ?? ''));
?>
<tr class="events-stats-row">
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
    <td class="events-stats-td-date"><a class="events-cell-edit" href="<?= h($edit) ?>"><?= h(events_admin_format_datum_cell($r)) ?></a></td>
    <td class="events-stats-td-org"><a class="events-cell-edit" href="<?= h($edit) ?>"><?= $organizerName !== '' ? h($organizerName) : '–' ?></a></td>
    <td class="events-stats-td-name"><a class="events-cell-edit" href="<?= h($edit) ?>"><?= h((string) $r['event_name']) ?></a></td>
    <td>
        <a class="events-cell-edit events-cell-edit--badge" href="<?= h($edit) ?>">
            <span class="event-status-badge <?= h($badgeClass) ?>"><?= h(events_post_status_label($st)) ?></span>
        </a>
    </td>
    <?php
    $metricEditUrl = $edit;
    $metricTitle = 'Naptár előnézet';
    $metricGroupClass = 'events-metric-cell--preview';
    $metricTotalOnly = true;
    $metricCounts = events_view_metric_counts_from_row($r, 'naptar_elonezetek');
    require __DIR__ . '/admin_metric_count_cells.php';
    $metricTitle = 'Eseményoldal';
    $metricGroupClass = 'events-metric-cell--page';
    $metricTotalOnly = false;
    $metricShowBot = true;
    $metricShowHuman = true;
    $metricCounts = events_view_metric_counts_from_row($r, 'megtekintesek');
    require __DIR__ . '/admin_metric_count_cells.php';
    ?>
    <td class="events-stats-td-id"><a class="events-cell-edit" href="<?= h($edit) ?>"><?= $eid ?></a></td>
</tr>
