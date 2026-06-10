<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/eventpics.php';
requireSuperadmin();

$db = getDb();
$eventRows = events_featured_image_admin_all_events($db);
$editBase = events_url('szerkeszt.php?id=');

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Képek–események';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<div class="card events-kepek-esemenyek">
    <h1 class="card-title">Képek–események</h1>
    <p class="help events-kepek-esemenyek__intro">
        Az összes esemény kiemelt kép beállítása. A szűrők azonnal frissítik a listát.
        Csak superadmin érheti el.
    </p>

    <?php if ($eventRows === []): ?>
        <p class="help">Nincs esemény az adatbázisban.</p>
    <?php else: ?>
        <p class="events-kepek-esemenyek__count" aria-live="polite">
            <strong><span id="events-kepek-visible-count">0</span></strong>
            / <span id="events-kepek-total-count"><?= count($eventRows) ?></span> esemény
        </p>

        <div class="table-wrap events-admin-table-wrap">
            <table class="sortable-table events-admin-table events-kepek-esemenyek__table" id="events-kepek-table">
                <thead>
                    <tr>
                        <th>Név</th>
                        <th>Kiemelt kép típus</th>
                        <th>URL</th>
                        <th>Saját link</th>
                    </tr>
                    <tr class="events-kepek-esemenyek__filter-row">
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
                        ?>
                        <tr
                            data-kepek-row
                            data-name="<?= h($searchName) ?>"
                            data-type="<?= h((string) ($meta['search_type'] ?? '')) ?>"
                            data-url="<?= h((string) ($meta['search_url'] ?? '')) ?>"
                            data-own="<?= h((string) ($meta['search_own'] ?? '')) ?>"
                        >
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
                        <td colspan="4" class="events-kepek-esemenyek__no-results">Nincs találat a szűrőkre.</td>
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

            function applyFilters() {
                var visible = 0;
                rows.forEach(function (row) {
                    var show = rowMatches(row);
                    row.hidden = !show;
                    if (show) visible++;
                });
                if (visibleCountEl) visibleCountEl.textContent = String(visible);
                if (emptyRow) emptyRow.hidden = visible > 0;
            }

            filters.forEach(function (el) {
                var isSearch = el.type === 'search' || el.tagName === 'INPUT';
                if (isSearch && el.type === 'search') {
                    el.addEventListener('input', function () {
                        clearTimeout(searchTimer);
                        searchTimer = setTimeout(applyFilters, 120);
                    });
                } else {
                    el.addEventListener('change', applyFilters);
                    el.addEventListener('input', applyFilters);
                }
            });

            applyFilters();
        })();
        </script>
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
