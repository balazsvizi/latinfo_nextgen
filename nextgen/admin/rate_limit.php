<?php
declare(strict_types=1);

/**
 * Rate limit bucketek listázása / nullázása (superadmin).
 */
require_once __DIR__ . '/../init.php';
requireSuperadmin();

$pageTitle = 'Belépési korlát (rate limit)';
$redirectUrl = nextgen_url('admin/rate_limit.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require('admin_rate_limit', '_csrf', $redirectUrl);
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'reset_one') {
        $bucket = rate_limit_sanitize_bucket((string) ($_POST['bucket'] ?? ''));
        if ($bucket === '' || $bucket === 'default') {
            flash('error', 'Érvénytelen bucket.');
        } elseif (rate_limit_reset($bucket)) {
            rendszer_log('rate_limit', null, 'Nullázva', $bucket);
            flash('success', 'Rate limit nullázva: ' . $bucket);
        } else {
            flash('error', 'Nem sikerült törölni a bucketet.');
        }
        redirect($redirectUrl);
    }

    if ($action === 'reset_self') {
        $bucket = rate_limit_sanitize_bucket(rate_limit_client_key('admin_login'));
        if (rate_limit_reset($bucket)) {
            rendszer_log('rate_limit', null, 'Saját IP nullázva', $bucket);
            flash('success', 'A saját IP admin belépési korlátja nullázva.');
        } else {
            flash('error', 'Nem sikerült nullázni.');
        }
        redirect($redirectUrl);
    }

    if ($action === 'reset_all') {
        $count = rate_limit_reset_all();
        rendszer_log('rate_limit', null, 'Összes nullázva', (string) $count . ' fájl');
        flash('success', 'Összes rate limit nullázva (' . $count . ').');
        redirect($redirectUrl);
    }

    flash('error', 'Ismeretlen művelet.');
    redirect($redirectUrl);
}

require_once __DIR__ . '/../partials/header.php';

$items = rate_limit_list();
$selfBucket = rate_limit_sanitize_bucket(rate_limit_client_key('admin_login'));
$flashSuccess = flash('success');
$flashError = flash('error');
?>
<div class="card">
    <h2>Belépési korlát (rate limit)</h2>
    <p>
        A sikertelen belépési próbálkozások IP-alapú korlátozása.
        A <code>vba</code> felhasználónál az ablak <strong>1 perc</strong>, egyébként <strong>15 perc</strong> (max. 8 próbálkozás).
    </p>

    <?php if ($flashSuccess): ?>
        <p class="alert alert-success"><?= h($flashSuccess) ?></p>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <p class="error"><?= h($flashError) ?></p>
    <?php endif; ?>

    <div class="toolbar" style="gap:0.5rem;flex-wrap:wrap;">
        <form method="post" action="" class="inline-form" onsubmit="return confirm('Nullázod a saját IP admin belépési korlátját?');">
            <?= csrf_input('admin_rate_limit') ?>
            <input type="hidden" name="action" value="reset_self">
            <button type="submit" class="btn btn-primary">Saját IP nullázása</button>
        </form>
        <form method="post" action="" class="inline-form" onsubmit="return confirm('Nullázod az ÖSSZES rate limit bucketet?');">
            <?= csrf_input('admin_rate_limit') ?>
            <input type="hidden" name="action" value="reset_all">
            <button type="submit" class="btn btn-danger">Összes nullázása</button>
        </form>
    </div>

    <p class="help text-muted" style="margin-top:0.75rem;">
        Saját admin bucket: <code><?= h($selfBucket) ?></code>
    </p>

    <?php if ($items === []): ?>
        <p style="margin-top:1rem;">Nincs aktív rate limit bejegyzés.</p>
    <?php else: ?>
        <div class="table-wrap" style="margin-top:1rem;">
            <table>
                <thead>
                    <tr>
                        <th>Bucket</th>
                        <th>Próbálkozások</th>
                        <th>Legrégebbi</th>
                        <th>Legújabb</th>
                        <th>Művelet</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <code><?= h($item['bucket']) ?></code>
                                <?php if ($item['bucket'] === $selfBucket): ?>
                                    <span class="text-muted">(saját IP)</span>
                                <?php endif; ?>
                            </td>
                            <td><?= (int) $item['hits'] ?></td>
                            <td><?= $item['oldest'] !== null ? h(date('Y-m-d H:i:s', (int) $item['oldest'])) : '—' ?></td>
                            <td><?= $item['newest'] !== null ? h(date('Y-m-d H:i:s', (int) $item['newest'])) : '—' ?></td>
                            <td class="actions">
                                <form method="post" action="" class="inline-form" onsubmit="return confirm('Nullázod ezt a bucketet?');">
                                    <?= csrf_input('admin_rate_limit') ?>
                                    <input type="hidden" name="action" value="reset_one">
                                    <input type="hidden" name="bucket" value="<?= h($item['bucket']) ?>">
                                    <button type="submit" class="btn btn-sm btn-secondary">Nullázás</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
