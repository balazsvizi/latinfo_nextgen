<?php
declare(strict_types=1);

/**
 * @return array<string, array{label: string, help: string}>
 */
function nextgen_partner_portal_permission_catalog(): array
{
    return [
        'stat' => [
            'label' => 'Stat',
            'help' => 'Események, naptár és statisztikák megtekintése.',
        ],
        'event_edit' => [
            'label' => 'Esemény szerk',
            'help' => 'Események szerkesztése — a portálon később jelenik meg.',
        ],
        'email_automata' => [
            'label' => 'E-mail automata',
            'help' => 'Automatikus e-mail küldés — a portálon később jelenik meg.',
        ],
        'email_messages' => [
            'label' => 'E-mail üzenetek',
            'help' => 'Üzenőfal az adminnal.',
        ],
    ];
}

/**
 * @return list<string>
 */
function nextgen_partner_portal_permission_keys(): array
{
    return array_keys(nextgen_partner_portal_permission_catalog());
}

/**
 * Meglévő partnerek és új fiókok alapértelmezett jogai (a mai portál funkciói).
 *
 * @return list<string>
 */
function nextgen_partner_default_portal_permissions(): array
{
    return ['stat', 'email_messages'];
}

function nextgen_partner_ensure_permission_schema(PDO $db): bool
{
    static $done = null;
    if ($done !== null) {
        return $done;
    }
    if (!nextgen_partners_table_ready($db)) {
        $done = false;

        return false;
    }
    try {
        $stmt = $db->query("SHOW COLUMNS FROM `nextgen_partners` LIKE 'portal_jogok'");
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $db->exec("
                ALTER TABLE `nextgen_partners`
                ADD COLUMN `portal_jogok` VARCHAR(191) NOT NULL DEFAULT '[\"stat\",\"email_messages\"]'
            ");
        }
        $done = true;

        return true;
    } catch (Throwable $ex) {
        error_log('nextgen_partner_ensure_permission_schema: ' . $ex->getMessage());
        $done = false;

        return false;
    }
}

/**
 * @param list<mixed> $keys
 * @return list<string>
 */
function nextgen_partner_normalize_portal_permissions(array $keys): array
{
    $valid = nextgen_partner_portal_permission_keys();
    $out = [];
    foreach ($keys as $key) {
        $key = trim((string) $key);
        if ($key !== '' && in_array($key, $valid, true) && !in_array($key, $out, true)) {
            $out[] = $key;
        }
    }

    return $out;
}

/**
 * @param list<mixed> $keys
 */
function nextgen_partner_encode_portal_permissions(array $keys): string
{
    $json = json_encode(nextgen_partner_normalize_portal_permissions($keys), JSON_UNESCAPED_UNICODE);

    return is_string($json) ? $json : '[]';
}

/**
 * @return list<string>
 */
function nextgen_partner_parse_portal_permissions(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        return nextgen_partner_normalize_portal_permissions($decoded);
    }
    $parts = preg_split('/\s*,\s*/', $raw) ?: [];

    return nextgen_partner_normalize_portal_permissions($parts);
}

/**
 * @param array<string, mixed>|null $partner
 * @return list<string>
 */
function nextgen_partner_portal_permissions_from_row(?array $partner): array
{
    if ($partner === null) {
        return [];
    }
    if (!array_key_exists('portal_jogok', $partner)) {
        return nextgen_partner_default_portal_permissions();
    }

    return nextgen_partner_parse_portal_permissions((string) ($partner['portal_jogok'] ?? ''));
}

/**
 * @param array<string, mixed>|null $partner
 */
function nextgen_partner_has_portal_permission(?array $partner, string $permission): bool
{
    return in_array($permission, nextgen_partner_portal_permissions_from_row($partner), true);
}

/**
 * @param mixed $posted
 * @return list<string>
 */
function nextgen_partner_portal_permissions_from_post(mixed $posted): array
{
    if (!is_array($posted)) {
        return [];
    }

    return nextgen_partner_normalize_portal_permissions($posted);
}

/**
 * @param list<string> $keys
 * @return array{ok: true}|array{ok: false, error: string}
 */
function nextgen_partner_update_portal_permissions(PDO $db, int $partnerId, array $keys): array
{
    if ($partnerId <= 0) {
        return ['ok' => false, 'error' => 'Érvénytelen partner.'];
    }
    if (!nextgen_partner_ensure_permission_schema($db)) {
        return ['ok' => false, 'error' => 'A jogosultságok oszlopa nem hozható létre.'];
    }

    $normalized = nextgen_partner_normalize_portal_permissions($keys);
    $encoded = nextgen_partner_encode_portal_permissions($normalized);

    try {
        $stmt = $db->prepare('UPDATE `nextgen_partners` SET `portal_jogok` = ? WHERE `id` = ?');
        $stmt->execute([$encoded, $partnerId]);

        $labels = [];
        $catalog = nextgen_partner_portal_permission_catalog();
        foreach ($normalized as $key) {
            $labels[] = (string) ($catalog[$key]['label'] ?? $key);
        }
        nextgen_partner_log(
            $db,
            $partnerId,
            'Portál jogosultságok mentve',
            $labels === [] ? '(nincs)' : implode(', ', $labels)
        );

        return ['ok' => true];
    } catch (Throwable $ex) {
        error_log('nextgen_partner_update_portal_permissions: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'Jogosultságok mentése sikertelen.'];
    }
}
