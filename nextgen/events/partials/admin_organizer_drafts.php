<?php
declare(strict_types=1);
/** @var list<array<string, mixed>> $draftRows */
/** @var string $editBase */

$editBase = $editBase ?? events_url('szerkeszt.php?id=');
?>
<div class="card events-organizer-drafts">
    <h2 class="card-title">Piszkozatok</h2>
    <?php if ($draftRows === []): ?>
        <p class="help events-organizer-drafts__empty">Nincs piszkozat ehhez a szervezőhöz.</p>
    <?php else: ?>
        <div class="table-wrap events-admin-table-wrap">
            <table class="sortable-table events-admin-table">
                <thead>
                    <tr>
                        <th class="events-th-actions" scope="col"><span class="visually-hidden">Műveletek</span></th>
                        <th>Dátum</th>
                        <th>Név</th>
                        <th>Státusz</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($draftRows as $row): ?>
                        <?php
                        $eid = (int) ($row['id'] ?? 0);
                        $edit = $editBase . $eid;
                        $st = (string) ($row['event_status'] ?? '');
                        $badgeClass = events_post_status_badge_class($st);
                        ?>
                        <tr>
                            <td class="events-td-actions">
                                <a href="<?= h($edit) ?>" class="events-icon-action" title="Szerkesztés" aria-label="Szerkesztés">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </a>
                            </td>
                            <td><a class="events-cell-edit" href="<?= h($edit) ?>"><?= h(events_admin_format_datum_cell($row)) ?></a></td>
                            <td><a class="events-cell-edit" href="<?= h($edit) ?>"><?= h((string) ($row['event_name'] ?? '')) ?></a></td>
                            <td>
                                <a class="events-cell-edit events-cell-edit--badge" href="<?= h($edit) ?>">
                                    <span class="event-status-badge <?= h($badgeClass) ?>"><?= h(events_post_status_label($st)) ?></span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
