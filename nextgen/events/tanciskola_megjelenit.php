<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/event_public_lang.php';
require_once __DIR__ . '/lib/dance_schools.php';
require_once __DIR__ . '/lib/dance_entity_views.php';

$lang = events_public_resolve_megjelenit_lang();
$C = events_public_common_nav_strings($lang);
$S = [
    'logo_home_title' => (string) ($C['logo_home_title'] ?? 'Latinfo'),
    'logo_home_aria' => (string) ($C['logo_home_aria'] ?? 'Latinfo főoldal'),
    'footer_home_link' => (string) ($C['footer_home_link'] ?? 'Latinfo.hu'),
    'admin_edit_title' => $lang === 'en' ? 'Edit' : 'Szerkesztés',
    'admin_edit_aria' => $lang === 'en' ? 'Edit this dance school in admin' : 'Tánciskola szerkesztése az adminban',
];
$cssUrl = events_url('assets/event_public.css');
$htmlLang = $lang === 'en' ? 'en' : 'hu';

$slug = trim((string) ($_GET['slug'] ?? ''));
$db = getDb();
dance_schools_ensure_schema($db);
$school = $slug !== '' ? dance_school_by_slug($db, $slug) : null;

$notFound = static function () use ($lang, $htmlLang, $cssUrl, $S): void {
    http_response_code(404);
    events_public_send_noindex_header();
    header('Content-Type: text/html; charset=UTF-8');
    $urlHu = events_public_home_lang_switch_url('hu');
    $urlEn = events_public_home_lang_switch_url('en');
    ?><!DOCTYPE html>
<html lang="<?= h($htmlLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= events_public_ga_head_markup() ?>
    <?= events_public_robots_noindex_head_markup() ?>
    <title><?= h($lang === 'en' ? 'Dance school not found' : 'Nincs ilyen tánciskola') ?></title>
    <?= events_public_favicon_head_markup() ?>
    <link rel="stylesheet" href="<?= h($cssUrl) ?>">
</head>
<body class="event-public-page">
<div class="event-shell">
<article class="event-public event-public--not-found">
    <header class="event-public__hero event-public__hero--compact">
        <?php
        $showAdminEdit = false;
        $adminEditUrl = '';
        require __DIR__ . '/partials/public_shell_hero_bar.php';
        ?>
    </header>
    <p class="event-not-found-msg"><?= h($lang === 'en' ? 'Dance school not found.' : 'Nincs ilyen tánciskola.') ?></p>
    <?php $standalone = true; require __DIR__ . '/partials/public_shell_footer.php'; ?>
</article>
</div>
</body>
</html><?php
    exit;
};

if ($school === null) {
    $notFound();
}

$schoolId = (int) ($school['id'] ?? 0);
$isActive = !empty($school['is_active']);
$isPublished = !empty($school['is_published']);
$isPubliclyVisible = $isActive && $isPublished;
$isAdmin = function_exists('isLoggedIn') && isLoggedIn();
if (!$isPubliclyVisible && !$isAdmin) {
    $notFound();
}
$isPreview = !$isPubliclyVisible;

$title = (string) ($school['name'] ?? '');
$safeBody = events_sanitize_html_fragment((string) ($school['description'] ?? ''));
$descRaw = trim(strip_tags($safeBody));
$desc = function_exists('mb_substr') ? mb_substr($descRaw, 0, 160, 'UTF-8') : substr($descRaw, 0, 160);

$venues = [];
foreach (dance_school_venues($db, $schoolId) as $link) {
    if (empty($link['is_active'])) {
        continue;
    }
    $linkId = (int) ($link['id'] ?? 0);
    $link['offerings'] = dance_school_offerings_for_school_venue($db, $linkId);
    $venues[] = $link;
}
$teachers = dance_school_teachers_list($db, $schoolId);
$eventsAll = dance_school_events_list($db, $schoolId);
$eventsUpcoming = [];
$nowTs = time();
foreach ($eventsAll as $ev) {
    if (empty($ev['is_published']) && !$isAdmin) {
        continue;
    }
    $starts = strtotime((string) ($ev['starts_at'] ?? ''));
    if ($starts !== false && $starts >= $nowTs - 3600) {
        $eventsUpcoming[] = $ev;
    }
}

$ageLabels = dance_school_age_group_labels();
$levelLabels = dance_school_level_labels();
$classTypeLabels = dance_school_class_type_labels();
$eventTypeLabels = dance_school_event_type_labels();
$roleLabels = dance_school_teacher_role_labels();

$photoUrl = trim((string) ($school['photo_url'] ?? ''));
$logoUrl = trim((string) ($school['logo_url'] ?? ''));
$photoAbs = $photoUrl !== '' ? events_absolute_url($photoUrl) : '';
$logoAbs = $logoUrl !== '' ? events_absolute_url($logoUrl) : '';
$heroMedia = $photoAbs !== '' ? $photoAbs : $logoAbs;
$heroIsLogo = $photoAbs === '' && $logoAbs !== '';

$website = trim((string) ($school['website_url'] ?? ''));
$facebook = trim((string) ($school['facebook_url'] ?? ''));
$instagram = trim((string) ($school['instagram_url'] ?? ''));
$tiktok = trim((string) ($school['tiktok_url'] ?? ''));
$youtube = trim((string) ($school['youtube_url'] ?? ''));
$email = !empty($school['email_is_private']) && !$isAdmin ? '' : trim((string) ($school['email'] ?? ''));
$phone = !empty($school['phone_is_private']) && !$isAdmin ? '' : trim((string) ($school['phone'] ?? ''));
$scheduleUrl = trim((string) ($school['schedule_url'] ?? ''));
$registrationUrl = trim((string) ($school['registration_url'] ?? ''));
$city = trim((string) ($school['city'] ?? ''));
$trialInfo = trim((string) ($school['trial_lesson_info'] ?? ''));
$pricingInfo = trim((string) ($school['pricing_info'] ?? ''));
$languages = trim((string) ($school['languages'] ?? ''));

$canonical = events_absolute_url(events_tanciskola_megjelenit_url((string) $school['slug']));
$urlHu = events_tanciskola_megjelenit_url((string) $school['slug']);
$urlEn = $urlHu . (str_contains($urlHu, '?') ? '&' : '?') . 'lang=en';
$showAdminEdit = $isAdmin;
$adminEditUrl = events_url('tanciskola_szerkeszt.php?id=') . $schoolId;

dance_entity_view_record($db, 'school', $schoolId, $isPreview ? 'preview' : 'page_view');

header('Content-Type: text/html; charset=UTF-8');
if ($isPreview) {
    events_public_send_noindex_header();
}
?>
<!DOCTYPE html>
<html lang="<?= h($htmlLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= events_public_ga_head_markup() ?>
    <?= $isPreview ? events_public_robots_noindex_head_markup() : events_public_robots_index_head_markup() ?>
    <title><?= h($title) ?> – <?= h(SITE_NAME) ?></title>
    <?php if ($desc !== ''): ?><meta name="description" content="<?= h($desc) ?>"><?php endif; ?>
    <link rel="canonical" href="<?= h($canonical) ?>">
    <?= events_public_favicon_head_markup() ?>
    <link rel="stylesheet" href="<?= h($cssUrl) ?>">
</head>
<body class="event-public-page">
<div class="event-shell">
<article class="event-public dance-school-public">
    <header class="event-public__hero">
        <?php require __DIR__ . '/partials/public_shell_hero_bar.php'; ?>
        <div class="event-public__hero-inner dj-public__hero-inner">
            <?php if ($isPreview): ?>
                <p style="margin:0 0 0.75rem;padding:0.55rem 0.75rem;border:1px solid #c9a227;background:#fff8e1;border-radius:6px;font-size:0.95rem;">
                    <?= h(!$isActive
                        ? ($lang === 'en' ? 'Admin preview — this school is inactive.' : 'Admin előnézet — ez a tánciskola inaktív.')
                        : ($lang === 'en' ? 'Admin preview — not published yet.' : 'Admin előnézet — még nem publikus.')) ?>
                </p>
            <?php endif; ?>
            <div class="dj-public__identity">
                <?php if ($heroMedia !== ''): ?>
                    <div class="dj-public__media">
                        <div class="dj-public__avatar<?= $heroIsLogo ? ' dj-public__avatar--logo' : '' ?>">
                            <img class="dj-public__avatar-img<?= $heroIsLogo ? ' dj-public__avatar-img--logo' : '' ?>" src="<?= h($heroMedia) ?>" alt="" width="120" height="120" loading="eager">
                        </div>
                    </div>
                <?php endif; ?>
                <div class="dj-public__identity-text">
                    <p class="event-public__eyebrow"><?= h($lang === 'en' ? 'Dance school' : 'Tánciskola') ?></p>
                    <h1 class="event-public__title"><?= h($title) ?></h1>
                    <?php if ($city !== ''): ?>
                        <p class="venue-address-line"><?= h($city) ?></p>
                    <?php endif; ?>
                    <div class="event-public__badges" style="margin-top:0.5rem;">
                        <?php if (!empty($school['accepts_beginners'])): ?>
                            <span class="event-status-badge"><?= h($lang === 'en' ? 'Beginners welcome' : 'Fogad kezdőket') ?></span>
                        <?php endif; ?>
                        <?php if (!empty($school['has_kids_classes'])): ?>
                            <span class="event-status-badge"><?= h($lang === 'en' ? 'Kids classes' : 'Gyerekóra') ?></span>
                        <?php endif; ?>
                        <?php if (!empty($school['has_performance_team'])): ?>
                            <span class="event-status-badge"><?= h($lang === 'en' ? 'Performance team' : 'Fellépő csoport') ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php
            $djProfile = [
                'website_url' => $website,
                'facebook_url' => $facebook,
                'instagram_url' => $instagram,
                'tiktok_url' => $tiktok,
                'youtube_url' => $youtube,
                'email' => $email,
                'phone' => $phone,
            ];
            $G = [
                'dj_contacts_aria' => $lang === 'en' ? 'Contacts' : 'Elérhetőségek',
                'dj_link_website' => $lang === 'en' ? 'Website' : 'Weboldal',
                'dj_link_facebook' => 'Facebook',
                'dj_link_instagram' => 'Instagram',
                'dj_link_tiktok' => 'TikTok',
                'dj_link_youtube' => 'YouTube',
                'dj_link_email' => $lang === 'en' ? 'Email' : 'e-mail',
                'dj_link_phone' => $lang === 'en' ? 'Phone' : 'Telefon',
            ];
            require __DIR__ . '/partials/public_dj_contacts.php';
            ?>
            <?php if ($scheduleUrl !== '' || $registrationUrl !== ''): ?>
                <p class="venue-address-line" style="margin-top:0.75rem;">
                    <?php if ($scheduleUrl !== ''): ?>
                        <a href="<?= h($scheduleUrl) ?>" target="_blank" rel="noopener noreferrer"><?= h($lang === 'en' ? 'Schedule' : 'Órarend') ?></a>
                    <?php endif; ?>
                    <?php if ($scheduleUrl !== '' && $registrationUrl !== ''): ?>
                        <span aria-hidden="true"> · </span>
                    <?php endif; ?>
                    <?php if ($registrationUrl !== ''): ?>
                        <a href="<?= h($registrationUrl) ?>" target="_blank" rel="noopener noreferrer"><?= h($lang === 'en' ? 'Registration' : 'Jelentkezés') ?></a>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
    </header>

    <div class="event-public__content">
        <?php if ($safeBody !== ''): ?>
            <section class="event-public__section" aria-label="<?= h($lang === 'en' ? 'About' : 'Bemutatkozás') ?>">
                <h2 class="djs-public__section-title"><?= h($lang === 'en' ? 'About' : 'Bemutatkozás') ?></h2>
                <div class="event-body"><?= $safeBody ?></div>
            </section>
        <?php endif; ?>

        <?php if ($trialInfo !== '' || $pricingInfo !== '' || $languages !== ''): ?>
            <section class="event-public__section" aria-label="<?= h($lang === 'en' ? 'Info' : 'Infó') ?>">
                <h2 class="djs-public__section-title"><?= h($lang === 'en' ? 'Practical info' : 'Gyakorlati infó') ?></h2>
                <?php if ($languages !== ''): ?>
                    <p><strong><?= h($lang === 'en' ? 'Languages' : 'Nyelvek') ?>:</strong> <?= h($languages) ?></p>
                <?php endif; ?>
                <?php if ($trialInfo !== ''): ?>
                    <p><strong><?= h($lang === 'en' ? 'Trial class' : 'Próbaóra') ?>:</strong> <?= nl2br(h($trialInfo)) ?></p>
                <?php endif; ?>
                <?php if ($pricingInfo !== ''): ?>
                    <p><strong><?= h($lang === 'en' ? 'Pricing' : 'Árazás') ?>:</strong> <?= nl2br(h($pricingInfo)) ?></p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($venues !== []): ?>
            <section class="event-public__section" aria-label="<?= h($lang === 'en' ? 'Venues' : 'Helyszínek') ?>">
                <h2 class="djs-public__section-title"><?= h($lang === 'en' ? 'Venues & classes' : 'Helyszínek és órák') ?></h2>
                <?php foreach ($venues as $v): ?>
                    <?php
                    $vName = trim((string) ($v['venue_name'] ?? ''));
                    $vCity = trim((string) ($v['venue_city'] ?? ''));
                    $vAddr = trim((string) ($v['venue_address'] ?? ''));
                    $vMaps = trim((string) ($v['venue_google_maps_url'] ?? ''));
                    $vNotes = trim((string) ($v['notes'] ?? ''));
                    $vSlug = trim((string) ($v['venue_slug'] ?? ''));
                    $offerings = is_array($v['offerings'] ?? null) ? $v['offerings'] : [];
                    ?>
                    <div class="dance-school-public__venue" style="margin-bottom:1.25rem;">
                        <h3 style="margin:0 0 0.35rem;font-size:1.1rem;">
                            <?php if ($vSlug !== ''): ?>
                                <a href="<?= h(events_helyszin_megjelenit_url($vSlug)) ?>"><?= h($vName) ?></a>
                            <?php else: ?>
                                <?= h($vName) ?>
                            <?php endif; ?>
                        </h3>
                        <?php
                        $meta = array_filter([$vCity, $vAddr]);
                        if ($meta !== [] || $vMaps !== ''):
                            ?>
                            <p class="help" style="margin:0 0 0.5rem;">
                                <?= h(implode(' · ', $meta)) ?>
                                <?php if ($vMaps !== ''): ?>
                                    <?php if ($meta !== []): ?> · <?php endif; ?>
                                    <a href="<?= h($vMaps) ?>" target="_blank" rel="noopener noreferrer">Google Maps</a>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($vNotes !== ''): ?>
                            <p class="help"><?= nl2br(h($vNotes)) ?></p>
                        <?php endif; ?>
                        <?php if ($offerings !== []): ?>
                            <ul style="margin:0.35rem 0 0;padding-left:1.2rem;">
                                <?php foreach ($offerings as $off): ?>
                                    <?php
                                    $label = trim((string) ($off['style_label'] ?? ''));
                                    if ($label === '') {
                                        $label = trim((string) ($off['style_name'] ?? ''));
                                    }
                                    if ($label === '') {
                                        $label = '—';
                                    }
                                    $bits = [$label];
                                    $age = (string) ($off['age_group'] ?? '');
                                    $level = (string) ($off['level'] ?? '');
                                    $ctype = (string) ($off['class_type'] ?? '');
                                    if ($age !== '' && isset($ageLabels[$age])) {
                                        $bits[] = $ageLabels[$age];
                                    }
                                    if ($level !== '' && isset($levelLabels[$level])) {
                                        $bits[] = $levelLabels[$level];
                                    }
                                    if ($ctype !== '' && isset($classTypeLabels[$ctype])) {
                                        $bits[] = $classTypeLabels[$ctype];
                                    }
                                    $note = trim((string) ($off['schedule_note'] ?? ''));
                                    ?>
                                    <li>
                                        <?= h(implode(' · ', $bits)) ?>
                                        <?php if ($note !== ''): ?>
                                            <span class="help"> — <?= h($note) ?></span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if ($teachers !== []): ?>
            <section class="event-public__section" aria-label="<?= h($lang === 'en' ? 'Teachers' : 'Tánctanárok') ?>">
                <h2 class="djs-public__section-title"><?= h($lang === 'en' ? 'Teachers' : 'Tánctanárok') ?></h2>
                <ul style="margin:0;padding-left:1.2rem;">
                    <?php foreach ($teachers as $tch): ?>
                        <?php
                        $tName = trim((string) ($tch['teacher_name'] ?? ''));
                        $tRole = (string) ($tch['role_type'] ?? 'teacher');
                        $tRoleLabel = $roleLabels[$tRole] ?? $tRole;
                        $tNote = trim((string) ($tch['role_note'] ?? ''));
                        $tId = (int) ($tch['tag_id'] ?? 0);
                        ?>
                        <li>
                            <?php if ($isAdmin && $tId > 0): ?>
                                <a href="<?= h(events_url('tanar_szerkeszt.php?id=') . $tId) ?>"><?= h($tName) ?></a>
                            <?php else: ?>
                                <?= h($tName) ?>
                            <?php endif; ?>
                            <span class="help"> (<?= h($tRoleLabel) ?>)</span>
                            <?php if ($tNote !== ''): ?>
                                <span class="help"> — <?= h($tNote) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if ($eventsUpcoming !== []): ?>
            <section class="event-public__section" aria-label="<?= h($lang === 'en' ? 'Upcoming' : 'Közelgő') ?>">
                <h2 class="djs-public__section-title"><?= h($lang === 'en' ? 'Upcoming workshops' : 'Közelgő workshopok') ?></h2>
                <ul style="margin:0;padding-left:1.2rem;">
                    <?php foreach ($eventsUpcoming as $ev): ?>
                        <?php
                        $evTitle = trim((string) ($ev['title'] ?? ''));
                        $evType = (string) ($ev['event_type'] ?? 'workshop');
                        $evTypeLabel = $eventTypeLabels[$evType] ?? $evType;
                        $evStarts = (string) ($ev['starts_at'] ?? '');
                        $evVenue = trim((string) ($ev['venue_name'] ?? ''));
                        $evReg = trim((string) ($ev['registration_url'] ?? ''));
                        $startsFmt = $evStarts !== '' ? date('Y.m.d. H:i', strtotime($evStarts) ?: time()) : '';
                        ?>
                        <li>
                            <strong><?= h($evTitle) ?></strong>
                            <span class="help"> — <?= h($evTypeLabel) ?><?php if ($startsFmt !== ''): ?>, <?= h($startsFmt) ?><?php endif; ?><?php if ($evVenue !== ''): ?>, <?= h($evVenue) ?><?php endif; ?></span>
                            <?php if ($evReg !== ''): ?>
                                · <a href="<?= h($evReg) ?>" target="_blank" rel="noopener noreferrer"><?= h($lang === 'en' ? 'Register' : 'Jelentkezés') ?></a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
    </div>

    <?php require __DIR__ . '/partials/public_shell_footer.php'; ?>
</article>
</div>
</body>
</html>
