<?php
$pageTitle = 'E-mail / SMTP fiókok';
require_once __DIR__ . '/../../../nextgen/core/database.php';
require_once __DIR__ . '/../../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../../nextgen/includes/functions.php';
requireSuperadmin();

require_once __DIR__ . '/../../partials/header.php';

$db = getDb();
$stmt = $db->query('SELECT id, név, host, port, titkosítás, from_email, from_name, alapértelmezett, létrehozva
                    FROM finance_email_accounts ORDER BY alapértelmezett DESC, név ASC');
$listak = $stmt->fetchAll();
?>
<div class="card">
    <h2>E-mail / SMTP fiókok</h2>
    <p class="text-muted">SMTP fiókok kezelése és teszt e-mail küldése a kiválasztott fiókkal.</p>
    <div class="form-actions" style="margin-bottom: 1rem;">
        <a href="<?= h(nextgen_url('admin/email/letrehoz.php')) ?>" class="btn btn-primary">Új SMTP fiók</a>
        <a href="<?= h(nextgen_url('admin/email/teszt.php')) ?>" class="btn btn-secondary">Teszt e-mail küldése</a>
    </div>
    <div class="table-wrap">
        <table class="sortable-table">
            <thead>
                <tr>
                    <th>Név</th>
                    <th>Host</th>
                    <th>Port</th>
                    <th>Titkosítás</th>
                    <th>Feladó (From)</th>
                    <th>Alapértelmezett</th>
                    <th>Műveletek</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($listak)): ?>
                <tr><td colspan="7">Még nincs SMTP fiók. <a href="<?= h(nextgen_url('admin/email/letrehoz.php')) ?>">Új fiók hozzáadása</a></td></tr>
                <?php else: ?>
                <?php foreach ($listak as $r): ?>
                <tr>
                    <td><?= h($r['név']) ?></td>
                    <td><?= h($r['host']) ?></td>
                    <td><?= (int)$r['port'] ?></td>
                    <td><?= $r['titkosítás'] === 'tls' ? 'TLS' : ($r['titkosítás'] === 'ssl' ? 'SSL' : '–') ?></td>
                    <td><?= h($r['from_name'] ? $r['from_name'] . ' &lt;' . $r['from_email'] . '&gt;' : $r['from_email']) ?></td>
                    <td><?= $r['alapértelmezett'] ? 'Igen' : '–' ?></td>
                    <td class="actions">
                        <a href="<?= h(nextgen_url('admin/email/szerkeszt.php?id=')) ?><?= (int)$r['id'] ?>" class="btn btn-sm btn-secondary">Szerkeszt</a>
                        <a href="<?= h(nextgen_url('admin/email/teszt.php?config_id=')) ?><?= (int)$r['id'] ?>" class="btn btn-sm btn-primary">Teszt</a>
                        <form method="post" action="<?= h(nextgen_url('admin/email/torles.php')) ?>" style="display:inline;" onsubmit="return confirm('Biztosan törli ezt az SMTP fiókot?');">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Törlés</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
