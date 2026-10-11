<?php

declare(strict_types=1);

/**
 * Levélsablonok modul bootstrap.
 * Biztosítja, hogy a szükséges tábla létezzen.
 */
function ensure_levelsablonok_table(PDO $db): void
{
    $db->exec("
        CREATE TABLE IF NOT EXISTS finance_email_templates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            név VARCHAR(255) NOT NULL,
            kód VARCHAR(100) NOT NULL UNIQUE,
            tárgy VARCHAR(255) NOT NULL DEFAULT '',
            megjegyzés TEXT NULL,
            html_tartalom MEDIUMTEXT NOT NULL,
            létrehozva DATETIME DEFAULT CURRENT_TIMESTAMP,
            módosítva DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Régi telepítéseknél lehet, hogy a tárgy oszlop hiányzik.
    try {
        $col = $db->query("SHOW COLUMNS FROM finance_email_templates LIKE 'tárgy'")->fetch();
        if (!$col) {
            $db->exec("ALTER TABLE finance_email_templates ADD COLUMN tárgy VARCHAR(255) NOT NULL DEFAULT '' AFTER kód");
        }
    } catch (Throwable $e) {
        // nincs ALTER jog -> marad fallback tárgy a kódban
    }

    try {
        $col = $db->query("SHOW COLUMNS FROM finance_email_templates LIKE 'cc_emails'")->fetch();
        if (!$col) {
            $db->exec("ALTER TABLE finance_email_templates ADD COLUMN cc_emails TEXT NULL DEFAULT NULL AFTER tárgy");
        }
    } catch (Throwable $e) {
        // nincs ALTER jog
    }
}

/**
 * Egyedi sablonkód generálása másoláshoz (max. 100 karakter).
 */
function levelsablonok_unique_copy_kod(PDO $db, string $baseKod): string
{
    $base = preg_replace('/[^a-z0-9_.-]+/i', '_', trim($baseKod)) ?? '';
    $base = trim($base, '._-');
    if ($base === '') {
        $base = 'sablon';
    }

    $suffix = '_masolat';
    $maxBaseLen = 100 - strlen($suffix);
    if (strlen($base) > $maxBaseLen) {
        $base = substr($base, 0, $maxBaseLen);
        $base = rtrim($base, '._-');
        if ($base === '') {
            $base = 'sablon';
        }
    }

    $candidate = $base . $suffix;
    $check = $db->prepare('SELECT 1 FROM finance_email_templates WHERE kód = ? LIMIT 1');
    $n = 2;
    while (true) {
        $check->execute([$candidate]);
        if (!$check->fetchColumn()) {
            return $candidate;
        }
        $num = (string) $n;
        $maxBaseLenN = 100 - strlen($suffix) - strlen($num);
        $baseN = strlen($base) > $maxBaseLenN ? rtrim(substr($base, 0, $maxBaseLenN), '._-') : $base;
        if ($baseN === '') {
            $baseN = 'sablon';
        }
        $candidate = $baseN . $suffix . $num;
        $n++;
        if ($n > 9999) {
            return $base . $suffix . bin2hex(random_bytes(3));
        }
    }
}

/**
 * Másolási sablon: név „(másolat)”, új egyedi kód, többi mező a forrásból.
 *
 * @return array<string, mixed>|null
 */
function levelsablonok_load_copy_template(PDO $db, int $sourceId): ?array
{
    if ($sourceId <= 0) {
        return null;
    }

    $stmt = $db->prepare('SELECT * FROM finance_email_templates WHERE id = ? LIMIT 1');
    $stmt->execute([$sourceId]);
    $src = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$src) {
        return null;
    }

    $nev = rtrim((string) ($src['név'] ?? ''));
    if ($nev !== '' && !str_ends_with($nev, ' (másolat)')) {
        $nev .= ' (másolat)';
    } elseif ($nev === '') {
        $nev = 'Másolat';
    }
    if (mb_strlen($nev) > 255) {
        $nev = mb_substr($nev, 0, 255);
    }

    return [
        'név' => $nev,
        'kód' => levelsablonok_unique_copy_kod($db, (string) ($src['kód'] ?? '')),
        'tárgy' => (string) ($src['tárgy'] ?? ''),
        'cc_emails' => (string) ($src['cc_emails'] ?? ''),
        'megjegyzés' => (string) ($src['megjegyzés'] ?? ''),
        'html_tartalom' => (string) ($src['html_tartalom'] ?? ''),
        'source_id' => $sourceId,
    ];
}

