<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/init.php';
require_once dirname(__DIR__, 2) . '/lib/partner/partners.php';
require_once dirname(__DIR__, 2) . '/lib/partner/messages.php';
require_once dirname(__DIR__, 2) . '/lib/partner/migrate_finance_contacts.php';
require_once dirname(__DIR__, 2) . '/lib/partner/activity_log.php';
requireLogin();

$db = getDb();
$migrateFlash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'migrate_finance_contacts') {
    if (!csrf_validate('partner_admin_migrate_finance')) {
        $migrateFlash = ['type' => 'error', 'text' => 'Érvénytelen kérés (CSRF).'];
    } else {
        $migrateResult = nextgen_partner_migrate_from_finance_contacts($db);
        if ($migrateResult['ok']) {
            $migrateFlash = [
                'type' => 'success',
                'text' => sprintf(
                    'Migráció kész: %d új partner, %d egyesítve, %d már létezett, %d finance hozzárendelés.',
                    $migrateResult['created'],
                    $migrateResult['merged'],
                    $migrateResult['skipped'],
                    $migrateResult['linked']
                ),
            ];
        } else {
            $migrateFlash = [
                'type' => 'error',
                'text' => 'Migráció részben sikertelen: ' . implode(' ', $migrateResult['errors']),
            ];
        }
    }
}

$pageTitle = 'Partnerek';
require_once dirname(__DIR__, 2) . '/partials/header.php';

$kereso = trim((string) ($_GET['kereso'] ?? ''));
$partners = nextgen_partners_list($db, $kereso !== '' ? $kereso : null);
$tableReady = nextgen_partners_table_ready($db);
$unread = nextgen_partner_unread_reply_count($db);
$financeMigrateStatus = nextgen_partner_finance_contacts_migration_status($db);
$partnerActivityLog = nextgen_partner_activity_log_recent($db, 40);
$partnerActivityLogGlobal = true;
?>
<div class="card">
    <h2>Partnerek</h2>
    <?php if (!$tableReady): ?>
        <p class="alert alert-warning">Futtasd: <code>partner/sql/migration_partners.sql</code></p>
    <?php elseif (!nextgen_partner_activity_log_table_ready($db)): ?>
        <p class="alert alert-warning">Partner napló: futtasd <code>partner/sql/migration_partner_activity_log.sql</code></p>
    <?php endif; ?>
    <?php if ($migrateFlash): ?>
        <p class="alert alert-<?= $migrateFlash['type'] === 'success' ? 'success' : 'danger' ?>">
            <?= h($migrateFlash['text']) ?>
        </p>
    <?php endif; ?>
    <?php if ($tableReady && $financeMigrateStatus['total_contacts'] > 0): ?>
        <p class="help">
            Finance kontaktok: <?= (int) $financeMigrateStatus['migrated'] ?> / <?= (int) $financeMigrateStatus['total_contacts'] ?> migrálva.
            <?php if ($financeMigrateStatus['pending'] > 0): ?>
                <form method="post" style="display:inline;" onsubmit="return confirm('Minden finance kontakt partnerré alakítása? Az új partnerek inaktívak maradnak, jelszót az admin állít be.');">
                    <?= csrf_input('partner_admin_migrate_finance') ?>
                    <input type="hidden" name="action" value="migrate_finance_contacts">
                    <button type="submit" class="btn btn-secondary btn-sm">Finance kontaktok migrálása (<?= (int) $financeMigrateStatus['pending'] ?>)</button>
                </form>
            <?php endif; ?>
        </p>
    <?php endif; ?>
    <p class="toolbar">
        <form method="get" style="display:inline-flex;gap:0.5rem;flex-wrap:wrap;">
            <input type="search" name="kereso" placeholder="Név, e-mail, ID…" value="<?= h($kereso) ?>">
            <button type="submit" class="btn btn-secondary btn-sm">Keresés</button>
        </form>
        <a href="<?= h(nextgen_url('admin/partnerek/letrehoz.php')) ?>" class="btn btn-primary btn-sm">Új partner</a>
        <a href="<?= h(nextgen_url('admin/partnerek/uzenetek.php')) ?>" class="btn btn-secondary btn-sm">
            Üzenetek<?= $unread > 0 ? ' (' . $unread . ')' : '' ?>
        </a>
        <a href="<?= h(nextgen_url('partner/login.php')) ?>" class="btn btn-secondary btn-sm" target="_blank" rel="noopener">Partner portál</a>
    </p>
    <div class="table-wrap">
        <table class="sortable-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Név</th>
                    <th>E-mail</th>
                    <th>Telefon</th>
                    <th>Szervezők</th>
                    <th>DJ-k</th>
                    <th>Finance</th>
                    <th>Státusz</th>
                    <th>Műveletek</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($partners as $p): ?>
                <tr>
                    <td><?= (int) $p['id'] ?></td>
                    <td><?= h((string) ($p['név'] ?? '')) ?></td>
                    <td><?= h((string) ($p['email'] ?? '')) ?></td>
                    <td><?= h((string) ($p['telefon'] ?? '')) ?></td>
                    <td class="text-center"><?= (int) ($p['organizer_count'] ?? 0) ?></td>
                    <td class="text-center"><?= (int) ($p['dj_count'] ?? 0) ?></td>
                    <td class="text-center"><?= (int) ($p['finance_count'] ?? 0) ?></td>
                    <td><?= !empty($p['aktív']) ? 'Aktív' : 'Inaktív' ?></td>
                    <td class="actions">
                        <a href="<?= h(nextgen_url('admin/partnerek/szerkeszt.php?id=') . (int) $p['id']) ?>" class="btn btn-sm btn-secondary">Szerkeszt</a>
                        <a href="<?= h(nextgen_url('admin/partnerek/uzenetek.php?partner_id=') . (int) $p['id']) ?>" class="btn btn-sm btn-secondary">Üzenetek</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($partners === []): ?>
        <p class="help">Nincs partner.</p>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/partials/activity_log.php'; ?>
<?php require_once dirname(__DIR__, 2) . '/partials/footer.php'; ?>
