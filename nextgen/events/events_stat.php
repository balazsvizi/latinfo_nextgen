<?php
declare(strict_types=1);

/**
 * Event Admin Stat kezdőlap — partnerportál-szerű áttekintés az összes eseményre.
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/admin_stats.php';
requireLogin();

$db = getDb();
$home = events_admin_stats_home_summary($db);

$todayYmd = events_admin_calendar_effective_today()->format('Y-m-d');
$listUrl = events_url('events_admin.php');
$upcomingUrl = events_url('events_admin.php?' . http_build_query([
    'f_start_from' => $todayYmd,
    'list_limit' => 'all',
]));
$publishedUrl = events_url('events_admin.php?' . http_build_query([
    'status' => events_public_post_status(),
    'list_limit' => 'all',
]));
$chartsUrl = events_url('events_statisztika.php');
$listaStatUrl = events_url('events_lista_stat.php');
$realtimeUrl = events_url('events_realtime.php');
$calendarUrl = events_url('events_naptar.php');
$venuesUrl = events_url('venues.php');
$organizersUrl = events_url('organizers.php');
$categoriesUrl = events_url('categories.php');
$tagsUrl = events_url('tags.php');
$stylesUrl = events_url('styles.php');
$partnersUrl = nextgen_url('admin/partnerek/');
$financeUrl = events_url('finance.php');
$editBase = events_url('szerkeszt.php?id=');

$nextName = trim((string) (($home['next'] ?? [])['event_name'] ?? ''));
$catalog = $home['catalog'];

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Stat';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<section class="events-stat-hero">
    <div class="events-stat-hero__text">
        <p class="events-stat-hero__eyebrow">Stat</p>
        <h1 class="events-stat-hero__title">Áttekintés</h1>
        <p class="events-stat-hero__lead">Az összes esemény, a menük számai és a megtekintések egy helyen.</p>
    </div>
    <div class="events-stat-hero__actions">
        <a class="btn btn-primary" href="<?= h($listUrl) ?>">Események</a>
        <a class="btn btn-secondary" href="<?= h($calendarUrl) ?>">Naptár</a>
        <a class="btn btn-secondary" href="<?= h($realtimeUrl) ?>">Valós idejű</a>
    </div>
</section>

<div class="events-stat-grid" aria-label="Esemény összesítők">
    <a class="events-stat-card" href="<?= h($listUrl) ?>">
        <span class="events-stat-card__label">Események</span>
        <span class="events-stat-card__value"><?= (int) $home['events_total'] ?></span>
        <span class="events-stat-card__hint"><?= (int) $home['published'] ?> közzétett · <?= (int) $home['draft'] ?> piszkozat</span>
    </a>
    <a class="events-stat-card" href="<?= h($upcomingUrl) ?>">
        <span class="events-stat-card__label">Közelgő</span>
        <span class="events-stat-card__value"><?= (int) $home['upcoming'] ?></span>
        <span class="events-stat-card__hint">
            <?php if ($home['next'] !== null && $nextName !== ''): ?>
                Következő: <?= h($nextName) ?>
            <?php else: ?>
                Nincs közelgő esemény
            <?php endif; ?>
        </span>
    </a>
    <a class="events-stat-card" href="<?= h($chartsUrl) ?>">
        <span class="events-stat-card__label">Statisztikák</span>
        <span class="events-stat-card__value"><?= (int) $home['page_views_30']['human'] ?></span>
        <span class="events-stat-card__hint">Oldalmegtekintés · utolsó 30 nap</span>
    </a>
    <a class="events-stat-card" href="<?= h($realtimeUrl) ?>">
        <span class="events-stat-card__label">Valós idejű</span>
        <span class="events-stat-card__value"><?= (int) $home['live_users'] ?></span>
        <span class="events-stat-card__hint">Egyedi látogató · utolsó 30 perc</span>
    </a>
</div>

<div class="events-stat-grid events-stat-grid--catalog" aria-label="Menük számai">
    <a class="events-stat-card events-stat-card--catalog" href="<?= h($venuesUrl) ?>">
        <span class="events-stat-card__label">Helyszínek</span>
        <span class="events-stat-card__value"><?= (int) $catalog['venues'] ?></span>
        <span class="events-stat-card__hint">Helyszín lista</span>
    </a>
    <a class="events-stat-card events-stat-card--catalog" href="<?= h($organizersUrl) ?>">
        <span class="events-stat-card__label">Szervezők</span>
        <span class="events-stat-card__value"><?= (int) $catalog['organizers'] ?></span>
        <span class="events-stat-card__hint">Szervező lista</span>
    </a>
    <a class="events-stat-card events-stat-card--catalog" href="<?= h($partnersUrl) ?>">
        <span class="events-stat-card__label">Partnerek</span>
        <span class="events-stat-card__value"><?= (int) $catalog['partners'] ?></span>
        <span class="events-stat-card__hint">Partneradmin</span>
    </a>
    <a class="events-stat-card events-stat-card--catalog" href="<?= h($categoriesUrl) ?>">
        <span class="events-stat-card__label">Kategóriák</span>
        <span class="events-stat-card__value"><?= (int) $catalog['categories'] ?></span>
        <span class="events-stat-card__hint">Egyéb · kategóriák</span>
    </a>
    <a class="events-stat-card events-stat-card--catalog" href="<?= h($tagsUrl) ?>">
        <span class="events-stat-card__label">Címkék</span>
        <span class="events-stat-card__value"><?= (int) $catalog['tags'] ?></span>
        <span class="events-stat-card__hint">Egyéb · címkék</span>
    </a>
    <a class="events-stat-card events-stat-card--catalog" href="<?= h($stylesUrl) ?>">
        <span class="events-stat-card__label">Stílusok</span>
        <span class="events-stat-card__value"><?= (int) $catalog['styles'] ?></span>
        <span class="events-stat-card__hint">Stílus lista</span>
    </a>
</div>

<div class="events-stat-split">
    <section class="card events-stat-panel">
        <div class="events-stat-panel__head">
            <h2 class="card-title">Közelgő események</h2>
            <a href="<?= h($upcomingUrl) ?>" class="events-stat-panel__link">Összes →</a>
        </div>
        <?php if ($home['upcoming_events'] === []): ?>
            <p class="help">Nincs közelgő esemény.</p>
        <?php else: ?>
            <ul class="events-stat-event-list">
                <?php foreach ($home['upcoming_events'] as $ev): ?>
                    <?php
                    $eid = (int) ($ev['id'] ?? 0);
                    $clickUrl = $eid > 0 ? $editBase . $eid : $listUrl;
                    ?>
                    <li>
                        <a class="events-stat-event-row" href="<?= h($clickUrl) ?>">
                            <span class="events-stat-event-row__date"><?= h(events_admin_format_datum_cell($ev)) ?></span>
                            <span class="events-stat-event-row__name"><?= h((string) ($ev['event_name'] ?? '')) ?></span>
                            <span class="events-stat-event-row__meta">
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

    <section class="card events-stat-panel">
        <div class="events-stat-panel__head">
            <h2 class="card-title">Stat oldalak</h2>
        </div>
        <div class="events-stat-quick-links">
            <a href="<?= h($chartsUrl) ?>" class="events-stat-quick-link">
                <strong>Statisztikák</strong>
                <span>Grafikon és összesítő az összes eseményre</span>
            </a>
            <a href="<?= h($listaStatUrl) ?>" class="events-stat-quick-link">
                <strong>Lista stat</strong>
                <span>Eseményenkénti megtekintés, előnézet, átkattintás</span>
            </a>
            <a href="<?= h($realtimeUrl) ?>" class="events-stat-quick-link">
                <strong>Valós idejű</strong>
                <span>Élő aktivitás az elmúlt 30 percben</span>
            </a>
            <a href="<?= h($publishedUrl) ?>" class="events-stat-quick-link">
                <strong>Közzétett események</strong>
                <span>Csak a nyilvános naptárban szereplő tételek</span>
            </a>
            <a href="<?= h($financeUrl) ?>" class="events-stat-quick-link">
                <strong>Finance</strong>
                <span>Szervezői díjak és esemény-pénzügy</span>
            </a>
        </div>
    </section>
</div>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
