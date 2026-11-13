<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/dance_schools.php';
requireLogin();

$db = getDb();
dance_schools_ensure_schema($db);

$hiba = '';
$keepId = (int) ($_POST['keep_id'] ?? $_GET['keep_id'] ?? $_GET['keep'] ?? 0);
$absorbId = (int) ($_POST['absorb_id'] ?? $_GET['absorb_id'] ?? $_GET['absorb'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['merge_dance_schools'])) {
    if (!csrf_validate('tanciskola_osszevonas')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet.';
    } else {
        $result = dance_school_merge($db, $keepId, $absorbId);
        if (!empty($result['ok'])) {
            $keepName = trim((string) ($result['keep_name'] ?? ''));
            $absorbName = trim((string) ($result['absorb_name'] ?? ''));
            $venueTotal = (int) ($result['venues_moved'] ?? 0) + (int) ($result['venues_merged'] ?? 0);
            $teacherTotal = (int) ($result['teachers_moved'] ?? 0) + (int) ($result['teachers_merged'] ?? 0);
            $partnerTotal = (int) ($result['partners_moved'] ?? 0) + (int) ($result['partners_merged'] ?? 0);
            if (function_exists('rendszer_log')) {
                rendszer_log(
                    'dance_school',
                    (int) ($result['keep_id'] ?? $keepId),
                    'Tánciskolák összevonva',
                    $absorbName . ' (#' . $absorbId . ') → ' . $keepName
                );
            }
            flash(
                'success',
                'A(z) „' . $absorbName . '” beolvadt a(z) „' . $keepName . '” iskolába. '
                . 'Helyszín: ' . $venueTotal
                . ' (ebből már megvolt: ' . (int) ($result['venues_merged'] ?? 0) . '), '
                . 'esemény: ' . (int) ($result['events'] ?? 0) . ', '
                . 'tanár: ' . $teacherTotal
                . ' (ebből már megvolt: ' . (int) ($result['teachers_merged'] ?? 0) . '), '
                . 'partner: ' . $partnerTotal . '.'
            );
            redirect(events_url('tanciskola_szerkeszt.php?id=') . (int) ($result['keep_id'] ?? $keepId));
        }
        $hiba = (string) ($result['error'] ?? 'Az összevonás sikertelen.');
    }
}

$schools = dance_schools_merge_choices($db);
$preview = null;
if ($keepId > 0 && $absorbId > 0) {
    $previewResult = dance_school_merge_preview($db, $keepId, $absorbId);
    if (empty($previewResult['ok'])) {
        if ($hiba === '') {
            $hiba = (string) ($previewResult['error'] ?? 'Az előnézet nem készíthető el.');
        }
    } else {
        $preview = $previewResult;
    }
}

$schoolOptionLabel = static function (array $school): string {
    $parts = [(string) $school['name']];
    if (($school['city'] ?? '') !== '') {
        $parts[] = (string) $school['city'];
    }
    if (($school['slug'] ?? '') !== '') {
        $parts[] = (string) $school['slug'];
    }
    $label = implode(' · ', $parts) . ' (#' . (int) $school['id'] . ')';
    if (empty($school['is_active'])) {
        $label .= ' · inaktív';
    }

    return $label;
};

$pageTitle = 'Tánciskolák összevonása';
$mainContentClass = 'main-content main-content--fullwidth';
$adminFloatTools = [
    [
        'href' => events_url('tanciskolak_admin.php'),
        'title' => 'Vissza a tánciskolák listájához',
        'aria' => 'Vissza a tánciskolák listájához',
        'icon' => 'back',
    ],
];
$adminFloatToolsRequireLogin = false;

require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>
<?php require __DIR__ . '/partials/admin_float_tools.php'; ?>

<div class="card events-admin-card">
    <div class="events-list-head">
        <h1 class="card-title" style="margin:0;">Tánciskolák összevonása</h1>
        <div class="events-list-actions">
            <a href="<?= h(events_url('tanciskolak_admin.php')) ?>" class="btn btn-secondary">← Tánciskolák listája</a>
        </div>
    </div>
    <?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <p class="help">Két rekordból egy marad. A megmaradó neve, slugja, publikáltsága és aktív állapota nem változik. A beolvasztott iskola helyszínei, órái, eseményei, tanárai, partnerkapcsolatai és megtekintései átkerülnek, az üres elérhetőségek kitöltődnek, majd a beolvasztott rekord törlődik. A régi nyilvános cím a megmaradó oldalra mutat.</p>

    <?php if (count($schools) < 2): ?>
        <p class="alert alert-warning">Legalább két tánciskola kell az összevonáshoz.</p>
    <?php else: ?>
        <form method="get" action="<?= h(events_url('tanciskola_osszevonas.php')) ?>" id="dance-school-merge-pick">
            <div class="events-tag-dj-profile__grid">
                <div class="form-group">
                    <label for="keep_id">Megmaradó iskola</label>
                    <input type="search" id="keep-filter" class="events-filter-input" placeholder="Szűrés név, város vagy slug alapján" autocomplete="off" style="margin-bottom:0.4rem;">
                    <select id="keep_id" name="keep_id" required style="width:100%;max-width:100%;">
                        <option value="">Válassz iskolát…</option>
                        <?php foreach ($schools as $school): ?>
                            <option value="<?= (int) $school['id'] ?>"<?= (int) $school['id'] === $keepId ? ' selected' : '' ?>><?= h($schoolOptionLabel($school)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="absorb_id">Beolvasztandó iskola</label>
                    <input type="search" id="absorb-filter" class="events-filter-input" placeholder="Szűrés név, város vagy slug alapján" autocomplete="off" style="margin-bottom:0.4rem;">
                    <select id="absorb_id" name="absorb_id" required style="width:100%;max-width:100%;">
                        <option value="">Válassz iskolát…</option>
                        <?php foreach ($schools as $school): ?>
                            <option value="<?= (int) $school['id'] ?>"<?= (int) $school['id'] === $absorbId ? ' selected' : '' ?>><?= h($schoolOptionLabel($school)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="events-list-actions" style="margin-top:0.75rem;">
                <button type="button" class="btn btn-secondary" id="dance-school-merge-swap">Csere</button>
                <button type="submit" class="btn btn-primary">Előnézet</button>
            </div>
        </form>
    <?php endif; ?>

    <?php if (is_array($preview)): ?>
        <?php
        $keep = is_array($preview['keep'] ?? null) ? $preview['keep'] : [];
        $absorb = is_array($preview['absorb'] ?? null) ? $preview['absorb'] : [];
        $counts = is_array($preview['counts'] ?? null) ? $preview['counts'] : [];
        $overlap = is_array($preview['overlap'] ?? null) ? $preview['overlap'] : [];
        $filled = is_array($preview['filled'] ?? null) ? $preview['filled'] : [];
        $keepName = trim((string) ($keep['name'] ?? ''));
        $absorbName = trim((string) ($absorb['name'] ?? ''));
        $confirmMsg = 'A(z) „' . $absorbName . '” beolvad a(z) „' . $keepName . '” iskolába, és utána törlődik. A művelet nem vonható vissza.';
        ?>
        <div class="events-edit-panel" style="margin-top:1.25rem;">
            <h3 class="events-edit-panel__title">Előnézet</h3>
            <p><strong>Megmarad:</strong> <?= h($keepName) ?> <span class="text-muted">(<?= h((string) ($keep['slug'] ?? '')) ?>)</span></p>
            <p><strong>Törlődik:</strong> <?= h($absorbName) ?> <span class="text-muted">(<?= h((string) ($absorb['slug'] ?? '')) ?>)</span></p>
            <ul>
                <li>Helyszín: <?= (int) ($counts['venues'] ?? 0) ?>, ebből már a megmaradón is rajta van: <?= (int) ($overlap['venues'] ?? 0) ?></li>
                <li>Óra / kínálat: <?= (int) ($counts['offerings'] ?? 0) ?>. Azonos helyszínen az azonos óra nem duplikálódik.</li>
                <li>Esemény: <?= (int) ($counts['events'] ?? 0) ?></li>
                <li>Tanár: <?= (int) ($counts['teachers'] ?? 0) ?>, ebből ugyanaz a szerep már megvan: <?= (int) ($overlap['teachers'] ?? 0) ?></li>
                <li>Partnerkapcsolat: <?= (int) ($counts['partners'] ?? 0) ?>, ebből ugyanaz a szerep már megvan: <?= (int) ($overlap['partners'] ?? 0) ?></li>
                <li>Megtekintési napló: <?= (int) ($counts['views'] ?? 0) ?> sor</li>
            </ul>
            <?php if ($filled !== []): ?>
                <p>A megmaradó üres mezői közül ezek töltődnek fel: <?= h(implode(', ', array_map('strval', $filled))) ?>.</p>
            <?php else: ?>
                <p>A megmaradó iskola szövegei és elérhetőségei változatlanok maradnak.</p>
            <?php endif; ?>
            <p class="help">Az admin jegyzetbe bekerül az összevonás ténye. A régi slug a megmaradó nyilvános oldalára irányít.</p>
            <p class="alert alert-warning" id="dance-school-merge-stale" hidden>A választás megváltozott. Kattints az Előnézetre, mielőtt összevonnád.</p>
            <form method="post" action="<?= h(events_url('tanciskola_osszevonas.php')) ?>" id="dance-school-merge-confirm">
                <?= csrf_input('tanciskola_osszevonas') ?>
                <input type="hidden" name="keep_id" value="<?= (int) $keepId ?>">
                <input type="hidden" name="absorb_id" value="<?= (int) $absorbId ?>">
                <button
                    type="submit"
                    class="btn btn-danger"
                    name="merge_dance_schools"
                    value="1"
                    id="dance-school-merge-submit"
                    data-confirm="<?= h($confirmMsg) ?>"
                >Összevonás</button>
            </form>
        </div>
    <?php endif; ?>
</div>
<script>
(function () {
    function bindFilter(inputId, selectId) {
        var input = document.getElementById(inputId);
        var select = document.getElementById(selectId);
        if (!input || !select) return;
        var all = Array.prototype.map.call(select.options, function (option) {
            return { value: option.value, text: option.textContent || '' };
        });
        input.addEventListener('input', function () {
            var query = input.value.trim().toLocaleLowerCase();
            var current = select.value;
            select.innerHTML = '';
            all.forEach(function (option) {
                if (option.value === '' || query === '' || option.text.toLocaleLowerCase().indexOf(query) !== -1 || option.value === current) {
                    var node = document.createElement('option');
                    node.value = option.value;
                    node.textContent = option.text;
                    select.appendChild(node);
                }
            });
            select.value = current;
        });
        select._mergeRestore = function () {
            var current = select.value;
            input.value = '';
            select.innerHTML = '';
            all.forEach(function (option) {
                var node = document.createElement('option');
                node.value = option.value;
                node.textContent = option.text;
                select.appendChild(node);
            });
            select.value = current;
        };
    }
    bindFilter('keep-filter', 'keep_id');
    bindFilter('absorb-filter', 'absorb_id');

    var pickForm = document.getElementById('dance-school-merge-pick');
    var keepSelect = document.getElementById('keep_id');
    var absorbSelect = document.getElementById('absorb_id');
    document.getElementById('dance-school-merge-swap')?.addEventListener('click', function () {
        if (!keepSelect || !absorbSelect || !pickForm) return;
        if (typeof keepSelect._mergeRestore === 'function') keepSelect._mergeRestore();
        if (typeof absorbSelect._mergeRestore === 'function') absorbSelect._mergeRestore();
        var keepValue = keepSelect.value;
        keepSelect.value = absorbSelect.value;
        absorbSelect.value = keepValue;
        if (typeof pickForm.requestSubmit === 'function') {
            pickForm.requestSubmit();
        } else {
            pickForm.submit();
        }
    });

    function markStale() {
        var submit = document.getElementById('dance-school-merge-submit');
        var stale = document.getElementById('dance-school-merge-stale');
        if (submit) submit.disabled = true;
        if (stale) stale.hidden = false;
    }
    keepSelect?.addEventListener('change', markStale);
    absorbSelect?.addEventListener('change', markStale);

    document.getElementById('dance-school-merge-confirm')?.addEventListener('submit', function (event) {
        var submitter = event.submitter || document.getElementById('dance-school-merge-submit');
        var message = submitter ? submitter.getAttribute('data-confirm') : '';
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });
})();
</script>
<?php
require_once dirname(__DIR__) . '/partials/footer.php';
