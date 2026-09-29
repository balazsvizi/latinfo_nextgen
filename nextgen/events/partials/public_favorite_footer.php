<?php
declare(strict_types=1);

/**
 * Szívecskék: dialógusok + JS (egyszer az oldal alján).
 *
 * @var bool $publicFavoritesEnabled
 * @var string $favoriteDialogLang
 */

if (empty($publicFavoritesEnabled)) {
    return;
}

$favoriteDialogLang = ($favoriteDialogLang ?? 'hu') === 'en' ? 'en' : 'hu';
require __DIR__ . '/public_favorite_dialogs.php';
$jsUrl = events_url('assets/public_favorites.js') . '?v=' . rawurlencode(nextgen_app_version());
?>
<script src="<?= h($jsUrl) ?>" defer></script>
