<?php
declare(strict_types=1);

/**
 * Nyilvános DJ profil blokk (kontaktok + bio).
 * A fotó a hero identity blokkban jelenik meg (tag.php).
 *
 * @var array<string, string> $G
 * @var array<string, string> $djProfile
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
$mixcloud = trim((string) ($djProfile['mixcloud_url'] ?? ''));
$email = trim((string) ($djProfile['email'] ?? ''));
$phone = trim((string) ($djProfile['phone'] ?? ''));
$hasLinks = $website !== '' || $facebook !== '' || $instagram !== '' || $soundcloud !== '' || $youtube !== '' || $mixcloud !== '' || $email !== '' || $phone !== '';
$hasBody = $bioHtml !== '';

if (!$hasBody && !$hasLinks) {
    return;
}
?>
<section class="dj-public__profile" aria-label="<?= h((string) ($G['dj_profile_aria'] ?? 'DJ profil')) ?>">
    <div class="dj-public__profile-panel">
        <?php if ($hasLinks): ?>
            <div class="dj-public__contacts-block">
                <h2 class="dj-public__contacts-title"><?= h((string) ($G['dj_contacts_aria'] ?? 'Elérhetőségek')) ?></h2>
                <?php require __DIR__ . '/public_dj_contacts.php'; ?>
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
