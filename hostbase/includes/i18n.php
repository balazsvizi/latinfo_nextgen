<?php
declare(strict_types=1);

/** @var array<string, string> */
$hbTranslations = [];

function hb_supported_locales(): array
{
    return ['hu', 'en'];
}

function hb_default_locale(): string
{
    return 'hu';
}

function hb_current_locale(): string
{
    $locale = $_SESSION['hb_locale'] ?? hb_default_locale();

    return in_array($locale, hb_supported_locales(), true) ? $locale : hb_default_locale();
}

function hb_set_locale(string $locale): void
{
    $_SESSION['hb_locale'] = in_array($locale, hb_supported_locales(), true)
        ? $locale
        : hb_default_locale();
}

function hb_load_translations(): void
{
    global $hbTranslations;
    $locale = hb_current_locale();
    $file = HB_ROOT . '/lang/' . $locale . '.php';
    if (!is_file($file)) {
        $file = HB_ROOT . '/lang/hu.php';
    }
    /** @var array<string, string> $loaded */
    $loaded = require $file;
    $hbTranslations = $loaded;
}

function hb_t(string $key, array $replace = []): string
{
    global $hbTranslations;
    $text = $hbTranslations[$key] ?? $key;
    foreach ($replace as $search => $value) {
        $text = str_replace(':' . $search, (string) $value, $text);
    }

    return $text;
}

function hb_locale_label(string $locale): string
{
    return match ($locale) {
        'en' => 'English',
        default => 'Magyar',
    };
}
