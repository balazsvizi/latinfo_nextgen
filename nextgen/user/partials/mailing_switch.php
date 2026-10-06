<?php
declare(strict_types=1);

/**
 * E-mail lista kapcsoló sor.
 *
 * @var int $mailSwitchId
 * @var string $mailSwitchName
 * @var bool $mailSwitchChecked
 * @var bool $mailSwitchLocked zárolt (nem kattintható)
 * @var bool $mailSwitchSoonLabel „hamarosan” jelvény
 * @var string $mailSwitchDesc opcionális leírás
 */
$mailSwitchId = (int) ($mailSwitchId ?? 0);
$mailSwitchName = (string) ($mailSwitchName ?? '');
$mailSwitchChecked = !empty($mailSwitchChecked);
$mailSwitchLocked = !empty($mailSwitchLocked);
$mailSwitchSoonLabel = !empty($mailSwitchSoonLabel) || $mailSwitchLocked;
$mailSwitchDesc = trim((string) ($mailSwitchDesc ?? ''));
$inputId = 'mail-list-' . $mailSwitchId;
?>
<label class="user-mail-switch<?= $mailSwitchLocked ? ' is-locked' : '' ?><?= $mailSwitchChecked ? ' is-on' : '' ?>" for="<?= h($inputId) ?>">
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
            <?= $mailSwitchChecked ? ' checked' : '' ?>
            <?= $mailSwitchLocked ? ' disabled' : '' ?>
        >
        <span class="user-mail-switch__track" aria-hidden="true">
            <span class="user-mail-switch__thumb"></span>
        </span>
    </span>
</label>
