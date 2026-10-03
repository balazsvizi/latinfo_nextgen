<?php
declare(strict_types=1);

/**
 * Partner – egy e-mail sor (cím + értesítési kapcsolók).
 *
 * @var int|string $partnerEmailRowIndex
 * @var array{email?: string, email_event_bekerult?: bool, email_szervezo_stat?: bool, is_login?: bool} $partnerEmailRow
 * @var bool $partnerEmailIsFirst
 */
$partnerEmailRowIndex = $partnerEmailRowIndex ?? 0;
$partnerEmailRow = $partnerEmailRow ?? [];
$partnerEmailIsFirst = !empty($partnerEmailIsFirst);
$emailValue = (string) ($partnerEmailRow['email'] ?? '');
$eventOn = array_key_exists('email_event_bekerult', $partnerEmailRow)
    ? !empty($partnerEmailRow['email_event_bekerult'])
    : true;
$statOn = array_key_exists('email_szervezo_stat', $partnerEmailRow)
    ? !empty($partnerEmailRow['email_szervezo_stat'])
    : true;
$inputId = 'partner-email-' . $partnerEmailRowIndex;
$eventId = 'partner-email-event-' . $partnerEmailRowIndex;
$statId = 'partner-email-stat-' . $partnerEmailRowIndex;
?>
<div class="partner-email-row" data-partner-email-row>
    <div class="partner-email-row__main">
        <label class="visually-hidden" for="<?= h($inputId) ?>">E-mail</label>
        <input
            type="email"
            id="<?= h($inputId) ?>"
            name="email_rows[<?= h((string) $partnerEmailRowIndex) ?>][email]"
            class="partner-email-row__input"
            value="<?= h($emailValue) ?>"
            <?= $partnerEmailIsFirst ? 'required' : '' ?>
            maxlength="255"
            autocomplete="off"
            placeholder="<?= $partnerEmailIsFirst ? 'Bejelentkezési e-mail *' : 'További e-mail' ?>"
        >
        <div class="partner-email-notify" role="group" aria-label="E-mail értesítések">
            <label class="partner-email-switch" for="<?= h($eventId) ?>">
                <input type="hidden" name="email_rows[<?= h((string) $partnerEmailRowIndex) ?>][email_event_bekerult]" value="0">
                <input
                    type="checkbox"
                    name="email_rows[<?= h((string) $partnerEmailRowIndex) ?>][email_event_bekerult]"
                    value="1"
                    id="<?= h($eventId) ?>"
                    class="partner-email-switch__input"
                    <?= $eventOn ? 'checked' : '' ?>
                >
                <span class="partner-email-switch__text">Event bekerült</span>
            </label>
            <label class="partner-email-switch" for="<?= h($statId) ?>">
                <input type="hidden" name="email_rows[<?= h((string) $partnerEmailRowIndex) ?>][email_szervezo_stat]" value="0">
                <input
                    type="checkbox"
                    name="email_rows[<?= h((string) $partnerEmailRowIndex) ?>][email_szervezo_stat]"
                    value="1"
                    id="<?= h($statId) ?>"
                    class="partner-email-switch__input"
                    <?= $statOn ? 'checked' : '' ?>
                >
                <span class="partner-email-switch__text">Szervező stat</span>
            </label>
        </div>
    </div>
    <button type="button" class="partner-email-row__remove" data-partner-email-remove aria-label="E-mail sor törlése">&times;</button>
</div>
