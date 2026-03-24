<?php
require_once __DIR__ . '/../../../nextgen/core/config.php';
require_once __DIR__ . '/../../../nextgen/core/database.php';
require_once __DIR__ . '/../../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../../nextgen/includes/functions.php';
require_once __DIR__ . '/../../../nextgen/includes/email.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    flash('error', 'Hiányzó azonosító.');
    redirect(nextgen_url('finance/szamlak/'));
}
$db = getDb();
$szamla = $db->prepare('SELECT s.*, sz.név AS szervezo_nev FROM számlák s JOIN szervezők sz ON sz.id = s.szervező_id WHERE s.id = ? AND (COALESCE(s.törölve,0) = 0)');
$szamla->execute([$id]);
$szamla = $szamla->fetch();
if (!$szamla) {
    flash('error', 'Számla nem található.');
    redirect(nextgen_url('finance/szamlak/'));
}
$szervezo_id = (int) $szamla['szervező_id'];

// SMTP feladók (email_config), alapértelmezett: naptar@latinfo.hu
$smtp_feladok = $db->query('SELECT id, név, from_email, from_name, alapértelmezett FROM email_config ORDER BY alapértelmezett DESC, név ASC')->fetchAll(PDO::FETCH_ASSOC);
$smtp_felado_ids = array_map(static fn($r) => (int)$r['id'], $smtp_feladok);
$default_felado_id = 0;
foreach ($smtp_feladok as $f) {
    if (mb_strtolower((string)($f['from_email'] ?? '')) === 'naptar@latinfo.hu') {
        $default_felado_id = (int)$f['id'];
        break;
    }
}
if ($default_felado_id === 0 && !empty($smtp_feladok)) {
    $default_felado_id = (int)$smtp_feladok[0]['id'];
}

// Levélsablon default: szamla_kuldes
$default_sablon_html = '';
$default_sablon_targy = '';
try {
    $sablonStmt = $db->prepare("SELECT tárgy, html_tartalom FROM levélsablonok WHERE kód = 'szamla_kuldes' LIMIT 1");
    $sablonStmt->execute();
    $sablonRow = $sablonStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $default_sablon_targy = (string)($sablonRow['tárgy'] ?? '');
    $default_sablon_html = (string)($sablonRow['html_tartalom'] ?? '');
} catch (Throwable $e) {
    $default_sablon_targy = '';
    $default_sablon_html = '';
}

// Címzettek: szervező kontaktjai közül, akiknél be van állítva a számlázás típus
$cimzettek_stmt = $db->prepare("
    SELECT DISTINCT k.id, k.név, k.email
    FROM szervező_kontakt sk
    JOIN kontaktok k ON k.id = sk.kontakt_id
    JOIN kontakt_típus_kapcsolat kt ON kt.kontakt_id = k.id
    JOIN kontakt_típusok t ON t.id = kt.típus_id
    WHERE sk.szervező_id = ?
      AND k.email IS NOT NULL
      AND k.email <> ''
      AND LOWER(t.név) IN ('számlázás', 'szamlazas')
    ORDER BY k.név
");
$cimzettek_stmt->execute([$szervezo_id]);
$szamlazasi_cimzettek = $cimzettek_stmt->fetchAll(PDO::FETCH_ASSOC);
$default_cimzettek_lista = [];
foreach ($szamlazasi_cimzettek as $c) {
    $em = trim((string)($c['email'] ?? ''));
    if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL)) {
        $default_cimzettek_lista[] = $em;
    }
}
$default_cimzettek_lista = array_values(array_unique($default_cimzettek_lista));
$default_cimzettek_text = implode("\n", $default_cimzettek_lista);

// Teszt cím default: bejelentkezett admin e-mailje
$admin_test_email = '';
$session_email = trim((string)($_SESSION['admin_email'] ?? ''));
if ($session_email !== '' && filter_var($session_email, FILTER_VALIDATE_EMAIL)) {
    $admin_test_email = $session_email;
}
if ($admin_test_email === '') {
    try {
        $aStmt = $db->prepare('SELECT email, felhasználónév FROM adminok WHERE id = ? LIMIT 1');
        $aStmt->execute([(int)($_SESSION['admin_id'] ?? 0)]);
        $admin = $aStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if (!empty($admin['email']) && filter_var($admin['email'], FILTER_VALIDATE_EMAIL)) {
            $admin_test_email = (string)$admin['email'];
        } elseif (!empty($admin['felhasználónév']) && filter_var($admin['felhasználónév'], FILTER_VALIDATE_EMAIL)) {
            $admin_test_email = (string)$admin['felhasználónév'];
        }
    } catch (Throwable $e) {
        $admin_test_email = '';
    }
}

$vissza = trim($_GET['vissza'] ?? '');
$vissza_szervezo_id = (int)($_GET['szervezo_id'] ?? 0);
$szerkeszt_url = nextgen_url('finance/szamlak/szerkeszt.php?id=') . $id;
if ($vissza !== '') {
    $szerkeszt_url .= '&vissza=' . rawurlencode($vissza);
    if ($vissza_szervezo_id > 0) {
        $szerkeszt_url .= '&szervezo_id=' . $vissza_szervezo_id;
    }
}
if ($vissza === 'szervezo' && $vissza_szervezo_id > 0) {
    $redirect_after_delete = nextgen_url('organizers/megtekint.php?id=') . $vissza_szervezo_id;
} elseif ($vissza === 'szamlazando') {
    $redirect_after_delete = nextgen_url('finance/szamlazando/');
} else {
    $redirect_after_delete = nextgen_url('finance/szamlak/');
}

// Számla e-mail küldés / tesztküldés
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['szamla_email_action'])) {
    $action = $_POST['szamla_email_action'] === 'test' ? 'test' : 'send';
    $felado_id = (int)($_POST['felado_config_id'] ?? 0);
    if (!in_array($felado_id, $smtp_felado_ids, true)) {
        $felado_id = $default_felado_id;
    }
    if ($felado_id <= 0) {
        flash('error', 'Nincs beállított feladó SMTP fiók.');
        redirect($szerkeszt_url);
    }

    $template_html = trim((string)($_POST['sablon_html'] ?? $default_sablon_html));
    if ($template_html === '') {
        $template_html = '<p>Kedves Partnerünk!</p><p>Csatolva küldjük a számlát.</p>';
    }
    $template_subject = trim((string)($_POST['sablon_targy'] ?? $default_sablon_targy));
    if ($template_subject === '') {
        $template_subject = 'Számla: {{szamla_szam}}';
    }
    $replace = [
        '{{szervezo_nev}}' => (string)$szamla['szervezo_nev'],
        '{{szamla_szam}}' => (string)$szamla['számla_szám'],
        '{{datum}}' => (string)$szamla['dátum'],
        '{{osszeg}}' => number_format((float)$szamla['összeg'], 0, ',', ' ') . ' Ft',
        '{{statusz}}' => szamla_statusz_label((string)$szamla['státusz']),
        '{{belso_megjegyzes}}' => (string)($szamla['belső_megjegyzés'] ?? ''),
    ];
    $subject = strtr($template_subject, $replace);
    $body = strtr($template_html, $replace);

    $fajlok_stmt = $db->prepare('SELECT eredeti_név, fájl_útvonal FROM számla_fájlok WHERE számla_id = ? ORDER BY id');
    $fajlok_stmt->execute([$id]);
    $attachments = [];
    foreach ($fajlok_stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $path = UPLOAD_PATH . '/' . $f['fájl_útvonal'];
        if (is_file($path)) {
            $attachments[] = ['path' => $path, 'name' => (string)$f['eredeti_név']];
        }
    }

    if ($action === 'test') {
        $teszt_cim = trim((string)($_POST['teszt_cim'] ?? ''));
        if (!filter_var($teszt_cim, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Érvényes teszt e-mail cím szükséges.');
            redirect($szerkeszt_url);
        }
        $res = email_kuld($teszt_cim, '[TESZT] ' . $subject, $body, [
            'config_id' => $felado_id,
            'html' => true,
            'attachments' => $attachments,
        ]);
        if ($res['ok']) {
            rendszer_log('számla', $id, 'Számla e-mail teszt küldve', 'Címzett: ' . $teszt_cim);
            flash('success', 'Teszt e-mail elküldve: ' . $teszt_cim);
        } else {
            flash('error', 'Tesztküldés hiba: ' . $res['hiba']);
        }
        redirect($szerkeszt_url);
    }

    $cimzettek_text = trim((string)($_POST['cimzettek_text'] ?? $default_cimzettek_text));
    $darabok = preg_split('/[\s,;]+/u', $cimzettek_text) ?: [];
    $cimzettek = [];
    foreach ($darabok as $em) {
        $em = trim((string)$em);
        if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL)) {
            $cimzettek[] = $em;
        }
    }
    $cimzettek = array_values(array_unique($cimzettek));
    if (empty($cimzettek)) {
        flash('error', 'Adj meg legalább egy érvényes címzett e-mail címet.');
        redirect($szerkeszt_url);
    }

    $res = email_kuld($cimzettek, $subject, $body, [
        'config_id' => $felado_id,
        'html' => true,
        'attachments' => $attachments,
    ]);
    if ($res['ok']) {
        rendszer_log('számla', $id, 'Számla e-mail küldve', 'Címzettek: ' . implode(', ', $cimzettek));
        flash('success', 'Számla e-mail elküldve.');
    } else {
        flash('error', 'Küldési hiba: ' . $res['hiba']);
    }
    redirect($szerkeszt_url);
}

/**
 * Csatolt számla fájlok feltöltése.
 * Visszatér: sikeresen feltöltött fájlok darabszáma.
 */
function feltolt_szamla_fajlok(PDO $db, int $szamla_id, array $fajlAdat): int
{
    if (!is_dir(UPLOAD_PATH)) {
        @mkdir(UPLOAD_PATH, 0755, true);
    }
    $upload_dir = UPLOAD_PATH . '/' . $szamla_id;
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }

    $names = is_array($fajlAdat['name'] ?? null) ? $fajlAdat['name'] : [($fajlAdat['name'] ?? '')];
    $tmp = is_array($fajlAdat['tmp_name'] ?? null) ? $fajlAdat['tmp_name'] : [($fajlAdat['tmp_name'] ?? '')];
    $errors = is_array($fajlAdat['error'] ?? null) ? $fajlAdat['error'] : [($fajlAdat['error'] ?? UPLOAD_ERR_NO_FILE)];

    $feltoltve = 0;
    for ($i = 0; $i < count($names); $i++) {
        if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && !empty($names[$i])) {
            $name = $names[$i];
            $ext = pathinfo($name, PATHINFO_EXTENSION) ?: 'bin';
            $ujnev = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($name)) ?: ('file_' . $i . '.' . $ext);
            $cel = $upload_dir . '/' . $ujnev;
            if (move_uploaded_file($tmp[$i], $cel)) {
                $db->prepare('INSERT INTO számla_fájlok (számla_id, eredeti_név, fájl_útvonal) VALUES (?, ?, ?)')
                    ->execute([$szamla_id, $name, $szamla_id . '/' . $ujnev]);
                $feltoltve++;
            }
        }
    }

    return $feltoltve;
}

// Számla adatok mentése
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mentes_szamla'])) {
    $szamla_szam = trim($_POST['számla_szám'] ?? '');
    $datum = trim($_POST['dátum'] ?? '');
    $osszeg = str_replace([' ', ','], ['', '.'], $_POST['összeg'] ?? '0');
    $megj = trim($_POST['belső_megjegyzés'] ?? '');
    $statusz = in_array($_POST['státusz'] ?? '', ['generált', 'kiküldve', 'kiegyenlítve', 'egyéb', 'KP', 'sztornó'], true) ? $_POST['státusz'] : $szamla['státusz'];
    if ($szamla_szam !== '') {
        $db->prepare('UPDATE számlák SET számla_szám = ?, dátum = ?, összeg = ?, belső_megjegyzés = ?, státusz = ? WHERE id = ?')
            ->execute([$szamla_szam, $datum ?: $szamla['dátum'], $osszeg ?: 0, $megj ?: null, $statusz, $id]);
        rendszer_log('számla', $id, 'Módosítva', null);
        flash('success', 'Számla mentve.');
        redirect($szerkeszt_url);
    }
}

// Számlázandó lecsatolás
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lecsatol_szamlazando_id'])) {
    $sid = (int) $_POST['lecsatol_szamlazando_id'];
    if ($sid) {
        $db->prepare('UPDATE számlázandó SET számla_id = NULL WHERE id = ? AND számla_id = ?')->execute([$sid, $id]);
        flash('success', 'Számlázandó tétel lecsatolva.');
        redirect($szerkeszt_url);
    }
}
// Számlázandó további csatolása
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hozzacsatol_szamlazando_ids']) && is_array($_POST['hozzacsatol_szamlazando_ids'])) {
    $csatol_ids = array_map('intval', $_POST['hozzacsatol_szamlazando_ids']);
    $csatol_ids = array_filter($csatol_ids);
    if (!empty($csatol_ids)) {
        $placeholders = implode(',', array_fill(0, count($csatol_ids), '?'));
        $db->prepare("UPDATE számlázandó SET számla_id = ? WHERE id IN ($placeholders) AND szervező_id = ? AND számla_id IS NULL AND (COALESCE(törölve,0) = 0)")
            ->execute(array_merge([$id], $csatol_ids, [$szervezo_id]));
        flash('success', 'Számlázandó tétel(ek) csatolva.');
        redirect($szerkeszt_url);
    }
}

// Csatolt fájl törlése
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['torol_fajl_id'])) {
    $fid = (int) $_POST['torol_fajl_id'];
    if ($fid) {
        $stmt = $db->prepare('SELECT fájl_útvonal FROM számla_fájlok WHERE id = ? AND számla_id = ?');
        $stmt->execute([$fid, $id]);
        $f = $stmt->fetch();
        if ($f) {
            $path = UPLOAD_PATH . '/' . $f['fájl_útvonal'];
            if (is_file($path)) {
                @unlink($path);
            }
            $db->prepare('DELETE FROM számla_fájlok WHERE id = ?')->execute([$fid]);
            flash('success', 'Fájl törölve.');
        }
    }
    redirect($szerkeszt_url);
}

// Új fájl(ok) feltöltés
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['uj_fajl']['name']) && is_dir(UPLOAD_PATH)) {
    $feltoltve = feltolt_szamla_fajlok($db, $id, $_FILES['uj_fajl']);
    if ($feltoltve > 0) {
        flash('success', $feltoltve === 1 ? 'Fájl feltöltve.' : $feltoltve . ' fájl feltöltve.');
        redirect($szerkeszt_url);
    }
}

// Számla törlése (soft delete: törölve=1, becsatolt fájlok törlése)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['torol_szamla'])) {
    rendszer_log('számla', $id, 'Törölve', null);
    $fajlok_stmt = $db->prepare('SELECT id, fájl_útvonal FROM számla_fájlok WHERE számla_id = ?');
    $fajlok_stmt->execute([$id]);
    while ($f = $fajlok_stmt->fetch(PDO::FETCH_ASSOC)) {
        $path = UPLOAD_PATH . '/' . $f['fájl_útvonal'];
        if (is_file($path)) {
            @unlink($path);
        }
    }
    $db->prepare('DELETE FROM számla_fájlok WHERE számla_id = ?')->execute([$id]);
    $db->prepare('UPDATE számlázandó SET számla_id = NULL WHERE számla_id = ?')->execute([$id]);
    $db->prepare('UPDATE számlák SET törölve = 1 WHERE id = ?')->execute([$id]);
    $upload_dir = UPLOAD_PATH . '/' . $id;
    if (is_dir($upload_dir)) {
        @array_map('unlink', glob($upload_dir . '/*'));
        @rmdir($upload_dir);
    }
    flash('success', 'Számla törölve.');
    $r = trim($_POST['vissza'] ?? '');
    $rsz = (int)($_POST['vissza_szervezo_id'] ?? 0);
    if ($r === 'szervezo' && $rsz > 0) {
        redirect(nextgen_url('organizers/megtekint.php?id=') . $rsz);
    }
    if ($r === 'szamlazando') {
        redirect(nextgen_url('finance/szamlazando/'));
    }
    redirect(nextgen_url('finance/szamlak/'));
}

// Frissített adatok
$szamla = $db->prepare('SELECT s.*, sz.név AS szervezo_nev FROM számlák s JOIN szervezők sz ON sz.id = s.szervező_id WHERE s.id = ? AND (COALESCE(s.törölve,0) = 0)');
$szamla->execute([$id]);
$szamla = $szamla->fetch();

$fajlok = $db->prepare('SELECT * FROM számla_fájlok WHERE számla_id = ? ORDER BY id');
$fajlok->execute([$id]);
$fajlok = $fajlok->fetchAll();

$csatolt_szamlazando = $db->prepare("
    SELECT s.id, s.összeg, s.megjegyzés,
           (SELECT GROUP_CONCAT(CONCAT(si.év, '-', LPAD(si.hónap, 2, '0')) ORDER BY si.év, si.hónap)
            FROM számlázandó_időszak si WHERE si.számlázandó_id = s.id) AS idoszakok
    FROM számlázandó s
    WHERE s.számla_id = ? AND (COALESCE(s.törölve,0) = 0)
    ORDER BY s.létrehozva
");
$csatolt_szamlazando->execute([$id]);
$csatolt_szamlazando = $csatolt_szamlazando->fetchAll(PDO::FETCH_ASSOC);

$tovabbi_szamlazando = $db->prepare("
    SELECT s.id, s.összeg, s.megjegyzés,
           (SELECT GROUP_CONCAT(CONCAT(si.év, '-', LPAD(si.hónap, 2, '0')) ORDER BY si.év, si.hónap)
            FROM számlázandó_időszak si WHERE si.számlázandó_id = s.id) AS idoszakok
    FROM számlázandó s
    WHERE s.szervező_id = ? AND s.számla_id IS NULL AND (COALESCE(s.törölve,0) = 0)
    ORDER BY s.létrehozva DESC
");
$tovabbi_szamlazando->execute([$szervezo_id]);
$tovabbi_szamlazando = $tovabbi_szamlazando->fetchAll(PDO::FETCH_ASSOC);

$szamla_logok_stmt = $db->prepare("
    SELECT l.id, l.művelet, l.részletek, l.létrehozva, a.név AS admin_nev
    FROM rendszer_log l
    LEFT JOIN adminok a ON a.id = l.admin_id
    WHERE l.entitás = 'számla' AND l.entitás_id = ?
    ORDER BY l.id DESC
    LIMIT 30
");
$szamla_logok_stmt->execute([$id]);
$szamla_logok = $szamla_logok_stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Számla szerkesztése: ' . $szamla['számla_szám'];
require_once __DIR__ . '/../../partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($e = flash('error')): ?><p class="alert alert-error"><?= h($e) ?></p><?php endif; ?>

<div class="card">
    <h2>Számla szerkesztése</h2>
    <p><a href="<?= h(nextgen_url('organizers/megtekint.php?id=')) ?><?= (int)$szamla['szervező_id'] ?>">← <?= h($szamla['szervezo_nev']) ?></a></p>
    <form method="post">
        <input type="hidden" name="mentes_szamla" value="1">
        <div class="form-row form-row-2">
            <div class="form-group">
                <label>Számlaszám *</label>
                <input type="text" name="számla_szám" value="<?= h($szamla['számla_szám']) ?>" required>
            </div>
            <div class="form-group">
                <label>Dátum *</label>
                <input type="date" name="dátum" value="<?= h($szamla['dátum']) ?>" required>
            </div>
        </div>
        <div class="form-row form-row-2">
            <div class="form-group">
                <label>Összeg *</label>
                <input type="text" name="összeg" value="<?= h($szamla['összeg']) ?>" inputmode="decimal" required>
            </div>
            <div class="form-group">
                <label>Státusz</label>
                <select name="státusz">
                    <?php foreach (['generált', 'kiküldve', 'kiegyenlítve', 'egyéb', 'KP', 'sztornó'] as $st): ?>
                        <option value="<?= h($st) ?>" <?= ($szamla['státusz'] ?? '') === $st ? 'selected' : '' ?>><?= szamla_statusz_label($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>Belső megjegyzés</label>
            <textarea name="belső_megjegyzés" rows="3"><?= h($szamla['belső_megjegyzés'] ?? '') ?></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Mentés</button>
            <a href="<?= h(nextgen_url('finance/szamlak/')) ?>" class="btn btn-secondary">Vissza a listához</a>
        </div>
    </form>
    <div class="form-actions" style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border);">
        <form method="post" class="inline-form" onsubmit="return confirm('Biztosan törölni szeretnéd ezt a számlát? A csatolt fájlok is törlődnek, a számlázandó tételek visszakerülnek „nem csatolt” állapotba.');">
            <input type="hidden" name="torol_szamla" value="1">
            <input type="hidden" name="vissza" value="<?= h($vissza) ?>">
            <input type="hidden" name="vissza_szervezo_id" value="<?= (int)$vissza_szervezo_id ?>">
            <button type="submit" class="btn btn-danger btn-sm">Számla törlése</button>
        </form>
    </div>
</div>

<div class="card">
    <h2>Csatolt fájlok</h2>
    <form method="post" enctype="multipart/form-data" id="uj-fajl-form" style="margin-bottom:1rem;">
        <div class="file-upload-wrap file-drop-zone" id="drop-zone-szerkeszt">
            <input type="file" name="uj_fajl[]" id="uj_fajl" class="file-input-native" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
            <label for="uj_fajl" class="file-input-btn">Fájl kiválasztása</label>
            <span class="file-name" id="uj_fajl_nev">Vagy húzd ide a fájl(oka)t</span>
            <button type="submit" class="btn btn-primary btn-sm">Feltöltés</button>
        </div>
    </form>
    <ul>
        <?php foreach ($fajlok as $f): ?>
        <li>
            <a href="<?= h(nextgen_url('finance/szamlak/letoltes.php?id=')) ?><?= (int)$f['id'] ?>"><?= h($f['eredeti_név']) ?></a>
            <form method="post" class="inline-form" style="display:inline; margin-left:0.35rem;" onsubmit="return confirm('Biztosan törölni szeretnéd ezt a csatolt fájlt?');">
                <input type="hidden" name="torol_fajl_id" value="<?= (int)$f['id'] ?>">
                <button type="submit" class="btn btn-secondary btn-sm">Törlés</button>
            </form>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php if (empty($fajlok)): ?><p>Nincs csatolt fájl.</p><?php endif; ?>
</div>

<div class="card">
    <h2>Csatolt számlázandó tételek – időszakok szerkesztése</h2>
    <p class="help">Az egyes tételek időszakait az „Időszakok szerkesztése” gombbal módosíthatod.</p>
    <?php if (!empty($csatolt_szamlazando)): ?>
    <ul class="szamlazando-csatolt-lista">
        <?php foreach ($csatolt_szamlazando as $sz): ?>
        <li class="szamlazando-csatolt-item">
            <span><?= h($sz['idoszakok'] ?? '–') ?></span>
            – <?= number_format((float)$sz['összeg'], 0, ',', ' ') ?> Ft
            <?php if (!empty($sz['megjegyzés'])): ?><span class="muted">(<?= h(mb_substr($sz['megjegyzés'], 0, 50)) ?><?= mb_strlen($sz['megjegyzés']) > 50 ? '…' : '' ?>)</span><?php endif; ?>
            <a href="<?= h(nextgen_url('finance/szamlazando/szerkeszt.php?id=')) ?><?= (int)$sz['id'] ?>&vissza=szamla&szamla_id=<?= (int)$id ?>" class="btn btn-sm btn-secondary">Időszakok szerkesztése</a>
            <form method="post" class="inline-form" style="display:inline; margin-left:0.35rem;">
                <input type="hidden" name="lecsatol_szamlazando_id" value="<?= (int)$sz['id'] ?>">
                <button type="submit" class="btn btn-secondary btn-sm">Lecsatolás</button>
            </form>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <p>Ehhez a számlához nincs csatolva számlázandó tétel.</p>
    <?php endif; ?>
    <?php if (!empty($tovabbi_szamlazando)): ?>
    <form method="post" class="mt-1">
        <p><strong>További csatolása:</strong></p>
        <div class="checkbox-group">
            <?php foreach ($tovabbi_szamlazando as $sz): ?>
            <label class="checkbox-label">
                <input type="checkbox" name="hozzacsatol_szamlazando_ids[]" value="<?= (int)$sz['id'] ?>">
                <span><?= h($sz['idoszakok'] ?? '–') ?> – <?= number_format((float)$sz['összeg'], 0, ',', ' ') ?> Ft</span>
            </label>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Csatolás</button>
    </form>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Számla e-mail küldés</h2>
    <p class="help">Alap sablon: <code>szamla_kuldes</code>. Mellékletként az összes ehhez a számlához csatolt fájl kerül kiküldésre.</p>
    <form method="post">
        <div class="form-row form-row-2">
            <div class="form-group">
                <label>Feladó (SMTP fiók)</label>
                <select name="felado_config_id" required>
                    <?php foreach ($smtp_feladok as $f): ?>
                        <option value="<?= (int)$f['id'] ?>" <?= ((int)$f['id'] === $default_felado_id) ? 'selected' : '' ?>>
                            <?= h(($f['név'] ?: 'Feladó') . ' – ' . ($f['from_name'] ? ($f['from_name'] . ' <' . $f['from_email'] . '>') : $f['from_email'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Teszt e-mail cím</label>
                <div class="teszt-email-sor">
                    <input type="email" name="teszt_cim" value="<?= h($admin_test_email) ?>" placeholder="teszt@pelda.hu">
                    <button type="submit" name="szamla_email_action" value="test" class="btn btn-secondary">Teszt</button>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>Címzettek (számlázás kontakt típus)</label>
            <?php if (empty($szamlazasi_cimzettek)): ?>
                <p class="text-danger">Figyelmeztetés: nincs számlázás típusú kontakt e-mail címmel.</p>
            <?php endif; ?>
            <textarea name="cimzettek_text" rows="4" placeholder="email1@pelda.hu&#10;email2@pelda.hu"><?= h($default_cimzettek_text) ?></textarea>
            <p class="help">Több címzett megadható új sorral, vesszővel vagy pontosvesszővel elválasztva.</p>
        </div>

        <div class="form-group">
            <label>Levélsablon tárgy (szamla_kuldes)</label>
            <input type="text" name="sablon_targy" value="<?= h($default_sablon_targy) ?>" placeholder="pl. Számla: {{szamla_szam}}">
            <p class="help">Használható változók: <code>{{szervezo_nev}}</code>, <code>{{szamla_szam}}</code>, <code>{{datum}}</code>, <code>{{osszeg}}</code>, <code>{{statusz}}</code>, <code>{{belso_megjegyzes}}</code></p>
        </div>

        <div class="form-group">
            <label>Levélsablon HTML (szamla_kuldes)</label>
            <textarea name="sablon_html" class="js-html-editor-source" rows="10"><?= h($default_sablon_html) ?></textarea>
            <p class="help">Használható változók: <code>{{szervezo_nev}}</code>, <code>{{szamla_szam}}</code>, <code>{{datum}}</code>, <code>{{osszeg}}</code>, <code>{{statusz}}</code>, <code>{{belso_megjegyzes}}</code></p>
        </div>

        <div class="form-actions">
            <button type="submit" name="szamla_email_action" value="send" class="btn btn-primary">Küldés</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>Számla log</h2>
    <?php if (!empty($szamla_logok)): ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Időpont</th>
                        <th>Művelet</th>
                        <th>Részletek</th>
                        <th>Admin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($szamla_logok as $l): ?>
                        <tr>
                            <td><?= (int)$l['id'] ?></td>
                            <td><?= h($l['létrehozva']) ?></td>
                            <td><?= h($l['művelet']) ?></td>
                            <td><?= h($l['részletek'] ?? '') ?></td>
                            <td><?= h($l['admin_nev'] ?? '–') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p>Nincs még logbejegyzés ehhez a számlához.</p>
    <?php endif; ?>
</div>
<script>
(function () {
    function buildEditor(textarea) {
        var wrapper = document.createElement('div');
        wrapper.className = 'html-editor';

        var toolbar = document.createElement('div');
        toolbar.className = 'html-editor-toolbar';
        toolbar.innerHTML = ''
            + '<button type="button" data-cmd="bold"><strong>B</strong></button>'
            + '<button type="button" data-cmd="italic"><em>I</em></button>'
            + '<button type="button" data-cmd="underline"><u>U</u></button>'
            + '<button type="button" data-cmd="insertUnorderedList">Lista</button>'
            + '<button type="button" data-cmd="insertOrderedList">Számozás</button>'
            + '<button type="button" data-cmd="createLink">Link</button>'
            + '<button type="button" data-cmd="formatBlock" data-value="h2">Címsor</button>'
            + '<button type="button" data-cmd="formatBlock" data-value="p">Bekezdés</button>'
            + '<button type="button" class="js-toggle-source">Forráskód</button>'
            + '<button type="button" class="js-toggle-preview">Előnézet</button>';

        var editor = document.createElement('div');
        editor.className = 'html-editor-area';
        editor.contentEditable = 'true';
        editor.innerHTML = textarea.value || '';

        var preview = document.createElement('div');
        preview.className = 'html-editor-preview';
        preview.hidden = true;

        textarea.parentNode.insertBefore(wrapper, textarea);
        wrapper.appendChild(toolbar);
        wrapper.appendChild(editor);
        wrapper.appendChild(preview);
        wrapper.appendChild(textarea);
        textarea.classList.add('html-editor-source');
        textarea.hidden = true;

        var sourceMode = false;

        function syncToSource() { textarea.value = editor.innerHTML; }
        function syncFromSource() { editor.innerHTML = textarea.value; }

        editor.addEventListener('input', syncToSource);

        toolbar.addEventListener('click', function (e) {
            var btn = e.target.closest('button');
            if (!btn) return;

            if (btn.classList.contains('js-toggle-source')) {
                sourceMode = !sourceMode;
                if (sourceMode) {
                    syncToSource();
                    textarea.hidden = false;
                    editor.hidden = true;
                    preview.hidden = true;
                    btn.classList.add('is-active');
                } else {
                    syncFromSource();
                    textarea.hidden = true;
                    editor.hidden = false;
                    btn.classList.remove('is-active');
                }
                return;
            }

            if (btn.classList.contains('js-toggle-preview')) {
                if (sourceMode) syncFromSource();
                syncToSource();
                preview.innerHTML = textarea.value;
                preview.hidden = !preview.hidden;
                btn.classList.toggle('is-active', !preview.hidden);
                return;
            }

            var cmd = btn.getAttribute('data-cmd');
            if (!cmd) return;
            editor.focus();
            if (cmd === 'createLink') {
                var url = window.prompt('Link URL:', 'https://');
                if (url) document.execCommand('createLink', false, url);
            } else if (cmd === 'formatBlock') {
                document.execCommand('formatBlock', false, btn.getAttribute('data-value') || 'p');
            } else {
                document.execCommand(cmd, false, null);
            }
            syncToSource();
        });

        var form = textarea.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                if (!sourceMode) syncToSource();
            });
        }
    }

    document.querySelectorAll('.js-html-editor-source').forEach(buildEditor);
})();
</script>
<script>
(function() {
    var zone = document.getElementById('drop-zone-szerkeszt');
    var form = document.getElementById('uj-fajl-form');
    var inp = document.getElementById('uj_fajl');
    var nev = document.getElementById('uj_fajl_nev');
    function updateFileLabel() {
        if (inp && inp.files && inp.files.length > 0) {
            nev.textContent = inp.files.length === 1 ? inp.files[0].name : inp.files.length + ' fájl kiválasztva';
            nev.classList.add('has-file');
        } else {
            nev.textContent = 'Vagy húzd ide a fájl(oka)t';
            nev.classList.remove('has-file');
        }
    }
    if (inp && nev) inp.addEventListener('change', updateFileLabel);
    if (zone && form) {
        ['dragenter', 'dragover'].forEach(function(ev) {
            zone.addEventListener(ev, function(e) { e.preventDefault(); e.stopPropagation(); zone.classList.add('is-dragover'); });
        });
        zone.addEventListener('dragleave', function(e) { e.preventDefault(); zone.classList.remove('is-dragover'); });
        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            zone.classList.remove('is-dragover');
            var files = e.dataTransfer && e.dataTransfer.files;
            if (!files || files.length === 0) return;
            var fd = new FormData();
            for (var i = 0; i < files.length; i++) fd.append('uj_fajl[]', files[i]);
            fetch(form.action, { method: 'POST', body: fd })
                .then(function(r) { if (r.redirected) window.location.href = r.url; });
        });
    }
})();
</script>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
