<?php
declare(strict_types=1);

/**
 * Admin prototípus nézet: publikus mcal chrome + layout variáns.
 *
 * @var string $layout
 * @var array{id: string, letter: string, title: string, lead: string, hint: string} $layoutMeta
 * @var array<string, array{id: string, letter: string, title: string, lead: string, hint: string}> $layouts
 * @var array<string, string> $D
 * @var array<string, string> $S
 * @var string $lang
 * @var string $monthKey
 * @var string $headerDateLabel
 * @var string $selectedDayKey
 * @var string $selectedDayHeading
 * @var string $emptyDayLabel
 * @var list<string> $weekdayLetters
 * @var list<list<array{date: DateTimeImmutable, inMonth: bool, isToday: bool, isPast: bool, key: string}>> $gridWeeks
 * @var array<string, list<array<string, mixed>>> $eventsByDayPayload
 * @var array<string, string> $dayHeadings
 * @var list<array<string, mixed>> $selectedEvents
 * @var array<int, array<string, mixed>> $calendarPreviewById
 * @var string $liveMcalUrl
 * @var string $hubUrl
 * @var string $cssPublicUrl
 * @var string $cssProtoUrl
 * @var int $maxDots
 */
$mcalRootClass = 'mcal mcal--proto-dense';
if ($layout === 'split') {
    $mcalRootClass .= ' mcal--proto-split';
}
$pageClass = 'mcal-proto-page mcal-proto-page--' . $layout;
$htmlLang = $lang === 'en' ? 'en' : 'hu';
?>
<!DOCTYPE html>
<html lang="<?= h($htmlLang) ?>" class="mcal-proto-html mcal-proto-html--<?= h($layout) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= events_public_robots_noindex_head_markup() ?>
    <meta name="theme-color" content="#3B50FF">
    <title><?= h($layoutMeta['letter'] . ' · ' . $layoutMeta['title']) ?> – Mobil naptár minta</title>
    <?= events_public_favicon_head_markup() ?>
    <link rel="stylesheet" href="<?= h($cssPublicUrl) ?>">
    <link rel="stylesheet" href="<?= h($cssProtoUrl) ?>">
</head>
<body class="event-public-page event-public-page--home event-public-page--mcal">
<div class="<?= h($pageClass) ?>">
    <div class="mcal-proto-bar" role="region" aria-label="Prototípus választó">
        <a class="mcal-proto-bar__hub" href="<?= h($hubUrl) ?>">Minták</a>
        <nav class="mcal-proto-bar__nav" aria-label="Elrendezés">
            <?php foreach ($layouts as $item): ?>
                <?php
                $itemUrl = mcal_prototype_page_url($item['id'], ['month' => $monthKey, 'day' => $selectedDayKey]);
                $isCurrent = $item['id'] === $layout;
                ?>
                <a
                    class="mcal-proto-bar__link<?= $isCurrent ? ' is-current' : '' ?>"
                    href="<?= h($itemUrl) ?>"
                    <?= $isCurrent ? ' aria-current="page"' : '' ?>
                ><?= h($item['letter']) ?> <?= h($item['title']) ?></a>
            <?php endforeach; ?>
        </nav>
        <a class="mcal-proto-bar__live" href="<?= h($liveMcalUrl) ?>">Élő</a>
    </div>
    <p class="mcal-proto-note"><?= h($layoutMeta['hint']) ?></p>

    <div class="event-shell event-shell--mcal">
        <article class="event-public home-public home-public--mcal">
            <header class="event-public__hero">
                <?php require __DIR__ . '/public_shell_hero_bar.php'; ?>
            </header>
            <section class="home-public__main" aria-label="<?= h((string) $D['calendar_aria']) ?>">
                <div
                    class="<?= h($mcalRootClass) ?>"
                    id="mcal-root"
                    data-selected="<?= h($selectedDayKey) ?>"
                    data-month="<?= h($monthKey) ?>"
                    data-layout="<?= h($layout) ?>"
                    data-empty="<?= h($emptyDayLabel) ?>"
                    data-lang="<?= h($lang) ?>"
                >
                    <div class="mcal__toolbar">
                        <div class="mcal__date-wrap">
                            <button type="button" class="mcal__date-btn" id="mcal-date-btn" aria-haspopup="dialog" aria-expanded="false" aria-controls="mcal-month-picker" title="<?= h((string) ($D['month_nav_aria'] ?? 'Hónap választás')) ?>">
                                <span class="mcal__date-label" id="mcal-header-date"><?= h($headerDateLabel) ?></span>
                                <svg class="mcal__date-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                            <input
                                type="month"
                                class="mcal__month-input"
                                id="mcal-month-picker"
                                value="<?= h($monthKey) ?>"
                                aria-label="<?= h((string) ($D['month_nav_aria'] ?? 'Hónap választás')) ?>"
                                tabindex="-1"
                            >
                        </div>
                    </div>

                    <div class="mcal__grid-wrap" id="mcal-grid-wrap">
                        <div class="mcal__weekdays" aria-hidden="true">
                            <?php foreach ($weekdayLetters as $letter): ?>
                                <span class="mcal__weekday"><?= h($letter) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <div class="mcal__grid" role="grid" aria-label="<?= h((string) ($D['mcal_grid_aria'] ?? 'Havi naptár')) ?>">
                            <?php foreach ($gridWeeks as $weekDays): ?>
                                <div class="mcal__week">
                                    <?php foreach ($weekDays as $day): ?>
                                        <?php
                                        $dayKey = (string) $day['key'];
                                        $eventCount = isset($eventsByDayPayload[$dayKey]) ? count($eventsByDayPayload[$dayKey]) : 0;
                                        $hasEvents = $eventCount > 0;
                                        $isSelected = $dayKey === $selectedDayKey;
                                        $classes = 'mcal__day';
                                        if (!$day['inMonth']) {
                                            $classes .= ' mcal__day--outside';
                                        }
                                        if (!empty($day['isToday'])) {
                                            $classes .= ' mcal__day--today';
                                        }
                                        if ($isSelected) {
                                            $classes .= ' is-selected';
                                        }
                                        if ($hasEvents) {
                                            $classes .= ' has-events';
                                        }
                                        $dayNum = (int) $day['date']->format('j');
                                        $dotCount = min($eventCount, $maxDots);
                                        $ariaDay = $dayKey . ($eventCount > 0 ? ', ' . $eventCount : '');
                                        ?>
                                        <button
                                            type="button"
                                            class="<?= h($classes) ?>"
                                            role="gridcell"
                                            data-day="<?= h($dayKey) ?>"
                                            data-count="<?= $eventCount ?>"
                                            data-in-month="<?= $day['inMonth'] ? '1' : '0' ?>"
                                            aria-label="<?= h($ariaDay) ?>"
                                            aria-pressed="<?= $isSelected ? 'true' : 'false' ?>"
                                        >
                                            <span class="mcal__day-num"><?= $dayNum ?></span>
                                            <span class="mcal__day-dots" aria-hidden="true" data-count="<?= $eventCount ?>">
                                                <?php if ($eventCount > $maxDots): ?>
                                                    <span class="mcal__day-count"><?= $eventCount > 9 ? '9+' : (string) $eventCount ?></span>
                                                <?php else: ?>
                                                    <?php for ($di = 0; $di < $dotCount; $di++): ?>
                                                        <span class="mcal__day-dot"></span>
                                                    <?php endfor; ?>
                                                <?php endif; ?>
                                            </span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <section class="mcal__day-panel" aria-live="polite">
                        <div class="mcal__day-scroll" id="mcal-day-scroll">
                            <h2 class="mcal__day-heading" id="mcal-day-heading"><?= h($selectedDayHeading) ?></h2>
                            <div class="mcal__events" id="mcal-events">
                            <?php if ($selectedEvents === []): ?>
                                <p class="mcal__empty" id="mcal-empty"><?= h($emptyDayLabel) ?></p>
                            <?php else: ?>
                                <?php foreach ($selectedEvents as $item): ?>
                                    <?php
                                    $barClass = 'mcal__event-bar';
                                    if (($item['changeType'] ?? '') === 'cancelled') {
                                        $barClass .= ' mcal__event-bar--cancelled';
                                    } elseif (($item['changeType'] ?? '') === 'modified') {
                                        $barClass .= ' mcal__event-bar--modified';
                                    }
                                    $nameClass = 'mcal__event-name';
                                    if (!empty($item['nameStruck'])) {
                                        $nameClass .= ' mcal__event-name--struck';
                                    }
                                    ?>
                                    <a
                                        class="mcal__event js-cal-event-preview"
                                        href="<?= h((string) $item['url']) ?>"
                                        data-preview-id="<?= (int) $item['id'] ?>"
                                        aria-haspopup="dialog"
                                    >
                                        <span class="mcal__event-meta"><?= h((string) $item['meta']) ?></span>
                                        <span class="<?= h($barClass) ?>" style="--mcal-event-accent: <?= h((string) $item['accent']) ?>">
                                            <?php if (($item['changeBadge'] ?? '') !== ''): ?>
                                                <span class="mcal__event-change<?= ($item['changeType'] ?? '') === 'cancelled' ? ' mcal__event-change--cancelled' : (($item['changeType'] ?? '') === 'modified' ? ' mcal__event-change--modified' : '') ?>"><?= h((string) $item['changeBadge']) ?></span>
                                            <?php endif; ?>
                                            <span class="<?= h($nameClass) ?>"><?= h((string) $item['name']) ?></span>
                                            <?php if (trim((string) ($item['city'] ?? '')) !== ''): ?>
                                                <span class="mcal__event-city"><?= h((string) $item['city']) ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        </div>
                        <div class="mcal-proto-more" id="mcal-more" hidden>
                            <span class="mcal-proto-more__fade" aria-hidden="true"></span>
                            <button type="button" class="mcal-proto-more__btn" id="mcal-more-btn">
                                <span>További események</span>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                        </div>
                    </section>
                </div>
            </section>
            <footer class="event-public__footer">
                <?php require __DIR__ . '/public_shell_footer.php'; ?>
            </footer>
        </article>
    </div>
</div>

<script type="application/json" id="mcal-events-data"><?= json_encode($eventsByDayPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script type="application/json" id="mcal-day-headings"><?= json_encode($dayHeadings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script>
(function () {
    var root = document.getElementById('mcal-root');
    if (!root) return;

    var eventsData = {};
    var headings = {};
    try {
        var evEl = document.getElementById('mcal-events-data');
        if (evEl) eventsData = JSON.parse(evEl.textContent || '{}');
    } catch (e) { eventsData = {}; }
    try {
        var hEl = document.getElementById('mcal-day-headings');
        if (hEl) headings = JSON.parse(hEl.textContent || '{}');
    } catch (e) { headings = {}; }

    var emptyLabel = root.getAttribute('data-empty') || '';
    var eventsEl = document.getElementById('mcal-events');
    var headingEl = document.getElementById('mcal-day-heading');
    var monthInput = document.getElementById('mcal-month-picker');
    var dateBtn = document.getElementById('mcal-date-btn');
    var moreEl = document.getElementById('mcal-more');
    var moreBtn = document.getElementById('mcal-more-btn');
    var dayScroll = document.getElementById('mcal-day-scroll');
    var isSplit = document.documentElement.classList.contains('mcal-proto-html--split');

    function scrollMetrics() {
        if (isSplit && dayScroll) {
            return {
                scrollTop: dayScroll.scrollTop,
                client: dayScroll.clientHeight,
                scroll: dayScroll.scrollHeight
            };
        }
        var el = document.scrollingElement || document.documentElement;
        return {
            scrollTop: el.scrollTop || window.pageYOffset || 0,
            client: window.innerHeight,
            scroll: el.scrollHeight
        };
    }

    function updateMoreHint() {
        if (!moreEl) return;
        var m = scrollMetrics();
        var canScroll = m.scroll > m.client + 12;
        var remaining = m.scroll - (m.scrollTop + m.client);
        var show = canScroll && remaining > 18;
        moreEl.hidden = !show;
    }

    function scheduleMoreHint() {
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(updateMoreHint);
        });
    }

    function scrollMore() {
        var m = scrollMetrics();
        var delta = Math.min(Math.max(m.client * 0.55, 120), 260);
        if (isSplit && dayScroll) {
            dayScroll.scrollBy({ top: delta, behavior: 'smooth' });
            return;
        }
        window.scrollBy({ top: delta, behavior: 'smooth' });
    }

    function esc(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderEventHtml(it) {
        var barClass = 'mcal__event-bar';
        if (it.changeType === 'cancelled') barClass += ' mcal__event-bar--cancelled';
        else if (it.changeType === 'modified') barClass += ' mcal__event-bar--modified';
        var nameClass = 'mcal__event-name';
        if (it.nameStruck) nameClass += ' mcal__event-name--struck';
        var badge = '';
        if (it.changeBadge) {
            var bClass = 'mcal__event-change';
            if (it.changeType === 'cancelled') bClass += ' mcal__event-change--cancelled';
            else if (it.changeType === 'modified') bClass += ' mcal__event-change--modified';
            badge = '<span class="' + bClass + '">' + esc(it.changeBadge) + '</span>';
        }
        var city = it.city ? '<span class="mcal__event-city">' + esc(it.city) + '</span>' : '';
        return '<a class="mcal__event js-cal-event-preview" href="' + esc(it.url) + '" data-preview-id="' + esc(String(it.id)) + '" aria-haspopup="dialog">'
            + '<span class="mcal__event-meta">' + esc(it.meta) + '</span>'
            + '<span class="' + barClass + '" style="--mcal-event-accent: ' + esc(it.accent) + '">'
            + badge
            + '<span class="' + nameClass + '">' + esc(it.name) + '</span>'
            + city
            + '</span>'
            + '</a>';
    }

    function renderDay(dayKey) {
        root.setAttribute('data-selected', dayKey);
        if (headingEl) {
            headingEl.textContent = headings[dayKey] || dayKey;
        }
        var items = eventsData[dayKey] || [];
        if (!eventsEl) return;
        if (!items.length) {
            eventsEl.innerHTML = '<p class="mcal__empty" id="mcal-empty">' + esc(emptyLabel) + '</p>';
            scheduleMoreHint();
            return;
        }
        var html = '';
        for (var i = 0; i < items.length; i++) {
            html += renderEventHtml(items[i]);
        }
        eventsEl.innerHTML = html;
        scheduleMoreHint();
    }

    function selectDay(dayKey, btn) {
        var buttons = root.querySelectorAll('.mcal__day');
        for (var i = 0; i < buttons.length; i++) {
            buttons[i].classList.remove('is-selected');
            buttons[i].setAttribute('aria-pressed', 'false');
        }
        if (btn) {
            btn.classList.add('is-selected');
            btn.setAttribute('aria-pressed', 'true');
        }
        renderDay(dayKey);
        try {
            var url = new URL(window.location.href);
            url.searchParams.set('day', dayKey);
            window.history.replaceState({}, '', url.toString());
        } catch (e) { /* ignore */ }
    }

    root.addEventListener('click', function (e) {
        var dayBtn = e.target.closest('.mcal__day');
        if (dayBtn && root.contains(dayBtn)) {
            var dayKey = dayBtn.getAttribute('data-day');
            if (!dayKey) return;
            if (dayBtn.getAttribute('data-in-month') !== '1') {
                var parts = dayKey.split('-');
                if (parts.length === 3) {
                    var navUrl = new URL(window.location.href);
                    navUrl.searchParams.set('month', parts[0] + '-' + parts[1]);
                    navUrl.searchParams.set('day', dayKey);
                    window.location.href = navUrl.toString();
                }
                return;
            }
            selectDay(dayKey, dayBtn);
        }
    });

    if (dateBtn && monthInput) {
        dateBtn.addEventListener('click', function () {
            dateBtn.setAttribute('aria-expanded', 'true');
            if (typeof monthInput.showPicker === 'function') {
                try { monthInput.showPicker(); return; } catch (e) { /* fall through */ }
            }
            monthInput.focus();
            monthInput.click();
        });
        monthInput.addEventListener('change', function () {
            var val = monthInput.value;
            if (!val) return;
            var url = new URL(window.location.href);
            url.searchParams.set('month', val);
            url.searchParams.delete('day');
            window.location.href = url.toString();
        });
        monthInput.addEventListener('blur', function () {
            dateBtn.setAttribute('aria-expanded', 'false');
        });
    }

    if (moreBtn) {
        moreBtn.addEventListener('click', function () {
            scrollMore();
        });
    }
    if (isSplit && dayScroll) {
        dayScroll.addEventListener('scroll', updateMoreHint, { passive: true });
    } else {
        window.addEventListener('scroll', updateMoreHint, { passive: true });
    }
    window.addEventListener('resize', scheduleMoreHint);
    scheduleMoreHint();
})();
</script>
<?php require __DIR__ . '/event_image_orientation_script.php'; ?>
<?php if ($calendarPreviewById !== []): ?>
    <?php require __DIR__ . '/public_calendar_event_preview.php'; ?>
<?php endif; ?>
</body>
</html>
