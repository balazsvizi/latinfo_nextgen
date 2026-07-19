<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
partner_require_login();

$db = getDb();
$partnerId = partner_current_id();
$organizers = nextgen_partner_group_organizer_assignments_for_form(nextgen_partner_events_organizers($db, $partnerId));

$pageTitle = 'Szervezők';
$activeNav = 'organizers';
require_once __DIR__ . '/partials/header.php';
?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="partner-page-head">
    <div>
        <h1 class="partner-page-title">Szervezők</h1>
        <p class="partner-page-lead">Válaszd ki a szervezői profilod — dashboard, statisztikák, és az eseményeid.</p>
    </div>
</div>

<div class="card">
    <?php if ($organizers === []): ?>
        <p class="help">Még nincs hozzárendelt szerveződ. Kérjük, vedd fel a kapcsolatot az üzemeltetővel.</p>
    <?php else: ?>
        <div class="partner-entity-list">
            <?php foreach ($organizers as $org): ?>
                <?php
                $oid = (int) ($org['organizer_id'] ?? $org['id'] ?? 0);
                $roleTypes = $org['role_types'] ?? [];
                if (!is_array($roleTypes) || $roleTypes === []) {
                    $roleTypes = ['event'];
                }
                $orgNote = trim((string) ($org['role_note'] ?? ''));
                ?>
                <a class="partner-entity-card" href="<?= h(partner_url('szervezo.php?id=') . $oid) ?>">
                    <div>
                        <p class="partner-entity-card__title"><?= h((string) ($org['name'] ?? '')) ?></p>
                        <p class="partner-entity-card__meta">
                            <span class="partner-role-badges">
                                <?php foreach ($roleTypes as $roleType): ?>
                                    <span class="partner-role-badge"><?= h(nextgen_partner_organizer_role_label((string) $roleType)) ?></span>
                                <?php endforeach; ?>
                            </span>
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
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
