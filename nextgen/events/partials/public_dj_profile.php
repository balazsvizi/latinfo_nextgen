<?php
declare(strict_types=1);

/**
 * Nyilvános DJ profil blokk (fotó, bio, elérhetőségek).
 *
 * @var array<string, string> $G
 * @var array{
 *   description?: string,
 *   photo_url?: string,
 *   website_url?: string,
 *   facebook_url?: string,
 *   instagram_url?: string,
 *   soundcloud_url?: string,
 *   email?: string,
 *   phone?: string
 * } $djProfile
 * @var string $djDisplayName
 */

$djProfile = is_array($djProfile ?? null) ? $djProfile : [];
$djDisplayName = (string) ($djDisplayName ?? '');
$photoUrl = trim((string) ($djProfile['photo_url'] ?? ''));
$photoAbs = $photoUrl !== '' ? events_absolute_url($photoUrl) : '';
$bioHtml = events_sanitize_html_fragment((string) ($djProfile['description'] ?? ''));
$website = trim((string) ($djProfile['website_url'] ?? ''));
$facebook = trim((string) ($djProfile['facebook_url'] ?? ''));
$instagram = trim((string) ($djProfile['instagram_url'] ?? ''));
$soundcloud = trim((string) ($djProfile['soundcloud_url'] ?? ''));
$email = trim((string) ($djProfile['email'] ?? ''));
$phone = trim((string) ($djProfile['phone'] ?? ''));

$hasLinks = $website !== '' || $facebook !== '' || $instagram !== '' || $soundcloud !== '' || $email !== '' || $phone !== '';
$hasBody = $bioHtml !== '';
$hasPhoto = $photoAbs !== '';

if (!$hasPhoto && !$hasBody && !$hasLinks) {
    return;
}
?>
<section class="dj-public__profile" aria-label="<?= h((string) ($G['dj_profile_aria'] ?? 'DJ profil')) ?>">
    <div class="dj-public__profile-layout<?= $hasPhoto ? '' : ' dj-public__profile-layout--no-photo' ?>">
        <?php if ($hasPhoto): ?>
            <div class="dj-public__photo">
                <img
                    class="dj-public__photo-img"
                    src="<?= h($photoAbs) ?>"
                    alt="<?= h($djDisplayName !== '' ? $djDisplayName : ((string) ($G['dj_photo_alt'] ?? 'DJ'))) ?>"
                    loading="lazy"
                    decoding="async"
                >
            </div>
        <?php endif; ?>
        <div class="dj-public__profile-main">
            <?php if ($hasLinks): ?>
                <div class="dj-public__contacts" role="group" aria-label="<?= h((string) ($G['dj_contacts_aria'] ?? 'Elérhetőségek')) ?>">
                    <?php if ($website !== ''): ?>
                        <a class="dj-public__contact" href="<?= h($website) ?>" target="_blank" rel="noopener noreferrer">
                            <span class="dj-public__contact-icon" aria-hidden="true">🌐</span>
                            <span><?= h((string) ($G['dj_link_website'] ?? 'Weboldal')) ?></span>
                        </a>
                    <?php endif; ?>
                    <?php if ($facebook !== ''): ?>
                        <a class="dj-public__contact" href="<?= h($facebook) ?>" target="_blank" rel="noopener noreferrer">
                            <span class="dj-public__contact-icon" aria-hidden="true">📘</span>
                            <span><?= h((string) ($G['dj_link_facebook'] ?? 'Facebook')) ?></span>
                        </a>
                    <?php endif; ?>
                    <?php if ($instagram !== ''): ?>
                        <a class="dj-public__contact" href="<?= h($instagram) ?>" target="_blank" rel="noopener noreferrer">
                            <span class="dj-public__contact-icon" aria-hidden="true">📷</span>
                            <span><?= h((string) ($G['dj_link_instagram'] ?? 'Instagram')) ?></span>
                        </a>
                    <?php endif; ?>
                    <?php if ($soundcloud !== ''): ?>
                        <a class="dj-public__contact" href="<?= h($soundcloud) ?>" target="_blank" rel="noopener noreferrer">
                            <span class="dj-public__contact-icon" aria-hidden="true">🎵</span>
                            <span><?= h((string) ($G['dj_link_soundcloud'] ?? 'SoundCloud')) ?></span>
                        </a>
                    <?php endif; ?>
                    <?php if ($email !== ''): ?>
                        <a class="dj-public__contact" href="mailto:<?= h($email) ?>">
                            <span class="dj-public__contact-icon" aria-hidden="true">✉️</span>
                            <span><?= h($email) ?></span>
                        </a>
                    <?php endif; ?>
                    <?php if ($phone !== ''): ?>
                        <a class="dj-public__contact" href="tel:<?= h(preg_replace('/[^\d+]/', '', $phone) ?? $phone) ?>">
                            <span class="dj-public__contact-icon" aria-hidden="true">📞</span>
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
    </div>
</section>
