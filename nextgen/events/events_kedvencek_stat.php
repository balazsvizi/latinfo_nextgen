<?php
declare(strict_types=1);

/**
 * Kedvencek (szívecskék) statisztika.
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/event_edit_stats.php';
require_once __DIR__ . '/lib/favorite_stats.php';
requireLogin();

$db = getDb();
$statsQuery = $_GET;
if (
    trim((string) ($statsQuery['stat_date_from'] ?? '')) === ''
    && trim((string) ($statsQuery['stat_date_to'] ?? '')) === ''
) {
    $defaultRange = events_edit_stats_range_for_preset('7');
    $statsQuery['stat_date_from'] = $defaultRange['date_from'];
    $statsQuery['stat_date_to'] = $defaultRange['date_to'];
}

$statsParams = latinfo_favorite_stats_params_from_request($statsQuery);
$statsAllDateFrom = latinfo_favorite_stats_earliest_date($db);
$statsData = latinfo_favorite_stats($db, $statsParams);

$statsFormAction = events_url('events_kedvencek_stat.php');
$statsActivePreset = events_edit_stats_detect_preset($statsParams, $statsAllDateFrom);
$statsFilterExtraQuery = array_filter([
    'fav_type' => $statsParams['entity_type'] !== 'all' ? $statsParams['entity_type'] : null,
    'fav_actor' => $statsParams['actor'] !== 'all' ? $statsParams['actor'] : null,
], static fn ($v): bool => $v !== null && $v !== '');

$statsPresetLinks = [];
foreach (events_edit_stats_presets() as $preset) {
    $presetId = (string) $preset['id'];
    $statsPresetLinks[] = [
        'id' => $presetId,
        'label' => (string) $preset['label'],
        'url' => events_edit_stats_filter_url(
            $statsFormAction,
            events_edit_stats_range_for_preset($presetId, $statsAllDateFrom),
            $statsFilterExtraQuery
        ),
        'active' => $statsActivePreset === $presetId,
    ];
}

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Kedvencek stat';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="events-stat-page-head">
    <div>
        <h1 class="events-stat-page-title">Kedvencek (szívecskék)</h1>
        <p class="events-stat-page-lead">
            Esemény, szervező, helyszín, DJ és előadó szívecskéi időszak, típus és fiók/vendég bontásban.
            <?= h((string) $statsParams['date_from']) ?> – <?= h((string) $statsParams['date_to']) ?>.
        </p>
    </div>
    <div class="events-stat-page-actions">
        <a href="<?= h(events_url('events_statisztika.php')) ?>" class="btn btn-secondary btn-sm">Esemény stat</a>
        <a href="<?= h(events_url('events_havi_stat.php')) ?>" class="btn btn-secondary btn-sm">Havi stat</a>
        <a href="<?= h(events_url('events_public_stat.php')) ?>" class="btn btn-secondary btn-sm">Publikus oldalak</a>
        <a href="<?= h(events_url('kedvencek_beallitas.php')) ?>" class="btn btn-secondary btn-sm">Beállítás</a>
        <a href="<?= h(events_url('events_realtime.php')) ?>" class="btn btn-secondary btn-sm">Valós idejű</a>
    </div>
</div>

<?php require __DIR__ . '/partials/favorite_stats.php'; ?>

<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
