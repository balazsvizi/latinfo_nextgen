<?php
declare(strict_types=1);

/**
 * landingpage tábla – CREATE / migráció (nyilvános landing + visszajelzés + admin lista).
 */
if (!function_exists('ensure_landingpage_table')) {
    function ensure_landingpage_table(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS nextgen_landing_feedback (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ilyen_legyen TEXT NULL,
                ilyen_ne_legyen TEXT NULL,
                email VARCHAR(255) NULL,
                ip VARCHAR(45) NULL,
                user_agent VARCHAR(512) NULL,
                létrehozva DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        try {
            $db->exec('ALTER TABLE nextgen_landing_feedback MODIFY email VARCHAR(255) NULL');
        } catch (Throwable $e) {
            // tábla már jó, vagy nincs ALTER jog
        }
        foreach ([
            'nev' => 'VARCHAR(255) NULL',
            'telefon' => 'VARCHAR(50) NULL',
        ] as $column => $definition) {
            try {
                $db->exec("ALTER TABLE nextgen_landing_feedback ADD COLUMN $column $definition AFTER email");
            } catch (Throwable $e) {
                // oszlop már létezik
            }
        }
        try {
            $db->exec('ALTER TABLE nextgen_landing_feedback ADD COLUMN forras VARCHAR(500) NULL AFTER telefon');
        } catch (Throwable $e) {
            // oszlop már létezik
        }
    }
}

if (!function_exists('landing_client_meta')) {
    /**
     * @return array{0: ?string, 1: ?string} [ip, user_agent]
     */
    function landing_client_meta(): array
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        if (is_string($ip) && strlen($ip) > 45) {
            $ip = substr($ip, 0, 45);
        }
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        if (is_string($ua) && strlen($ua) > 512) {
            $ua = substr($ua, 0, 512);
        }

        return [$ip, $ua];
    }
}

if (!function_exists('landing_feedback_normalize_forras')) {
    function landing_feedback_normalize_forras(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($raw, 'UTF-8') > 500) {
                $raw = mb_substr($raw, 0, 500, 'UTF-8');
            }
        } elseif (strlen($raw) > 500) {
            $raw = substr($raw, 0, 500);
        }

        return $raw;
    }
}

if (!function_exists('landing_feedback_is_self_url')) {
    function landing_feedback_is_self_url(string $url): bool
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $path = rawurldecode($path);
        $pathLower = function_exists('mb_strtolower')
            ? mb_strtolower($path, 'UTF-8')
            : strtolower($path);

        return str_contains($pathLower, '/feedback')
            || str_contains($pathLower, '/visszajelzes')
            || str_contains($pathLower, '/visszajelzés');
    }
}

if (!function_exists('landing_feedback_resolve_forras')) {
    /**
     * Honnan érkezett: ?from= / rejtett mező / session / HTTP Referer.
     */
    function landing_feedback_resolve_forras(?string $posted = null): ?string
    {
        $candidates = [];
        if ($posted !== null && trim($posted) !== '') {
            $candidates[] = $posted;
        }
        $fromGet = trim((string) ($_GET['from'] ?? ''));
        if ($fromGet !== '') {
            $candidates[] = $fromGet;
        }
        if (isset($_SESSION['landing_feedback_forras']) && is_string($_SESSION['landing_feedback_forras'])) {
            $candidates[] = $_SESSION['landing_feedback_forras'];
        }
        $ref = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
        if ($ref !== '' && !landing_feedback_is_self_url($ref)) {
            $candidates[] = $ref;
        }

        foreach ($candidates as $raw) {
            $normalized = landing_feedback_normalize_forras($raw);
            if ($normalized !== null) {
                $_SESSION['landing_feedback_forras'] = $normalized;

                return $normalized;
            }
        }

        return null;
    }
}

if (!function_exists('landing_feedback_insert')) {
    /**
     * @param array{
     *     ilyen_legyen?: string,
     *     ilyen_ne_legyen?: string,
     *     email?: string,
     *     nev?: string,
     *     telefon?: string,
     *     forras?: ?string,
     *     ip?: ?string,
     *     user_agent?: ?string
     * } $data
     */
    function landing_feedback_insert(PDO $db, array $data): void
    {
        $ilyen = trim((string) ($data['ilyen_legyen'] ?? ''));
        $ne = trim((string) ($data['ilyen_ne_legyen'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $nev = trim((string) ($data['nev'] ?? ''));
        $telefon = trim((string) ($data['telefon'] ?? ''));
        $forras = landing_feedback_normalize_forras(
            isset($data['forras']) ? (string) $data['forras'] : null
        );
        $ip = $data['ip'] ?? null;
        $ua = $data['user_agent'] ?? null;

        $stmt = $db->prepare(
            'INSERT INTO nextgen_landing_feedback
                (ilyen_legyen, ilyen_ne_legyen, email, nev, telefon, forras, ip, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $ilyen !== '' ? $ilyen : null,
            $ne !== '' ? $ne : null,
            $email !== '' ? $email : null,
            $nev !== '' ? $nev : null,
            $telefon !== '' ? $telefon : null,
            $forras,
            is_string($ip) ? $ip : null,
            is_string($ua) ? $ua : null,
        ]);
    }
}

if (!function_exists('landing_feedback_send_mail')) {
    /**
     * @param array{
     *     ilyen_legyen?: string,
     *     ilyen_ne_legyen?: string,
     *     email?: string,
     *     nev?: string,
     *     telefon?: string,
     *     forras?: ?string,
     *     ip?: ?string,
     *     context?: string
     * } $data
     */
    function landing_feedback_send_mail(array $data): void
    {
        if (!function_exists('email_kuld')) {
            require_once __DIR__ . '/email.php';
        }
        if (!function_exists('h')) {
            require_once __DIR__ . '/functions.php';
        }

        $ilyen = trim((string) ($data['ilyen_legyen'] ?? ''));
        $ne = trim((string) ($data['ilyen_ne_legyen'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $nev = trim((string) ($data['nev'] ?? ''));
        $telefon = trim((string) ($data['telefon'] ?? ''));
        $forras = trim((string) ($data['forras'] ?? ''));
        $ip = trim((string) ($data['ip'] ?? ''));
        $context = trim((string) ($data['context'] ?? 'visszajelzés'));

        $targy = SITE_NAME . ' – Új visszajelzés';
        $sor = static function (string $cimke, string $ertek): string {
            if ($ertek === '') {
                return '';
            }

            return '<p><strong>' . h($cimke) . ':</strong><br>' . nl2br(h($ertek)) . '</p>';
        };
        $szoveg = '<p>Új visszajelzés érkezett (' . h($context) . ').</p>'
            . $sor('Ilyen legyen', $ilyen)
            . $sor('Ilyen ne legyen', $ne)
            . $sor('Név', $nev)
            . $sor('E-mail', $email)
            . $sor('Telefon', $telefon)
            . $sor('Honnan', $forras)
            . $sor('IP', $ip)
            . '<p><a href="' . h(site_url('nextgen/config/lanueva.php')) . '">Megnyitás az adminban</a></p>';

        $mailOpciok = ['html' => true];
        if ($email !== '') {
            $mailOpciok['reply_to'] = $email;
        }
        $mailResult = email_kuld('balazsv@gmail.com', $targy, $szoveg, $mailOpciok);
        if (!$mailResult['ok']) {
            error_log('landing feedback mail: ' . ($mailResult['hiba'] ?? ''));
        }
    }
}

if (!function_exists('landing_feedback_has_text')) {
    function landing_feedback_has_text(array $r): bool
    {
        return trim((string) ($r['ilyen_legyen'] ?? '')) !== ''
            || trim((string) ($r['ilyen_ne_legyen'] ?? '')) !== '';
    }

    function landing_feedback_is_ertesites(array $r): bool
    {
        return trim((string) ($r['email'] ?? '')) !== '' && !landing_feedback_has_text($r);
    }

    function landing_feedback_where_tipus(string $tipus): string
    {
        if ($tipus === 'visszajelzes') {
            return 'WHERE ((ilyen_legyen IS NOT NULL AND ilyen_legyen != \'\') OR (ilyen_ne_legyen IS NOT NULL AND ilyen_ne_legyen != \'\'))';
        }
        if ($tipus === 'ertesites') {
            return 'WHERE email IS NOT NULL AND email != \'\' AND (ilyen_legyen IS NULL OR ilyen_legyen = \'\') AND (ilyen_ne_legyen IS NULL OR ilyen_ne_legyen = \'\')';
        }

        return '';
    }

    function landing_feedback_tipus_cimke(array $r): string
    {
        if (landing_feedback_is_ertesites($r)) {
            return 'Értesítés (e-mail)';
        }

        return 'Visszajelzés';
    }

    function landing_feedback_forras_cimke(array $r): string
    {
        $forras = trim((string) ($r['forras'] ?? ''));
        if ($forras === '') {
            return '–';
        }
        if ($forras === 'lanueva') {
            return 'LaNueva';
        }
        if ($forras === 'feedback' || $forras === 'visszajelzes' || $forras === 'visszajelzés') {
            return 'Feedback';
        }

        return $forras;
    }
}
