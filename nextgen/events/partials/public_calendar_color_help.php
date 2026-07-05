<?php
declare(strict_types=1);

/**
 * Naptár színjelmagyarázat — gomb a fejlécben + popup (dialog).
 *
 * @var array<string, string> $D
 * @var list<array{label: string, color: string}> $calendarColorLegend
 * @var string $calendarColorHelpPart 'button'|'dialog'
 */

$calendarColorHelpPart = $calendarColorHelpPart ?? 'button';
$calendarColorLegend = $calendarColorLegend ?? [];
$defaultColor = '#6D8F63';
$scaleItems = $calendarColorLegend;
$hasDefaultInLegend = false;
foreach ($scaleItems as $item) {
    if (strtoupper((string) ($item['color'] ?? '')) === $defaultColor) {
        $hasDefaultInLegend = true;
        break;
    }
}
if (!$hasDefaultInLegend) {
    $scaleItems[] = [
        'label' => (string) ($D['cal_colors_help_default'] ?? 'Alapértelmezett'),
        'color' => $defaultColor,
    ];
}
$scaleCount = max(1, count($scaleItems));
$scaleGradientParts = [];
foreach ($scaleItems as $idx => $item) {
    $hexRaw = strtoupper(trim((string) ($item['color'] ?? $defaultColor)));
    if (!preg_match('/^#[0-9A-F]{6}$/', $hexRaw)) {
        $hexRaw = $defaultColor;
    }
    $pct = $scaleCount <= 1 ? 0 : round(100 * $idx / ($scaleCount - 1), 1);
    $scaleGradientParts[] = $hexRaw . ' ' . $pct . '%';
}
$scaleGradient = 'linear-gradient(90deg, ' . implode(', ', $scaleGradientParts) . ')';

if ($calendarColorHelpPart === 'button'): ?>
    <button
        type="button"
        class="events-cal-color-help-btn"
        id="events-cal-color-help-open"
        aria-haspopup="dialog"
        aria-controls="events-cal-color-help"
        title="<?= h((string) ($D['cal_colors_help_btn_aria'] ?? '')) ?>"
    >
        <span class="events-cal-color-help-btn__swatch" style="background: <?= h($scaleGradient) ?>;" aria-hidden="true"></span>
        <span class="events-cal-color-help-btn__label"><?= h((string) ($D['cal_colors_help_btn'] ?? 'Színek')) ?></span>
    </button>
<?php
    return;
endif;

if ($calendarColorHelpPart !== 'dialog') {
    return;
}
?>
<dialog class="events-cal-color-help" id="events-cal-color-help" aria-labelledby="events-cal-color-help-title">
    <div class="events-cal-color-help__panel">
        <header class="events-cal-color-help__head">
            <h2 class="events-cal-color-help__title" id="events-cal-color-help-title"><?= h((string) ($D['cal_colors_help_title'] ?? '')) ?></h2>
            <button type="button" class="events-cal-color-help__close" id="events-cal-color-help-close" aria-label="<?= h((string) ($D['cal_colors_help_close'] ?? 'Bezárás')) ?>">×</button>
        </header>
        <p class="events-cal-color-help__intro help"><?= h((string) ($D['cal_colors_help_intro'] ?? '')) ?></p>
        <div
            class="events-cal-color-help__scale"
            role="img"
            aria-label="<?= h((string) ($D['cal_colors_help_scale_aria'] ?? '')) ?>"
            style="--cal-color-scale-count: <?= (int) $scaleCount ?>;"
        >
            <?php foreach ($scaleItems as $item): ?>
                <?php $hex = h((string) ($item['color'] ?? $defaultColor)); ?>
                <span class="events-cal-color-help__scale-seg" style="background-color: <?= $hex ?>;" title="<?= h((string) ($item['label'] ?? '')) ?>"></span>
            <?php endforeach; ?>
        </div>
        <ul class="events-cal-color-help__list" role="list" aria-label="<?= h((string) ($D['cal_colors_help_list_aria'] ?? '')) ?>">
            <?php foreach ($scaleItems as $item): ?>
                <?php $hex = h((string) ($item['color'] ?? $defaultColor)); ?>
                <li class="events-cal-color-help__item">
                    <span class="events-cal-color-help__chip" style="background-color: <?= $hex ?>;" aria-hidden="true"></span>
                    <span class="events-cal-color-help__name"><?= h((string) ($item['label'] ?? '')) ?></span>
                    <span class="events-cal-color-help__hex"><?= $hex ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</dialog>
<script>
(function () {
    var openBtn = document.getElementById('events-cal-color-help-open');
    var dialog = document.getElementById('events-cal-color-help');
    var closeBtn = document.getElementById('events-cal-color-help-close');
    if (!openBtn || !dialog) return;

    function openHelp() {
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', 'open');
        }
    }

    function closeHelp() {
        if (typeof dialog.close === 'function') {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
        }
    }

    openBtn.addEventListener('click', openHelp);
    if (closeBtn) {
        closeBtn.addEventListener('click', closeHelp);
    }
    dialog.addEventListener('click', function (e) {
        if (e.target === dialog) {
            closeHelp();
        }
    });
    dialog.addEventListener('cancel', function (e) {
        e.preventDefault();
        closeHelp();
    });
})();
</script>
