<?php
declare(strict_types=1);

/**
 * Új mobil naptár nézet (prototípus).
 *
 * @var string $lang
 * @var array<string, string> $D
 * @var DateTimeImmutable $monthFirst
 * @var string $monthKey
 * @var list<array{date: DateTimeImmutable, inMonth: bool, isToday: bool, isPast: bool, key: string}> $gridDays
 * @var array<string, list<array<string, mixed>>> $byDay
 * @var array<int, list<array{color?: string}>> $categoriesByEventId
 * @var string $selectedDayKey
 * @var string $mcalListViewUrl
 * @var string $mcalMonthViewUrl
 * @var string $mcalDayModeUrl
 * @var string $prevMonthUrl
 * @var string $nextMonthUrl
 * @var string $mcalMode month|day
 * @var bool $filtersActive
 * @var bool $filtersPanelOpen
 * @var array<string, mixed> $filters
 */

require_once __DIR__ . '/../lib/public_mobile_calendar.php';

$mcalMode = ($mcalMode ?? 'month') === 'day' ? 'day' : 'month';
$filtersPanelOpen = !empty($filtersPanelOpen);
$weekdayLetters = events_public_mobile_calendar_weekday_letters($lang);
$eventsByDayPayload = events_public_mobile_calendar_events_by_day_payload($byDay, $categoriesByEventId, $lang);
$selectedDayHeading = events_public_mobile_calendar_day_heading($selectedDayKey, $lang);
$selectedEvents = $eventsByDayPayload[$selectedDayKey] ?? [];
$monthPickerValue = $monthKey;
$headerDateLabel = events_public_calendar_month_label($monthFirst, $lang);
$searchNameValue = trim((string) ($filters['f_name'] ?? ''));
$searchOpen = $searchNameValue !== '';
$emptyDayLabel = (string) ($D['mcal_empty_day'] ?? ($lang === 'en' ? 'No events on this day.' : 'Nincs esemény ezen a napon.'));
$searchPh = (string) ($D['filter_name_ph'] ?? ($lang === 'en' ? 'Search in title…' : 'Keresés a címben…'));
$viewListLabel = (string) ($D['view_list'] ?? 'Lista');
$viewMonthLabel = (string) ($D['mcal_view_month'] ?? ($lang === 'en' ? 'Month' : 'Hónap'));
$viewDayLabel = (string) ($D['mcal_view_day'] ?? ($lang === 'en' ? 'Day' : 'Nap'));
$maxDots = 3;
?>
<div
    class="mcal<?= $mcalMode === 'day' ? ' mcal--day-mode' : '' ?>"
    id="mcal-root"
    data-selected="<?= h($selectedDayKey) ?>"
    data-month="<?= h($monthKey) ?>"
    data-mode="<?= h($mcalMode) ?>"
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
                value="<?= h($monthPickerValue) ?>"
                aria-label="<?= h((string) ($D['month_nav_aria'] ?? 'Hónap választás')) ?>"
                tabindex="-1"
            >
        </div>
        <div class="mcal__actions">
            <button type="button" class="mcal__icon-btn<?= $searchOpen ? ' is-active' : '' ?>" id="mcal-search-btn" aria-label="<?= h((string) ($D['mcal_search_aria'] ?? 'Keresés')) ?>" title="<?= h((string) ($D['mcal_search_aria'] ?? 'Keresés')) ?>" aria-expanded="<?= $searchOpen ? 'true' : 'false' ?>" aria-controls="mcal-search-panel">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
            <button type="button" class="mcal__icon-btn<?= $filtersPanelOpen ? ' is-active' : '' ?>" id="mcal-filter-btn" aria-label="<?= h((string) ($D['filters_toggle'] ?? 'Szűrők')) ?>" title="<?= h((string) ($D['filters_toggle'] ?? 'Szűrők')) ?>" aria-expanded="<?= $filtersPanelOpen ? 'true' : 'false' ?>" aria-controls="home-filters-panel">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h10M14 7a2 2 0 1 0 4 0 2 2 0 0 0-4 0zM20 17H10M10 17a2 2 0 1 1-4 0 2 2 0 0 1 4 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
            <div class="mcal__view-wrap">
                <button type="button" class="mcal__icon-btn mcal__icon-btn--view is-active" id="mcal-view-btn" aria-label="<?= h((string) ($D['view_switch_aria'] ?? 'Nézet választó')) ?>" aria-haspopup="menu" aria-expanded="false" aria-controls="mcal-view-menu">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2" stroke="currentColor" stroke-width="2"/><path d="M4 9h16M9 5v15M15 5v15" stroke="currentColor" stroke-width="2"/></svg>
                </button>
                <div class="mcal__view-menu" id="mcal-view-menu" role="menu" hidden>
                    <a class="mcal__view-item" role="menuitem" href="<?= h($mcalListViewUrl) ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M8 6h12M8 12h12M8 18h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="4" cy="6" r="1.5" fill="currentColor"/><circle cx="4" cy="12" r="1.5" fill="currentColor"/><circle cx="4" cy="18" r="1.5" fill="currentColor"/></svg>
                        <span><?= h($viewListLabel) ?></span>
                    </a>
                    <a class="mcal__view-item<?= $mcalMode === 'month' ? ' is-current' : '' ?>" role="menuitem" href="<?= h($mcalMonthViewUrl) ?>"<?= $mcalMode === 'month' ? ' aria-current="page"' : '' ?>>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4" y="4" width="6" height="6" rx="1" stroke="currentColor" stroke-width="2"/><rect x="14" y="4" width="6" height="6" rx="1" stroke="currentColor" stroke-width="2"/><rect x="4" y="14" width="6" height="6" rx="1" stroke="currentColor" stroke-width="2"/><rect x="14" y="14" width="6" height="6" rx="1" stroke="currentColor" stroke-width="2"/></svg>
                        <span><?= h($viewMonthLabel) ?></span>
                    </a>
                    <a class="mcal__view-item<?= $mcalMode === 'day' ? ' is-current' : '' ?>" role="menuitem" href="<?= h($mcalDayModeUrl) ?>" data-mcal-day-link<?= $mcalMode === 'day' ? ' aria-current="page"' : '' ?>>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="5" y="4" width="14" height="16" rx="2" stroke="currentColor" stroke-width="2"/><path d="M5 9h14" stroke="currentColor" stroke-width="2"/></svg>
                        <span><?= h($viewDayLabel) ?></span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="mcal__search-panel" id="mcal-search-panel"<?= $searchOpen ? '' : ' hidden' ?>>
        <label class="mcal__search-label" for="mcal-search-input"><?= h((string) ($D['filter_name'] ?? 'Esemény neve')) ?></label>
        <div class="mcal__search-row">
            <input
                type="search"
                class="mcal__search-input"
                id="mcal-search-input"
                value="<?= h($searchNameValue) ?>"
                placeholder="<?= h($searchPh) ?>"
                autocomplete="off"
                enterkeyhint="search"
            >
            <button type="button" class="mcal__search-submit" id="mcal-search-submit"><?= h((string) ($D['mcal_search_go'] ?? ($lang === 'en' ? 'Search' : 'Keresés'))) ?></button>
        </div>
    </div>

    <div class="mcal__grid-wrap" id="mcal-grid-wrap"<?= $mcalMode === 'day' ? ' hidden' : '' ?>>
        <div class="mcal__weekdays" aria-hidden="true">
            <?php foreach ($weekdayLetters as $letter): ?>
                <span class="mcal__weekday"><?= h($letter) ?></span>
            <?php endforeach; ?>
        </div>
        <div class="mcal__grid" role="grid" aria-label="<?= h((string) ($D['mcal_grid_aria'] ?? 'Havi naptár')) ?>">
            <?php foreach ($gridDays as $day): ?>
                <?php
                $dayKey = (string) $day['key'];
                $eventCount = isset($byDay[$dayKey]) ? count($byDay[$dayKey]) : 0;
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
    </div>

    <section class="mcal__day-panel" aria-live="polite">
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
                        </span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

<script type="application/json" id="mcal-events-data"><?= json_encode($eventsByDayPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script type="application/json" id="mcal-day-headings"><?php
$headings = [];
foreach ($gridDays as $day) {
    $k = (string) $day['key'];
    $headings[$k] = events_public_mobile_calendar_day_heading($k, $lang);
}
echo json_encode($headings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?></script>
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
    var viewBtn = document.getElementById('mcal-view-btn');
    var viewMenu = document.getElementById('mcal-view-menu');
    var monthInput = document.getElementById('mcal-month-picker');
    var dateBtn = document.getElementById('mcal-date-btn');
    var searchBtn = document.getElementById('mcal-search-btn');
    var searchPanel = document.getElementById('mcal-search-panel');
    var searchInput = document.getElementById('mcal-search-input');
    var searchSubmit = document.getElementById('mcal-search-submit');
    var filterBtn = document.getElementById('mcal-filter-btn');
    var filtersPanel = document.getElementById('home-filters-panel');
    var filtersWereActive = !!(filterBtn && filterBtn.classList.contains('is-active'));
    var dayModeLink = viewMenu ? viewMenu.querySelector('[data-mcal-day-link]') : null;
    var form = document.getElementById('events-home-filter-form');
    var nameField = document.getElementById('ev-f-name');

    function esc(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function syncDayModeLink(dayKey) {
        if (!dayModeLink) return;
        try {
            var u = new URL(dayModeLink.href, window.location.origin);
            u.searchParams.set('day', dayKey);
            u.searchParams.set('view', 'mcal');
            u.searchParams.set('mcal_mode', 'day');
            dayModeLink.href = u.pathname + u.search + u.hash;
        } catch (e) { /* ignore */ }
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
        return '<a class="mcal__event js-cal-event-preview" href="' + esc(it.url) + '" data-preview-id="' + esc(String(it.id)) + '" aria-haspopup="dialog">'
            + '<span class="mcal__event-meta">' + esc(it.meta) + '</span>'
            + '<span class="' + barClass + '" style="--mcal-event-accent: ' + esc(it.accent) + '">'
            + badge
            + '<span class="' + nameClass + '">' + esc(it.name) + '</span>'
            + '</span>'
            + '</a>';
    }

    function renderDay(dayKey) {
        root.setAttribute('data-selected', dayKey);
        if (headingEl) {
            headingEl.textContent = headings[dayKey] || dayKey;
        }
        syncDayModeLink(dayKey);
        var items = eventsData[dayKey] || [];
        if (!eventsEl) return;
        if (!items.length) {
            eventsEl.innerHTML = '<p class="mcal__empty" id="mcal-empty">' + esc(emptyLabel) + '</p>';
            return;
        }
        var html = '';
        for (var i = 0; i < items.length; i++) {
            html += renderEventHtml(items[i]);
        }
        eventsEl.innerHTML = html;
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
                    var url = new URL(window.location.href);
                    url.searchParams.set('month', parts[0] + '-' + parts[1]);
                    url.searchParams.set('day', dayKey);
                    url.searchParams.set('view', 'mcal');
                    window.location.href = url.toString();
                }
                return;
            }
            selectDay(dayKey, dayBtn);
        }
    });

    function closeViewMenu() {
        if (!viewMenu || !viewBtn) return;
        viewMenu.hidden = true;
        viewBtn.setAttribute('aria-expanded', 'false');
    }

    if (viewBtn && viewMenu) {
        viewBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = viewMenu.hidden;
            viewMenu.hidden = !open;
            viewBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function (e) {
            if (!viewMenu.hidden && !viewMenu.contains(e.target) && e.target !== viewBtn) {
                closeViewMenu();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeViewMenu();
        });
    }

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
            url.searchParams.set('view', 'mcal');
            url.searchParams.delete('day');
            window.location.href = url.toString();
        });
        monthInput.addEventListener('blur', function () {
            dateBtn.setAttribute('aria-expanded', 'false');
        });
    }

    function runSearch() {
        if (!searchInput) return;
        var q = (searchInput.value || '').trim();
        if (nameField) {
            nameField.value = q;
        }
        if (form) {
            form.requestSubmit ? form.requestSubmit() : form.submit();
        }
    }

    if (searchBtn && searchPanel) {
        searchBtn.addEventListener('click', function () {
            var willOpen = searchPanel.hidden;
            searchPanel.hidden = !willOpen;
            searchBtn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            if (willOpen) {
                searchBtn.classList.add('is-active');
                window.setTimeout(function () {
                    if (searchInput) searchInput.focus();
                }, 30);
            } else if (!(searchInput && searchInput.value.trim())) {
                searchBtn.classList.remove('is-active');
            }
        });
    }
    if (searchSubmit) {
        searchSubmit.addEventListener('click', runSearch);
    }
    if (searchInput) {
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                runSearch();
            }
        });
    }

    function openFilters() {
        if (!filtersPanel) return;
        if (searchPanel && !searchPanel.hidden) {
            searchPanel.hidden = true;
            if (searchBtn && !(searchInput && searchInput.value.trim())) {
                searchBtn.classList.remove('is-active');
                searchBtn.setAttribute('aria-expanded', 'false');
            }
        }
        if (!filtersPanel.open) {
            filtersPanel.open = true;
        }
        if (filterBtn) {
            filterBtn.setAttribute('aria-expanded', 'true');
            filterBtn.classList.add('is-active');
        }
        filtersPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    if (filterBtn) {
        filterBtn.addEventListener('click', function () {
            if (!filtersPanel) return;
            if (filtersPanel.open) {
                filtersPanel.open = false;
                filterBtn.setAttribute('aria-expanded', 'false');
                if (!filtersWereActive) {
                    filterBtn.classList.remove('is-active');
                }
            } else {
                openFilters();
            }
        });
    }
})();
</script>
