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
$emailIsPrivate = events_tag_profile_flag_is_on($tagProfile['email_is_private'] ?? '0');
$phoneIsPrivate = events_tag_profile_flag_is_on($tagProfile['phone_is_private'] ?? '0');

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
            <label for="tag_mixcloud_url_<?= h($fid) ?>">Mixcloud</label>
            <input type="url" id="tag_mixcloud_url_<?= h($fid) ?>" name="tag_mixcloud_url" maxlength="2000" value="<?= h((string) ($tagProfile['mixcloud_url'] ?? '')) ?>" placeholder="https://www.mixcloud.com/…">
        </div>
        <div class="form-group">
            <label for="tag_youtube_url_<?= h($fid) ?>">YouTube</label>
            <input type="url" id="tag_youtube_url_<?= h($fid) ?>" name="tag_youtube_url" maxlength="2000" value="<?= h((string) ($tagProfile['youtube_url'] ?? '')) ?>" placeholder="https://youtube.com/…">
        </div>
        <div class="form-group events-tag-dj-privacy<?= $emailIsPrivate ? ' is-private' : '' ?>" data-dj-privacy-field>
            <div class="events-tag-dj-profile__field-head">
                <label for="tag_email_<?= h($fid) ?>">E-mail</label>
                <label class="events-tag-dj-privacy__chip" for="tag_email_is_private_<?= h($fid) ?>">
                    <input
                        type="checkbox"
                        name="tag_email_is_private"
                        value="1"
                        id="tag_email_is_private_<?= h($fid) ?>"
                        class="events-tag-dj-privacy__input"
                        data-dj-privacy-toggle
                        <?= $emailIsPrivate ? 'checked' : '' ?>
                    >
                    <span class="events-tag-dj-privacy__chip-label">Privát</span>
                </label>
            </div>
            <input type="email" id="tag_email_<?= h($fid) ?>" name="tag_email" maxlength="255" value="<?= h((string) ($tagProfile['email'] ?? '')) ?>" placeholder="dj@example.com">
            <p class="help events-tag-dj-privacy__help-public">Publikus: megjelenik a nyilvános oldalon.</p>
            <p class="help events-tag-dj-privacy__help-private">Privát: csak adminnak látszik.</p>
        </div>
        <div class="form-group events-tag-dj-privacy<?= $phoneIsPrivate ? ' is-private' : '' ?>" data-dj-privacy-field>
            <div class="events-tag-dj-profile__field-head">
                <label for="tag_phone_<?= h($fid) ?>">Telefon</label>
                <label class="events-tag-dj-privacy__chip" for="tag_phone_is_private_<?= h($fid) ?>">
                    <input
                        type="checkbox"
                        name="tag_phone_is_private"
                        value="1"
                        id="tag_phone_is_private_<?= h($fid) ?>"
                        class="events-tag-dj-privacy__input"
                        data-dj-privacy-toggle
                        <?= $phoneIsPrivate ? 'checked' : '' ?>
                    >
                    <span class="events-tag-dj-privacy__chip-label">Privát</span>
                </label>
            </div>
            <input type="tel" id="tag_phone_<?= h($fid) ?>" name="tag_phone" maxlength="64" value="<?= h((string) ($tagProfile['phone'] ?? '')) ?>" placeholder="+36 …">
            <p class="help events-tag-dj-privacy__help-public">Publikus: megjelenik a nyilvános oldalon.</p>
            <p class="help events-tag-dj-privacy__help-private">Privát: csak adminnak látszik.</p>
        </div>
    </div>
    <div class="form-group">
        <label for="tag_admin_notes_<?= h($fid) ?>">Megjegyzés</label>
        <textarea
            id="tag_admin_notes_<?= h($fid) ?>"
            name="tag_admin_notes"
            rows="4"
            maxlength="10000"
            placeholder="Belső megjegyzés (csak adminnak)…"
        ><?= h((string) ($tagProfile['admin_notes'] ?? '')) ?></textarea>
        <p class="help">Csak az adminisztrációnak látszik – nem jelenik meg a nyilvános oldalon.</p>
    </div>
</div>
<script>
(function () {
    document.querySelectorAll('[data-dj-privacy-field]').forEach(function (field) {
        var toggle = field.querySelector('[data-dj-privacy-toggle]');
        if (!toggle) return;
        function sync() {
            field.classList.toggle('is-private', !!toggle.checked);
        }
        toggle.addEventListener('change', sync);
        sync();
    });
})();
</script>
