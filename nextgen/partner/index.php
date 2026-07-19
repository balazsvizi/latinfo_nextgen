<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
partner_require_login();

$db = getDb();
partner_refresh_session_from_db($db);
$partner = partner_current($db);
if ($partner === null) {
    redirect(partner_url('login.php'));
}

$partnerId = partner_current_id();
$context = partner_portal_current_context($db, $partnerId);
$contexts = partner_portal_available_contexts($db, $partnerId);
$events = partner_portal_fetch_events($db, $partnerId, $context);
$stats = partner_portal_event_stats_summary($events);
$msgCount = partner_portal_message_count($db, $partnerId);
$msgPending = partner_portal_admin_reply_pending($db, $partnerId);

$upcomingEvents = [];
$nowTs = events_admin_calendar_effective_today()->getTimestamp();
foreach ($events as $ev) {
    $startRaw = trim((string) ($ev['event_start'] ?? ''));
    if ($startRaw === '') {
        continue;
    }
    try {
        $endRaw = trim((string) ($ev['event_end'] ?? $startRaw));
        $endTs = (new DateTimeImmutable($endRaw !== '' ? $endRaw : $startRaw))->getTimestamp();
    } catch (Throwable) {
        continue;
    }
    if ($endTs >= $nowTs) {
        $upcomingEvents[] = $ev;
    }
    if (count($upcomingEvents) >= 5) {
        break;
    }
}

$orgCount = count(array_filter($contexts, static fn (array $c): bool => $c['type'] === 'organizer'));
$djCount = count(array_filter($contexts, static fn (array $c): bool => $c['type'] === 'dj'));

$pageTitle = 'Kezdőlap';
$activeNav = 'home';
require_once __DIR__ . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<section class="partner-hero partner-hero--compact">
    <div class="partner-hero__text">
        <p class="partner-hero__eyebrow">Kezdőlap</p>
        <h1 class="partner-hero__title">Áttekintés</h1>
        <p class="partner-hero__lead">
            Események, naptár, profilok és üzenetek — az aktív partner: <strong><?= h($context['label']) ?></strong>.
        </p>
    </div>
    <div class="partner-hero__actions">
        <a class="btn btn-primary" href="<?= h(partner_url('esemenyek.php')) ?>">Eseményeim</a>
        <a class="btn btn-secondary" href="<?= h(partner_url('naptar.php')) ?>">Naptár</a>
        <a class="btn btn-secondary" href="<?= h(partner_url('uzenetek.php')) ?>">Üzenetek<?= $msgPending ? ' · válasz érkezett' : '' ?></a>
    </div>
</section>

<div class="partner-stat-grid">
    <a class="partner-stat-card" href="<?= h(partner_url('esemenyek.php')) ?>">
        <span class="partner-stat-card__label">Események</span>
        <span class="partner-stat-card__value"><?= (int) $stats['total'] ?></span>
        <span class="partner-stat-card__hint"><?= (int) $stats['published'] ?> közzétett · <?= (int) $stats['draft'] ?> piszkozat</span>
    </a>
    <a class="partner-stat-card" href="<?= h(partner_url('esemenyek.php?scope=upcoming')) ?>">
        <span class="partner-stat-card__label">Közelgő</span>
        <span class="partner-stat-card__value"><?= (int) $stats['upcoming'] ?></span>
        <span class="partner-stat-card__hint">
            <?php if ($stats['next'] !== null): ?>
                Következő: <?= h((string) ($stats['next']['event_name'] ?? '')) ?>
            <?php else: ?>
                Nincs közelgő esemény
            <?php endif; ?>
        </span>
    </a>
    <a class="partner-stat-card" href="<?= h(partner_url('szervezok.php')) ?>">
        <span class="partner-stat-card__label">Profilok</span>
        <span class="partner-stat-card__value"><?= $orgCount + $djCount ?></span>
        <span class="partner-stat-card__hint"><?= $orgCount ?> szervező · <?= $djCount ?> DJ</span>
    </a>
    <a class="partner-stat-card<?= $msgPending ? ' partner-stat-card--pulse' : '' ?>" href="<?= h(partner_url('uzenetek.php')) ?>">
        <span class="partner-stat-card__label">Üzenetek</span>
        <span class="partner-stat-card__value"><?= $msgCount ?></span>
        <span class="partner-stat-card__hint"><?= $msgPending ? 'Új válasz az admintól' : 'Üzenőfal a csapattal' ?></span>
    </a>
</div>

<div class="partner-split">
    <section class="card partner-panel">
        <div class="partner-panel__head">
            <h2 class="card-title">Közelgő események</h2>
            <a href="<?= h(partner_url('esemenyek.php')) ?>" class="partner-panel__link">Összes →</a>
        </div>
        <?php if ($upcomingEvents === []): ?>
            <p class="help">Nincs közelgő esemény a kiválasztott profilhoz.</p>
        <?php else: ?>
            <ul class="partner-event-list">
                <?php foreach ($upcomingEvents as $ev): ?>
                    <?php $eid = (int) ($ev['id'] ?? 0); ?>
                    <li>
                        <a class="partner-event-row" href="<?= h(partner_portal_event_detail_url($eid)) ?>">
                            <span class="partner-event-row__date"><?= h(events_admin_format_datum_cell($ev)) ?></span>
                            <span class="partner-event-row__name"><?= h((string) ($ev['event_name'] ?? '')) ?></span>
                            <span class="partner-event-row__meta">
                                <?= h(events_post_status_label((string) ($ev['event_status'] ?? ''))) ?>
                                <?php if (trim((string) ($ev['venue_city'] ?? '')) !== ''): ?>
                                    · <?= h((string) $ev['venue_city']) ?>
                                <?php endif; ?>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="card partner-panel">
        <div class="partner-panel__head">
            <h2 class="card-title">Gyors linkek</h2>
        </div>
        <div class="partner-quick-links">
            <a href="<?= h(partner_url('naptar.php')) ?>" class="partner-quick-link">
                <strong>Partner naptár</strong>
                <span>Teljes hónap, a te eseményeid kiemelve</span>
            </a>
            <?php if ($orgCount > 0): ?>
                <a href="<?= h(partner_url('szervezok.php')) ?>" class="partner-quick-link">
                    <strong>Szervező dashboard</strong>
                    <span>Statisztikák és megtekintések</span>
                </a>
            <?php endif; ?>
            <?php if ($djCount > 0): ?>
                <a href="<?= h(partner_url('djs.php')) ?>" class="partner-quick-link">
                    <strong>DJ profilok</strong>
                    <span>Események a DJ címkéidhez</span>
                </a>
            <?php endif; ?>
            <a href="<?= h(partner_url('uzenetek.php')) ?>" class="partner-quick-link">
                <strong>Üzenet az adminnak</strong>
                <span>Teljes előzménnyel, mint egy beszélgetés</span>
            </a>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
