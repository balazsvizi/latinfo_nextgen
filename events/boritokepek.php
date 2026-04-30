<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/nextgen/includes/auth.php';
require_once __DIR__ . '/lib/eventpics.php';
requireLogin();

$db = getDb();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'delete_eventpic') {
    if (!csrf_validate('events_boritokepek')) {
        flash('error', 'Lejárt vagy érvénytelen munkamenet. Töltsd újra az oldalt.');
    } else {
        $fn = trim((string) ($_POST['filename'] ?? ''));
        if (!events_eventpics_is_safe_filename($fn)) {
            flash('error', 'Érvénytelen fájlnév.');
        } elseif (!isset($_POST['confirm_delete']) || (string) $_POST['confirm_delete'] !== '1') {
            flash('error', 'A törléshez jelöld be a megerősítő négyzetet.');
        } else {
            [$ok, $msg] = events_eventpics_delete_with_clear($db, $fn);
            if ($ok) {
                flash('success', $msg);
                rendszer_log('eventpics', null, 'Borítókép törölve', $fn);
                redirect(events_url('boritokepek.php'));
                exit;
            }
            flash('error', $msg);
            redirect(events_url('boritokepek.php?file=') . rawurlencode($fn));
            exit;
        }
    }
    redirect(events_url('boritokepek.php'));
    exit;
}

$allFiles = events_eventpics_list_files();
$f_q = trim((string) ($_GET['q'] ?? ''));
$files = $allFiles;
if ($f_q !== '') {
    $fl = mb_strtolower($f_q, 'UTF-8');
    $files = array_values(array_filter($files, static function (string $f) use ($fl): bool {
        return str_contains(mb_strtolower($f, 'UTF-8'), $fl);
    }));
}

$selected = trim((string) ($_GET['file'] ?? ''));
if ($selected !== '') {
    if (!events_eventpics_is_safe_filename($selected)) {
        flash('error', 'Érvénytelen fájlnév.');
        $selected = '';
    } elseif (!in_array($selected, $allFiles, true)) {
        flash('error', 'Nincs ilyen feltöltött kép.');
        $selected = '';
    }
}

$usage = $selected !== '' ? events_events_using_eventpic($db, $selected) : [];
$usageCount = count($usage);
$editBase = events_url('szerkeszt.php?id=');
$picBase = site_url('events/eventpics/');

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Borítóképek';
require_once dirname(__DIR__) . '/nextgen/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="card events-boritokepek-card">
    <div class="events-list-head">
        <h2 class="events-list-title">Borítóképek</h2>
        <p class="help" style="margin:0;max-width:40rem;">A feltöltött eventpics képek listája. Válassz egy képet a használat és törlés kezeléséhez. A törlés a fájlt eltávolítja a lemezről, és az érintett eseményeknél üresre állítja a kiemelt kép URL mezőt.</p>
    </div>

    <form method="get" action="<?= h(events_url('boritokepek.php')) ?>" class="events-boritokepek-filter" role="search">
        <?php if ($selected !== ''): ?>
            <input type="hidden" name="file" value="<?= h($selected) ?>">
        <?php endif; ?>
        <label for="boritokepek-q">Szűrés fájlnév szerint</label>
        <div class="events-boritokepek-filter-row">
            <input type="search" id="boritokepek-q" name="q" value="<?= h($f_q) ?>" placeholder="pl. 2025 vagy banner" autocomplete="off" spellcheck="false">
            <button type="submit" class="btn btn-secondary">Szűrés</button>
            <?php if ($f_q !== '' || $selected !== ''): ?>
                <a href="<?= h(events_url('boritokepek.php')) ?>" class="btn btn-secondary">Szűrő és kijelölés törlése</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="events-boritokepek-layout">
        <section class="events-boritokepek-grid-wrap" aria-label="Feltöltött képek">
            <?php if ($files === []): ?>
                <p class="help"><?= $allFiles === [] ? 'Még nincs egy feltöltött kép sem az eventpics mappában.' : 'A szűrésnek nincs találata.' ?></p>
            <?php else: ?>
                <ul class="events-boritokepek-grid" role="list">
                    <?php foreach ($files as $pic): ?>
                        <?php
                        $isSel = $selected === $pic;
                        $href = events_url('boritokepek.php?file=' . rawurlencode($pic)) . ($f_q !== '' ? '&q=' . rawurlencode($f_q) : '');
                        ?>
                        <li class="events-boritokepek-grid__cell">
                            <a class="events-boritokepek-thumb<?= $isSel ? ' is-selected' : '' ?>" href="<?= h($href) ?>">
                                <img src="<?= h($picBase . rawurlencode($pic)) ?>" alt="" width="160" height="120" loading="lazy" decoding="async">
                                <span class="events-boritokepek-thumb__name"><?= h($pic) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <?php if ($selected !== ''): ?>
            <aside class="events-boritokepek-detail" aria-label="Kiválasztott kép">
                <h3 class="events-boritokepek-detail__title">Kiválasztott</h3>
                <figure class="events-boritokepek-detail__figure">
                    <img src="<?= h($picBase . rawurlencode($selected)) ?>" alt="<?= h($selected) ?>" width="640" height="360" loading="lazy" decoding="async">
                    <figcaption class="events-boritokepek-detail__fn"><?= h($selected) ?></figcaption>
                </figure>

                <h4 class="events-boritokepek-detail__sub">Események, ahol ez a borító szerepel</h4>
                <?php if ($usageCount === 0): ?>
                    <p class="help">Egyik eseménynél sincs így beállítva a kiemelt kép URL (vagy más URL van megadva elsőbbséggel).</p>
                <?php else: ?>
                    <p class="help">Összesen <strong><?= (int) $usageCount ?></strong> esemény.</p>
                    <ul class="events-boritokepek-usage" role="list">
                        <?php foreach ($usage as $ev): ?>
                            <li>
                                <a href="<?= h($editBase . (int) $ev['id']) ?>"><?= h((string) $ev['event_name']) ?></a>
                                <span class="events-boritokepek-usage__meta">#<?= (int) $ev['id'] ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <form method="post" action="<?= h(events_url('boritokepek.php?file=') . rawurlencode($selected)) ?>" class="events-boritokepek-delete" onsubmit="return confirm('Biztosan törlöd ezt a képet? <?= (int) $usageCount ?> esemény borító URL-je üres lesz, a fájl véglegesen törlődik.');">
                    <?= csrf_input('events_boritokepek') ?>
                    <input type="hidden" name="action" value="delete_eventpic">
                    <input type="hidden" name="filename" value="<?= h($selected) ?>">
                    <p class="events-boritokepek-delete__warn">
                        <?php if ($usageCount > 0): ?>
                            Figyelem: a törlés után <strong><?= (int) $usageCount ?></strong> eseménynél nem lesz kitöltve a kiemelt kép (eventpics) URL-je.
                        <?php else: ?>
                            Ez a kép jelenleg nincs egyetlen eseményhez sem rendelve az adatbázisban; a fájl így is véglegesen törlődik a lemezről.
                        <?php endif; ?>
                    </p>
                    <label class="events-boritokepek-delete__confirm">
                        <input type="checkbox" name="confirm_delete" value="1" required>
                        Megerősítem a törlést
                    </label>
                    <button type="submit" class="btn btn-secondary" style="margin-top:0.75rem;">Kép törlése</button>
                </form>
            </aside>
        <?php else: ?>
            <aside class="events-boritokepek-detail events-boritokepek-detail--empty" aria-hidden="true">
                <p class="help">Válassz egy képet a listából a részletek és a törlés megjelenítéséhez.</p>
            </aside>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/nextgen/partials/footer.php'; ?>
