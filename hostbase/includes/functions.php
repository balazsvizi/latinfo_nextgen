<?php
declare(strict_types=1);

function hb_redirect(string $url, int $code = 302): void
{
    if (!headers_sent()) {
        header('Location: ' . $url, true, $code);
        exit;
    }

    $safeUrl = hb_h($url);
    echo '<script>window.location.href="' . $safeUrl . '";</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . $safeUrl . '"></noscript>';
    exit;
}

function hb_h(?string $s): string
{
    return $s === null ? '' : htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function hb_flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_hb_flash'][$key] = $message;

        return null;
    }

    $value = $_SESSION['_hb_flash'][$key] ?? null;
    unset($_SESSION['_hb_flash'][$key]);

    return is_string($value) ? $value : null;
}

function hb_format_date(string $date): string
{
    $ts = strtotime($date);
    if ($ts === false) {
        return $date;
    }

    return date('Y.m.d', $ts);
}

function hb_format_datetime(string $datetime): string
{
    $ts = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }

    return date('Y.m.d H:i', $ts);
}

function hb_format_time(string $time): string
{
    $parts = explode(':', $time);

    return sprintf('%s:%s', $parts[0] ?? '00', $parts[1] ?? '00');
}

function hb_nights_between(string $checkIn, string $checkOut): int
{
    $in = new DateTimeImmutable($checkIn);
    $out = new DateTimeImmutable($checkOut);
    $diff = $in->diff($out);

    return max(0, (int) $diff->days);
}

function hb_client_ip(): ?string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    return is_string($ip) && $ip !== '' ? $ip : null;
}

function hb_post_int(string $key, int $default = 0): int
{
    return filter_var($_POST[$key] ?? $default, FILTER_VALIDATE_INT) !== false
        ? (int) ($_POST[$key] ?? $default)
        : $default;
}

function hb_get_int(string $key, int $default = 0): int
{
    return filter_var($_GET[$key] ?? $default, FILTER_VALIDATE_INT) !== false
        ? (int) ($_GET[$key] ?? $default)
        : $default;
}

function hb_post_string(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function hb_get_string(string $key, string $default = ''): string
{
    return trim((string) ($_GET[$key] ?? $default));
}

function hb_validate_date(string $date): bool
{
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);

    return $dt !== false && $dt->format('Y-m-d') === $date;
}
