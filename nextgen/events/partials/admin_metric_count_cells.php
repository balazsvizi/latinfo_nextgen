<?php
declare(strict_types=1);
/**
 * Metrika cellák: emberi / bot / össz (részletek opcionálisan elrejthetők).
 *
 * @var array{human: int, bot: int, total: int} $metricCounts
 * @var string $metricEditUrl
 * @var string $metricTitle
 * @var string $metricGroupClass pl. events-metric-cell--preview
 * @var bool $metricShowBot
 * @var bool $metricShowHuman
 * @var bool $metricTotalOnly Ha true: csak az össz (pl. előnézet).
 */
$metricCounts = $metricCounts ?? ['human' => 0, 'bot' => 0, 'total' => 0];
$metricEditUrl = $metricEditUrl ?? '#';
$metricTitle = $metricTitle ?? '';
$metricGroupClass = trim((string) ($metricGroupClass ?? ''));
$metricShowBot = $metricShowBot ?? true;
$metricShowHuman = $metricShowHuman ?? true;
$metricTotalOnly = !empty($metricTotalOnly);

$metricItems = [];
if ($metricTotalOnly) {
    $metricItems[] = ['kind' => 'total', 'label' => '', 'value' => (int) $metricCounts['total']];
} else {
    if ($metricShowHuman) {
        $metricItems[] = ['kind' => 'human', 'label' => 'emberi', 'value' => (int) $metricCounts['human']];
    }
    if ($metricShowBot) {
        $metricItems[] = ['kind' => 'bot', 'label' => 'bot', 'value' => (int) $metricCounts['bot']];
    }
    $metricItems[] = ['kind' => 'total', 'label' => 'összesen', 'value' => (int) $metricCounts['total']];
}

foreach ($metricItems as $item):
    $classes = 'events-metric-cell events-metric-cell--' . $item['kind'];
    if ($metricGroupClass !== '') {
        $classes .= ' ' . $metricGroupClass;
    }
    if ($item['value'] === 0) {
        $classes .= ' is-zero';
    }
    $title = $metricTitle;
    if ($item['label'] !== '') {
        $title .= ' — ' . $item['label'];
    }
    ?>
    <td class="text-center <?= h($classes) ?>" title="<?= h($title) ?>">
        <a class="events-metric-val" href="<?= h($metricEditUrl) ?>"><?= $item['value'] ?></a>
    </td>
<?php endforeach; ?>
<?php
$metricShowBot = true;
$metricShowHuman = true;
$metricTotalOnly = false;
?>
