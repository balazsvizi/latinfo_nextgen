<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/eventpics.php';
require_once __DIR__ . '/lib/admin_event_filters.php';
requireSuperadmin();

$db = getDb();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('events_kepek_esemenyek')) {
        flash('error', 'Lejárt vagy érvénytelen munkamenet. Töltsd újra az oldalt.');
    } else {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'url_to_own') {
            $ids = is_array($_POST['event_ids'] ?? null) ? $_POST['event_ids'] : [];
            $result = events_featured_image_bulk_url_to_own($db, $ids);

            if ($result['ok'] > 0) {
                rendszer_log('kepek_esemenyek', null, 'URL→Saját', $result['ok'] . ' esemény');
                $parts = [$result['ok'] . ' sikeres'];
                if ($result['skipped'] > 0) {
                    $parts[] = $result['skipped'] . ' kihagyva';
                }
                if ($result['failed'] > 0) {
                    $parts[] = $result['failed'] . ' hiba';
                }
                flash('success', 'URL → Saját: ' . implode(', ', $parts) . '.');
            } elseif ($result['failed'] > 0) {
                flash('error', 'URL → Saját: minden kijelölt eseménynél hiba történt (' . $result['failed'] . ').');
            } elseif ($result['skipped'] > 0) {
                flash('error', 'URL → Saját: nincs átvihető kép a kijelöltek között (' . $result['skipped'] . ' kihagyva).');
            } else {
                flash('error', 'Nincs kijelölt esemény.');
            }

            if ($result['failed'] > 0 && $result['messages'] !== []) {
                $_SESSION['events_kepek_bulk_errors'] = array_slice($result['messages'], 0, 12);
            }
        } else {
            flash('error', 'Ismeretlen művelet.');
        }
    }
    redirect(events_url('kepek_esemenyek.php'));
}

$bulkErrors = $_SESSION['events_kepek_bulk_errors'] ?? [];
unset($_SESSION['events_kepek_bulk_errors']);

$listLimitParsed = events_admin_list_limit_from_get();
$list_limit = $listLimitParsed['sql_limit'];
$listLimitValue = $listLimitParsed['value'];
$listPoolCount = events_admin_table_pool_count($db, 'events_calendar_events', $list_limit);

$eventRows = events_featured_image_admin_all_events($db, $list_limit);
$listDisplayedCount = count($eventRows);
$editBase = events_url('szerkeszt.php?id=');

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Képek–események';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>
<?php if ($bulkErrors !== []): ?>
    <ul class="events-kepek-esemenyek__bulk-errors">
        <?php foreach ($bulkErrors as $line): ?>
            <li><?= h((string) $line) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<div class="card events-kepek-esemenyek">
    <div class="events-list-head">
        <div class="events-list-head__start">
            <h1 class="events-list-title card-title" style="margin:0;">Képek–események</h1>
            <?php
            $listLimitInForm = false;
            $listLimitStandalone = true;
            require __DIR__ . '/partials/admin_list_display_limit.php';
            ?>
        </div>
    </div>
    <p class="help events-kepek-esemenyek__intro">
        Az összes esemény kiemelt kép beállítása. A szűrők azonnal frissítik a listát.
        Csak superadmin érheti el.
    </p>

    <?php if ($eventRows === []): ?>
        <p class="help">Nincs esemény az adatbázisban.</p>
    <?php else: ?>
        <form method="post" action="<?= h(events_url('kepek_esemenyek.php')) ?>" class="events-kepek-esemenyek__bulk" id="events-kepek-bulk-form">
            <?= csrf_input('events_kepek_esemenyek') ?>
            <input type="hidden" name="action" value="url_to_own">
            <div class="events-kepek-esemenyek__toolbar">
                <button type="submit" class="btn btn-primary" id="events-kepek-btn-url-to-own" disabled>
                    URL → Saját
                </button>
                <span class="events-kepek-esemenyek__selected" id="events-kepek-selected-label" aria-live="polite">0 kiválasztva</span>
                <span class="events-kepek-esemenyek__toolbar-hint help">A kijelölt URL típusú események képét letölti és eventpics-be menti.</span>
            </div>
        </form>

        <p class="events-kepek-esemenyek__count" aria-live="polite">
            <strong><span id="events-kepek-visible-count">0</span></strong>
            / <span id="events-kepek-total-count"><?= (int) $listDisplayedCount ?></span> esemény
            <?php if ($listDisplayedCount < $listPoolCount): ?>
                <span class="help"> (<?= h(events_admin_list_count_label($listDisplayedCount, $listPoolCount)) ?>)</span>
            <?php endif; ?>
        </p>

        <div class="table-wrap events-admin-table-wrap">
            <table class="sortable-table events-admin-table events-kepek-esemenyek__table" id="events-kepek-table">
                <thead>
                    <tr>
                        <th class="events-kepek-esemenyek__th-check">
                            <label class="events-kepek-esemenyek__check-all" title="Látható URL sorok kijelölése">
                                <input type="checkbox" id="events-kepek-check-all" aria-label="Látható URL sorok kijelölése">
                            </label>
                        </th>
                        <th>Név</th>
                        <th>Kiemelt kép típus</th>
                        <th>URL</th>
                        <th>Saját link</th>
                    </tr>
                    <tr class="events-kepek-esemenyek__filter-row">
                        <th><span class="visually-hidden">Kijelölés</span></th>
                        <th>
                            <label class="visually-hidden" for="events-kepek-filter-name">Szűrés név szerint</label>
                            <input type="search" class="events-filter-input events-kepek-esemenyek__filter" id="events-kepek-filter-name" data-filter="name" placeholder="Név…" autocomplete="off">
                        </th>
                        <th>
                            <label class="visually-hidden" for="events-kepek-filter-type">Szűrés típus szerint</label>
                            <select class="events-filter-input events-kepek-esemenyek__filter" id="events-kepek-filter-type" data-filter="type">
                                <option value="">Bármely</option>
                                <option value="none">—</option>
                                <option value="url">URL</option>
                                <option value="own">Saját</option>
                            </select>
                        </th>
                        <th>
                            <label class="visually-hidden" for="events-kepek-filter-url">Szűrés URL szerint</label>
                            <input type="search" class="events-filter-input events-kepek-esemenyek__filter" id="events-kepek-filter-url" data-filter="url" placeholder="URL…" autocomplete="off" spellcheck="false">
                        </th>
                        <th>
                            <label class="visually-hidden" for="events-kepek-filter-own">Szűrés saját link szerint</label>
                            <input type="search" class="events-filter-input events-kepek-esemenyek__filter" id="events-kepek-filter-own" data-filter="own" placeholder="Saját link…" autocomplete="off" spellcheck="false">
                        </th>
                    </tr>
                </thead>
                <tbody id="events-kepek-tbody">
                    <?php foreach ($eventRows as $row): ?>
                        <?php
                        $eid = (int) ($row['id'] ?? 0);
                        $edit = $editBase . $eid;
                        $meta = $row['featured_meta'];
                        $name = (string) ($row['event_name'] ?? '');
                        $searchName = mb_strtolower($name, 'UTF-8');
                        $urlDisplay = (string) ($meta['url'] ?? '');
                        $ownDisplay = (string) ($meta['own_link'] ?? '');
                        $urlHref = $urlDisplay !== '' ? events_absolute_url($urlDisplay) : '';
                        $ownHref = $ownDisplay !== '' ? events_absolute_url($ownDisplay) : '';
                        $isUrlType = ($meta['type'] ?? '') === 'url';
                        ?>
                        <tr
                            data-kepek-row
                            data-name="<?= h($searchName) ?>"
                            data-type="<?= h((string) ($meta['search_type'] ?? '')) ?>"
                            data-url="<?= h((string) ($meta['search_url'] ?? '')) ?>"
                            data-own="<?= h((string) ($meta['search_own'] ?? '')) ?>"
                            data-can-migrate="<?= $isUrlType ? '1' : '0' ?>"
                        >
                            <td class="events-kepek-esemenyek__td-check">
                                <?php if ($isUrlType): ?>
                                    <input
                                        type="checkbox"
                                        class="events-kepek-esemenyek__row-check"
                                        form="events-kepek-bulk-form"
                                        name="event_ids[]"
                                        value="<?= $eid ?>"
                                        aria-label="Kijelölés: <?= h($name) ?>"
                                    >
                                <?php else: ?>
                                    <span class="events-kepek-esemenyek__check-placeholder" aria-hidden="true">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a class="events-cell-edit" href="<?= h($edit) ?>"><?= h($name) ?></a>
                                <span class="events-kepek-esemenyek__id">#<?= $eid ?></span>
                            </td>
                            <td><?= h((string) ($meta['type_label'] ?? '—')) ?></td>
                            <td class="events-kepek-esemenyek__url-cell">
                                <?php if ($urlDisplay !== ''): ?>
                                    <a href="<?= h($urlHref) ?>" target="_blank" rel="noopener noreferrer" class="events-kepek-esemenyek__link" title="<?= h($urlDisplay) ?>"><?= h($urlDisplay) ?></a>
                                <?php else: ?>
                                    <span class="events-kepek-esemenyek__empty">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="events-kepek-esemenyek__own-cell">
                                <?php if ($ownDisplay !== ''): ?>
                                    <a href="<?= h($ownHref) ?>" target="_blank" rel="noopener noreferrer" class="events-kepek-esemenyek__link" title="<?= h($ownDisplay) ?>"><?= h($ownDisplay) ?></a>
                                <?php else: ?>
                                    <span class="events-kepek-esemenyek__empty">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr id="events-kepek-empty" hidden>
                        <td colspan="5" class="events-kepek-esemenyek__no-results">Nincs találat a szűrőkre.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <script>
        (function () {
            var tbody = document.getElementById('events-kepek-tbody');
            if (!tbody) return;

            var rows = Array.prototype.slice.call(tbody.querySelectorAll('[data-kepek-row]'));
            var emptyRow = document.getElementById('events-kepek-empty');
            var visibleCountEl = document.getElementById('events-kepek-visible-count');
            var filters = Array.prototype.slice.call(document.querySelectorAll('.events-kepek-esemenyek__filter'));
            var searchTimer = null;
            var bulkForm = document.getElementById('events-kepek-bulk-form');
            var bulkBtn = document.getElementById('events-kepek-btn-url-to-own');
            var selectedLabel = document.getElementById('events-kepek-selected-label');
            var checkAll = document.getElementById('events-kepek-check-all');

            function filterValue(key) {
                var el = document.querySelector('.events-kepek-esemenyek__filter[data-filter="' + key + '"]');
                if (!el) return '';
                return (el.value || '').trim().toLowerCase();
            }

            function rowMatches(row) {
                var nameQ = filterValue('name');
                if (nameQ !== '' && (row.getAttribute('data-name') || '').indexOf(nameQ) === -1) {
                    return false;
                }

                var typeQ = filterValue('type');
                if (typeQ !== '' && row.getAttribute('data-type') !== typeQ) {
                    return false;
                }

                var urlQ = filterValue('url');
                if (urlQ !== '' && (row.getAttribute('data-url') || '').indexOf(urlQ) === -1) {
                    return false;
                }

                var ownQ = filterValue('own');
                if (ownQ !== '' && (row.getAttribute('data-own') || '').indexOf(ownQ) === -1) {
                    return false;
                }

                return true;
            }

            function visibleMigratableChecks() {
                var out = [];
                rows.forEach(function (row) {
                    if (row.hidden) return;
                    if (row.getAttribute('data-can-migrate') !== '1') return;
                    var cb = row.querySelector('.events-kepek-esemenyek__row-check');
                    if (cb) out.push(cb);
                });
                return out;
            }

            function syncBulkUi() {
                var checked = 0;
                rows.forEach(function (row) {
                    var cb = row.querySelector('.events-kepek-esemenyek__row-check');
                    if (cb && cb.checked) checked++;
                });
                if (selectedLabel) {
                    selectedLabel.textContent = checked + ' kiválasztva';
                }
                if (bulkBtn) {
                    bulkBtn.disabled = checked === 0;
                }
                if (checkAll) {
                    var visible = visibleMigratableChecks();
                    if (visible.length === 0) {
                        checkAll.checked = false;
                        checkAll.indeterminate = false;
                        checkAll.disabled = true;
                    } else {
                        checkAll.disabled = false;
                        var visChecked = visible.filter(function (cb) { return cb.checked; }).length;
                        checkAll.checked = visChecked === visible.length && visible.length > 0;
                        checkAll.indeterminate = visChecked > 0 && visChecked < visible.length;
                    }
                }
            }

            function applyFilters() {
                var visible = 0;
                rows.forEach(function (row) {
                    var show = rowMatches(row);
                    row.hidden = !show;
                    if (!show) {
                        var cb = row.querySelector('.events-kepek-esemenyek__row-check');
                        if (cb) cb.checked = false;
                    }
                    if (show) visible++;
                });
                if (visibleCountEl) visibleCountEl.textContent = String(visible);
                if (emptyRow) emptyRow.hidden = visible > 0;
                syncBulkUi();
            }

            filters.forEach(function (el) {
                if (el.type === 'search') {
                    el.addEventListener('input', function () {
                        clearTimeout(searchTimer);
                        searchTimer = setTimeout(applyFilters, 120);
                    });
                } else {
                    el.addEventListener('change', applyFilters);
                    el.addEventListener('input', applyFilters);
                }
            });

            if (checkAll) {
                checkAll.addEventListener('change', function () {
                    var on = checkAll.checked;
                    visibleMigratableChecks().forEach(function (cb) {
                        cb.checked = on;
                    });
                    syncBulkUi();
                });
            }

            tbody.addEventListener('change', function (e) {
                if (e.target && e.target.classList.contains('events-kepek-esemenyek__row-check')) {
                    syncBulkUi();
                }
            });

            if (bulkForm) {
                bulkForm.addEventListener('submit', function (e) {
                    var checked = tbody.querySelectorAll('.events-kepek-esemenyek__row-check:checked').length;
                    if (checked === 0) {
                        e.preventDefault();
                        return;
                    }
                    if (!window.confirm('Biztosan átállítod ' + checked + ' esemény képét URL-ről sajátra? A képek letöltődnek az eventpics mappába.')) {
                        e.preventDefault();
                    }
                });
            }

            applyFilters();
        })();
        </script>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/admin_list_display_limit_script.php'; ?>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
