<?php
declare(strict_types=1);
/**
 * Metrika cellák: emberi / bot / össz.
 *
 * @var array{human: int, bot: int, total: int} $metricCounts
 * @var string $metricEditUrl
 * @var string $metricTitle
 * @var string $metricGroupClass pl. events-metric-cell--preview
 */
$metricCounts = $metricCounts ?? ['human' => 0, 'bot' => 0, 'total' => 0];
$metricEditUrl = $metricEditUrl ?? '#';
$metricTitle = $metricTitle ?? '';
$metricGroupClass = trim((string) ($metricGroupClass ?? ''));

$metricItems = [
    ['kind' => 'human', 'label' => 'emberi', 'value' => (int) $metricCounts['human']],
    ['kind' => 'bot', 'label' => 'bot', 'value' => (int) $metricCounts['bot']],
    ['kind' => 'total', 'label' => 'összesen', 'value' => (int) $metricCounts['total']],
];

foreach ($metricItems as $item):
    $classes = 'events-metric-cell events-metric-cell--' . $item['kind'];
    if ($metricGroupClass !== '') {
        $classes .= ' ' . $metricGroupClass;
    }
    if ($item['value'] === 0) {
        $classes .= ' is-zero';
    }
    ?>
    <td class="text-center <?= h($classes) ?>" title="<?= h($metricTitle . ' — ' . $item['label']) ?>">
        <a class="events-metric-val" href="<?= h($metricEditUrl) ?>"><?= $item['value'] ?></a>
    </td>
<?php endforeach; ?>
