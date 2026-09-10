<?php
declare(strict_types=1);

/**
 * Nyilvános DJ profil blokk (bio).
 * A kontaktok a hero identity alatt jelennek meg (public_dj_contacts.php).
 * A fotó a hero identity blokkban jelenik meg (tag.php).
 *
 * @var array<string, string> $G
 * @var array<string, string> $djProfile
 * @var string $djDisplayName
 */

$djProfile = is_array($djProfile ?? null) ? $djProfile : [];
$djDisplayName = (string) ($djDisplayName ?? '');
$bioHtml = events_sanitize_html_fragment((string) ($djProfile['description'] ?? ''));

if ($bioHtml === '') {
    return;
}
?>
<section class="dj-public__profile" aria-label="<?= h((string) ($G['dj_profile_aria'] ?? 'DJ profil')) ?>">
    <div class="dj-public__profile-panel">
        <div class="dj-public__bio event-rich-text">
            <h2 class="dj-public__bio-title"><?= h((string) ($G['dj_bio_heading'] ?? 'Bemutatkozás')) ?></h2>
            <?= $bioHtml ?>
        </div>
    </div>
</section>
