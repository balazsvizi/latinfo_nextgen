<?php
declare(strict_types=1);
/** @var int $id */
/** @var PDO $db */
/** @var string $portalHiba */

require_once dirname(__DIR__) . '/lib/organizer_accounts.php';
require_once dirname(__DIR__, 2) . '/lib/partner/partners.php';

$portalAccount = events_organizer_account_by_organizer_id($db, $id);
$tableReady = nextgen_partners_table_ready($db);
$partnerUrl = partner_url('');
$hiba = $portalHiba ?? '';
?>
<div class="card szervezo-admin-portal-card">
    <h2 class="card-title">Partner fiók</h2>
    <p class="help">
        A szervezői portál az egységes <strong>Partner portálba</strong> költözött:
        <a href="<?= h($partnerUrl) ?>" target="_blank" rel="noopener"><?= h($partnerUrl) ?></a>
    </p>
    <p class="help">
        Partner felvétel és szervező hozzárendelés:
        <a href="<?= h(nextgen_url('admin/partnerek/')) ?>">Admin → Partnerek</a>
        <?php if ($tableReady): ?>
            · <a href="<?= h(nextgen_url('admin/partnerek/szerkeszt.php')) ?>">szerkesztés</a>
        <?php endif; ?>
    </p>

    <?php if (!$tableReady): ?>
        <p class="alert alert-warning">
            Futtasd: <code>nextgen/partner/sql/migration_partners.sql</code>
            (a régi <code>events_organizer_accounts</code> adatokat is átmigrálja).
        </p>
    <?php elseif ($portalAccount !== null): ?>
        <p class="alert alert-warning">
            Ehhez a szervezőhöz még létezik régi portál fiók (<code><?= h((string) $portalAccount['email']) ?></code>).
            Hozd létre vagy rendeld hozzá a partnert az admin felületen, majd a régi fiók helyett a partner portált használd.
        </p>
    <?php elseif ($hiba !== ''): ?>
        <p class="alert alert-error"><?= h($hiba) ?></p>
    <?php endif; ?>

    <p class="toolbar">
        <a href="<?= h(nextgen_url('admin/partnerek/letrehoz.php')) ?>" class="btn btn-primary btn-sm">Új partner</a>
        <a href="<?= h(nextgen_url('admin/partnerek/')) ?>" class="btn btn-secondary btn-sm">Partner lista</a>
    </p>
</div>
