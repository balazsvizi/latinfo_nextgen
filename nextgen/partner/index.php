<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
partner_require_login();

$db = getDb();
$partnerId = partner_current_id();
partner_refresh_session_from_db($db);
$partner = partner_current($db);
if ($partner === null) {
    redirect(partner_url('login.php'));
}

$organizers = nextgen_partner_events_organizers($db, $partnerId);
$djs = nextgen_partner_djs($db, $partnerId);
$financeOrgs = nextgen_partner_finance_organizers($db, $partnerId);
$messages = nextgen_partner_messages_for_partner($db, $partnerId);

$pageTitle = 'Kezdőlap';
$activeNav = 'home';
require_once __DIR__ . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="card">
    <h1 class="card-title">Üdvözöllek, <?= h(partner_session_display_name()) ?>!</h1>
    <div class="partner-profile-grid">
        <div class="partner-profile-field">
            <span class="partner-profile-label">E-mail</span>
            <span class="partner-profile-value"><?= h((string) ($partner['email'] ?? '')) ?></span>
        </div>
        <div class="partner-profile-field">
            <span class="partner-profile-label">Telefon</span>
            <span class="partner-profile-value"><?= h((string) ($partner['telefon'] ?? '')) !== '' ? h((string) $partner['telefon']) : '–' ?></span>
        </div>
    </div>
</div>

<div class="dash-cards">
    <a href="<?= h(partner_url('szervezok.php')) ?>" class="dash-card">
        <h3>Szervezők</h3>
        <div class="num"><?= count($organizers) ?></div>
        <p>Esemény szervezői profilok</p>
    </a>
    <a href="<?= h(partner_url('djs.php')) ?>" class="dash-card">
        <h3>DJ-k</h3>
        <div class="num"><?= count($djs) ?></div>
        <p>Hozzárendelt DJ oldalak</p>
    </a>
    <a href="<?= h(partner_url('uzenetek.php')) ?>" class="dash-card">
        <h3>Üzenetek</h3>
        <div class="num"><?= count($messages) ?></div>
        <p>Üzenőfal a Latinfo csapat felé</p>
    </a>
    <?php if ($financeOrgs !== []): ?>
    <div class="dash-card">
        <h3>Finance szervezők</h3>
        <div class="num"><?= count($financeOrgs) ?></div>
        <p>Kapcsolt pénzügyi profilok</p>
    </div>
    <?php endif; ?>
</div>

<?php if ($organizers !== []): ?>
<div class="card">
    <h2 class="card-title">Szervezőid</h2>
    <div class="partner-entity-list">
        <?php foreach ($organizers as $org): ?>
            <?php
            $orgRole = nextgen_partner_organizer_role_label((string) ($org['role_type'] ?? 'event'));
            $orgNote = trim((string) ($org['role_note'] ?? ''));
            ?>
            <a class="partner-entity-card" href="<?= h(partner_url('szervezo.php?id=') . (int) $org['id']) ?>">
                <div>
                    <p class="partner-entity-card__title"><?= h((string) ($org['name'] ?? '')) ?></p>
                    <p class="partner-entity-card__meta">
                        <span class="partner-role-badge"><?= h($orgRole) ?></span>
                        <?php if ($orgNote !== ''): ?>
                            · <?= h($orgNote) ?>
                        <?php else: ?>
                            · Dashboard és statisztikák
                        <?php endif; ?>
                    </p>
                </div>
                <span aria-hidden="true">→</span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
