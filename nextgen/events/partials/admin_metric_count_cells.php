<?php
declare(strict_types=1);
/**
 * Metrika cella: emberi / bot / össz.
 *
 * @var array{human: int, bot: int, total: int} $metricCounts
 * @var string $metricEditUrl
 * @var string $metricTitle
 */
$metricCounts = $metricCounts ?? ['human' => 0, 'bot' => 0, 'total' => 0];
$metricEditUrl = $metricEditUrl ?? '#';
$metricTitle = $metricTitle ?? '';
?>
<td class="text-center events-metric-cell" title="<?= h($metricTitle . ' — emberi') ?>">
    <a class="events-cell-edit" href="<?= h($metricEditUrl) ?>"><?= (int) $metricCounts['human'] ?></a>
</td>
<td class="text-center events-metric-cell" title="<?= h($metricTitle . ' — bot') ?>">
    <a class="events-cell-edit" href="<?= h($metricEditUrl) ?>"><?= (int) $metricCounts['bot'] ?></a>
</td>
<td class="text-center events-metric-cell events-metric-cell--total" title="<?= h($metricTitle . ' — összesen') ?>">
    <a class="events-cell-edit" href="<?= h($metricEditUrl) ?>"><?= (int) $metricCounts['total'] ?></a>
</td>
