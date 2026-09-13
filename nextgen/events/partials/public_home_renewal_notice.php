<?php
declare(strict_types=1);

/**
 * Megújult naptár – kompakt felhívás a logó és a nyelvváltó között.
 *
 * @var array<string, string> $S
 * @var array{visible: bool, text: string, aria: string, url: string, style: string, version_id?: int, lang?: string}|null $renewalNotice
 */
$renewalNotice = $renewalNotice ?? null;
if (!is_array($renewalNotice) || empty($renewalNotice['visible'])) {
    return;
}
$noticeText = (string) ($renewalNotice['text'] ?? '');
$noticeAria = (string) ($renewalNotice['aria'] ?? $noticeText);
$noticeUrl = (string) ($renewalNotice['url'] ?? '');
$noticeStyle = (string) ($renewalNotice['style'] ?? '');
$noticeVersionId = (int) ($renewalNotice['version_id'] ?? 0);
$noticeLang = ((string) ($renewalNotice['lang'] ?? 'hu')) === 'en' ? 'en' : 'hu';
$trackClicks = $noticeUrl !== ''
    && $noticeVersionId > 0
    && function_exists('events_public_visitor_metrics_allowed')
    && events_public_visitor_metrics_allowed();
?>
<div class="home-public__renewal-notice">
    <?php if ($noticeUrl !== ''): ?>
        <a
            class="home-public__renewal-notice-link"
            href="<?= h($noticeUrl) ?>"
            aria-label="<?= h($noticeAria) ?>"
            <?php if ($noticeStyle !== ''): ?>style="<?= h($noticeStyle) ?>"<?php endif; ?>
            <?php if ($trackClicks): ?>
                data-notice-track="1"
                data-notice-version="<?= $noticeVersionId ?>"
                data-notice-lang="<?= h($noticeLang) ?>"
            <?php endif; ?>
        ><?= h($noticeText) ?></a>
    <?php else: ?>
        <span
            class="home-public__renewal-notice-link"
            <?php if ($noticeStyle !== ''): ?>style="<?= h($noticeStyle) ?>"<?php endif; ?>
        ><?= h($noticeText) ?></span>
    <?php endif; ?>
</div>
<?php if ($trackClicks): ?>
<script>
(function () {
    var trackUrl = <?= json_encode(events_url('ajax_notice_click.php'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var link = document.querySelector('a.home-public__renewal-notice-link[data-notice-track="1"]');
    if (!trackUrl || !link) return;

    function trackNoticeClick() {
        var versionId = link.getAttribute('data-notice-version') || '';
        var lang = link.getAttribute('data-notice-lang') || 'hu';
        if (!versionId) return;
        var body = new FormData();
        body.append('version_id', String(versionId));
        body.append('lang', lang);
        if (navigator.sendBeacon) {
            navigator.sendBeacon(trackUrl, body);
            return;
        }
        fetch(trackUrl, { method: 'POST', body: body, keepalive: true }).catch(function () {});
    }

    link.addEventListener('click', trackNoticeClick);
})();
</script>
<?php endif; ?>
