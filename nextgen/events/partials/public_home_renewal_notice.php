<?php
declare(strict_types=1);

/**
 * Megújult naptár – visszajelzés felhívás a főoldal fejrészében.
 *
 * @var array<string, string> $D
 */
$lanuevaUrl = site_url('lanueva/');
?>
<div class="home-public__renewal-notice" role="note" aria-label="<?= h((string) ($D['renewal_notice_aria'] ?? '')) ?>">
    <h1 class="home-public__renewal-notice-title">
        <?= h((string) ($D['renewal_notice_prefix'] ?? '')) ?>
        <a class="home-public__renewal-notice-link" href="<?= h($lanuevaUrl) ?>"><?= h((string) ($D['renewal_notice_link'] ?? '')) ?></a>
    </h1>
</div>
