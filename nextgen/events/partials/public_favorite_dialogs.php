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
?>
<dialog class="public-favorite-dialog" id="public-favorite-auth-dialog" data-public-favorite-auth-dialog>
    <form method="dialog" class="public-favorite-dialog__panel">
        <h2 class="public-favorite-dialog__title"><?= h($isEn ? 'Save favorites' : 'Kedvencek mentése') ?></h2>
        <p class="public-favorite-dialog__lead">
            <?= h($isEn
                ? 'Sign in to manage favorites on your profile, or continue without an account — your heart will still be saved on this page.'
                : 'Jelentkezz be, hogy a profilodon is kezelhesd a kedvenceket, vagy folytasd fiók nélkül — a szívecskét így is elmentjük.') ?>
        </p>
        <div class="public-favorite-dialog__actions">
            <a class="btn btn-primary" href="<?= h($favoriteLoginUrl) ?>"><?= h($isEn ? 'Sign in' : 'Bejelentkezés') ?></a>
            <?php
            $favoriteSignupUrl = user_url('signup.php');
            if ($favoriteReturnPath !== '' && ($favoriteReturnPath[0] ?? '') === '/') {
                $favoriteSignupUrl .= '?return=' . rawurlencode($favoriteReturnPath);
            }
            ?>
            <a class="btn btn-secondary" href="<?= h($favoriteSignupUrl) ?>"><?= h($isEn ? 'Register' : 'Regisztráció') ?></a>
            <button type="submit" class="btn btn-ghost" value="guest"><?= h($isEn ? 'Continue without account' : 'Fiók nélkül') ?></button>
        </div>
    </form>
</dialog>

<dialog class="public-favorite-dialog" id="public-favorite-event-dialog" data-public-favorite-event-dialog>
    <form method="dialog" class="public-favorite-dialog__panel" data-public-favorite-event-form>
        <h2 class="public-favorite-dialog__title"><?= h($isEn ? 'What do you want to favorite?' : 'Mit jelölsz kedvencnek?') ?></h2>
        <p class="public-favorite-dialog__lead"><?= h($isEn ? 'Choose one or more items linked to this event.' : 'Válaszd ki, mi kerüljön a kedvenceid közé ehhez az eseményhez.') ?></p>
        <ul class="public-favorite-dialog__choices" data-public-favorite-event-choices></ul>
        <div class="public-favorite-dialog__actions">
            <button type="submit" class="btn btn-primary" value="save"><?= h($isEn ? 'Save' : 'Mentés') ?></button>
            <button type="button" class="btn btn-secondary" data-public-favorite-event-cancel><?= h($isEn ? 'Cancel' : 'Mégse') ?></button>
        </div>
    </form>
</dialog>
