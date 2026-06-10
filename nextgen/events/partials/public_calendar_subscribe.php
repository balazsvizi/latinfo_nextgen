<?php
declare(strict_types=1);
/** @var array<string, string> $D */
/** @var array<string, string|int> $icalFeedParams */
/** @var string $lang */

require_once dirname(__DIR__) . '/lib/ical_export.php';

$calendarName = (string) ($D['page_title'] ?? 'Events');
$links = events_ical_subscribe_links($icalFeedParams, $calendarName, $lang);
$menuId = 'events-cal-subscribe-menu';
?>
<div class="events-cal-subscribe">
    <details class="events-cal-subscribe__details">
        <summary class="events-cal-subscribe__toggle">
            <span><?= h((string) ($D['cal_subscribe_toggle'] ?? 'Feliratkozás a naptárra')) ?></span>
            <span class="events-cal-subscribe__chevron" aria-hidden="true">▾</span>
        </summary>
        <ul class="events-cal-subscribe__menu" id="<?= h($menuId) ?>" role="list">
            <li>
                <a href="<?= h($links['google']) ?>" target="_blank" rel="noopener noreferrer"><?= h((string) ($D['cal_subscribe_google'] ?? 'Google Naptár')) ?></a>
            </li>
            <li>
                <a href="<?= h($links['ical']) ?>"><?= h((string) ($D['cal_subscribe_ical'] ?? 'iCalendar')) ?></a>
            </li>
            <li>
                <a href="<?= h($links['outlook365']) ?>" target="_blank" rel="noopener noreferrer"><?= h((string) ($D['cal_subscribe_outlook365'] ?? 'Outlook 365')) ?></a>
            </li>
            <li>
                <a href="<?= h($links['outlook_live']) ?>" target="_blank" rel="noopener noreferrer"><?= h((string) ($D['cal_subscribe_outlook_live'] ?? 'Outlook Live')) ?></a>
            </li>
            <li>
                <a href="<?= h($links['download']) ?>"><?= h((string) ($D['cal_subscribe_export'] ?? '.ics fájl export')) ?></a>
            </li>
            <li>
                <a href="<?= h($links['download_outlook']) ?>"><?= h((string) ($D['cal_subscribe_export_outlook'] ?? 'Outlook .ics export')) ?></a>
            </li>
        </ul>
    </details>
</div>
