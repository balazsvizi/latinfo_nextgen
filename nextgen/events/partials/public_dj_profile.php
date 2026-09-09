<?php
declare(strict_types=1);

/**
 * Nyilvános DJ profil blokk (bio, elérhetőségek).
 * A fotó a hero identity blokkban jelenik meg (tag.php).
 *
 * @var array<string, string> $G
 * @var array{
 *   description?: string,
 *   photo_url?: string,
 *   website_url?: string,
 *   facebook_url?: string,
 *   instagram_url?: string,
 *   soundcloud_url?: string,
 *   youtube_url?: string,
 *   email?: string,
 *   phone?: string
 * } $djProfile
 * @var string $djDisplayName
 */

$djProfile = is_array($djProfile ?? null) ? $djProfile : [];
$djDisplayName = (string) ($djDisplayName ?? '');
$bioHtml = events_sanitize_html_fragment((string) ($djProfile['description'] ?? ''));
$website = trim((string) ($djProfile['website_url'] ?? ''));
$facebook = trim((string) ($djProfile['facebook_url'] ?? ''));
$instagram = trim((string) ($djProfile['instagram_url'] ?? ''));
$soundcloud = trim((string) ($djProfile['soundcloud_url'] ?? ''));
$youtube = trim((string) ($djProfile['youtube_url'] ?? ''));
$email = trim((string) ($djProfile['email'] ?? ''));
$phone = trim((string) ($djProfile['phone'] ?? ''));

$hasLinks = $website !== '' || $facebook !== '' || $instagram !== '' || $soundcloud !== '' || $youtube !== '' || $email !== '' || $phone !== '';
$hasBody = $bioHtml !== '';

if (!$hasBody && !$hasLinks) {
    return;
}

$svgAttrs = 'class="dj-public__contact-svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
?>
<section class="dj-public__profile" aria-label="<?= h((string) ($G['dj_profile_aria'] ?? 'DJ profil')) ?>">
    <div class="dj-public__profile-panel">
        <?php if ($hasLinks): ?>
            <div class="dj-public__contacts" role="group" aria-label="<?= h((string) ($G['dj_contacts_aria'] ?? 'Elérhetőségek')) ?>">
                <?php if ($website !== ''): ?>
                    <a class="dj-public__contact" href="<?= h($website) ?>" target="_blank" rel="noopener noreferrer">
                        <svg <?= $svgAttrs ?>><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>
                        <span><?= h((string) ($G['dj_link_website'] ?? 'Weboldal')) ?></span>
                    </a>
                <?php endif; ?>
                <?php if ($facebook !== ''): ?>
                    <a class="dj-public__contact" href="<?= h($facebook) ?>" target="_blank" rel="noopener noreferrer">
                        <svg <?= $svgAttrs ?>><path d="M14 8h2V5h-2a4 4 0 0 0-4 4v2H8v3h2v7h3v-7h2.2L16 11h-3V9a1 1 0 0 1 1-1z"/></svg>
                        <span><?= h((string) ($G['dj_link_facebook'] ?? 'Facebook')) ?></span>
                    </a>
                <?php endif; ?>
                <?php if ($instagram !== ''): ?>
                    <a class="dj-public__contact" href="<?= h($instagram) ?>" target="_blank" rel="noopener noreferrer">
                        <svg <?= $svgAttrs ?>><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
                        <span><?= h((string) ($G['dj_link_instagram'] ?? 'Instagram')) ?></span>
                    </a>
                <?php endif; ?>
                <?php if ($youtube !== ''): ?>
                    <a class="dj-public__contact" href="<?= h($youtube) ?>" target="_blank" rel="noopener noreferrer">
                        <svg <?= $svgAttrs ?>><rect x="2" y="5" width="20" height="14" rx="3"/><path d="M10 9.5v5l5-2.5-5-2.5z" fill="currentColor" stroke="none"/></svg>
                        <span><?= h((string) ($G['dj_link_youtube'] ?? 'YouTube')) ?></span>
                    </a>
                <?php endif; ?>
                <?php if ($soundcloud !== ''): ?>
                    <a class="dj-public__contact" href="<?= h($soundcloud) ?>" target="_blank" rel="noopener noreferrer">
                        <svg <?= $svgAttrs ?>><path d="M4 14v3M7 11v6M10 9v8M13 7v10M16 10v7M19 12v5"/></svg>
                        <span><?= h((string) ($G['dj_link_soundcloud'] ?? 'SoundCloud')) ?></span>
                    </a>
                <?php endif; ?>
                <?php if ($email !== ''): ?>
                    <a class="dj-public__contact" href="mailto:<?= h($email) ?>">
                        <svg <?= $svgAttrs ?>><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 7 9-7"/></svg>
                        <span><?= h($email) ?></span>
                    </a>
                <?php endif; ?>
                <?php if ($phone !== ''): ?>
                    <a class="dj-public__contact" href="tel:<?= h(preg_replace('/[^\d+]/', '', $phone) ?? $phone) ?>">
                        <svg <?= $svgAttrs ?>><path d="M6.5 3.5h3l1.5 4-2 1.5a12 12 0 0 0 5 5l1.5-2 4 1.5v3A2 2 0 0 1 17.5 18 13.5 13.5 0 0 1 4 4.5a2 2 0 0 1 2.5-1z"/></svg>
                        <span><?= h($phone) ?></span>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($hasBody): ?>
            <div class="dj-public__bio event-rich-text">
                <h2 class="dj-public__bio-title"><?= h((string) ($G['dj_bio_heading'] ?? 'Bemutatkozás')) ?></h2>
                <?= $bioHtml ?>
            </div>
        <?php endif; ?>
    </div>
</section>
