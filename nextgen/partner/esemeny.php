<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
partner_require_login();

$db = getDb();
$partnerId = partner_current_id();
$eventId = (int) ($_GET['id'] ?? 0);
partner_require_event_access($db, $eventId);

$event = partner_portal_event_by_id($db, $eventId);
if ($event === null) {
    flash('error', 'Az esemény nem található.');
    redirect(partner_url('esemenyek.php'));
}

$publishedStatus = events_public_post_status();
$status = (string) ($event['event_status'] ?? '');
$slug = trim((string) ($event['event_slug'] ?? ''));
$publicUrl = ($status === $publishedStatus && $slug !== '')
    ? events_public_canonical_url($slug)
    : null;

$organizers = partner_portal_event_organizer_names($db, $eventId);
$statsParams = events_edit_stats_params_from_request($_GET);
$statsData = events_edit_stats_for_event($db, $eventId, $statsParams);

$pageTitle = (string) ($event['event_name'] ?? 'Esemény');
$activeNav = 'events';
require_once __DIR__ . '/partials/header.php';

$venueBits = array_filter([
    trim((string) ($event['venue_name'] ?? '')),
    trim((string) ($event['venue_address'] ?? '')),
    trim((string) ($event['venue_city'] ?? '')),
]);
?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<p class="toolbar partner-toolbar">
    <a href="<?= h(partner_url('esemenyek.php')) ?>" class="btn btn-secondary">← Események</a>
    <?php if ($publicUrl !== null): ?>
        <a href="<?= h($publicUrl) ?>" class="btn btn-secondary" target="_blank" rel="noopener">Nyilvános oldal ↗</a>
    <?php endif; ?>
    <a href="<?= h(partner_url('naptar.php')) ?>" class="btn btn-secondary">Naptár</a>
</p>

<article class="card partner-event-detail">
    <header class="partner-event-detail__head">
        <p class="partner-event-detail__eyebrow"><?= h(events_admin_format_datum_cell($event)) ?></p>
        <h1 class="partner-page-title"><?= h((string) ($event['event_name'] ?? '')) ?></h1>
        <p class="partner-event-detail__status">
            <span class="event-status-badge <?= h(events_post_status_badge_class($status)) ?>"><?= h(events_post_status_label($status)) ?></span>
        </p>
    </header>

    <div class="partner-event-detail__grid">
        <div>
            <h2 class="partner-section-title">Részletek</h2>
            <dl class="partner-dl">
                <div>
                    <dt>Helyszín</dt>
                    <dd><?= h($venueBits !== [] ? implode(' · ', $venueBits) : 'Nincs megadva') ?></dd>
                </div>
                <div>
                    <dt>Szervezők</dt>
                    <dd>
                        <?php if ($organizers === []): ?>
                            —
                        <?php else: ?>
                            <?php foreach ($organizers as $i => $org): ?>
                                <?php if ($i > 0): ?>, <?php endif; ?>
                                <?= h($org['name']) ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </dd>
                </div>
                <?php
                $costFrom = $event['event_cost_from'] ?? null;
                $costTo = $event['event_cost_to'] ?? null;
                $costLabel = '';
                $hasFrom = $costFrom !== null && $costFrom !== '';
                $hasTo = $costTo !== null && $costTo !== '';
                if ($hasFrom || $hasTo) {
                    if ($hasFrom && $hasTo && (float) $costFrom !== (float) $costTo) {
                        $costLabel = (string) $costFrom . ' – ' . (string) $costTo . ' Ft';
                    } else {
                        $costLabel = (string) ($hasFrom ? $costFrom : $costTo) . ' Ft';
                    }
                }
                if ($costLabel !== ''):
                ?>
                    <div>
                        <dt>Belépő</dt>
                        <dd><?= h($costLabel) ?></dd>
                    </div>
                <?php endif; ?>
            </dl>
            <?php
            $desc = trim(strip_tags((string) ($event['event_description'] ?? '')));
            if ($desc !== ''):
                $short = mb_strlen($desc) > 400 ? mb_substr($desc, 0, 400) . '…' : $desc;
            ?>
                <p class="partner-event-detail__desc"><?= h($short) ?></p>
            <?php endif; ?>
        </div>
        <aside class="partner-event-detail__aside">
            <div class="partner-aside-card">
                <h3 class="partner-section-title">Megtekintések</h3>
                <?php
                $totals = $statsData['totals'] ?? [];
                $pageHuman = (int) ($totals['page_views_human'] ?? $totals['page_views'] ?? 0);
                $pageBot = (int) ($totals['page_views_bot'] ?? 0);
                $pageTotal = (int) ($totals['page_views'] ?? ($pageHuman + $pageBot));
                $previewHuman = (int) ($totals['calendar_previews_human'] ?? $totals['calendar_previews'] ?? 0);
                $previewTotal = (int) ($totals['calendar_previews'] ?? $previewHuman);
                ?>
                <p class="partner-aside-card__big"><?= $pageHuman ?></p>
                <p class="help">Oldal — emberi (választott időszak)</p>
                <p class="help">Bot: <?= $pageBot ?> · Össz: <?= $pageTotal ?></p>
                <p class="partner-aside-card__big partner-aside-card__big--sm"><?= $previewTotal ?></p>
                <p class="help">Naptár előnézet</p>
                <form method="get" class="partner-mini-stats-form">
                    <input type="hidden" name="id" value="<?= $eventId ?>">
                    <label class="events-filter-label" for="stat_date_from">Tól</label>
                    <input class="events-filter-input" type="date" name="stat_date_from" id="stat_date_from" value="<?= h($statsParams['date_from']) ?>">
                    <label class="events-filter-label" for="stat_date_to">Ig</label>
                    <input class="events-filter-input" type="date" name="stat_date_to" id="stat_date_to" value="<?= h($statsParams['date_to']) ?>">
                    <button type="submit" class="btn btn-secondary btn-sm">Frissítés</button>
                </form>
            </div>
            <?php if ($publicUrl !== null): ?>
                <p class="help">A nyilvános oldal külön nyílik meg — itt a partner nézetet látod.</p>
            <?php else: ?>
                <p class="help">Ez az esemény még nincs közzétéve, ezért nincs nyilvános link.</p>
            <?php endif; ?>
        </aside>
    </div>
</article>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
