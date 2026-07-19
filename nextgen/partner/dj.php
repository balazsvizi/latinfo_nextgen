<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
partner_require_login();

$db = getDb();
$partnerId = partner_current_id();
$tagId = (int) ($_GET['id'] ?? 0);
partner_require_dj_access($db, $tagId);

$djs = nextgen_partner_group_dj_assignments_for_form(nextgen_partner_djs($db, $partnerId));
$dj = null;
foreach ($djs as $row) {
    if ((int) ($row['tag_id'] ?? $row['id'] ?? 0) === $tagId) {
        $dj = $row;
        break;
    }
}
if ($dj === null) {
    flash('error', 'A DJ profil nem található.');
    redirect(partner_url('djs.php'));
}

partner_portal_set_context('d:' . $tagId);
$context = partner_portal_current_context($db, $partnerId);
$events = partner_portal_fetch_events($db, $partnerId, $context);
$stats = partner_portal_event_stats_summary($events);
$publicUrl = events_url('tag.php?id=') . $tagId;
$roleTypes = $dj['role_types'] ?? ['dj'];
if (!is_array($roleTypes) || $roleTypes === []) {
    $roleTypes = ['dj'];
}

$pageTitle = 'DJ: ' . (string) ($dj['name'] ?? '');
$activeNav = 'djs';
require_once __DIR__ . '/partials/header.php';
?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<p class="toolbar partner-toolbar">
    <a href="<?= h(partner_url('djs.php')) ?>" class="btn btn-secondary">← DJ-k</a>
    <a href="<?= h($publicUrl) ?>" class="btn btn-secondary" target="_blank" rel="noopener">Nyilvános DJ oldal ↗</a>
    <a href="<?= h(partner_url('esemenyek.php')) ?>" class="btn btn-secondary">Események</a>
</p>

<div class="card szervezo-profile-card">
    <h1 class="card-title"><?= h((string) ($dj['name'] ?? '')) ?></h1>
    <p class="help">
        <span class="partner-role-badges">
            <?php foreach ($roleTypes as $roleType): ?>
                <span class="partner-role-badge"><?= h(nextgen_partner_dj_role_label((string) $roleType)) ?></span>
            <?php endforeach; ?>
        </span>
        · DJ dashboard – események a címkéhez
    </p>
</div>

<div class="dash-cards szervezo-dash-cards">
    <div class="dash-card szervezo-dash-card">
        <h3>Összes esemény</h3>
        <div class="num"><?= (int) $stats['total'] ?></div>
    </div>
    <div class="dash-card szervezo-dash-card">
        <h3>Közzétett</h3>
        <div class="num"><?= (int) $stats['published'] ?></div>
    </div>
    <div class="dash-card szervezo-dash-card">
        <h3>Közelgő</h3>
        <div class="num"><?= (int) $stats['upcoming'] ?></div>
    </div>
    <div class="dash-card szervezo-dash-card">
        <h3>Következő</h3>
        <div class="num szervezo-dash-card__date">
            <?php if ($stats['next'] !== null): ?>
                <?= h((string) ($stats['next']['event_name'] ?? '')) ?>
            <?php else: ?>
                —
            <?php endif; ?>
        </div>
    </div>
</div>

<section class="card partner-panel">
    <div class="partner-panel__head">
        <h2 class="card-title">Események</h2>
    </div>
    <?php if ($events === []): ?>
        <p class="help">Ehhez a DJ profilhoz még nincs esemény.</p>
    <?php else: ?>
        <ul class="partner-event-list">
            <?php foreach (array_slice($events, 0, 40) as $ev): ?>
                <?php $eid = (int) ($ev['id'] ?? 0); ?>
                <li>
                    <a class="partner-event-row" href="<?= h(partner_portal_event_detail_url($eid)) ?>">
                        <span class="partner-event-row__date"><?= h(events_admin_format_datum_cell($ev)) ?></span>
                        <span class="partner-event-row__name"><?= h((string) ($ev['event_name'] ?? '')) ?></span>
                        <span class="partner-event-row__meta"><?= h(events_post_status_label((string) ($ev['event_status'] ?? ''))) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
