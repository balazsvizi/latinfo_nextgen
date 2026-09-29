<?php
declare(strict_types=1);

/**
 * Egyszeri betöltés: auth + esemény-választó dialógusok (szívecskék).
 *
 * @var string $favoriteDialogLang
 */

$favoriteDialogLang = ($favoriteDialogLang ?? 'hu') === 'en' ? 'en' : 'hu';
$isEn = $favoriteDialogLang === 'en';
$favoriteReturnPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
$queryString = (string) ($_SERVER['QUERY_STRING'] ?? '');
if ($favoriteReturnPath !== '' && $queryString !== '') {
    $favoriteReturnPath .= '?' . $queryString;
}
$favoriteLoginUrl = user_url('login.php');
if ($favoriteReturnPath !== '' && ($favoriteReturnPath[0] ?? '') === '/') {
    $favoriteLoginUrl .= '?return=' . rawurlencode($favoriteReturnPath);
}
$favoriteSignupUrl = user_url('signup.php');
if ($favoriteReturnPath !== '' && ($favoriteReturnPath[0] ?? '') === '/') {
    $favoriteSignupUrl .= '?return=' . rawurlencode($favoriteReturnPath);
}
$closeLabel = $isEn ? 'Close' : 'Bezárás';
?>
<dialog class="public-favorite-dialog public-favorite-dialog--auth" id="public-favorite-auth-dialog" data-public-favorite-auth-dialog aria-labelledby="public-favorite-auth-title">
    <form method="dialog" class="public-favorite-dialog__panel">
        <button type="submit" class="public-favorite-dialog__close" value="cancel" aria-label="<?= h($closeLabel) ?>">
            <span aria-hidden="true">×</span>
        </button>
        <div class="public-favorite-dialog__badge" aria-hidden="true">
            <span class="public-favorite-dialog__badge-heart">♥</span>
        </div>
        <h2 class="public-favorite-dialog__title" id="public-favorite-auth-title"><?= h($isEn ? 'Save to favorites' : 'Mentsd kedvencnek') ?></h2>
        <p class="public-favorite-dialog__lead">
            <?= h($isEn
                ? 'Sign in to sync hearts across devices and edit them on your profile — or continue as a guest on this device.'
                : 'Jelentkezz be, hogy minden eszközön lásd a kedvenceidet és a profilodon szerkeszd őket — vagy folytasd vendégként ezen a böngészőn.') ?>
        </p>
        <div class="public-favorite-dialog__stack">
            <a class="public-favorite-dialog__cta public-favorite-dialog__cta--primary" href="<?= h($favoriteLoginUrl) ?>"><?= h($isEn ? 'Sign in' : 'Bejelentkezés') ?></a>
            <a class="public-favorite-dialog__cta public-favorite-dialog__cta--secondary" href="<?= h($favoriteSignupUrl) ?>"><?= h($isEn ? 'Create account' : 'Regisztráció') ?></a>
        </div>
        <div class="public-favorite-dialog__divider" role="presentation">
            <span><?= h($isEn ? 'or' : 'vagy') ?></span>
        </div>
        <button type="submit" class="public-favorite-dialog__text-action" value="guest"><?= h($isEn ? 'Continue without account' : 'Fiók nélkül folytatom') ?></button>
    </form>
</dialog>

<dialog class="public-favorite-dialog public-favorite-dialog--pick" id="public-favorite-event-dialog" data-public-favorite-event-dialog aria-labelledby="public-favorite-event-title">
    <form method="dialog" class="public-favorite-dialog__panel" data-public-favorite-event-form>
        <button type="button" class="public-favorite-dialog__close" data-public-favorite-event-cancel aria-label="<?= h($closeLabel) ?>">
            <span aria-hidden="true">×</span>
        </button>
        <div class="public-favorite-dialog__badge public-favorite-dialog__badge--pick" aria-hidden="true">
            <span class="public-favorite-dialog__badge-heart">♥</span>
        </div>
        <h2 class="public-favorite-dialog__title" id="public-favorite-event-title"><?= h($isEn ? 'What do you love?' : 'Mit jelölsz kedvencnek?') ?></h2>
        <p class="public-favorite-dialog__lead"><?= h($isEn ? 'Pick everything you want to follow from this event.' : 'Jelöld ki, mi kerüljön a kedvenceid közé ehhez az eseményhez.') ?></p>
        <ul class="public-favorite-dialog__choices" data-public-favorite-event-choices></ul>
        <div class="public-favorite-dialog__stack public-favorite-dialog__stack--actions">
            <button type="submit" class="public-favorite-dialog__cta public-favorite-dialog__cta--primary" value="save"><?= h($isEn ? 'Save favorites' : 'Kedvencek mentése') ?></button>
            <button type="button" class="public-favorite-dialog__cta public-favorite-dialog__cta--ghost" data-public-favorite-event-cancel><?= h($isEn ? 'Cancel' : 'Mégse') ?></button>
        </div>
    </form>
</dialog>
