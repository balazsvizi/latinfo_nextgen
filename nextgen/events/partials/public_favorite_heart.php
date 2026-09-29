<?php
declare(strict_types=1);

/**
 * Szívecske gomb egy entitás nyilvános oldalán.
 *
 * @var string $favoriteEntityType  event|organizer|venue|dj
 * @var int $favoriteEntityId
 * @var string $favoriteLang
 * @var array<string,mixed>|null $favoriteEventPicker  esemény oldal: kapcsolódó választék
 */

$favoriteEntityType = latinfo_favorites_normalize_type((string) ($favoriteEntityType ?? '')) ?? '';
$favoriteEntityId = (int) ($favoriteEntityId ?? 0);
$favoriteLang = ($favoriteLang ?? 'hu') === 'en' ? 'en' : 'hu';
$favoriteEventPicker = is_array($favoriteEventPicker ?? null) ? $favoriteEventPicker : null;

if ($favoriteEntityType === '' || $favoriteEntityId <= 0) {
    return;
}

$dbFav = getDb();
if (!latinfo_favorites_ensure_schema($dbFav) || !latinfo_favorites_public_enabled($dbFav)) {
    return;
}

if (!function_exists('user_is_logged_in')) {
    require_once dirname(__DIR__, 2) . '/user/includes/auth.php';
}

$state = latinfo_favorites_state($dbFav, $favoriteEntityType, $favoriteEntityId);
$isLoggedIn = user_is_logged_in();
$isEventPicker = $favoriteEntityType === LATINFO_FAVORITE_TYPE_EVENT && $favoriteEventPicker !== null;
if ($isEventPicker && !empty($favoriteEventPicker['items']) && is_array($favoriteEventPicker['items'])) {
    foreach ($favoriteEventPicker['items'] as $pickRow) {
        if (!empty($pickRow['active'])) {
            $state['active'] = true;
            break;
        }
    }
}
$mode = $isEventPicker ? 'event' : 'simple';
$pickerJson = $isEventPicker
    ? json_encode($favoriteEventPicker, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
    : '';
$labelAdd = $favoriteLang === 'en' ? 'Add to favorites' : 'Kedvencnek jelölés';
$labelRemove = $favoriteLang === 'en' ? 'Remove from favorites' : 'Kedvenc törlése';
$ariaLabel = $state['active'] ? $labelRemove : $labelAdd;
?>
<div
    class="public-favorite"
    data-public-favorite
    data-mode="<?= h($mode) ?>"
    data-entity-type="<?= h($favoriteEntityType) ?>"
    data-entity-id="<?= (int) $favoriteEntityId ?>"
    data-lang="<?= h($favoriteLang) ?>"
    data-logged-in="<?= $isLoggedIn ? '1' : '0' ?>"
    data-active="<?= $state['active'] ? '1' : '0' ?>"
    data-count="<?= (int) $state['count'] ?>"
    <?php if ($pickerJson !== '' && $pickerJson !== false): ?>
        data-event-picker="<?= h($pickerJson) ?>"
    <?php endif; ?>
>
    <button
        type="button"
        class="public-favorite__btn<?= $state['active'] ? ' is-active' : '' ?>"
        data-public-favorite-btn
        aria-pressed="<?= $state['active'] ? 'true' : 'false' ?>"
        aria-label="<?= h($ariaLabel) ?>"
    >
        <span class="public-favorite__icon" aria-hidden="true">♥</span>
        <span class="public-favorite__count" data-public-favorite-count><?= (int) $state['count'] ?></span>
    </button>
</div>
