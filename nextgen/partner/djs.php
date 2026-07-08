<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
partner_require_login();

$db = getDb();
$partnerId = partner_current_id();
$djs = nextgen_partner_group_dj_assignments_for_form(nextgen_partner_djs($db, $partnerId));
$publishedStatus = events_public_post_status();

$pageTitle = 'DJ-k';
$activeNav = 'djs';
require_once __DIR__ . '/partials/header.php';
?>

<div class="card">
    <h1 class="card-title">DJ-k</h1>
    <p class="help">A hozzád rendelt DJ profilok (nyilvános címke oldalak).</p>

    <?php if ($djs === []): ?>
        <p class="help">Még nincs hozzárendelt DJ oldalad.</p>
    <?php else: ?>
        <div class="partner-entity-list">
            <?php foreach ($djs as $dj): ?>
                <?php
                $tagId = (int) ($dj['tag_id'] ?? $dj['id'] ?? 0);
                $publicUrl = events_url('tag.php?id=') . $tagId;
                $roleTypes = $dj['role_types'] ?? [];
                if (!is_array($roleTypes) || $roleTypes === []) {
                    $roleTypes = ['dj'];
                }
                $djNote = trim((string) ($dj['role_note'] ?? ''));
                $catalog = events_public_dj_catalog($db, $publishedStatus);
                $stats = null;
                foreach ($catalog as $row) {
                    if ((int) ($row['id'] ?? 0) === $tagId) {
                        $stats = $row;
                        break;
                    }
                }
                ?>
                <a class="partner-entity-card" href="<?= h($publicUrl) ?>" target="_blank" rel="noopener">
                    <div>
                        <p class="partner-entity-card__title"><?= h((string) ($dj['name'] ?? '')) ?></p>
                        <p class="partner-entity-card__meta">
                            <span class="partner-role-badges">
                                <?php foreach ($roleTypes as $roleType): ?>
                                    <span class="partner-role-badge"><?= h(nextgen_partner_dj_role_label((string) $roleType)) ?></span>
                                <?php endforeach; ?>
                            </span>
                            <?php if ($djNote !== ''): ?>
                                · <?= h($djNote) ?>
                            <?php elseif ($stats !== null): ?>
                                · <?= (int) ($stats['event_total'] ?? 0) ?> esemény,
                                <?= (int) ($stats['event_upcoming'] ?? 0) ?> közelgő
                            <?php else: ?>
                                · Nyilvános DJ oldal
                            <?php endif; ?>
                        </p>
                    </div>
                    <span aria-hidden="true">↗</span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
