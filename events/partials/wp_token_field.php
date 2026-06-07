<?php
declare(strict_types=1);
/**
 * WordPress-szerű címke / token választó mező.
 *
 * @var string $wpTokenId
 * @var string $wpTokenLabel
 * @var string $wpTokenFieldName
 * @var string $wpTokenPlaceholder
 * @var string $wpTokenHelp
 * @var string|null $wpTokenManageUrl
 * @var string $wpTokenManageLabel
 * @var array<int, array{id:int,name:string}> $wpTokenAll
 * @var array<int, int> $wpTokenSelected
 * @var bool $wpTokenAllowCreate
 * @var string $wpTokenEntityType
 * @var bool $wpTokenSingle
 */
$wpTokenId = $wpTokenId ?? 'wp-token';
$wpTokenLabel = $wpTokenLabel ?? '';
$wpTokenFieldName = $wpTokenFieldName ?? 'ids[]';
$wpTokenPlaceholder = $wpTokenPlaceholder ?? 'Hozzáadás…';
$wpTokenHelp = $wpTokenHelp ?? '';
$wpTokenManageUrl = $wpTokenManageUrl ?? null;
$wpTokenManageLabel = $wpTokenManageLabel ?? 'Szerkesztés';
$wpTokenAll = $wpTokenAll ?? [];
$wpTokenSelected = $wpTokenSelected ?? [];
$wpTokenAllowCreate = $wpTokenAllowCreate ?? false;
$wpTokenEntityType = $wpTokenEntityType ?? '';
$wpTokenSingle = $wpTokenSingle ?? false;
$wpTokenJson = json_encode(
    ['all' => array_values($wpTokenAll), 'selected' => array_values($wpTokenSelected)],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
if ($wpTokenJson === false) {
    $wpTokenJson = '{"all":[],"selected":[]}';
}
$showInput = $wpTokenAll !== [] || $wpTokenAllowCreate;
?>
<div class="wp-token-field" id="<?= h($wpTokenId) ?>-field">
    <?php if ($wpTokenLabel !== ''): ?>
        <label class="wp-token-field__label" for="<?= h($wpTokenId) ?>-input"><?= h($wpTokenLabel) ?></label>
    <?php endif; ?>
    <?php if ($wpTokenHelp !== ''): ?>
        <p class="help wp-token-field__help"><?= h($wpTokenHelp) ?></p>
    <?php endif; ?>
    <?php if (!$showInput): ?>
        <p class="help">Még nincs elem felvéve.<?php if ($wpTokenManageUrl !== null): ?> <a href="<?= h($wpTokenManageUrl) ?>"><?= h($wpTokenManageLabel) ?></a><?php endif; ?></p>
    <?php else: ?>
        <div
            class="wp-token-input"
            id="<?= h($wpTokenId) ?>"
            data-wp-token="1"
            data-field-name="<?= h($wpTokenFieldName) ?>"
            data-placeholder="<?= h($wpTokenPlaceholder) ?>"
            <?= $wpTokenAllowCreate ? ' data-allow-create="1"' : '' ?>
            <?= $wpTokenEntityType !== '' ? ' data-entity-type="' . h($wpTokenEntityType) . '"' : '' ?>
            <?= $wpTokenSingle ? ' data-single="1"' : '' ?>
        >
            <script type="application/json" class="wp-token-input__json"><?= $wpTokenJson ?></script>
            <div class="wp-token-input__inner" tabindex="-1">
                <div class="wp-token-input__tokens" aria-live="polite"></div>
                <input
                    type="text"
                    class="wp-token-input__search"
                    id="<?= h($wpTokenId) ?>-input"
                    autocomplete="off"
                    spellcheck="false"
                    aria-autocomplete="list"
                    aria-expanded="false"
                    role="combobox"
                >
            </div>
            <ul class="wp-token-input__suggestions" role="listbox" hidden></ul>
            <div class="wp-token-input__hiddens"></div>
        </div>
        <div class="wp-token-field__popular" data-wp-token-popular hidden>
            <span class="wp-token-field__popular-label">Gyakran használt:</span>
            <span class="wp-token-field__popular-list"></span>
        </div>
        <?php if ($wpTokenManageUrl !== null): ?>
            <p class="wp-token-field__footer"><a href="<?= h($wpTokenManageUrl) ?>"><?= h($wpTokenManageLabel) ?></a></p>
        <?php endif; ?>
    <?php endif; ?>
</div>
