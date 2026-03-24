<?php
require_once __DIR__ . '/../../nextgen/core/database.php';
require_once __DIR__ . '/../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../nextgen/includes/functions.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    flash('error', 'Hiányzó azonosító.');
    redirect(nextgen_url('organizers/'));
}

$db = getDb();
$hasCimkeSzin = cimkek_has_szin($db);
$szervezo = $db->prepare('SELECT * FROM szervezők WHERE id = ?');
$szervezo->execute([$id]);
$szervezo = $szervezo->fetch();
if (!$szervezo) {
    flash('error', 'Szervező nem található.');
    redirect(nextgen_url('organizers/'));
}

// Megjegyzés hozzáadása
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['megjegyzes_szoveg'])) {
    $szoveg = trim($_POST['megjegyzes_szoveg']);
    if ($szoveg !== '') {
        $db->prepare('INSERT INTO szervező_megjegyzések (szervező_id, megjegyzés, admin_id) VALUES (?, ?, ?)')
            ->execute([$id, $szoveg, $_SESSION['admin_id'] ?? null]);
        rendszer_log('szervező_megjegyzés', (int)$db->lastInsertId(), 'Felvéve', null);
        flash('success', 'Megjegyzés hozzáadva.');
        redirect(nextgen_url('organizers/megtekint.php?id=') . $id . '#megjegyzesek');
    }
}

// Szervező log esemény hozzáadása
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_esemeny'])) {
    $esemeny = trim($_POST['log_esemeny']);
    $reszletek = trim($_POST['log_reszletek'] ?? '');
    if ($esemeny !== '') {
        $db->prepare('INSERT INTO szervező_log (szervező_id, esemény, részletek, admin_id) VALUES (?, ?, ?, ?)')
            ->execute([$id, $esemeny, $reszletek ?: null, $_SESSION['admin_id'] ?? null]);
        rendszer_log('szervező_log', (int)$db->lastInsertId(), 'Felvéve', null);
        flash('success', 'Esemény rögzítve.');
        redirect(nextgen_url('organizers/megtekint.php?id=') . $id . '#log');
    }
}

$cimkeSql = $hasCimkeSzin
    ? 'SELECT c.név, COALESCE(c.szín, "#6366F1") AS szín FROM szervező_címkék sc JOIN címkék c ON c.id = sc.címke_id WHERE sc.szervező_id = ? ORDER BY c.név'
    : 'SELECT c.név, "#6366F1" AS szín FROM szervező_címkék sc JOIN címkék c ON c.id = sc.címke_id WHERE sc.szervező_id = ? ORDER BY c.név';
$címkék = $db->prepare($cimkeSql);
$címkék->execute([$id]);
$címkék = $címkék->fetchAll();

$megjegyzesek = $db->prepare('SELECT m.*, a.név AS admin_név FROM szervező_megjegyzések m LEFT JOIN adminok a ON a.id = m.admin_id WHERE m.szervező_id = ? ORDER BY m.létrehozva DESC');
$megjegyzesek->execute([$id]);
$megjegyzesek = $megjegyzesek->fetchAll();

$szervezo_log = $db->prepare('SELECT l.*, a.név AS admin_név FROM szervező_log l LEFT JOIN adminok a ON a.id = l.admin_id WHERE l.szervező_id = ? ORDER BY l.létrehozva DESC');
$szervezo_log->execute([$id]);
$szervezo_log = $szervezo_log->fetchAll();

$kontaktok = $db->prepare('SELECT k.* FROM szervező_kontakt sk JOIN kontaktok k ON k.id = sk.kontakt_id WHERE sk.szervező_id = ? ORDER BY k.név');
$kontaktok->execute([$id]);
$kontaktok = $kontaktok->fetchAll();

$cimek = $db->prepare('SELECT * FROM számlázási_címek WHERE szervező_id = ? ORDER BY alapértelmezett DESC, név');
$cimek->execute([$id]);
$cimek = $cimek->fetchAll();

$szamlak = $db->prepare('SELECT * FROM számlák WHERE szervező_id = ? AND (COALESCE(törölve,0) = 0) ORDER BY dátum DESC');
$szamlak->execute([$id]);
$szamlak = $szamlak->fetchAll();

$szamlazando = $db->prepare('SELECT s.*, (SELECT GROUP_CONCAT(CONCAT(si.év, \'-\', LPAD(si.hónap, 2, \'0\')) ORDER BY si.év, si.hónap) FROM számlázandó_időszak si WHERE si.számlázandó_id = s.id) AS idoszakok FROM számlázandó s WHERE s.szervező_id = ? AND (COALESCE(s.törölve,0) = 0) ORDER BY s.létrehozva DESC');
$szamlazando->execute([$id]);
$szamlazando = $szamlazando->fetchAll();
$pageTitle = $szervezo['név'];
require_once __DIR__ . '/../partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>

<div class="card card-szervezo-fejlec" id="megjegyzesek">
    <div class="szervezo-fejlec-sor">
        <div class="szervezo-nev-blokk">
            <h2><?= h($szervezo['név']) ?> <span class="muted" style="font-size:0.75em; font-weight:normal;">(ID: <?= (int)$szervezo['id'] ?>)</span></h2>
            <p>
                <a href="<?= h(nextgen_url('organizers/szerkeszt.php?id=')) ?><?= $id ?>" class="btn btn-primary">Szerkesztés</a>
                <a href="<?= h(nextgen_url('organizers/')) ?>" class="btn btn-secondary">← Lista</a>
            </p>
            <p><strong>Címkék:</strong>
                <?php if ($címkék): ?>
                    <span class="cimke-badge-list">
                        <?php foreach ($címkék as $c): ?>
                            <?php
                                $szin = normalize_hex_color($c['szín'], '#6366F1');
                                $textColor = contrast_text_color($szin);
                            ?>
                            <span class="cimke-badge" style="--badge-bg: <?= h($szin) ?>; --badge-text: <?= h($textColor) ?>;"><?= h($c['név']) ?></span>
                        <?php endforeach; ?>
                    </span>
                <?php else: ?>
                    –
                <?php endif; ?>
            </p>
        </div>
        <div class="szervezo-megjegyzesek-blokk">
            <h3>Megjegyzések</h3>
            <form method="post" style="margin-bottom:0.75rem;">
                <div class="form-group" style="margin-bottom:0.5rem;">
                    <label class="visually-hidden" for="megjegyzes_szoveg">Új megjegyzés</label>
                    <textarea name="megjegyzes_szoveg" id="megjegyzes_szoveg" rows="2" placeholder="Új megjegyzés..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Hozzáadás</button>
            </form>
            <div class="notes-list">
                <?php foreach ($megjegyzesek as $m): ?>
                <div class="note-item">
                    <span class="note-date"><?= h($m['létrehozva']) ?> <?= $m['admin_név'] ? '(' . h($m['admin_név']) . ')' : '' ?></span>
                    <p style="margin:0.25rem 0 0;"><?= nl2br(h($m['megjegyzés'])) ?></p>
                </div>
                <?php endforeach; ?>
                <?php if (empty($megjegyzesek)): ?><p class="text-muted">Még nincs megjegyzés.</p><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$szamlazando_total = count($szamlazando);
$szamlak_total = count($szamlak);
$show_limit = 3;
?>
<div class="card card-collapsible-list" id="card-szamlazando">
    <h2>Számlázandó</h2>
    <p><a href="<?= h(nextgen_url('finance/szamlazando/letrehoz.php?szervezo_id=')) ?><?= $id ?>" class="btn btn-primary btn-sm">Új számlázandó</a></p>
    <?php if (!empty($szamlazando)): ?>
    <table>
        <thead><tr><th>ID</th><th>Időszak</th><th>Összeg</th><th>Megjegyzés</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($szamlazando as $i => $sz): ?>
            <tr class="<?= $i >= $show_limit ? 'collapse-extra' : '' ?>">
                <td><?= (int)$sz['id'] ?></td>
                <td><?= h($sz['idoszakok'] ?? '–') ?></td>
                <td><?= number_format((float)$sz['összeg'], 0, ',', ' ') ?></td>
                <td><?= h($sz['megjegyzés'] ?? '') ?></td>
                <td class="actions">
                    <?php if (empty($sz['számla_id'])): ?>
                        <a href="<?= h(nextgen_url('finance/szamlak/letrehoz.php?szervezo_id=')) ?><?= (int)$id ?>&szamlazando_id=<?= (int)$sz['id'] ?>" class="btn btn-primary btn-sm">Számlakészítés</a>
                    <?php endif; ?>
                    <a href="<?= h(nextgen_url('finance/szamlazando/szerkeszt.php?id=')) ?><?= (int)$sz['id'] ?>" class="btn btn-sm btn-secondary">Szerkeszt</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($szamlazando_total > $show_limit): ?>
    <p class="collapse-trigger-wrap"><button type="button" class="btn btn-secondary btn-sm js-collapse-trigger" data-target="card-szamlazando" data-more="<?= $szamlazando_total - $show_limit ?>">Tovább (<?= $szamlazando_total - $show_limit ?> további)</button></p>
    <?php endif; ?>
    <?php else: ?>
    <p>Nincs számlázandó tétel.</p>
    <?php endif; ?>
</div>

<div class="card card-collapsible-list" id="card-szamlak">
    <h2>Számlák</h2>
    <p><a href="<?= h(nextgen_url('finance/szamlak/letrehoz.php?szervezo_id=')) ?><?= $id ?>" class="btn btn-primary btn-sm">Új számla</a></p>
    <?php if (!empty($szamlak)): ?>
    <table>
        <thead><tr><th>ID</th><th>Számlaszám</th><th>Dátum</th><th>Összeg</th><th>Státusz</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($szamlak as $i => $sz): ?>
            <tr class="<?= $i >= $show_limit ? 'collapse-extra' : '' ?>">
                <td><?= (int)$sz['id'] ?></td>
                <td><a href="<?= h(nextgen_url('finance/szamlak/szerkeszt.php?id=')) ?><?= (int)$sz['id'] ?>&vissza=szervezo&szervezo_id=<?= (int)$id ?>"><?= h($sz['számla_szám']) ?></a></td>
                <td><?= h($sz['dátum']) ?></td>
                <td><?= number_format((float)$sz['összeg'], 0, ',', ' ') ?></td>
                <td><?= szamla_statusz_label($sz['státusz']) ?></td>
                <td><a href="<?= h(nextgen_url('finance/szamlak/szerkeszt.php?id=')) ?><?= (int)$sz['id'] ?>&vissza=szervezo&szervezo_id=<?= (int)$id ?>" class="btn btn-sm btn-secondary">Szerkeszt</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($szamlak_total > $show_limit): ?>
    <p class="collapse-trigger-wrap"><button type="button" class="btn btn-secondary btn-sm js-collapse-trigger" data-target="card-szamlak" data-more="<?= $szamlak_total - $show_limit ?>">Tovább (<?= $szamlak_total - $show_limit ?> további)</button></p>
    <?php endif; ?>
    <?php else: ?>
    <p>Nincs számla.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Kontaktok</h2>
    <p><a href="<?= h(nextgen_url('organizers/kontakt_hozzaad.php?szervezo_id=')) ?><?= $id ?>" class="btn btn-primary btn-sm">Kontakt hozzáadása</a></p>
    <table>
        <thead><tr><th>ID</th><th>Név</th><th>E-mail</th><th>Telefon</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($kontaktok as $k): ?>
            <tr>
                <td><?= (int)$k['id'] ?></td>
                <td><a href="<?= h(nextgen_url('contacts/megtekint.php?id=')) ?><?= (int)$k['id'] ?>"><?= h($k['név']) ?></a></td>
                <td><?= h($k['email'] ?? '') ?></td>
                <td><?= h($k['telefon'] ?? '') ?></td>
                <td><a href="<?= h(nextgen_url('organizers/kontakt_levetel.php?szervezo_id=')) ?><?= $id ?>&kontakt_id=<?= (int)$k['id'] ?>" class="btn btn-sm btn-secondary">Lecsatolás</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (empty($kontaktok)): ?><p>Nincs hozzárendelt kontakt.</p><?php endif; ?>
</div>

<div class="card">
    <h2>Számlázási címek</h2>
    <p><a href="<?= h(nextgen_url('finance/cimek/letrehoz.php?szervezo_id=')) ?><?= $id ?>" class="btn btn-primary btn-sm">Új cím</a></p>
    <table>
        <thead><tr><th>ID</th><th>Név</th><th>Ország</th><th>IRSZ</th><th>Település</th><th>Cím</th><th>Adószám</th><th>Megjegyzés</th><th></th><th class="th-right">Alapért.</th></tr></thead>
        <tbody>
            <?php foreach ($cimek as $c): ?>
            <tr>
                <td><?= (int)$c['id'] ?></td>
                <td><?= h($c['név']) ?></td>
                <td><?= h($c['ország']) ?></td>
                <td><?= h($c['irsz']) ?></td>
                <td><?= h($c['település'] ?? '') ?></td>
                <td><?= h($c['cím']) ?></td>
                <td><?= h($c['adószám'] ?? '') ?></td>
                <td><?= h(mb_substr($c['megjegyzés'] ?? '', 0, 50)) ?><?= mb_strlen($c['megjegyzés'] ?? '') > 50 ? '…' : '' ?></td>
                <td><a href="<?= h(nextgen_url('finance/cimek/szerkeszt.php?id=')) ?><?= (int)$c['id'] ?>" class="btn btn-sm btn-secondary">Szerkeszt</a></td>
                <td class="text-right"><?= $c['alapértelmezett'] ? 'Igen' : '' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (empty($cimek)): ?><p>Nincs számlázási cím.</p><?php endif; ?>
</div>

<div class="card" id="log">
    <h2>Szervező log (történések)</h2>
    <form method="post" style="margin-bottom:1rem;">
        <div class="form-group">
            <label>Esemény</label>
            <input type="text" name="log_esemeny" placeholder="Pl. E-mail kiküldve">
        </div>
        <div class="form-group">
            <label>Részletek (opcionális)</label>
            <textarea name="log_reszletek" rows="2"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Rögzítés</button>
    </form>
    <div class="log-list">
        <?php foreach ($szervezo_log as $l): ?>
        <div class="log-item">
            <span class="log-date">ID: <?= (int)($l['id'] ?? 0) ?> · <?= h($l['létrehozva']) ?> <?= $l['admin_név'] ? '(' . h($l['admin_név']) . ')' : '' ?></span>
            <p style="margin:0.25rem 0 0;"><?= h($l['esemény']) ?><?= $l['részletek'] ? ' – ' . nl2br(h($l['részletek'])) : '' ?></p>
        </div>
        <?php endforeach; ?>
        <?php if (empty($szervezo_log)): ?><p>Még nincs esemény.</p><?php endif; ?>
    </div>
</div>

<script>
(function() {
    document.querySelectorAll('.js-collapse-trigger').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var card = document.getElementById(this.getAttribute('data-target'));
            if (!card) return;
            var isExpanded = card.classList.toggle('is-expanded');
            this.textContent = isExpanded ? 'Kevesebb' : 'Tovább (' + this.getAttribute('data-more') + ' további)';
        });
    });
})();
</script>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
