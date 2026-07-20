<?php
declare(strict_types=1);
/** @var list<array<string, mixed>> $rows */
/** @var string $order */
/** @var string $dir_param */
/** @var array<string, string> $get_params */
/** @var string $editBase */
/** @var array<int, list<array{id: int, name: string, color: string}>> $categoriesByEventId */
/** @var array<int, list<array{id: int, name: string}>> $tagsByEventId */
/** @var array<int, list<array{id: int, name: string}>> $djsByEventId */
/** @var array<int, list<array{id: int, name: string}>> $mainStylesByEventId */
/** @var array<int, list<array{id: int, name: string}>> $supplementaryStylesByEventId */
/** @var bool $tagsAvailable */
/** @var bool $djsAvailable */
/** @var bool $stylesAvailable */
?>
<?php if ($rows === []): ?>
    <p class="events-admin-empty">Nincs találat.</p>
<?php else: ?>
    <div class="table-wrap events-admin-table-wrap">
        <table class="sortable-table events-admin-table">
            <thead>
                <tr>
                    <th class="events-th-actions" scope="col"><span class="visually-hidden">Műveletek</span></th>
                    <th><?= sort_th('Dátum', 'start', $order, $dir_param, $get_params) ?></th>
                    <th><?= sort_th('Szervező', 'organizer', $order, $dir_param, $get_params) ?></th>
                    <th><?= sort_th('Név', 'name', $order, $dir_param, $get_params) ?></th>
                    <th><?= sort_th('Meta', 'category', $order, $dir_param, $get_params) ?></th>
                    <th><?= sort_th('Státusz', 'status', $order, $dir_param, $get_params) ?></th>
                    <th class="th-center" title="Naptár előnézet — emberi"><?= sort_th('Előn. ember', 'cal_previews_human', $order, $dir_param, $get_params) ?></th>
                    <th class="th-center" title="Naptár előnézet — bot"><?= sort_th('Előn. bot', 'cal_previews_bot', $order, $dir_param, $get_params) ?></th>
                    <th class="th-center" title="Naptár előnézet — összesen"><?= sort_th('Előn. össz', 'cal_previews', $order, $dir_param, $get_params) ?></th>
                    <th class="th-center" title="Eseményoldal — emberi"><?= sort_th('Oldal ember', 'views_human', $order, $dir_param, $get_params) ?></th>
                    <th class="th-center" title="Eseményoldal — bot"><?= sort_th('Oldal bot', 'views_bot', $order, $dir_param, $get_params) ?></th>
                    <th class="th-center" title="Eseményoldal — összesen"><?= sort_th('Oldal össz', 'views', $order, $dir_param, $get_params) ?></th>
                    <th><?= sort_th('ID', 'id', $order, $dir_param, $get_params) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php require __DIR__ . '/admin_event_list_row.php'; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
