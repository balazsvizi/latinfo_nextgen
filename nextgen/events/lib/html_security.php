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

    $allowedTags = '<p><br><strong><b><em><i><u><s><ul><ol><li><blockquote><h1><h2><h3><h4><h5><h6><a><img><picture><source><figure><figcaption><table><thead><tbody><tr><th><td><hr><pre><code><span><div>';
    $safe = strip_tags($html, $allowedTags);

    // Inline event handlerek (onclick, onload, ...).
    $safe = (string) preg_replace('/\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/iu', '', $safe);
    // style attribútum tiltása (CSS alapú JS/exfil kockázat csökkentés).
    $safe = (string) preg_replace('/\s+style\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/iu', '', $safe);

    $safe = events_sanitize_html_urls($safe, 'href');
    $safe = events_sanitize_html_urls($safe, 'src');
    $safe = events_sanitize_html_urls($safe, 'srcset');

    return trim($safe);
}

/**
 * http(s) URL, amit a böngésző betölt — a FILTER_VALIDATE_URL sok érvényes címet elutasít.
 */
function events_http_https_url_is_acceptable(string $u): bool {
    if (!preg_match('#^https?://#i', $u)) {
        return false;
    }
    $p = parse_url($u);
    if (!is_array($p)) {
        return false;
    }
    $scheme = strtolower((string) ($p['scheme'] ?? ''));
    $host = (string) ($p['host'] ?? '');

    return ($scheme === 'http' || $scheme === 'https') && $host !== '';
}

/**
 * href/src/srcset attribútumokban csak biztonságos sémák maradhatnak.
 */
function events_sanitize_html_urls(string $html, string $attr): string {
    if ($attr === 'srcset') {
        return events_sanitize_html_srcset($html);
    }

    $pattern = '/\b' . preg_quote($attr, '/') . '\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/iu';

    return (string) preg_replace_callback($pattern, static function (array $m) use ($attr): string {
        $raw = $m[2] !== '' ? $m[2] : ($m[3] !== '' ? $m[3] : $m[4]);
        $v = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $v = preg_replace('/^\x{FEFF}|\x{200B}/u', '', $v) ?? $v;
        $lower = strtolower($v);

        if ($v === '' || str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'vbscript:') || str_starts_with($lower, 'data:')) {
            return '';
        }
        if (str_starts_with($v, '#') || str_starts_with($v, '/')) {
            return ' ' . $attr . '="' . h($v) . '"';
        }
        if (preg_match('#^https?://#i', $v) || str_starts_with($v, '//') || str_starts_with($lower, 'mailto:') || str_starts_with($lower, 'tel:')) {
            if ((str_starts_with($lower, 'http://') || str_starts_with($lower, 'https://')) && !events_http_https_url_is_acceptable($v)) {
                return '';
            }

            return ' ' . $attr . '="' . h($v) . '"';
        }
        if ($attr === 'src' && preg_match('#^[a-zA-Z0-9][a-zA-Z0-9_\-/]*\.(jpe?g|png|gif|webp|svg|avif)(\?[^\s>]*)?$#i', $v) && !str_contains($v, ':')) {
            return ' ' . $attr . '="' . h('/' . $v) . '"';
        }

        return '';
    }, $html);
}

/**
 * img srcset: vesszővel elválasztott URL(+opcionális méret) — minden URL külön ellenőrizve.
 */
function events_sanitize_html_srcset(string $html): string {
    $pattern = '/\bsrcset\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/iu';

    return (string) preg_replace_callback($pattern, static function (array $m): string {
        $raw = $m[2] !== '' ? $m[2] : ($m[3] !== '' ? $m[3] : $m[4]);
        $full = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($full === '') {
            return '';
        }
        $parts = preg_split('/\s*,\s*/', $full) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $tok = preg_split('/\s+/', $part, 2);
            $url = $tok[0] ?? '';
            $desc = isset($tok[1]) ? ' ' . $tok[1] : '';
            $url = preg_replace('/^\x{FEFF}|\x{200B}/u', '', $url) ?? $url;
            $lower = strtolower($url);
            if ($url === '' || str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'data:')) {
                continue;
            }
            $ok = false;
            if (str_starts_with($url, '/') || str_starts_with($url, '//')) {
                $ok = true;
            } elseif (preg_match('#^https?://#i', $url) && events_http_https_url_is_acceptable($url)) {
                $ok = true;
            } elseif (preg_match('#^[a-zA-Z0-9][a-zA-Z0-9_\-/]*\.(jpe?g|png|gif|webp|svg|avif)(\?[^\s]*)?$#i', $url) && !str_contains($url, ':')) {
                $url = '/' . $url;
                $ok = true;
            }
            if ($ok) {
                $out[] = trim(h($url) . $desc);
            }
        }
        if ($out === []) {
            return '';
        }

        return ' srcset="' . implode(', ', $out) . '"';
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
    if (!events_http_https_url_is_acceptable($u)) {
        return [null, 'A link formátuma érvénytelen.'];
    }

    return [$u, null];
}
