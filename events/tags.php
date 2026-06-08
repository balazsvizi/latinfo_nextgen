<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/nextgen/includes/auth.php';
require_once __DIR__ . '/lib/event_request.php';
require_once __DIR__ . '/lib/tag_type.php';
requireLogin();

$db = getDb();

if (!events_tags_tables_available($db)) {
    $mainContentClass = 'main-content main-content--fullwidth';
    $pageTitle = 'Címkék';
    require_once dirname(__DIR__) . '/nextgen/partials/header.php';
    echo '<div class="card events-admin-card">';
    echo '<p class="alert alert-error">Hiányoznak a címke táblák. Futtasd: <code>events/sql/migration_tags.sql</code></p>';
    echo '<p><a href="' . h(events_url('events_admin.php')) . '" class="btn btn-secondary">Vissza az eseményekhez</a></p>';
    echo '</div>';
    require_once dirname(__DIR__) . '/nextgen/partials/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!csrf_validate('events_tags')) {
        flash('error', 'Lejárt vagy érvénytelen munkamenet.');
        redirect(events_url('tags.php'));
    }

    if ($action === 'save_tag') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            flash('error', 'A címke neve kötelező.');
            redirect(events_url('tags.php?open_tag=') . ($id > 0 ? (string) $id : 'new'));
        }
        $typeRaw = $_POST['tag_type_codes'] ?? [];
        $typeCodes = events_tag_type_normalize_codes(is_array($typeRaw) ? $typeRaw : []);

        if ($id > 0) {
            $db->beginTransaction();
            try {
                $st = $db->prepare('UPDATE `events_tags` SET `name` = ? WHERE `id` = ?');
                $st->execute([$name, $id]);
                events_save_tag_types($db, $id, $typeCodes);
                $db->commit();
            } catch (Throwable $e) {
                $db->rollBack();
                throw $e;
            }
            flash('success', 'Címke mentve.');
            rendszer_log('tag', $id, 'Módosítva', $name);
            redirect(events_url('tags.php'));
        }

        $db->beginTransaction();
        try {
            $ins = $db->prepare('INSERT INTO `events_tags` (`name`) VALUES (?)');
            $ins->execute([$name]);
            $newId = (int) $db->lastInsertId();
            events_save_tag_types($db, $newId, $typeCodes);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        flash('success', 'Címke létrehozva.');
        rendszer_log('tag', $newId, 'Létrehozva', $name);
        redirect(events_url('tags.php'));
    }

    if ($action === 'delete_tag') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash('error', 'Érvénytelen címke azonosító.');
            redirect(events_url('tags.php'));
        }
        $st = $db->prepare('SELECT `name` FROM `events_tags` WHERE `id` = ?');
        $st->execute([$id]);
        $self = $st->fetch(PDO::FETCH_ASSOC);
        if (!$self) {
            flash('error', 'A címke nem található.');
            redirect(events_url('tags.php'));
        }
        $nm = (string) $self['name'];
        $stUse = $db->prepare('SELECT COUNT(*) FROM `events_calendar_event_tags` WHERE `tag_id` = ?');
        $stUse->execute([$id]);
        $useCnt = (int) $stUse->fetchColumn();
        if ($useCnt > 0) {
            flash('error', 'A címke nem törölhető, mert ' . $useCnt . ' esemény használja.');
            redirect(events_url('tags.php?open_tag=') . $id);
        }
        $db->prepare('DELETE FROM `events_tags` WHERE `id` = ?')->execute([$id]);
        flash('success', 'Címke törölve.');
        rendszer_log('tag', $id, 'Törölve', $nm);
        redirect(events_url('tags.php'));
    }

    redirect(events_url('tags.php'));
}

$tagRows = $db->query('SELECT `id`, `name` FROM `events_tags` ORDER BY `name` ASC, `id` ASC')->fetchAll(PDO::FETCH_ASSOC);

$tagTypesByTag = [];
if (events_tag_types_tables_available($db) && $tagRows !== []) {
    $tagIdsForTypes = array_values(array_unique(array_map(static fn (array $tr): int => (int) $tr['id'], $tagRows)));
    $tagTypesByTag = events_load_tag_types_map($db, $tagIdsForTypes);
}

$typeDisplayMeta = events_tag_type_display_meta();

$openTagRaw = (string) ($_GET['open_tag'] ?? '');
$openTagGroup = '';
if ($openTagRaw === 'new') {
    $openTagGroup = 'new';
} elseif ($openTagRaw !== '' && ctype_digit($openTagRaw)) {
    $openTagGroup = (string) (int) $openTagRaw;
}

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Címkék';
require_once dirname(__DIR__) . '/nextgen/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="card events-admin-card events-tags-admin">
    <div class="events-list-head">
        <h2 class="events-list-title">Esemény címkék</h2>
        <div class="events-list-actions">
            <a href="<?= h(events_url('events_admin.php')) ?>" class="btn btn-secondary">Események listája</a>
        </div>
    </div>

    <div class="table-wrap events-admin-table-wrap events-inline-expand-wrap">
        <table
            class="events-admin-table events-inline-expand-table"
            id="events-tags-inline-table"
            data-sticky-group="new"
            data-initial-open="<?= h($openTagGroup) ?>"
        >
            <thead>
                <tr>
                    <th scope="col">
                        <span class="events-inline-th-label">ID</span>
                        <button type="button" class="events-inline-sort-btn" data-sort-col="0" data-sort-type="int" aria-label="Rendezés ID szerint">↕</button>
                    </th>
                    <th scope="col">
                        <span class="events-inline-th-label">Név</span>
                        <button type="button" class="events-inline-sort-btn" data-sort-col="1" data-sort-type="text" aria-label="Rendezés név szerint">↕</button>
                    </th>
                    <th scope="col">
                        <span class="events-inline-th-label">Típusok</span>
                        <button type="button" class="events-inline-sort-btn" data-sort-col="2" data-sort-type="text" aria-label="Rendezés típus szerint">↕</button>
                    </th>
                </tr>
                <tr class="events-inline-filter-row">
                    <th><input type="search" class="events-inline-filter-input" data-filter-col="0" placeholder="Szűrés…" aria-label="Szűrés ID"></th>
                    <th><input type="search" class="events-inline-filter-input" data-filter-col="1" placeholder="Szűrés…" aria-label="Szűrés név"></th>
                    <th><input type="search" class="events-inline-filter-input" data-filter-col="2" placeholder="Szűrés…" aria-label="Szűrés típus"></th>
                </tr>
            </thead>
            <tbody>
                <tr
                    class="events-inline-summary<?= $openTagGroup === 'new' ? ' is-active' : '' ?>"
                    data-expand-group="new"
                    tabindex="0"
                    role="button"
                    aria-expanded="<?= $openTagGroup === 'new' ? 'true' : 'false' ?>"
                >
                    <td class="events-inline-summary-muted">—</td>
                    <td colspan="2"><strong>Új címke</strong> <span class="events-inline-summary-hint">(kattints a szerkesztéshez)</span></td>
                </tr>
                <tr class="events-inline-detail" data-expand-group="new" <?= $openTagGroup === 'new' ? '' : 'hidden' ?>>
                    <td colspan="3">
                        <div class="events-tags-admin__form-panel events-tags-admin__form-panel--inline">
                            <form method="post" action="<?= h(events_url('tags.php')) ?>">
                                <?= csrf_input('events_tags') ?>
                                <input type="hidden" name="action" value="save_tag">
                                <input type="hidden" name="id" value="0">
                                <div class="form-group">
                                    <label for="tag_name_new">Név *</label>
                                    <input type="text" id="tag_name_new" name="name" required maxlength="255" value="">
                                </div>
                                <?php
                                $tagTypeSelected = [];
                                require __DIR__ . '/partials/tags_types_fieldset.php';
                                ?>
                                <div class="toolbar">
                                    <button type="submit" class="btn btn-primary">Mentés</button>
                                </div>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php foreach ($tagRows as $tr): ?>
                    <?php
                    $tid = (int) $tr['id'];
                    $typeCodesRow = $tagTypesByTag[$tid] ?? [];
                    $isOpen = $openTagGroup !== '' && $openTagGroup === (string) $tid;
                    ?>
                    <tr
                        id="open-tag-<?= $tid ?>"
                        class="events-inline-summary<?= $isOpen ? ' is-active' : '' ?>"
                        data-expand-group="<?= $tid ?>"
                        tabindex="0"
                        role="button"
                        aria-expanded="<?= $isOpen ? 'true' : 'false' ?>"
                    >
                        <td><?= $tid ?></td>
                        <td><?= h((string) $tr['name']) ?></td>
                        <td>
                            <?php if ($typeCodesRow === []): ?>
                                <span class="events-tag-type-table-empty">—</span>
                            <?php else: ?>
                                <span class="events-tag-type-table-pills">
                                    <?php foreach ($typeCodesRow as $code): ?>
                                        <?php
                                        $meta = $typeDisplayMeta[$code] ?? ['icon' => '🏷️', 'tone' => 'default'];
                                        $tone = (string) ($meta['tone'] ?? 'default');
                                        $icon = (string) ($meta['icon'] ?? '🏷️');
                                        ?>
                                        <span class="events-tag-type-pill events-tag-type-pill--<?= h($tone) ?>">
                                            <span class="events-tag-type-pill__icon" aria-hidden="true"><?= $icon ?></span>
                                            <span class="events-tag-type-pill__label"><?= h(events_tag_type_label($code)) ?></span>
                                        </span>
                                    <?php endforeach; ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr class="events-inline-detail" data-expand-group="<?= $tid ?>" <?= $isOpen ? '' : 'hidden' ?>>
                        <td colspan="3">
                            <div class="events-tags-admin__form-panel events-tags-admin__form-panel--inline">
                                <form method="post" action="<?= h(events_url('tags.php')) ?>">
                                    <?= csrf_input('events_tags') ?>
                                    <input type="hidden" name="action" value="save_tag">
                                    <input type="hidden" name="id" value="<?= $tid ?>">
                                    <div class="form-group">
                                        <label for="tag_name_<?= $tid ?>">Név *</label>
                                        <input type="text" id="tag_name_<?= $tid ?>" name="name" required maxlength="255" value="<?= h((string) $tr['name']) ?>">
                                    </div>
                                    <?php
                                    $tagTypeSelected = $typeCodesRow;
                                    require __DIR__ . '/partials/tags_types_fieldset.php';
                                    ?>
                                    <div class="toolbar">
                                        <button type="submit" class="btn btn-primary">Mentés</button>
                                        <a href="<?= h(events_url('tag.php?id=') . $tid) ?>" class="btn btn-secondary" target="_blank" rel="noopener">Nyilvános oldal</a>
                                    </div>
                                </form>
                                <form method="post" action="<?= h(events_url('tags.php')) ?>" class="events-tags-delete-form" onsubmit="return confirm('Biztosan törlöd ezt a címkét?');">
                                    <?= csrf_input('events_tags') ?>
                                    <input type="hidden" name="action" value="delete_tag">
                                    <input type="hidden" name="id" value="<?= $tid ?>">
                                    <button type="submit" class="btn btn-secondary">Címke törlése</button>
                                </form>
                                <p class="help">Törlés csak akkor lehetséges, ha egy esemény sem használja.</p>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    /** data-expand-group értékek: csak `new` vagy pozitív egész (biztonságos querySelector). */
    function validExpandGroup(g) {
        return g === 'new' || /^\d+$/.test(String(g));
    }

    function queryByGroup(tbody, sel, g) {
        if (!validExpandGroup(g)) return null;
        return tbody.querySelector(sel + '[data-expand-group="' + g + '"]');
    }

    function getCellText(tr, colIndex) {
        var td = tr.cells[colIndex];
        return td ? td.textContent.trim() : '';
    }

    function parseSortValue(type, text) {
        if (type === 'int') {
            var n = parseInt(text.replace(/\D/g, ''), 10);
            return isNaN(n) ? 0 : n;
        }
        return text.toLowerCase();
    }

    function collectPairs(tbody, stickyGroup) {
        var summaries = tbody.querySelectorAll('.events-inline-summary');
        var pairs = [];
        summaries.forEach(function (sum) {
            var g = sum.getAttribute('data-expand-group');
            if (!g || !validExpandGroup(g)) return;
            var det = queryByGroup(tbody, '.events-inline-detail', g);
            if (!det) return;
            pairs.push({ group: g, summary: sum, detail: det, sticky: g === stickyGroup });
        });
        return pairs;
    }

    function sortTable(table, colIndex, sortType) {
        var tbody = table.querySelector('tbody');
        if (!tbody) return;
        var stickyGroup = table.getAttribute('data-sticky-group') || 'new';
        var key = 'sortcol-' + colIndex;
        var curDir = table.getAttribute('data-' + key) === 'asc' ? 'asc' : 'desc';
        var nextDir = curDir === 'asc' ? 'desc' : 'asc';
        table.setAttribute('data-' + key, nextDir);
        var dir = nextDir;

        var pairs = collectPairs(tbody, stickyGroup);
        var sticky = pairs.filter(function (p) { return p.sticky; });
        var movable = pairs.filter(function (p) { return !p.sticky; });

        movable.sort(function (a, b) {
            var va = parseSortValue(sortType, getCellText(a.summary, colIndex));
            var vb = parseSortValue(sortType, getCellText(b.summary, colIndex));
            var c = 0;
            if (sortType === 'int') {
                c = va - vb;
            } else {
                c = String(va).localeCompare(String(vb), 'hu', { sensitivity: 'base' });
            }
            return dir === 'asc' ? c : -c;
        });

        var ordered = sticky.concat(movable);
        ordered.forEach(function (p) {
            tbody.appendChild(p.summary);
            tbody.appendChild(p.detail);
        });
        if (table.id === 'events-tags-inline-table') {
            syncTagsBulkMaster();
        }
    }

    function applyFilters(table) {
        var tbody = table.querySelector('tbody');
        if (!tbody) return;
        var inputs = table.querySelectorAll('.events-inline-filter-input');
        var filters = [];
        inputs.forEach(function (inp) {
            var c = parseInt(inp.getAttribute('data-filter-col'), 10);
            filters[c] = (inp.value || '').trim().toLowerCase();
        });

        var summaries = tbody.querySelectorAll('.events-inline-summary');
        summaries.forEach(function (sum) {
            var g = sum.getAttribute('data-expand-group');
            if (!validExpandGroup(g)) return;
            var det = queryByGroup(tbody, '.events-inline-detail', g);
            var show = true;
            for (var col = 0; col < filters.length; col++) {
                if (!filters[col]) continue;
                var txt = getCellText(sum, col).toLowerCase();
                if (txt.indexOf(filters[col]) === -1) {
                    show = false;
                    break;
                }
            }
            sum.style.display = show ? '' : 'none';
            if (det) det.style.display = show ? '' : 'none';
            if (table.id === 'events-tags-inline-table' && !show) {
                var hideCb = sum.querySelector('input[name="tag_ids[]"]');
                if (hideCb) {
                    hideCb.checked = false;
                }
            }
        });
        if (table.id === 'events-tags-inline-table') {
            syncTagsBulkMaster();
        }
    }

    function closeAllDetails(tbody, exceptGroup) {
        tbody.querySelectorAll('.events-inline-detail').forEach(function (det) {
            var g = det.getAttribute('data-expand-group');
            if (exceptGroup !== null && g === exceptGroup) return;
            det.hidden = true;
        });
        tbody.querySelectorAll('.events-inline-summary').forEach(function (sum) {
            var g = sum.getAttribute('data-expand-group');
            if (exceptGroup !== null && g === exceptGroup) return;
            sum.classList.remove('is-active');
            sum.setAttribute('aria-expanded', 'false');
        });
    }

    function setOpen(tbody, group, open) {
        if (!validExpandGroup(group)) return;
        var sum = queryByGroup(tbody, '.events-inline-summary', group);
        var det = queryByGroup(tbody, '.events-inline-detail', group);
        if (!sum || !det) return;
        if (open) {
            closeAllDetails(tbody, group);
            det.hidden = false;
            sum.classList.add('is-active');
            sum.setAttribute('aria-expanded', 'true');
        } else {
            det.hidden = true;
            sum.classList.remove('is-active');
            sum.setAttribute('aria-expanded', 'false');
        }
    }

    function toggleGroup(table, group) {
        var tbody = table.querySelector('tbody');
        if (!tbody) return;
        if (!validExpandGroup(group)) return;
        var det = queryByGroup(tbody, '.events-inline-detail', group);
        if (!det) return;
        var willOpen = det.hidden;
        if (willOpen) {
            setOpen(tbody, group, true);
        } else {
            setOpen(tbody, group, false);
        }
    }

    function bindExpandTable(table) {
        var tbody = table.querySelector('tbody');
        if (!tbody) return;

        var initial = table.getAttribute('data-initial-open') || '';
        if (initial) {
            setOpen(tbody, initial, true);
            window.requestAnimationFrame(function () {
                var anchor = document.getElementById('open-tag-' + initial);
                if (anchor) {
                    anchor.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                }
                var det = queryByGroup(tbody, '.events-inline-detail', initial);
                if (det) {
                    var nameInput = det.querySelector('input[type="text"][name="name"]');
                    if (nameInput) {
                        try { nameInput.focus({ preventScroll: true }); } catch (e) {}
                    }
                }
            });
        }

        tbody.addEventListener('click', function (e) {
            var sum = e.target.closest('.events-inline-summary');
            if (!sum || !tbody.contains(sum)) return;
            if (e.target.closest('a, button, input, textarea, select, label')) return;
            var g = sum.getAttribute('data-expand-group');
            if (!g) return;
            var det = queryByGroup(tbody, '.events-inline-detail', g);
            if (!det) return;
            toggleGroup(table, g);
        });

        tbody.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            if (e.target.closest('input[type="checkbox"], input[type="search"], button')) return;
            var sum = e.target.closest('.events-inline-summary');
            if (!sum || !tbody.contains(sum)) return;
            e.preventDefault();
            var g = sum.getAttribute('data-expand-group');
            if (g) toggleGroup(table, g);
        });

        table.querySelectorAll('.events-inline-sort-btn').forEach(function (btn) {
            btn.addEventListener('click', function (ev) {
                ev.stopPropagation();
                var col = parseInt(btn.getAttribute('data-sort-col'), 10);
                var typ = btn.getAttribute('data-sort-type') || 'text';
                sortTable(table, col, typ);
            });
        });

        table.querySelectorAll('.events-inline-filter-input').forEach(function (inp) {
            inp.addEventListener('input', function () {
                applyFilters(table);
            });
        });
    }

    var tagTableEl = document.getElementById('events-tags-inline-table');
    if (tagTableEl) {
        bindExpandTable(tagTableEl);
    }
})();
</script>

<?php require_once dirname(__DIR__) . '/nextgen/partials/footer.php'; ?>
