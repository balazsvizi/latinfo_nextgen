<?php
declare(strict_types=1);

/**
 * E-mail lista kapcsoló sor.
 *
 * @var int $mailSwitchId
 * @var string $mailSwitchName
 * @var bool $mailSwitchChecked
 * @var bool $mailSwitchSoonLabel „hamarosan” jelvény
 * @var string $mailSwitchDesc opcionális leírás
 * @var string $mailSwitchSlug
 * @var string $mailSwitchPairRole prefs|all|''
 * @var string $mailSwitchPairGroup kizáró csoport kulcs
 */
$mailSwitchId = (int) ($mailSwitchId ?? 0);
$mailSwitchName = (string) ($mailSwitchName ?? '');
$mailSwitchChecked = !empty($mailSwitchChecked);
$mailSwitchSoonLabel = !empty($mailSwitchSoonLabel);
$mailSwitchDesc = trim((string) ($mailSwitchDesc ?? ''));
$mailSwitchSlug = trim((string) ($mailSwitchSlug ?? ''));
$mailSwitchPairRole = trim((string) ($mailSwitchPairRole ?? ''));
$mailSwitchPairGroup = trim((string) ($mailSwitchPairGroup ?? ''));
$inputId = 'mail-list-' . $mailSwitchId;
?>
<label class="user-mail-switch<?= $mailSwitchChecked ? ' is-on' : '' ?>" for="<?= h($inputId) ?>">
    <span class="user-mail-switch__copy">
        <span class="user-mail-switch__title">
            <?= h($mailSwitchName) ?>
            <?php if ($mailSwitchSoonLabel): ?>
                <span class="user-mailing-soon">hamarosan</span>
            <?php endif; ?>
        </span>
        <?php if ($mailSwitchDesc !== ''): ?>
            <span class="user-mail-switch__desc"><?= h($mailSwitchDesc) ?></span>
        <?php endif; ?>
    </span>
    <span class="user-mail-switch__control">
        <input
            type="checkbox"
            id="<?= h($inputId) ?>"
            class="user-mail-switch__input"
            name="lists[<?= $mailSwitchId ?>]"
            value="1"
            data-mail-slug="<?= h($mailSwitchSlug) ?>"
            <?php if ($mailSwitchPairGroup !== '' && $mailSwitchPairRole !== ''): ?>
                data-mail-pair-group="<?= h($mailSwitchPairGroup) ?>"
                data-mail-pair-role="<?= h($mailSwitchPairRole) ?>"
            <?php endif; ?>
            <?= $mailSwitchChecked ? ' checked' : '' ?>
        >
        <span class="user-mail-switch__track" aria-hidden="true">
            <span class="user-mail-switch__thumb"></span>
        </span>
    </span>
</label>
