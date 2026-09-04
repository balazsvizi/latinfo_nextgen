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
$canStat = nextgen_partner_has_portal_permission($partner, 'stat');
$canMessages = nextgen_partner_has_portal_permission($partner, 'email_messages');
$canEventEdit = nextgen_partner_has_portal_permission($partner, 'event_edit');
$canEmailAutomata = nextgen_partner_has_portal_permission($partner, 'email_automata');

$context = partner_portal_current_context($db, $partnerId);
$events = [];
$stats = ['total' => 0, 'published' => 0, 'draft' => 0, 'upcoming' => 0, 'next' => null];
$pageViews30 = ['human' => 0];
$upcomingEvents = [];
$msgCount = 0;
$msgPending = false;

if ($canStat) {
    $events = partner_portal_fetch_events($db, $partnerId, $context);
    $stats = partner_portal_event_stats_summary($events);
    $pageViews30 = partner_portal_page_views_summary(
        $db,
        $partnerId,
        $context,
        events_edit_stats_range_for_preset('30')
    );
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
}

if ($canMessages) {
    $msgCount = partner_portal_message_count($db, $partnerId);
    $msgPending = partner_portal_admin_reply_pending($db, $partnerId);
}

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
    </div>
    <?php if ($canStat || $canMessages): ?>
        <div class="partner-hero__actions">
            <?php if ($canStat): ?>
                <a class="btn btn-primary" href="<?= h(partner_url('events.php')) ?>">Eseményeim</a>
                <a class="btn btn-secondary" href="<?= h(partner_url('calendar.php')) ?>">Naptár</a>
            <?php endif; ?>
            <?php if ($canMessages): ?>
                <a class="btn btn-secondary" href="<?= h(partner_url('messages.php')) ?>">Üzenetek<?= $msgPending ? ' · válasz érkezett' : '' ?></a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php if ($canStat || $canMessages): ?>
<div class="partner-stat-grid">
    <?php if ($canStat): ?>
        <a class="partner-stat-card" href="<?= h(partner_url('events.php')) ?>">
            <span class="partner-stat-card__label">Események</span>
            <span class="partner-stat-card__value"><?= (int) $stats['total'] ?></span>
            <span class="partner-stat-card__hint"><?= (int) $stats['published'] ?> közzétett · <?= (int) $stats['draft'] ?> piszkozat</span>
        </a>
        <a class="partner-stat-card" href="<?= h(partner_url('events.php?scope=upcoming')) ?>">
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
        <a class="partner-stat-card" href="<?= h(partner_url('statistics.php')) ?>">
            <span class="partner-stat-card__label">Statisztikák</span>
            <span class="partner-stat-card__value"><?= (int) $pageViews30['human'] ?></span>
            <span class="partner-stat-card__hint">Oldalmegtekintés · utolsó 30 nap</span>
        </a>
    <?php endif; ?>
    <?php if ($canMessages): ?>
        <a class="partner-stat-card<?= $msgPending ? ' partner-stat-card--pulse' : '' ?>" href="<?= h(partner_url('messages.php')) ?>">
            <span class="partner-stat-card__label">Üzenetek</span>
            <span class="partner-stat-card__value"><?= $msgCount ?></span>
            <span class="partner-stat-card__hint"><?= $msgPending ? 'Új válasz az admintól' : 'Üzenőfal a csapattal' ?></span>
        </a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($canStat || $canMessages): ?>
<div class="partner-split">
    <?php if ($canStat): ?>
    <section class="card partner-panel">
        <div class="partner-panel__head">
            <h2 class="card-title">Közelgő események</h2>
            <a href="<?= h(partner_url('events.php')) ?>" class="partner-panel__link">Összes →</a>
        </div>
        <?php if ($upcomingEvents === []): ?>
            <p class="help">Nincs közelgő esemény a kiválasztott profilhoz.</p>
        <?php else: ?>
            <ul class="partner-event-list">
                <?php foreach ($upcomingEvents as $ev): ?>
                    <?php
                    $clickUrl = partner_portal_event_click_url($ev);
                    $isPublic = partner_portal_event_public_url($ev) !== null;
                    ?>
                    <li>
                        <a class="partner-event-row" href="<?= h($clickUrl) ?>"<?= $isPublic ? ' target="_blank" rel="noopener"' : '' ?>>
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
    <?php endif; ?>

    <section class="card partner-panel">
        <div class="partner-panel__head">
            <h2 class="card-title">Gyors linkek</h2>
        </div>
        <div class="partner-quick-links">
            <?php if ($canStat): ?>
                <a href="<?= h(partner_url('calendar.php')) ?>" class="partner-quick-link">
                    <strong>Partner naptár</strong>
                    <span>Teljes hónap, a te eseményeid kiemelve</span>
                </a>
                <a href="<?= h(partner_url('statistics.php')) ?>" class="partner-quick-link">
                    <strong>Statisztikák</strong>
                    <span>Megtekintések a te eseményeiden</span>
                </a>
            <?php endif; ?>
            <?php if ($canMessages): ?>
                <a href="<?= h(partner_url('messages.php')) ?>" class="partner-quick-link">
                    <strong>Üzenet az adminnak</strong>
                    <span>Teljes előzménnyel, mint egy beszélgetés</span>
                </a>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php else: ?>
<div class="card">
    <h2 class="card-title">Nincs elérhető modul</h2>
    <p class="help">
        Ehhez a fiókhoz még nincs kiosztva a jelenlegi partnerportál funkciói (Stat vagy E-mail üzenetek).
        <?php if ($canEventEdit || $canEmailAutomata): ?>
            Az Esemény szerkesztés és az E-mail automata modulok később jelennek meg a portálon.
        <?php else: ?>
            Kérjük, vedd fel a kapcsolatot az üzemeltetővel.
        <?php endif; ?>
    </p>
    <p class="toolbar">
        <a href="<?= h(partner_url('profile.php')) ?>" class="btn btn-secondary">Profil</a>
    </p>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
