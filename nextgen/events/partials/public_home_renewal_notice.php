<?php
declare(strict_types=1);

/**
 * Megújult naptár – kompakt felhívás a logó és a nyelvváltó között.
 *
 * @var array<string, string> $S
 */
$lanuevaUrl = site_url('lanueva/');
?>
<div class="home-public__renewal-notice" role="note" aria-label="<?= h((string) ($S['renewal_notice_aria'] ?? '')) ?>">
    <p class="home-public__renewal-notice-text">
        <span class="home-public__renewal-notice-prefix"><?= h((string) ($S['renewal_notice_prefix'] ?? '')) ?></span>
        <a class="home-public__renewal-notice-link" href="<?= h($lanuevaUrl) ?>"><?= h((string) ($S['renewal_notice_link'] ?? '')) ?></a>
    </p>
</div>
