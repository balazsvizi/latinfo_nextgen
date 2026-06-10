<?php
declare(strict_types=1);
/** @var array{today: list<array<string, mixed>>, soon: list<array<string, mixed>>, past: list<array<string, mixed>>} $listPartition */
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

$listPartitionLabels = [
    'today' => 'Ma',
    'soon' => 'Hamarosan',
    'past' => 'Lezajlott',
];
$listPartitionAria = 'Eseménylista szekciók';

$sections = [
    ['id' => 'events-list-today', 'key' => 'today', 'title' => 'Ma', 'empty' => 'Ma nincs esemény.', 'past' => false],
    ['id' => 'events-list-soon', 'key' => 'soon', 'title' => 'Hamarosan', 'empty' => 'Nincs közelgő esemény.', 'past' => false],
    ['id' => 'events-list-past', 'key' => 'past', 'title' => 'Lezajlott', 'empty' => 'Nincs lezajlott esemény.', 'past' => true],
];

$totalCount = count($listPartition['today']) + count($listPartition['soon']) + count($listPartition['past']);
?>
<?php if ($totalCount === 0): ?>
    <p class="events-admin-empty">Nincs találat.</p>
<?php else: ?>
    <?php require __DIR__ . '/events_list_partition_nav.php'; ?>
    <?php foreach ($sections as $section): ?>
        <?php
        $sectionRows = $listPartition[$section['key']] ?? [];
        $sectionClass = 'events-list-partition-section events-admin-list-section';
        if ($section['past']) {
            $sectionClass .= ' events-list-partition-section--past events-admin-list-section--past';
        }
        ?>
        <section class="<?= h($sectionClass) ?>" id="<?= h((string) $section['id']) ?>" aria-labelledby="<?= h((string) $section['id']) ?>-title">
            <h3 class="events-list-partition-section__title events-admin-list-section__title" id="<?= h((string) $section['id']) ?>-title"><?= h((string) $section['title']) ?></h3>
            <?php if ($sectionRows === []): ?>
                <p class="events-admin-list-section__empty"><?= h((string) $section['empty']) ?></p>
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
                            <?php foreach ($sectionRows as $r): ?>
                                <?php require __DIR__ . '/admin_event_list_row.php'; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
