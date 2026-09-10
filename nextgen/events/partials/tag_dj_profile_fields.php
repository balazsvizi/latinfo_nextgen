<?php
declare(strict_types=1);

/**
 * DJ profil szöveges / link mezők (fotó külön partial).
 *
 * @var int $tagFormId
 * @var array<string, string> $tagProfile
 * @var bool $tagProfileStandalone  ha true: nincs rejtő wrapper (önálló DJ szerkesztő)
 * @var bool $tagProfileVisible
 */

$tagFormId = (int) ($tagFormId ?? 0);
$tagProfile = is_array($tagProfile ?? null) ? $tagProfile : events_tag_profile_empty();
$tagProfileStandalone = (bool) ($tagProfileStandalone ?? false);
$tagProfileVisible = (bool) ($tagProfileVisible ?? $tagProfileStandalone);
$fid = $tagFormId > 0 ? (string) $tagFormId : 'new';

if (!$tagProfileStandalone):
?>
<div
    class="events-tag-dj-profile"
    data-tag-dj-profile
    <?= $tagProfileVisible ? '' : 'hidden' ?>
>
    <h4 class="events-tag-dj-profile__title">DJ profil</h4>
    <p class="help">A részletes adatlap a <a href="<?= h(events_url('djs_admin.php')) ?>">DJ-k</a> menüpontban szerkeszthető.</p>
</div>
<?php
    return;
endif;
?>
<div class="events-tag-dj-profile events-tag-dj-profile--standalone">
    <div class="form-group">
        <label for="tag_description_<?= h($fid) ?>">Bio / leírás</label>
        <textarea
            id="tag_description_<?= h($fid) ?>"
            name="tag_description"
            rows="6"
            placeholder="Rövid bemutatkozás…"
        ><?= h((string) ($tagProfile['description'] ?? '')) ?></textarea>
        <p class="help">Egyszerű HTML megengedett (bekezdések, linkek, listák).</p>
    </div>
    <div class="events-tag-dj-profile__grid">
        <div class="form-group">
            <label for="tag_website_url_<?= h($fid) ?>">Weboldal</label>
            <input type="url" id="tag_website_url_<?= h($fid) ?>" name="tag_website_url" maxlength="2000" value="<?= h((string) ($tagProfile['website_url'] ?? '')) ?>" placeholder="https://…">
        </div>
        <div class="form-group">
            <label for="tag_facebook_url_<?= h($fid) ?>">Facebook</label>
            <input type="url" id="tag_facebook_url_<?= h($fid) ?>" name="tag_facebook_url" maxlength="2000" value="<?= h((string) ($tagProfile['facebook_url'] ?? '')) ?>" placeholder="https://facebook.com/…">
        </div>
        <div class="form-group">
            <label for="tag_instagram_url_<?= h($fid) ?>">Instagram</label>
            <input type="url" id="tag_instagram_url_<?= h($fid) ?>" name="tag_instagram_url" maxlength="2000" value="<?= h((string) ($tagProfile['instagram_url'] ?? '')) ?>" placeholder="https://instagram.com/…">
        </div>
        <div class="form-group">
            <label for="tag_soundcloud_url_<?= h($fid) ?>">SoundCloud</label>
            <input type="url" id="tag_soundcloud_url_<?= h($fid) ?>" name="tag_soundcloud_url" maxlength="2000" value="<?= h((string) ($tagProfile['soundcloud_url'] ?? '')) ?>" placeholder="https://soundcloud.com/…">
        </div>
        <div class="form-group">
            <label for="tag_youtube_url_<?= h($fid) ?>">YouTube</label>
            <input type="url" id="tag_youtube_url_<?= h($fid) ?>" name="tag_youtube_url" maxlength="2000" value="<?= h((string) ($tagProfile['youtube_url'] ?? '')) ?>" placeholder="https://youtube.com/…">
        </div>
        <div class="form-group">
            <label for="tag_email_<?= h($fid) ?>">Publikus e-mail</label>
            <input
                type="email"
                id="tag_email_<?= h($fid) ?>"
                name="tag_email"
                maxlength="255"
                value="<?= h((string) ($tagProfile['email'] ?? '')) ?>"
                placeholder="dj@example.com"
                data-dj-public-email
            >
            <p class="help">Ez jelenik meg a nyilvános DJ oldalon.</p>
        </div>
        <div class="form-group">
            <label for="tag_contact_email_<?= h($fid) ?>">Kapcsolati e-mail</label>
            <div class="events-tag-dj-profile__email-row">
                <input
                    type="email"
                    id="tag_contact_email_<?= h($fid) ?>"
                    name="tag_contact_email"
                    maxlength="255"
                    value="<?= h((string) ($tagProfile['contact_email'] ?? '')) ?>"
                    placeholder="kapcsolat@example.com"
                    data-dj-contact-email
                >
                <button
                    type="button"
                    class="btn btn-secondary"
                    data-dj-copy-public-email
                    title="Másolás a publikus e-mailből"
                >Másolás a publikusból</button>
            </div>
            <p class="help">Csak adminisztrációnak – nem jelenik meg publikuson.</p>
        </div>
        <div class="form-group">
            <label for="tag_phone_<?= h($fid) ?>">Telefon</label>
            <input type="tel" id="tag_phone_<?= h($fid) ?>" name="tag_phone" maxlength="64" value="<?= h((string) ($tagProfile['phone'] ?? '')) ?>" placeholder="+36 …">
        </div>
    </div>
</div>
<script>
(function () {
    document.querySelectorAll('[data-dj-copy-public-email]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var root = btn.closest('.events-tag-dj-profile') || document;
            var publicInput = root.querySelector('[data-dj-public-email]');
            var contactInput = root.querySelector('[data-dj-contact-email]');
            if (!publicInput || !contactInput) {
                return;
            }
            var value = (publicInput.value || '').trim();
            if (value === '') {
                publicInput.focus();
                return;
            }
            contactInput.value = value;
            contactInput.focus();
            contactInput.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });
})();
</script>
