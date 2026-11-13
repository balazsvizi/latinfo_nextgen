<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
partner_require_portal_permission('stat');

$db = getDb();
$partnerId = partner_current_id();
$context = partner_portal_current_context($db, $partnerId);
$events = partner_portal_editable_events(partner_portal_fetch_events($db, $partnerId, $context));
$q = mb_strtolower(trim((string) ($_GET['q'] ?? '')), 'UTF-8');

$filtered = [];
foreach ($events as $ev) {
    $name = mb_strtolower((string) ($ev['event_name'] ?? ''), 'UTF-8');
    if ($q !== '' && !str_contains($name, $q)) {
        continue;
    }
    $filtered[] = $ev;
}

$pageTitle = 'Szerkesztés';
$activeNav = 'edit';
require_once __DIR__ . '/partials/header.php';
?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>

<div class="partner-page-head">
    <div>
        <h1 class="partner-page-title">Szerkesztés</h1>
        <p class="partner-page-lead">
            A <strong><?= h($context['label']) ?></strong> profil azon eseményei, amelyeket szerkeszthetsz.
        </p>
    </div>
</div>

<form method="get" class="partner-filter-bar card">
    <div class="partner-filter-bar__row">
        <div class="form-group">
            <label for="event_q">Keresés</label>
            <input type="search" id="event_q" name="q" value="<?= h((string) ($_GET['q'] ?? '')) ?>" placeholder="Esemény neve…" autocomplete="off">
        </div>
        <div class="form-group partner-filter-bar__actions">
            <button type="submit" class="btn btn-primary">Szűrés</button>
            <a class="btn btn-secondary" href="<?= h(partner_url('szerkesztes.php')) ?>">Törlés</a>
        </div>
    </div>
</form>

<section class="card partner-panel">
    <p class="help" style="margin-top:0;"><?= count($filtered) ?> szerkeszthető esemény</p>
    <?php if ($filtered === []): ?>
        <p class="help">Nincs szerkeszthető esemény a kiválasztott profilhoz.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="partner-table">
                <thead>
                    <tr>
                        <th>Dátum</th>
                        <th>Név</th>
                        <th>Helyszín</th>
                        <th>Státusz</th>
                        <th><span class="visually-hidden">Szerkesztés</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filtered as $ev): ?>
                        <?php
                        $st = (string) ($ev['event_status'] ?? '');
                        $eventName = (string) ($ev['event_name'] ?? '');
                        $editUrl = partner_portal_event_edit_url((int) ($ev['id'] ?? 0));
                        $publicUrl = partner_portal_event_public_url($ev);
                        $venueBits = array_filter([
                            trim((string) ($ev['venue_name'] ?? '')),
                            trim((string) ($ev['venue_city'] ?? '')),
                        ]);
                        ?>
                        <tr>
                            <td data-label="Dátum"><?= h(events_admin_format_datum_cell($ev)) ?></td>
                            <td data-label="Név">
                                <a href="<?= h($editUrl) ?>"><?= h($eventName) ?></a>
                                <?php if ($publicUrl !== null): ?>
                                    <a class="partner-event-public-link" href="<?= h($publicUrl) ?>" target="_blank" rel="noopener">Nyilvános</a>
                                <?php endif; ?>
                            </td>
                            <td data-label="Helyszín"><?= h($venueBits !== [] ? implode(', ', $venueBits) : '—') ?></td>
                            <td data-label="Státusz">
                                <span class="event-status-badge <?= h(events_post_status_badge_class($st)) ?>"><?= h(events_post_status_label($st)) ?></span>
                            </td>
                            <td data-label="Szerkesztés"><?= partner_portal_edit_icon_html($editUrl, 'Szerkesztés: ' . $eventName) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
