<?php
declare(strict_types=1);

/**
 * Naptár színjelmagyarázat — jobb oldali popover a fejlécben.
 *
 * @var array<string, string> $D
 * @var list<array{label: string, color: string}> $calendarColorLegend
 */

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
?>
<div class="events-cal-color-help-wrap" id="events-cal-color-help-wrap">
    <button
        type="button"
        class="events-cal-color-help-btn"
        id="events-cal-color-help-open"
        aria-expanded="false"
        aria-controls="events-cal-color-help-panel"
        title="<?= h((string) ($D['cal_colors_help_btn_aria'] ?? '')) ?>"
    >
        <span class="events-cal-color-help-btn__swatch" style="background: <?= h($scaleGradient) ?>;" aria-hidden="true"></span>
        <span class="events-cal-color-help-btn__label"><?= h((string) ($D['cal_colors_help_btn'] ?? 'Színek')) ?></span>
    </button>
    <div
        class="events-cal-color-help-panel"
        id="events-cal-color-help-panel"
        role="dialog"
        aria-labelledby="events-cal-color-help-title"
        aria-modal="true"
        hidden
    >
        <header class="events-cal-color-help-panel__head">
            <button type="button" class="events-cal-color-help-panel__close" id="events-cal-color-help-close" aria-label="<?= h((string) ($D['cal_colors_help_close'] ?? 'Bezárás')) ?>">×</button>
            <h2 class="events-cal-color-help-panel__title" id="events-cal-color-help-title"><?= h((string) ($D['cal_colors_help_title'] ?? '')) ?></h2>
        </header>
        <p class="events-cal-color-help-panel__intro"><?= h((string) ($D['cal_colors_help_intro'] ?? '')) ?></p>
        <div
            class="events-cal-color-help-panel__scale"
            role="img"
            aria-label="<?= h((string) ($D['cal_colors_help_scale_aria'] ?? '')) ?>"
            style="--cal-color-scale-count: <?= (int) $scaleCount ?>;"
        >
            <?php foreach ($scaleItems as $item): ?>
                <?php $hex = h((string) ($item['color'] ?? $defaultColor)); ?>
                <span class="events-cal-color-help-panel__scale-seg" style="background-color: <?= $hex ?>;" title="<?= h((string) ($item['label'] ?? '')) ?>"></span>
            <?php endforeach; ?>
        </div>
        <ul class="events-cal-color-help-panel__list" role="list" aria-label="<?= h((string) ($D['cal_colors_help_list_aria'] ?? '')) ?>">
            <?php foreach ($scaleItems as $item): ?>
                <?php $hex = h((string) ($item['color'] ?? $defaultColor)); ?>
                <li class="events-cal-color-help-panel__item">
                    <span class="events-cal-color-help-panel__name"><?= h((string) ($item['label'] ?? '')) ?></span>
                    <span class="events-cal-color-help-panel__chip" style="background-color: <?= $hex ?>;" aria-hidden="true"></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<script>
(function () {
    var wrap = document.getElementById('events-cal-color-help-wrap');
    var openBtn = document.getElementById('events-cal-color-help-open');
    var panel = document.getElementById('events-cal-color-help-panel');
    var closeBtn = document.getElementById('events-cal-color-help-close');
    if (!wrap || !openBtn || !panel) return;

    function setOpen(on) {
        wrap.classList.toggle('is-open', on);
        openBtn.setAttribute('aria-expanded', on ? 'true' : 'false');
        panel.hidden = !on;
    }

    function openHelp() {
        setOpen(true);
    }

    function closeHelp() {
        setOpen(false);
    }

    openBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        if (wrap.classList.contains('is-open')) {
            closeHelp();
        } else {
            openHelp();
        }
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            closeHelp();
        });
    }

    document.addEventListener('click', function (e) {
        if (!wrap.classList.contains('is-open')) return;
        if (wrap.contains(e.target)) return;
        closeHelp();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && wrap.classList.contains('is-open')) {
            closeHelp();
        }
    });
})();
</script>
