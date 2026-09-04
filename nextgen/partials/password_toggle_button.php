<?php
declare(strict_types=1);

$passwordToggleInputId = $passwordToggleInputId ?? '';
?>
<button
    type="button"
    class="password-toggle-btn"
    aria-label="Jelszó megjelenítése"
    aria-pressed="false"
    title="Jelszó megjelenítése"
    <?php if ($passwordToggleInputId !== ''): ?>aria-controls="<?= h($passwordToggleInputId) ?>"<?php endif; ?>
>
    <svg class="password-toggle-icon password-toggle-icon--show" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
        <circle cx="12" cy="12" r="3"></circle>
    </svg>
    <svg class="password-toggle-icon password-toggle-icon--hide" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden>
        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"></path>
        <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"></path>
        <path d="M1 1l22 22"></path>
        <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"></path>
    </svg>
</button>
<?php
unset($passwordToggleInputId);
?>
