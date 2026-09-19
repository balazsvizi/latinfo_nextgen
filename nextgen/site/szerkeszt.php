<?php
declare(strict_types=1);

/**
 * Latinfo.hu kezdőoldal – modulok sorrendje (asztali bal/jobb + mobil) és be/ki.
 */

require_once dirname(__DIR__) . '/init.php';
requireLogin();
require_once dirname(__DIR__) . '/events/bootstrap.php';
require_once __DIR__ . '/lib/site_modules.php';

$db = getDb();
$schemaOk = latinfo_home_modules_ensure_schema($db);
$hiba = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require('latinfo_home_modules', '_csrf', latinfo_home_edit_url());
    $action = (string) ($_POST['action'] ?? '');

    if (!$schemaOk) {
        $hiba = 'A kezdőoldal táblái nem hozhatók létre. Ellenőrizd az adatbázis-jogosultságokat.';
    } elseif ($action === 'save_modules') {
        try {
            $enabled = $_POST['is_enabled'] ?? [];
            $railKeys = $_POST['module_key_rail'] ?? [];
            $calendarKeys = $_POST['module_key_calendar'] ?? [];
            $mobileKeys = $_POST['module_key_mobile'] ?? [];
            if (!is_array($railKeys)) {
                $railKeys = [];
            }
            if (!is_array($calendarKeys)) {
                $calendarKeys = [];
            }
            if (!is_array($mobileKeys)) {
                $mobileKeys = [];
            }

            $desktopMeta = [];
            $order = 10;
            foreach ($railKeys as $key) {
                $key = trim((string) $key);
                if ($key === '' || isset($desktopMeta[$key])) {
                    continue;
                }
                $desktopMeta[$key] = ['column' => 'rail', 'sort_order' => $order];
                $order += 10;
            }
            foreach ($calendarKeys as $key) {
                $key = trim((string) $key);
                if ($key === '' || isset($desktopMeta[$key])) {
                    continue;
                }
                $desktopMeta[$key] = ['column' => 'calendar', 'sort_order' => $order];
                $order += 10;
            }

            $mobileOrder = [];
            foreach ($mobileKeys as $i => $key) {
                $key = trim((string) $key);
                if ($key === '' || isset($mobileOrder[$key])) {
                    continue;
                }
                $mobileOrder[$key] = ($i + 1) * 10;
            }

            $rows = [];
            foreach (latinfo_home_module_catalog() as $key => $meta) {
                $fallbackCol = ((string) ($meta['column'] ?? 'rail') === 'calendar') ? 'calendar' : 'rail';
                $desk = $desktopMeta[$key] ?? null;
                $rows[] = [
                    'module_key' => $key,
                    'is_enabled' => isset($enabled[$key]) && (string) $enabled[$key] === '1',
                    'sort_order' => $desk['sort_order'] ?? (int) $meta['default_order'],
                    'sort_order_mobile' => $mobileOrder[$key] ?? (int) $meta['default_order_mobile'],
                    'column' => $desk['column'] ?? $fallbackCol,
                ];
            }
            latinfo_home_modules_save_order($db, $rows);
            if (function_exists('rendszer_log')) {
                rendszer_log('kezdőoldal_modulok', 0, 'Sorrend mentve', '');
            }
            flash('success', 'A modulok sorrendje és láthatósága mentve.');
            redirect(latinfo_home_edit_url());
        } catch (Throwable $e) {
            error_log('latinfo home modules save: ' . $e->getMessage());
            $hiba = 'A mentés nem sikerült.';
        }
    } else {
        $hiba = 'Ismeretlen művelet.';
    }
}

$modulesDesktop = $schemaOk ? latinfo_home_modules_all($db, 'desktop') : [];
$modulesMobile = $schemaOk ? latinfo_home_modules_all($db, 'mobile') : [];
$railModules = [];
$calendarModules = [];
$enabledByKey = [];
foreach ($modulesDesktop as $mod) {
    $key = (string) $mod['module_key'];
    $enabledByKey[$key] = !empty($mod['is_enabled']);
    if ((string) ($mod['column'] ?? 'rail') === 'calendar') {
        $calendarModules[] = $mod;
    } else {
        $railModules[] = $mod;
    }
}

/**
 * @param array<string, mixed> $mod
 * @param array<string, bool> $enabledByKey
 */
$renderCard = static function (array $mod, array $enabledByKey, string $inputName, bool $showToggle): void {
    $key = (string) $mod['module_key'];
    $isOn = !empty($enabledByKey[$key]);
    $label = (string) $mod['label'];
    ?>
    <li class="lh-mod-card<?= $isOn ? ' is-on' : ' is-off' ?>" draggable="true" data-module-key="<?= h($key) ?>">
        <span class="lh-mod-handle" title="Húzd átrendezéshez" aria-hidden="true">⋮⋮</span>
        <div class="lh-mod-card__body">
            <div class="lh-mod-card__title">
                <strong><?= h($label) ?></strong>
                <span class="lh-mod-state" data-state-for="<?= h($key) ?>"><?= $isOn ? 'Be' : 'Ki' ?></span>
            </div>
            <?php if (!empty($mod['editable'])): ?>
                <a class="lh-mod-edit" href="<?= h(latinfo_home_module_edit_url($key)) ?>">Szerkeszt</a>
            <?php endif; ?>
        </div>
        <?php if ($showToggle): ?>
            <button
                type="button"
                class="lh-mod-toggle<?= $isOn ? ' is-on' : ' is-off' ?>"
                data-toggle-key="<?= h($key) ?>"
                aria-pressed="<?= $isOn ? 'true' : 'false' ?>"
                aria-label="<?= h($label . ($isOn ? ' – bekapcsolva' : ' – kikapcsolva')) ?>"
            ><?= $isOn ? 'Be' : 'Ki' ?></button>
        <?php endif; ?>
        <input type="hidden" name="<?= h($inputName) ?>[]" value="<?= h($key) ?>">
    </li>
    <?php
};

$pageTitle = 'Kezdőoldal modulok';
$mainContentClass = 'main-content main-content--fullwidth';
$extraHead = '<style>
.lh-mod-grid{display:grid;grid-template-columns:1fr;gap:1.25rem;align-items:start}
@media (min-width:960px){.lh-mod-grid{grid-template-columns:2fr 1fr;gap:1.35rem}}
.lh-mod-pane{min-width:0;padding:1rem;border:1px solid var(--border);border-radius:.75rem;background:rgba(255,255,255,.4)}
.lh-mod-pane h3{margin:0 0 .35rem;font-size:1.05rem}
.lh-mod-hint{margin:.2rem 0 .9rem;color:var(--muted,#667);font-size:.9rem}
.lh-mod-cols{display:grid;grid-template-columns:1fr 1fr;gap:.85rem}
@media (max-width:640px){.lh-mod-cols{grid-template-columns:1fr}}
.lh-mod-col-box{min-height:12rem;padding:.65rem;border:1px dashed rgba(109,143,99,.45);border-radius:.65rem;background:rgba(247,250,245,.7)}
.lh-mod-col-box.is-drag-over{border-color:var(--primary,#6d8f63);background:rgba(109,143,99,.1)}
.lh-mod-col-box h4{margin:0 0 .55rem;font-size:.82rem;text-transform:uppercase;letter-spacing:.04em;color:var(--muted,#556)}
.lh-mod-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:.45rem;min-height:2.5rem}
.lh-mod-card{display:flex;align-items:center;gap:.5rem;padding:.55rem .6rem;border:1px solid var(--border);border-radius:.55rem;background:#fff;cursor:grab;transition:opacity .15s,border-color .15s,background .15s}
.lh-mod-card.is-dragging{opacity:.5;cursor:grabbing}
.lh-mod-card.is-off{background:#f3f1ef;border-color:#d7d0c8;opacity:.92}
.lh-mod-card.is-on{border-color:rgba(109,143,99,.55);box-shadow:inset 3px 0 0 var(--primary,#6d8f63)}
.lh-mod-handle{flex:0 0 auto;color:var(--muted,#778);user-select:none;letter-spacing:-.08em}
.lh-mod-card__body{flex:1 1 auto;min-width:0}
.lh-mod-card__title{display:flex;flex-wrap:wrap;align-items:center;gap:.35rem}
.lh-mod-state{display:inline-block;padding:.12rem .45rem;border-radius:999px;font-size:.72rem;font-weight:700;letter-spacing:.02em}
.lh-mod-card.is-on .lh-mod-state{background:rgba(109,143,99,.18);color:#2f5a2c}
.lh-mod-card.is-off .lh-mod-state{background:rgba(160,80,60,.16);color:#8a3b28}
.lh-mod-edit{display:inline-block;margin-top:.25rem;font-size:.8rem}
.lh-mod-toggle{flex:0 0 auto;min-width:2.6rem;padding:.35rem .55rem;border:1px solid transparent;border-radius:.45rem;font-weight:700;font-size:.8rem;cursor:pointer}
.lh-mod-toggle.is-on{background:var(--primary,#6d8f63);color:#fff}
.lh-mod-toggle.is-off{background:#e8e0d8;color:#6a4a3a;border-color:#d2c4b8}
.lh-mod-enabled-store{display:none}
</style>';

require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>

<div class="card lh-admin">
    <div class="events-list-head">
        <h2 class="events-list-title">Kezdőoldal modulok</h2>
        <div class="events-list-actions">
            <a href="<?= h(latinfo_home_preview_url()) ?>" class="btn btn-secondary btn-sm">Előnézet</a>
            <a href="<?= h(latinfo_home_modules_stat_url()) ?>" class="btn btn-secondary btn-sm">Stat</a>
        </div>
    </div>
    <p class="text-muted" style="margin-top:0">
        Asztalin húzd a modulokat bal/jobb oszlopba. Mobilon állítsd a függőleges sorrendet.
        A be/ki kapcsolás közös; a Kikapcsolt modulok nem jelennek meg a kezdőoldalon.
    </p>

    <?php if (!$schemaOk): ?>
        <p class="alert alert-error">A kezdőoldal táblái nem érhetők el.</p>
    <?php else: ?>
        <form method="post" action="<?= h(latinfo_home_edit_url()) ?>" id="lh-modules-form">
            <?= csrf_input('latinfo_home_modules') ?>
            <input type="hidden" name="action" value="save_modules">
            <div class="lh-mod-enabled-store" aria-hidden="true">
                <?php foreach ($modulesDesktop as $mod): ?>
                    <?php $key = (string) $mod['module_key']; ?>
                    <input type="hidden" name="is_enabled[<?= h($key) ?>]" value="<?= !empty($enabledByKey[$key]) ? '1' : '0' ?>" data-enabled-input="<?= h($key) ?>">
                <?php endforeach; ?>
            </div>

            <div class="lh-mod-grid">
                <section class="lh-mod-pane" aria-labelledby="lh-mod-desktop-title">
                    <h3 id="lh-mod-desktop-title">Asztali nézet</h3>
                    <p class="lh-mod-hint">Húzd a kártyákat az oszlopok között (bal ↔ jobb).</p>
                    <div class="lh-mod-cols">
                        <div class="lh-mod-col-box" data-drop-column="rail">
                            <h4>Bal oszlop</h4>
                            <ul class="lh-mod-list" data-sortable data-column="rail">
                                <?php foreach ($railModules as $mod): ?>
                                    <?php $renderCard($mod, $enabledByKey, 'module_key_rail', true); ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="lh-mod-col-box" data-drop-column="calendar">
                            <h4>Jobb oszlop</h4>
                            <ul class="lh-mod-list" data-sortable data-column="calendar">
                                <?php foreach ($calendarModules as $mod): ?>
                                    <?php $renderCard($mod, $enabledByKey, 'module_key_calendar', true); ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </section>

                <section class="lh-mod-pane" aria-labelledby="lh-mod-mobile-title">
                    <h3 id="lh-mod-mobile-title">Mobil nézet</h3>
                    <p class="lh-mod-hint">Egy oszlop, fentről lefelé. Be/ki itt is állítható.</p>
                    <ul class="lh-mod-list" data-sortable data-column="mobile">
                        <?php foreach ($modulesMobile as $mod): ?>
                            <?php $renderCard($mod, $enabledByKey, 'module_key_mobile', true); ?>
                        <?php endforeach; ?>
                    </ul>
                </section>
            </div>

            <div style="margin-top:1.25rem">
                <button type="submit" class="btn btn-primary">Mentés</button>
            </div>
        </form>
        <script>
        (function () {
            var form = document.getElementById('lh-modules-form');
            if (!form) return;

            function setEnabled(key, on) {
                form.querySelectorAll('[data-enabled-input="' + key + '"]').forEach(function (input) {
                    input.value = on ? '1' : '0';
                });
                form.querySelectorAll('[data-module-key="' + key + '"]').forEach(function (card) {
                    card.classList.toggle('is-on', on);
                    card.classList.toggle('is-off', !on);
                    var state = card.querySelector('[data-state-for="' + key + '"]');
                    if (state) state.textContent = on ? 'Be' : 'Ki';
                });
                form.querySelectorAll('[data-toggle-key="' + key + '"]').forEach(function (btn) {
                    btn.classList.toggle('is-on', on);
                    btn.classList.toggle('is-off', !on);
                    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                    btn.textContent = on ? 'Be' : 'Ki';
                    var label = (btn.getAttribute('aria-label') || '').replace(/ – .+$/, '');
                    btn.setAttribute('aria-label', label + (on ? ' – bekapcsolva' : ' – kikapcsolva'));
                });
            }

            form.addEventListener('click', function (e) {
                var btn = e.target && e.target.closest ? e.target.closest('[data-toggle-key]') : null;
                if (!btn) return;
                e.preventDefault();
                var key = btn.getAttribute('data-toggle-key') || '';
                if (!key) return;
                var on = !btn.classList.contains('is-on');
                setEnabled(key, on);
            });

            function refreshNames(list) {
                var column = list.getAttribute('data-column') || '';
                var name = column === 'rail' ? 'module_key_rail[]'
                    : (column === 'calendar' ? 'module_key_calendar[]' : 'module_key_mobile[]');
                list.querySelectorAll('li[data-module-key]').forEach(function (card) {
                    var input = card.querySelector('input[type="hidden"][name^="module_key_"]');
                    if (input) input.name = name;
                });
            }

            var dragCard = null;
            var dragFrom = null;

            function clearOver() {
                form.querySelectorAll('.is-drag-over').forEach(function (el) {
                    el.classList.remove('is-drag-over');
                });
            }

            form.querySelectorAll('[data-sortable]').forEach(function (list) {
                list.addEventListener('dragstart', function (e) {
                    var card = e.target && e.target.closest ? e.target.closest('li[data-module-key]') : null;
                    if (!card || !list.contains(card)) return;
                    if (e.target.closest('a, button')) {
                        e.preventDefault();
                        return;
                    }
                    dragCard = card;
                    dragFrom = list;
                    card.classList.add('is-dragging');
                    try {
                        e.dataTransfer.effectAllowed = 'move';
                        e.dataTransfer.setData('text/plain', card.getAttribute('data-module-key') || '');
                    } catch (err) {}
                });

                list.addEventListener('dragend', function () {
                    if (dragCard) dragCard.classList.remove('is-dragging');
                    dragCard = null;
                    dragFrom = null;
                    clearOver();
                    form.querySelectorAll('[data-sortable]').forEach(refreshNames);
                });

                list.addEventListener('dragover', function (e) {
                    if (!dragCard) return;
                    e.preventDefault();
                    try { e.dataTransfer.dropEffect = 'move'; } catch (err) {}
                    var box = list.closest('[data-drop-column]') || list;
                    clearOver();
                    box.classList.add('is-drag-over');
                    var target = e.target && e.target.closest ? e.target.closest('li[data-module-key]') : null;
                    if (target && list.contains(target) && target !== dragCard) {
                        var rect = target.getBoundingClientRect();
                        var before = (e.clientY - rect.top) < rect.height / 2;
                        list.insertBefore(dragCard, before ? target : target.nextSibling);
                    } else if (!target) {
                        list.appendChild(dragCard);
                    }
                });

                list.addEventListener('drop', function (e) {
                    e.preventDefault();
                    clearOver();
                    form.querySelectorAll('[data-sortable]').forEach(refreshNames);
                });
            });
        })();
        </script>
    <?php endif; ?>
</div>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
