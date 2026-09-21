<?php
declare(strict_types=1);

/**
 * @var array<string, string> $H
 * @var list<array<string, mixed>> $enabledModules
 * @var list<array<string, mixed>> $quickNews
 * @var array<string, mixed> $dayEvents
 * @var array<int, list<array{color?: string}>> $categoriesByEventId
 * @var list<array<string, mixed>> $spotlightCards
 * @var list<array<string, mixed>> $spotlightVisible
 * @var int $spotlightMobileCount
 * @var array<string, string> $Dj
 * @var string $cmsAnchorSpotlight
 * @var array<string, string> $ratingStrings
 * @var string $ratingAjaxUrl
 * @var array{average?: float, count?: int} $ratingSummary
 * @var array{title: string, lead: string, cta_label: string, cta_url: string, note: string, show_icon: bool, is_external: bool, configured: bool} $donablyView
 * @var string $calendarUrl
 * @var string $djsUrl
 * @var string $editUrl
 * @var string $cssPublicUrl
 * @var string $cssHomeUrl
 * @var string $lang
 * @var string $htmlLang
 * @var array<string, string> $S
 * @var string $urlHu
 * @var string $urlEn
 * @var bool $isEventsHome
 * @var bool $showAdminEdit
 * @var string $adminEditUrl
 * @var list<array<string, mixed>> $adminFloatTools
 * @var array<int, array<string, mixed>> $calendarPreviewById
 * @var bool $lhModuleTrackAllowed
 */
$eventsPartial = dirname(__DIR__, 2) . '/events/partials';
$D = $Dj;
$categoriesByEventId = is_array($categoriesByEventId ?? null) ? $categoriesByEventId : [];
$calendarPreviewById = is_array($calendarPreviewById ?? null) ? $calendarPreviewById : [];
$enabledModules = is_array($enabledModules ?? null) ? $enabledModules : [];
$mobileOrderIndex = is_array($mobileOrderIndex ?? null) ? $mobileOrderIndex : [];
$lhModuleTrackAllowed = !empty($lhModuleTrackAllowed);
$donablyView = is_array($donablyView ?? null) ? $donablyView : [
    'title' => '',
    'lead' => '',
    'cta_label' => '',
    'cta_url' => '',
    'note' => '',
    'show_icon' => true,
    'is_external' => false,
    'configured' => false,
];

$railModules = [];
$calendarModules = [];
foreach ($enabledModules as $mod) {
    if ((string) ($mod['column'] ?? 'rail') === 'calendar') {
        $calendarModules[] = $mod;
    } else {
        $railModules[] = $mod;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= h($htmlLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#6d8f63">
    <?= events_public_robots_index_head_markup() ?>
    <title><?= h(latinfo_home_document_title($lang)) ?></title>
    <?= latinfo_home_share_head_markup($lang) ?>
    <?= events_public_favicon_head_markup() ?>
    <link rel="stylesheet" href="<?= h($cssPublicUrl) ?>">
    <link rel="stylesheet" href="<?= h($cssHomeUrl) ?>">
</head>
<body class="event-public-page event-public-page--home event-public-page--latinfo-home">
<?php require $eventsPartial . '/admin_float_tools.php'; ?>
<div class="event-shell">
<article class="event-public home-public latinfo-home">
    <header class="event-public__hero event-public__hero--bar-only">
        <?php require $eventsPartial . '/public_shell_hero_bar.php'; ?>
        <h1 class="visually-hidden"><?= h(SITE_NAME) ?></h1>
        <?php require __DIR__ . '/home_calendar_cta.php'; ?>
    </header>

    <div class="latinfo-home__stage">
        <div class="latinfo-home__board">
            <?php if ($railModules !== []): ?>
                <div class="latinfo-home__rail">
                    <?php foreach ($railModules as $mod): ?>
                        <?php require __DIR__ . '/home_module_render.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($calendarModules !== []): ?>
                <div class="latinfo-home__calendar" id="naptar">
                    <?php foreach ($calendarModules as $mod): ?>
                        <?php require __DIR__ . '/home_module_render.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php require $eventsPartial . '/public_shell_footer.php'; ?>
</article>
</div>
<?php require $eventsPartial . '/public_dj_spotlight_script.php'; ?>
<?php require $eventsPartial . '/event_image_orientation_script.php'; ?>
<?php require __DIR__ . '/home_module_track_script.php'; ?>
<?php if ($calendarPreviewById !== []): ?>
<?php
$D = $S;
require $eventsPartial . '/public_calendar_event_preview.php';
?>
<?php endif; ?>
</body>
</html>
