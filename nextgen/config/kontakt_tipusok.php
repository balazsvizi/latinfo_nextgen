<?php
$pageTitle = 'Kontakt típusok';
require_once __DIR__ . '/../partials/header.php';

$db = getDb();
$hiba = '';

// Kompatibilitás: eltérő telepítéseken más lehet a táblaelnevezés
// (finance_*, nextgen_* vagy régi magyar nevek).
$tableExists = static function (PDO $db, string $table): bool {
    try {
        $stmt = $db->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
};

$resolveTable = static function (PDO $db, array $candidates, string $default) use ($tableExists): string {
    foreach ($candidates as $candidate) {
        if ($tableExists($db, $candidate)) {
            return $candidate;
        }
    }
    return $default;
};

$contactTypesTable = $resolveTable($db, ['finance_contact_types', 'nextgen_contact_types', 'kontakt_típusok'], 'finance_contact_types');
$contactTypeLinksTable = $resolveTable($db, ['finance_contact_type_links', 'nextgen_contact_type_links', 'kontakt_típus_kapcsolat'], 'finance_contact_type_links');
$contactsTable = $resolveTable($db, ['finance_contacts', 'nextgen_contacts', 'kontaktok'], 'finance_contacts');

// Új típus felvétele
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['uj_tipus_nev'])) {
    $nev = trim($_POST['uj_tipus_nev']);
    $leiras = trim($_POST['uj_tipus_leiras'] ?? '');
    if ($nev !== '') {
        try {
            $db->prepare("INSERT INTO {$contactTypesTable} (név, leírás) VALUES (?, ?)")->execute([$nev, $leiras ?: null]);
            rendszer_log('kontakt_típus', (int)$db->lastInsertId(), 'Létrehozva', 'Név: ' . $nev);
            flash('success', 'Kontakt típus felvéve.');
            redirect(nextgen_url('config/kontakt_tipusok.php'));
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) $hiba = 'Ilyen kontakt típus már létezik.';
            else $hiba = $e->getMessage();
        }
    }
}

// Név és leírás módosítása
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['szerkeszt_id'], $_POST['uj_nev'])) {
    $tid = (int) $_POST['szerkeszt_id'];
    $nev = trim($_POST['uj_nev']);
    $leiras = trim($_POST['uj_leiras'] ?? '');
    if ($tid && $nev !== '') {
        try {
            $db->prepare("UPDATE {$contactTypesTable} SET név = ?, leírás = ? WHERE id = ?")->execute([$nev, $leiras ?: null, $tid]);
            rendszer_log('kontakt_típus', $tid, 'Módosítva', 'Új név: ' . $nev);
            flash('success', 'Kontakt típus módosítva.');
            redirect(nextgen_url('config/kontakt_tipusok.php'));
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) $hiba = 'Ilyen kontakt típus már létezik.';
            else $hiba = $e->getMessage();
        }
    }
}

// Típus törlése – csak ha sehol nem használt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['torol_id'])) {
    $tid = (int) $_POST['torol_id'];
    if ($tid) {
        $hasznalat = $db->prepare("
            SELECT k.id, k.név
            FROM {$contactTypeLinksTable} kt
            JOIN {$contactsTable} k ON k.id = kt.kontakt_id
            WHERE kt.típus_id = ?
            ORDER BY k.név
        ");
        $hasznalat->execute([$tid]);
        $hasznalatban_kontaktok = $hasznalat->fetchAll();
        if (!empty($hasznalatban_kontaktok)) {
            $nevek = array_map(function ($r) { return $r['név']; }, $hasznalatban_kontaktok);
            $hiba = 'A típus még használatban van, ezért nem törölhető. Kontaktok: ' . implode(', ', $nevek);
        } else {
            $db->prepare("DELETE FROM {$contactTypesTable} WHERE id = ?")->execute([$tid]);
            rendszer_log('kontakt_típus', $tid, 'Törölve', null);
            flash('success', 'Kontakt típus törölve.');
            redirect(nextgen_url('config/kontakt_tipusok.php'));
        }
    }
}

$kereso = trim($_GET['kereso'] ?? '');
$order = isset($_GET['order']) && in_array($_GET['order'], ['név', 'létrehozva'], true) ? $_GET['order'] : 'név';
$dir_param = isset($_GET['dir']) && $_GET['dir'] === 'desc' ? 'desc' : 'asc';
$dir = $dir_param === 'desc' ? 'DESC' : 'ASC';

$where = '';
$params = [];
if ($kereso !== '') {
    $where = 'WHERE név LIKE ?';
    $params = ['%' . $kereso . '%'];
}
$stmt = $db->prepare("SELECT * FROM {$contactTypesTable} $where ORDER BY $order $dir");
$stmt->execute($params);
$tipusok = $stmt->fetchAll();

$get_params = array_filter(['kereso' => $kereso]);
?>
<div class="card card-kontakt-tipusok">
    <h2>Kontakt típusok</h2>
    <p class="card-lead">Kapcsolattípusok (számlázás, naptár, vezető, egyéb), amelyeket a kontaktoknál lehet beállítani. A leírás a típus választónál jelenik meg.</p>
    <?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>

    <section class="config-section config-section-uj" aria-labelledby="kontakt-tipus-uj-cim">
        <h3 id="kontakt-tipus-uj-cim" class="config-section-title">Új típus</h3>
        <form method="post" class="kontakt-tipus-form-uj">
            <div class="kontakt-tipus-uj-grid">
                <div class="form-group kontakt-tipus-uj-nev">
                    <label for="uj_tipus">Név *</label>
                    <input type="text" id="uj_tipus" name="uj_tipus_nev" placeholder="Pl. számlázás" required autocomplete="off">
                </div>
                <div class="form-group kontakt-tipus-uj-leiras">
                    <label for="uj_tipus_leiras">Leírás</label>
                    <input type="text" id="uj_tipus_leiras" name="uj_tipus_leiras" placeholder="Rövid magyarázat a választóhoz">
                </div>
                <div class="form-group kontakt-tipus-uj-gomb">
                    <label class="visually-hidden" for="uj_tipus_submit">Felvétel</label>
                    <button type="submit" id="uj_tipus_submit" class="btn btn-primary">Felvétel</button>
                </div>
            </div>
        </form>
    </section>

    <section class="config-section" aria-labelledby="kontakt-tipus-lista-cim">
        <div class="config-section-head">
            <h3 id="kontakt-tipus-lista-cim" class="config-section-title">Lista</h3>
            <form method="get" class="toolbar toolbar-inline kontakt-tipus-toolbar">
                <input type="search" name="kereso" placeholder="Keresés név szerint…" value="<?= h($kereso) ?>" aria-label="Keresés">
                <button type="submit" class="btn btn-primary">Keresés</button>
                <?php if ($kereso !== ''): ?>
                    <a href="<?= h(nextgen_url('config/kontakt_tipusok.php')) ?>" class="btn btn-secondary">Összes</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-wrap table-wrap-kontakt-tipusok">
            <table class="sortable-table kontakt-tipusok-table">
                <thead>
                    <tr>
                        <th class="th-nev"><?= sort_th('Név', 'név', $order, $dir_param, $get_params) ?></th>
                        <th class="th-leiras">Leírás</th>
                        <th class="th-datum"><?= sort_th('Létrehozva', 'létrehozva', $order, $dir_param, $get_params) ?></th>
                        <th class="th-actions">Műveletek</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tipusok as $t): ?>
                    <?php $fid = 'kontakt-tipus-szerkeszt-' . (int) $t['id']; ?>
                    <tr>
                        <td data-label="Név">
                            <form method="post" id="<?= h($fid) ?>" class="kontakt-tipus-row-form">
                                <input type="hidden" name="szerkeszt_id" value="<?= (int)$t['id'] ?>">
                                <input type="text" name="uj_nev" value="<?= h($t['név']) ?>" class="input-block" required aria-label="Név">
                            </form>
                        </td>
                        <td data-label="Leírás">
                            <input type="text" name="uj_leiras" value="<?= h($t['leírás'] ?? '') ?>" form="<?= h($fid) ?>" class="input-block" placeholder="Leírás" aria-label="Leírás">
                        </td>
                        <td data-label="Létrehozva" class="td-muted"><?= h($t['létrehozva']) ?></td>
                        <td class="td-actions">
                            <button type="submit" form="<?= h($fid) ?>" class="btn btn-sm btn-primary">Mentés</button>
                            <form method="post" class="inline-form" onsubmit="return confirm('Biztosan törlöd ezt a típust?');">
                                <input type="hidden" name="torol_id" value="<?= (int)$t['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-secondary">Törlés</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($tipusok) === 0): ?>
            <p class="text-muted kontakt-tipusok-ures"><?= $kereso !== '' ? 'Nincs találat.' : 'Még nincs kontakt típus.' ?></p>
        <?php endif; ?>
    </section>
</div>
<p><a href="<?= h(nextgen_url('contacts/')) ?>">← Vissza a finance_contactshoz</a></p>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>

