<?php
/**
 * landingpage tábla – CREATE / migráció (nyilvános landing + admin lista).
 */
if (!function_exists('ensure_landingpage_table')) {
    function ensure_landingpage_table(PDO $db): void {
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
    }
}

if (!function_exists('landing_feedback_has_text')) {
    function landing_feedback_has_text(array $r): bool {
        return trim((string) ($r['ilyen_legyen'] ?? '')) !== ''
            || trim((string) ($r['ilyen_ne_legyen'] ?? '')) !== '';
    }

    function landing_feedback_is_ertesites(array $r): bool {
        return trim((string) ($r['email'] ?? '')) !== '' && !landing_feedback_has_text($r);
    }

    function landing_feedback_where_tipus(string $tipus): string {
        if ($tipus === 'visszajelzes') {
            return 'WHERE ((ilyen_legyen IS NOT NULL AND ilyen_legyen != \'\') OR (ilyen_ne_legyen IS NOT NULL AND ilyen_ne_legyen != \'\'))';
        }
        if ($tipus === 'ertesites') {
            return 'WHERE email IS NOT NULL AND email != \'\' AND (ilyen_legyen IS NULL OR ilyen_legyen = \'\') AND (ilyen_ne_legyen IS NULL OR ilyen_ne_legyen = \'\')';
        }
        return '';
    }

    function landing_feedback_tipus_cimke(array $r): string {
        if (landing_feedback_is_ertesites($r)) {
            return 'Értesítés (e-mail)';
        }
        return 'Visszajelzés';
    }
}
