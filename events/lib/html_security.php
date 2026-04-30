<?php
declare(strict_types=1);

/**
 * Alap HTML sanitizer nyilvános tartalomhoz.
 * Cél: script/event handler/javascript: URL eltávolítása, közben alap formázó tagek megtartása.
 */
function events_sanitize_html_fragment(string $html): string {
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    $allowedTags = '<p><br><strong><b><em><i><u><s><ul><ol><li><blockquote><h1><h2><h3><h4><h5><h6><a><img><figure><figcaption><table><thead><tbody><tr><th><td><hr><pre><code><span><div>';
    $safe = strip_tags($html, $allowedTags);

    // Inline event handlerek (onclick, onload, ...).
    $safe = (string) preg_replace('/\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/iu', '', $safe);
    // style attribútum tiltása (CSS alapú JS/exfil kockázat csökkentés).
    $safe = (string) preg_replace('/\s+style\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/iu', '', $safe);

    $safe = events_sanitize_html_urls($safe, 'href');
    $safe = events_sanitize_html_urls($safe, 'src');

    return trim($safe);
}

/**
 * href/src attribútumokban csak biztonságos sémák maradhatnak.
 */
function events_sanitize_html_urls(string $html, string $attr): string {
    $pattern = '/\b' . preg_quote($attr, '/') . '\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/iu';

    return (string) preg_replace_callback($pattern, static function (array $m) use ($attr): string {
        $raw = $m[2] !== '' ? $m[2] : ($m[3] !== '' ? $m[3] : $m[4]);
        $v = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $lower = strtolower($v);

        if ($v === '' || str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'vbscript:') || str_starts_with($lower, 'data:')) {
            return '';
        }
        if (str_starts_with($v, '#') || str_starts_with($v, '/')) {
            return ' ' . $attr . '="' . h($v) . '"';
        }
        if (preg_match('#^https?://#i', $v) || str_starts_with($v, '//') || str_starts_with($lower, 'mailto:') || str_starts_with($lower, 'tel:')) {
            return ' ' . $attr . '="' . h($v) . '"';
        }

        return '';
    }, $html);
}

/**
 * URL normalizáló külső linkekhez.
 *
 * @return array{0: ?string, 1: ?string} [url|null, hiba|null]
 */
function events_normalize_safe_url(?string $raw, bool $allowRelative = true): array {
    $u = trim((string) $raw);
    if ($u === '') {
        return [null, null];
    }
    if (strlen($u) > 2000) {
        return [null, 'A link legfeljebb 2000 karakter lehet.'];
    }
    if (preg_match('/[\s<>"\'{}|\\\\^`\x00-\x1f]/', $u)) {
        return [null, 'A link érvénytelen karaktereket tartalmaz.'];
    }
    if ($allowRelative && (str_starts_with($u, '/') || str_starts_with($u, '//'))) {
        return [$u, null];
    }
    if (!preg_match('#^https?://#i', $u)) {
        return [null, 'A link csak http:// vagy https:// lehet.'];
    }
    if (!filter_var($u, FILTER_VALIDATE_URL)) {
        return [null, 'A link formátuma érvénytelen.'];
    }

    return [$u, null];
}
