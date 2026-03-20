<?php
$pageTitle = 'Teszt e-mail küldése';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/email.php';
requireSuperadmin();

require_once __DIR__ . '/../../partials/header.php';

$db = getDb();
$stmt = $db->query('SELECT id, név, from_email, from_name FROM email_config ORDER BY alapértelmezett DESC, név ASC');
$fiokok = $stmt->fetchAll();

$hiba = '';
$siker = '';
$kivalasztott_id = isset($_GET['config_id']) ? (int)$_GET['config_id'] : null;
if ($kivalasztott_id && !$fiokok) {
    $kivalasztott_id = null;
}
if (!$kivalasztott_id && !empty($fiokok)) {
    $kivalasztott_id = (int) $fiokok[0]['id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $config_id = (int) ($_POST['config_id'] ?? 0);
    $cim = trim($_POST['cim'] ?? '');
    if ($config_id <= 0) {
        $hiba = 'Válassz SMTP fiókot.';
    } elseif ($cim === '') {
        $hiba = 'Adjon meg egy címzett e-mail címet.';
    } elseif (!filter_var($cim, FILTER_VALIDATE_EMAIL)) {
        $hiba = 'Érvénytelen címzett e-mail.';
    } else {
        $targy = '[' . SITE_NAME . '] E-mail teszt';
        $szoveg = '<p>Ez egy teszt e-mail a backoffice rendszerből (SMTP).</p>'
            . '<p>Időpont: ' . date('Y-m-d H:i:s') . '</p>'
            . '<p>Ha megkaptad, az SMTP küldés működik.</p>';
        $eredmeny = email_kuld($cim, $targy, $szoveg, ['config_id' => $config_id, 'html' => true]);
        if ($eredmeny['ok']) {
            $siker = 'A teszt e-mail elküldve a megadott címre.';
        } else {
            $hiba = 'Küldés sikertelen: ' . h($eredmeny['hiba']);
        }
    }
    $kivalasztott_id = $config_id ?: $kivalasztott_id;
}
?>
<div class="card card-narrow">
    <h2>Teszt e-mail küldése</h2>
    <p class="text-muted">A kiválasztott SMTP fiók nevében kiküld egy teszt levelet a megadott címre.</p>
    <p class="email-teszt-laragon-hint text-muted" style="font-size:0.875rem; margin-top:0.5rem;">
        Ha <strong>lokálisan</strong> (pl. Laragon) tesztelsz és „Could not connect to SMTP host” hibát kapsz: a tárhely SMTP szervere gyakran csak a szerver IP-jéről fogad kapcsolatot, vagy a Windows tűzfal blokkolja a 587/465 portot. <strong>Éles környezetben</strong> a teszt általában rendben működik.
    </p>
    <?php if ($hiba): ?><p class="alert alert-error"><?= $hiba ?></p><?php endif; ?>
    <?php if ($siker): ?><p class="alert alert-success"><?= h($siker) ?></p><?php endif; ?>
    <?php if (empty($fiokok)): ?>
    <p class="alert alert-error">Nincs még SMTP fiók. <a href="<?= h(BASE_URL) ?>/admin/email/letrehoz.php">Új fiók hozzáadása</a></p>
    <?php else: ?>
    <form method="post" id="email-teszt-form">
        <div class="form-group">
            <label>SMTP fiók *</label>
            <select name="config_id" required>
                <?php foreach ($fiokok as $f): ?>
                <option value="<?= (int)$f['id'] ?>" <?= $kivalasztott_id === (int)$f['id'] ? 'selected' : '' ?>>
                    <?= h($f['név']) ?> (<?= h($f['from_email']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Címzett e-mail *</label>
            <input type="email" name="cim" value="<?= h($_POST['cim'] ?? '') ?>" required placeholder="pelda@email.hu">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary" id="email-teszt-btn">Teszt e-mail küldése</button>
            <a href="<?= h(BASE_URL) ?>/admin/email/" class="btn btn-secondary">Vissza</a>
        </div>
        <p id="email-teszt-ujratoltes" class="text-muted" style="margin-top:0.75rem;display:none;">Ha a küldés lefutott vagy timeoutolt, <a href="<?= h(BASE_URL) ?>/admin/email/teszt.php">kattints ide</a> az oldal frissítéséhez.</p>
    </form>
    <script>
    (function() {
        var form = document.getElementById('email-teszt-form');
        var btn = document.getElementById('email-teszt-btn');
        var link = document.getElementById('email-teszt-ujratoltes');
        if (!form) return;
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            btn.disabled = true;
            btn.textContent = 'Küldés…';
            link.style.display = 'none';
            var fd = new FormData(form);
            var controller = new AbortController();
            var timeoutId = setTimeout(function() {
                controller.abort();
            }, 18000);
            fetch(form.action || window.location.href, {
                method: 'POST',
                body: fd,
                signal: controller.signal
            }).then(function(r) { return r.text(); }).then(function(html) {
                clearTimeout(timeoutId);
                btn.disabled = false;
                btn.textContent = 'Teszt e-mail küldése';
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var err = doc.querySelector('.alert-error');
                var ok = doc.querySelector('.alert-success');
                var msg = form.parentElement.querySelector('.email-teszt-eredmeny');
                if (msg) msg.remove();
                if (err) {
                    msg = document.createElement('p');
                    msg.className = 'alert alert-error email-teszt-eredmeny';
                    msg.textContent = err.textContent.trim();
                    form.parentElement.insertBefore(msg, form);
                } else if (ok) {
                    msg = document.createElement('p');
                    msg.className = 'alert alert-success email-teszt-eredmeny';
                    msg.textContent = ok.textContent.trim();
                    form.parentElement.insertBefore(msg, form);
                }
            }).catch(function(err) {
                clearTimeout(timeoutId);
                if (err.name === 'AbortError') {
                    alert('A küldés túl sokáig tartott (timeout 18 mp). Az SMTP szerver valószínűleg nem elérhető a tárhelyről (pl. a 587-es port blokkolva). Ellenőrizd az SMTP beállításokat vagy a tárhely dokumentációját.');
                } else {
                    alert('Hiba: ' + (err.message || 'ismeretlen'));
                }
                btn.disabled = false;
                btn.textContent = 'Teszt e-mail küldése';
                link.style.display = 'block';
            });
        });
    })();
    </script>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
