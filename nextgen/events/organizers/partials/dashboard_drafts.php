<?php
declare(strict_types=1);
/** @var list<array<string, mixed>> $draftRows */
?>
<div class="card events-organizer-drafts">
    <h2 class="card-title">Piszkozatok</h2>
    <?php if ($draftRows === []): ?>
        <p class="help events-organizer-drafts__empty">Nincs piszkozat eseményed.</p>
    <?php else: ?>
        <div class="table-wrap events-admin-table-wrap">
            <table class="sortable-table events-admin-table">
                <thead>
                    <tr>
                        <th>Dátum</th>
                        <th>Név</th>
                        <th>Státusz</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($draftRows as $row): ?>
                        <?php
                        $st = (string) ($row['event_status'] ?? '');
                        $badgeClass = events_post_status_badge_class($st);
                        ?>
                        <tr>
                            <td><?= h(events_admin_format_datum_cell($row)) ?></td>
                            <td><?= h((string) ($row['event_name'] ?? '')) ?></td>
                            <td>
                                <span class="event-status-badge <?= h($badgeClass) ?>"><?= h(events_post_status_label($st)) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
