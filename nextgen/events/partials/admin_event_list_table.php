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
                    <th class="th-center"><?= sort_th('Előnézet', 'cal_previews', $order, $dir_param, $get_params) ?></th>
                    <th class="th-center"><?= sort_th('Oldal', 'views', $order, $dir_param, $get_params) ?></th>
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
