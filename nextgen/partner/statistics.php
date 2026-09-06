<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/lib/partner/media_value_trials.php';
partner_require_portal_permission('stat');

$db = getDb();
$partnerId = partner_current_id();
$context = partner_portal_current_context($db, $partnerId);
$scope = partner_portal_scope_ids($db, $partnerId, $context);
$organizerIds = $scope['organizer_ids'];

$statsParams = events_edit_stats_params_from_request($_GET);
$statsAllDateFrom = events_edit_stats_earliest_view_date_for_organizers($db, $organizerIds);
$statsData = events_edit_stats_for_organizers($db, $organizerIds, $statsParams);
$statsEventRows = $statsData['event_rows'] ?? [];
$statsPreferPartnerLinks = false;
$statsEventDetailUrl = static function (array $row): ?string {
    return partner_portal_event_public_url($row);
};

if ($organizerIds !== [] && !empty($statsParams['custom_rates'])) {
    $totals = is_array($statsData['totals'] ?? null) ? $statsData['totals'] : [];
    $pageHuman = (int) ($totals['page_views_human'] ?? 0);
    $externalHuman = (int) ($totals['external_info_clicks_human'] ?? 0);
    [$pageUnit, $clickUnit] = events_edit_stats_resolve_media_units($statsParams);
    $mediaValue = events_edit_stats_media_value($pageHuman, $externalHuman, $pageUnit, $clickUnit);
    nextgen_partner_media_value_trial_save($db, [
        'partner_id' => $partnerId,
        'page_unit_ft' => (int) $mediaValue['page_unit_ft'],
        'click_unit_ft' => (int) $mediaValue['click_unit_ft'],
        'page_views_human' => (int) $mediaValue['page_views_human'],
        'external_clicks_human' => (int) $mediaValue['external_clicks_human'],
        'page_value_ft' => (int) $mediaValue['page_value_ft'],
        'click_value_ft' => (int) $mediaValue['click_value_ft'],
        'total_ft' => (int) $mediaValue['total_ft'],
        'date_from' => (string) $statsParams['date_from'],
        'date_to' => (string) $statsParams['date_to'],
        'stat_mode' => (string) ($statsParams['mode'] ?? 'smart'),
        'context_label' => (string) ($context['label'] ?? ''),
    ]);
}

$pageTitle = 'Statisztikák';
$activeNav = 'stats';
require_once __DIR__ . '/partials/header.php';
?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="partner-page-head">
    <div>
        <h1 class="partner-page-title">Statisztikák</h1>
        <p class="partner-page-lead">
            Megtekintések és generált médiaérték a(z) <strong><?= h($context['label']) ?></strong> profil eseményein —
            ugyanaz a nézet, mint az esemény-statisztikáknál, partnerre szűrve.
        </p>
    </div>
</div>

<?php if ($organizerIds === []): ?>
    <div class="card">
        <p class="help">Nincs hozzárendelt szervező, ezért nincs megjeleníthető statisztika.</p>
    </div>
<?php else: ?>
    <?php
    $statsFormAction = partner_url('statistics.php');
    $statsChartDomId = 'partner-stats-chart';
    $statsAllowCustomMediaRates = true;
    require dirname(__DIR__) . '/events/organizers/partials/dashboard_stats.php';
    ?>
<?php endif; ?>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
