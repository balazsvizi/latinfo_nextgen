<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
partner_require_login();

$db = getDb();
$partnerId = partner_current_id();
$context = partner_portal_current_context($db, $partnerId);
$events = partner_portal_fetch_events($db, $partnerId, $context);
$stats = partner_portal_event_stats_summary($events);
$publishedStatus = events_public_post_status();
$scope = (string) ($_GET['scope'] ?? 'all');
if (!in_array($scope, ['all', 'upcoming', 'past', 'draft'], true)) {
    $scope = 'all';
}
$q = mb_strtolower(trim((string) ($_GET['q'] ?? '')), 'UTF-8');

$nowTs = events_admin_calendar_effective_today()->getTimestamp();
$filtered = [];
foreach ($events as $ev) {
    $st = (string) ($ev['event_status'] ?? '');
    $name = mb_strtolower((string) ($ev['event_name'] ?? ''), 'UTF-8');
    if ($q !== '' && !str_contains($name, $q)) {
        continue;
    }
    $startRaw = trim((string) ($ev['event_start'] ?? ''));
    $endTs = null;
    if ($startRaw !== '') {
        try {
            $endRaw = trim((string) ($ev['event_end'] ?? $startRaw));
            $endTs = (new DateTimeImmutable($endRaw !== '' ? $endRaw : $startRaw))->getTimestamp();
        } catch (Throwable) {
            $endTs = null;
        }
    }
    $isDraft = in_array($st, ['draft', 'auto-draft'], true);
    $isUpcoming = $endTs !== null && $endTs >= $nowTs;
    $isPast = $endTs !== null && $endTs < $nowTs;

    if ($scope === 'draft' && !$isDraft) {
        continue;
    }
    if ($scope === 'upcoming' && !$isUpcoming) {
        continue;
    }
    if ($scope === 'past' && !$isPast) {
        continue;
    }
    $filtered[] = $ev;
}

$pageTitle = 'Események';
$activeNav = 'events';
require_once __DIR__ . '/partials/header.php';

$baseListUrl = partner_url('esemenyek.php');
?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="partner-page-head">
    <div>
        <h1 class="partner-page-title">Események</h1>
        <p class="partner-page-lead">
            A <strong><?= h($context['label']) ?></strong> profilhoz tartozó események.
            Kattints egy eseményre a partner részletekhez (nem a nyilvános oldalra).
        </p>
    </div>
</div>

<div class="partner-stat-grid partner-stat-grid--compact">
    <div class="partner-stat-card partner-stat-card--static">
        <span class="partner-stat-card__label">Összes</span>
        <span class="partner-stat-card__value"><?= (int) $stats['total'] ?></span>
    </div>
    <div class="partner-stat-card partner-stat-card--static">
        <span class="partner-stat-card__label">Közelgő</span>
        <span class="partner-stat-card__value"><?= (int) $stats['upcoming'] ?></span>
    </div>
    <div class="partner-stat-card partner-stat-card--static">
        <span class="partner-stat-card__label">Közzétett</span>
        <span class="partner-stat-card__value"><?= (int) $stats['published'] ?></span>
    </div>
    <div class="partner-stat-card partner-stat-card--static">
        <span class="partner-stat-card__label">Piszkozat</span>
        <span class="partner-stat-card__value"><?= (int) $stats['draft'] ?></span>
    </div>
</div>

<form method="get" class="partner-filter-bar card">
    <div class="partner-filter-bar__row">
        <div class="form-group">
            <label for="event_q">Keresés</label>
            <input type="search" id="event_q" name="q" value="<?= h((string) ($_GET['q'] ?? '')) ?>" placeholder="Esemény neve…" autocomplete="off">
        </div>
        <div class="form-group">
            <label for="event_scope">Szűrés</label>
            <select id="event_scope" name="scope">
                <option value="all"<?= $scope === 'all' ? ' selected' : '' ?>>Összes</option>
                <option value="upcoming"<?= $scope === 'upcoming' ? ' selected' : '' ?>>Közelgő</option>
                <option value="past"<?= $scope === 'past' ? ' selected' : '' ?>>Múltbeli</option>
                <option value="draft"<?= $scope === 'draft' ? ' selected' : '' ?>>Piszkozatok</option>
            </select>
        </div>
        <div class="form-group partner-filter-bar__actions">
            <button type="submit" class="btn btn-primary">Szűrés</button>
            <a class="btn btn-secondary" href="<?= h($baseListUrl) ?>">Törlés</a>
        </div>
    </div>
</form>

<section class="card partner-panel">
    <p class="help" style="margin-top:0;"><?= count($filtered) ?> esemény</p>
    <?php if ($filtered === []): ?>
        <p class="help">Nincs megjeleníthető esemény.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="partner-table">
                <thead>
                    <tr>
                        <th>Dátum</th>
                        <th>Név</th>
                        <th>Helyszín</th>
                        <th>Státusz</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filtered as $ev): ?>
                        <?php
                        $eid = (int) ($ev['id'] ?? 0);
                        $st = (string) ($ev['event_status'] ?? '');
                        $venueBits = array_filter([
                            trim((string) ($ev['venue_name'] ?? '')),
                            trim((string) ($ev['venue_city'] ?? '')),
                        ]);
                        ?>
                        <tr>
                            <td data-label="Dátum"><?= h(events_admin_format_datum_cell($ev)) ?></td>
                            <td data-label="Név">
                                <a href="<?= h(partner_portal_event_detail_url($eid)) ?>"><?= h((string) ($ev['event_name'] ?? '')) ?></a>
                            </td>
                            <td data-label="Helyszín"><?= h($venueBits !== [] ? implode(', ', $venueBits) : '—') ?></td>
                            <td data-label="Státusz">
                                <span class="event-status-badge <?= h(events_post_status_badge_class($st)) ?>"><?= h(events_post_status_label($st)) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
