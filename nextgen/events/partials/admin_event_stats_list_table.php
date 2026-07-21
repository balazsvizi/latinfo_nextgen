<?php
declare(strict_types=1);
/** @var list<array<string, mixed>> $rows */
/** @var string $order */
/** @var string $dir_param */
/** @var array<string, string> $get_params */
/** @var string $editBase */
?>
<?php if ($rows === []): ?>
    <p class="events-admin-empty">Nincs találat.</p>
<?php else: ?>
    <div class="table-wrap events-admin-table-wrap events-stats-table-wrap">
        <table class="sortable-table events-admin-table events-admin-table--stats">
            <thead>
                <tr class="events-stats-thead-primary">
                    <th class="events-th-actions" scope="col" rowspan="2"><span class="visually-hidden">Műveletek</span></th>
                    <th rowspan="2"><?= sort_th('Dátum', 'start', $order, $dir_param, $get_params) ?></th>
                    <th rowspan="2"><?= sort_th('Szervező', 'organizer', $order, $dir_param, $get_params) ?></th>
                    <th rowspan="2"><?= sort_th('Név', 'name', $order, $dir_param, $get_params) ?></th>
                    <th rowspan="2"><?= sort_th('Státusz', 'status', $order, $dir_param, $get_params) ?></th>
                    <th class="th-center events-stats-th-group events-stats-th-group--preview" rowspan="2" scope="col" title="Naptár előnézet"><?= sort_th('Előnézet', 'cal_previews', $order, $dir_param, $get_params) ?></th>
                    <th class="events-stats-th-group events-stats-th-group--page" colspan="3" scope="colgroup">Oldal</th>
                    <th rowspan="2"><?= sort_th('ID', 'id', $order, $dir_param, $get_params) ?></th>
                </tr>
                <tr class="events-stats-thead-secondary">
                    <th class="th-center events-stats-th-sub events-stats-th-sub--page" title="Eseményoldal — emberi"><?= sort_th('Ember', 'views_human', $order, $dir_param, $get_params) ?></th>
                    <th class="th-center events-stats-th-sub events-stats-th-sub--page" title="Eseményoldal — bot"><?= sort_th('Bot', 'views_bot', $order, $dir_param, $get_params) ?></th>
                    <th class="th-center events-stats-th-sub events-stats-th-sub--page events-stats-th-sub--total" title="Eseményoldal — összesen"><?= sort_th('Össz', 'views', $order, $dir_param, $get_params) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php require __DIR__ . '/admin_event_stats_list_row.php'; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
