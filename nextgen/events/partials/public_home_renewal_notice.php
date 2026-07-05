<?php
declare(strict_types=1);

/**
 * Megújult naptár – kompakt felhívás a logó és a nyelvváltó között.
 *
 * @var array<string, string> $S
 */
$lanuevaUrl = site_url('lanueva/');
?>
<div class="home-public__renewal-notice">
    <a
        class="home-public__renewal-notice-link"
        href="<?= h($lanuevaUrl) ?>"
        aria-label="<?= h((string) ($S['renewal_notice_aria'] ?? '')) ?>"
    ><?= h((string) ($S['renewal_notice_text'] ?? '')) ?></a>
</div>
