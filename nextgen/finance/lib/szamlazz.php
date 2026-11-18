<?php
declare(strict_types=1);

/**
 * Szamlazz.hu Agent kapcsolat.
 * A kulcs a finance_szamlazz_settings táblában, titkosítva van.
 * Ha ott üres, a SZAMLAZZ_* konstansok (config.local.php) érvényesek.
 */

const SZAMLAZZ_AGENT_URL = 'https://www.szamlazz.hu/szamla/';

function szamlazz_ensure_schema(PDO $db): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS `finance_szamlazz_settings` (
            `id` TINYINT UNSIGNED NOT NULL,
            `agent_key_encrypted` TEXT NULL,
            `vat_code` VARCHAR(8) NOT NULL DEFAULT 'AAM',
            `seller_bank` VARCHAR(80) NOT NULL DEFAULT '',
            `seller_account` VARCHAR(32) NOT NULL DEFAULT '',
            `last_check_at` DATETIME NULL,
            `last_check_ok` TINYINT(1) UNSIGNED NULL,
            `last_check_message` VARCHAR(400) NULL,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $done = true;
}

/**
 * @return array{
 *   agent_key: string,
 *   key_set: bool,
 *   key_source: string,
 *   key_hint: string,
 *   vat_code: string,
 *   seller_bank: string,
 *   seller_account: string,
 *   last_check_at: ?string,
 *   last_check_ok: ?bool,
 *   last_check_message: string
 * }
 */
function szamlazz_settings(PDO $db): array
{
    szamlazz_ensure_schema($db);
    $stmt = $db->query('SELECT * FROM finance_szamlazz_settings WHERE id = 1 LIMIT 1');
    $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    $row = is_array($row) ? $row : [];

    $stored = trim((string) ($row['agent_key_encrypted'] ?? ''));
    $fromDb = $stored !== '' ? szamlazz_decrypt($stored) : '';
    $fromConfig = trim((string) (defined('SZAMLAZZ_AGENT_KEY') ? SZAMLAZZ_AGENT_KEY : ''));
    if ($fromDb !== '') {
        $key = $fromDb;
        $source = 'database';
    } elseif ($fromConfig !== '') {
        $key = $fromConfig;
        $source = 'config';
    } else {
        $key = '';
        $source = 'none';
    }

    $vat = strtoupper(trim((string) ($row['vat_code'] ?? '')));
    if ($vat !== 'AAM' && $vat !== '27') {
        $vat = strtoupper(trim((string) (defined('SZAMLAZZ_VAT_CODE') ? SZAMLAZZ_VAT_CODE : 'AAM')));
        $vat = $vat === '27' ? '27' : 'AAM';
    }

    $bank = trim((string) ($row['seller_bank'] ?? ''));
    $account = trim((string) ($row['seller_account'] ?? ''));
    if ($bank === '' && $account === '') {
        $bank = trim((string) (defined('SZAMLAZZ_SELLER_BANK') ? SZAMLAZZ_SELLER_BANK : ''));
        $account = trim((string) (defined('SZAMLAZZ_SELLER_ACCOUNT') ? SZAMLAZZ_SELLER_ACCOUNT : ''));
    }

    $checked = $row['last_check_at'] ?? null;
    $okRaw = $row['last_check_ok'] ?? null;

    return [
        'agent_key' => $key,
        'key_set' => $key !== '',
        'key_source' => $source,
        'key_hint' => strlen($key) >= 4 ? substr($key, -4) : '',
        'vat_code' => $vat,
        'seller_bank' => $bank,
        'seller_account' => $account,
        'last_check_at' => is_string($checked) && $checked !== '' ? $checked : null,
        'last_check_ok' => $okRaw === null ? null : ((int) $okRaw === 1),
        'last_check_message' => trim((string) ($row['last_check_message'] ?? '')),
    ];
}

/**
 * @param array<string, mixed> $post
 */
function szamlazz_save_from_post(PDO $db, array $post): void
{
    $current = szamlazz_settings($db);
    $keyInput = trim((string) ($post['szamlazz_agent_key'] ?? ''));
    if ($keyInput === '') {
        $key = $current['key_source'] === 'database' ? $current['agent_key'] : '';
    } else {
        if (preg_match('/\s/u', $keyInput) === 1 || strlen($keyInput) < 8 || strlen($keyInput) > 200) {
            throw new InvalidArgumentException('Az Agent kulcs 8–200 karakter, szóköz nélkül.');
        }
        $key = $keyInput;
    }

    $vat = strtoupper(trim((string) ($post['szamlazz_vat'] ?? 'AAM')));
    if ($vat !== 'AAM' && $vat !== '27') {
        throw new InvalidArgumentException('Az áfakód AAM vagy 27 lehet.');
    }

    $bank = trim((string) ($post['szamlazz_bank'] ?? ''));
    $account = szamlazz_normalize_account((string) ($post['szamlazz_account'] ?? ''));
    if (mb_strlen($bank) > 80) {
        throw new InvalidArgumentException('A bank neve legfeljebb 80 karakter.');
    }
    if (($bank === '') !== ($account === '')) {
        throw new InvalidArgumentException('A bank neve és a bankszámlaszám együtt kell.');
    }

    $encrypted = null;
    if ($key !== '') {
        try {
            $encrypted = szamlazz_encrypt($key);
        } catch (Throwable $e) {
            error_log('szamlazz_save_from_post: ' . $e->getMessage());
            throw new InvalidArgumentException('A kulcs titkosítása sikertelen.');
        }
    }

    $stmt = $db->prepare('
        INSERT INTO finance_szamlazz_settings (id, agent_key_encrypted, vat_code, seller_bank, seller_account)
        VALUES (1, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            agent_key_encrypted = VALUES(agent_key_encrypted),
            vat_code = VALUES(vat_code),
            seller_bank = VALUES(seller_bank),
            seller_account = VALUES(seller_account)
    ');
    $stmt->execute([$encrypted, $vat, $bank, $account]);
}

/**
 * Előnézeti bizonylatot kér. A szamlazz.hu ezzel nem rögzít számlát.
 *
 * @return array{ok: bool, message: string}
 */
function szamlazz_connection_test(PDO $db): array
{
    $settings = szamlazz_settings($db);
    if ($settings['agent_key'] === '') {
        $message = 'A szamlazz.hu Agent kulcs nincs beállítva.';
        szamlazz_store_check($db, false, $message);

        return ['ok' => false, 'message' => $message];
    }

    $result = szamlazz_issue($db, [
        'order_id' => 'LTCONN' . date('ymdHis'),
        'buyer_name' => 'Latinfo kapcsolatpróba',
        'buyer_email' => 'kapcsolat@latinfo.hu',
        'buyer_zip' => '1011',
        'buyer_city' => 'Budapest',
        'buyer_address' => 'Kapcsolat próba 1.',
        'buyer_tax_number' => null,
        'item_name' => 'Latinfo.hu kapcsolat-ellenőrzés',
        'gross_ft' => 1000,
        'paid' => false,
        'payment_method' => 'Átutalás',
        'note' => 'Szamlazz.hu kapcsolat ellenőrzése. Bizonylat nem készül.',
    ], true);

    if ($result['error'] !== null) {
        szamlazz_store_check($db, false, $result['error']);

        return ['ok' => false, 'message' => $result['error']];
    }
    if (!str_starts_with($result['pdf'], '%PDF')) {
        $message = 'A szamlazz.hu válaszolt, de nem küldött előnézeti PDF-et.';
        szamlazz_store_check($db, false, $message);

        return ['ok' => false, 'message' => $message];
    }

    $message = 'A szamlazz.hu elfogadta a kulcsot. Előnézeti PDF készült, bizonylat nem rögzült.';
    szamlazz_store_check($db, true, $message);

    return ['ok' => true, 'message' => $message];
}

/**
 * @param array{
 *   order_id: string,
 *   buyer_name: string,
 *   buyer_email: string,
 *   buyer_zip: string,
 *   buyer_city: string,
 *   buyer_address: string,
 *   buyer_tax_number: ?string,
 *   item_name: string,
 *   gross_ft: int,
 *   paid: bool,
 *   payment_method: string,
 *   note: string
 * } $document
 * @return array{invoice_number: string, pdf: string, error: ?string}
 */
function szamlazz_issue(PDO $db, array $document, bool $preview): array
{
    $settings = szamlazz_settings($db);
    if ($settings['agent_key'] === '') {
        return ['invoice_number' => '', 'pdf' => '', 'error' => 'A szamlazz.hu Agent kulcs nincs beállítva.'];
    }

    $parts = szamlazz_vat_parts((int) $document['gross_ft'], $settings['vat_code']);
    $note = trim((string) $document['note']);
    if ($parts['gross'] !== (int) $document['gross_ft']) {
        $note .= ($note !== '' ? ' ' : '') . 'Áfa-kerekítés miatt a bizonylat végösszege ' . $parts['gross'] . ' Ft.';
    }
    $document['note'] = $note;

    $xml = szamlazz_invoice_xml($settings, $document, $parts, $preview);
    $tmp = tempnam(sys_get_temp_dir(), 'szla');
    if ($tmp === false) {
        return ['invoice_number' => '', 'pdf' => '', 'error' => 'Az átmeneti számlafájl nem készült el.'];
    }
    $xmlFile = $tmp . '.xml';
    if (!@rename($tmp, $xmlFile) || file_put_contents($xmlFile, $xml) === false) {
        @unlink($tmp);
        @unlink($xmlFile);

        return ['invoice_number' => '', 'pdf' => '', 'error' => 'Az átmeneti számlafájl nem készült el.'];
    }

    try {
        return szamlazz_post_xml($xmlFile, $settings['agent_key']);
    } finally {
        @unlink($xmlFile);
    }
}

/**
 * @param array<string, mixed> $settings
 * @param array<string, mixed> $document
 * @param array{net: int, vat: int, gross: int, code: string} $parts
 */
function szamlazz_invoice_xml(array $settings, array $document, array $parts, bool $preview): string
{
    $ns = 'http://www.szamlazz.hu/xmlszamla';
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->formatOutput = false;
    $root = $doc->createElementNS($ns, 'xmlszamla');
    $doc->appendChild($root);

    $add = static function (DOMNode $parent, string $name, string $value = '') use ($doc, $ns): DOMElement {
        $el = $doc->createElementNS($ns, $name);
        if ($value !== '') {
            $el->appendChild($doc->createTextNode($value));
        }
        $parent->appendChild($el);

        return $el;
    };

    $beallitasok = $add($root, 'beallitasok');
    $add($beallitasok, 'szamlaagentkulcs', (string) $settings['agent_key']);
    $add($beallitasok, 'eszamla', 'true');
    $add($beallitasok, 'szamlaLetoltes', 'true');
    $add($beallitasok, 'valaszVerzio', '2');

    $today = date('Y-m-d');
    $fejlec = $add($root, 'fejlec');
    $add($fejlec, 'keltDatum', $today);
    $add($fejlec, 'teljesitesDatum', $today);
    $add($fejlec, 'fizetesiHataridoDatum', $today);
    $add($fejlec, 'fizmod', (string) $document['payment_method']);
    $add($fejlec, 'penznem', 'HUF');
    $add($fejlec, 'szamlaNyelve', 'hu');
    $add($fejlec, 'megjegyzes', (string) $document['note']);
    $add($fejlec, 'rendelesSzam', (string) $document['order_id']);
    $add($fejlec, 'fizetve', !empty($document['paid']) ? 'true' : 'false');
    if ($preview) {
        $add($fejlec, 'elonezetpdf', 'true');
    }

    $elado = $add($root, 'elado');
    $bank = trim((string) ($settings['seller_bank'] ?? ''));
    $account = trim((string) ($settings['seller_account'] ?? ''));
    if ($bank !== '' && $account !== '') {
        $add($elado, 'bank', $bank);
        $add($elado, 'bankszamlaszam', $account);
    }

    $vevo = $add($root, 'vevo');
    $add($vevo, 'nev', (string) $document['buyer_name']);
    $add($vevo, 'orszag', 'Magyarország');
    $add($vevo, 'irsz', (string) $document['buyer_zip']);
    $add($vevo, 'telepules', (string) $document['buyer_city']);
    $add($vevo, 'cim', (string) $document['buyer_address']);
    $add($vevo, 'email', (string) $document['buyer_email']);
    $add($vevo, 'sendEmail', 'false');
    $tax = trim((string) ($document['buyer_tax_number'] ?? ''));
    $add($vevo, 'adoalany', $tax !== '' ? '1' : '-1');
    if ($tax !== '') {
        $add($vevo, 'adoszam', $tax);
    }

    $tetelek = $add($root, 'tetelek');
    $tetel = $add($tetelek, 'tetel');
    $add($tetel, 'megnevezes', (string) $document['item_name']);
    $add($tetel, 'mennyiseg', '1.0');
    $add($tetel, 'mennyisegiEgyseg', 'db');
    $add($tetel, 'nettoEgysegar', szamlazz_money($parts['net']));
    $add($tetel, 'afakulcs', $parts['code']);
    $add($tetel, 'nettoErtek', szamlazz_money($parts['net']));
    $add($tetel, 'afaErtek', szamlazz_money($parts['vat']));
    $add($tetel, 'bruttoErtek', szamlazz_money($parts['gross']));

    $xml = $doc->saveXML();

    return is_string($xml) ? $xml : '';
}

/**
 * @return array{net: int, vat: int, gross: int, code: string}
 */
function szamlazz_vat_parts(int $grossFt, string $vatCode): array
{
    if ($vatCode !== '27') {
        return ['net' => $grossFt, 'vat' => 0, 'gross' => $grossFt, 'code' => 'AAM'];
    }
    $net = (int) round($grossFt / 1.27);
    $vat = (int) round($net * 0.27);

    return ['net' => $net, 'vat' => $vat, 'gross' => $net + $vat, 'code' => '27'];
}

/**
 * @return array{invoice_number: string, pdf: string, error: ?string}
 */
function szamlazz_post_xml(string $xmlFile, string $agentKey): array
{
    if (!function_exists('curl_init')) {
        return ['invoice_number' => '', 'pdf' => '', 'error' => 'A PHP cURL bővítmény hiányzik.'];
    }

    $headers = [];
    $ch = curl_init(SZAMLAZZ_AGENT_URL);
    if ($ch === false) {
        return ['invoice_number' => '', 'pdf' => '', 'error' => 'A szamlazz.hu hívás nem indult el.'];
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'action-xmlagentxmlfile' => new CURLFile($xmlFile, 'text/xml', 'invoice.xml'),
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$headers): int {
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            }

            return strlen($header);
        },
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    curl_close($ch);
    if ($errno !== 0 || !is_string($raw)) {
        return ['invoice_number' => '', 'pdf' => '', 'error' => 'A szamlazz.hu nem válaszolt.'];
    }

    $headerError = szamlazz_header_text((string) ($headers['szlahu_error'] ?? ''));
    $headerCode = trim((string) ($headers['szlahu_error_code'] ?? ''));
    if ($headerError !== '' || ($headerCode !== '' && $headerCode !== '0')) {
        $message = $headerError !== '' ? $headerError : ('Szamlazz.hu hiba: ' . $headerCode);

        return ['invoice_number' => '', 'pdf' => '', 'error' => szamlazz_redact($message, $agentKey)];
    }

    if (str_starts_with($raw, '%PDF')) {
        $number = trim((string) ($headers['szlahu_szamlaszam'] ?? ''));

        return ['invoice_number' => $number, 'pdf' => $raw, 'error' => null];
    }

    $parsed = new DOMDocument();
    if (@$parsed->loadXML($raw) === false) {
        error_log('szamlazz_post_xml: nem XML és nem PDF válasz');

        return ['invoice_number' => '', 'pdf' => '', 'error' => 'A szamlazz.hu válasza nem értelmezhető.'];
    }

    $success = strtolower(szamlazz_xml_text($parsed, 'sikeres'));
    $message = szamlazz_xml_text($parsed, 'hibauzenet');
    $code = szamlazz_xml_text($parsed, 'hibakod');
    if ($success !== 'true') {
        $text = $message !== '' ? $message : ($code !== '' ? ('Szamlazz.hu hiba: ' . $code) : 'A szamlazz.hu elutasította a bizonylatot.');

        return ['invoice_number' => '', 'pdf' => '', 'error' => szamlazz_redact($text, $agentKey)];
    }

    $pdf = base64_decode(szamlazz_xml_text($parsed, 'pdf'), true);
    if ($pdf === false) {
        $pdf = base64_decode(szamlazz_xml_text($parsed, 'szamlaPdf'), true);
    }
    if ($pdf === false) {
        $pdf = '';
    }

    return [
        'invoice_number' => szamlazz_xml_text($parsed, 'szamlaszam'),
        'pdf' => $pdf,
        'error' => null,
    ];
}

function szamlazz_xml_text(DOMDocument $doc, string $local): string
{
    $nodes = $doc->getElementsByTagName($local);
    if ($nodes->length === 0) {
        return '';
    }

    return trim((string) $nodes->item(0)?->textContent);
}

function szamlazz_store_check(PDO $db, bool $ok, string $message): void
{
    szamlazz_ensure_schema($db);
    $text = szamlazz_redact($message, '');
    $update = $db->prepare('
        UPDATE finance_szamlazz_settings
        SET last_check_at = NOW(), last_check_ok = ?, last_check_message = ?
        WHERE id = 1
    ');
    $update->execute([$ok ? 1 : 0, $text]);
    if ($update->rowCount() > 0) {
        return;
    }

    $exists = $db->query('SELECT id FROM finance_szamlazz_settings WHERE id = 1 LIMIT 1');
    if ($exists !== false && $exists->fetch()) {
        return;
    }

    $insert = $db->prepare('
        INSERT INTO finance_szamlazz_settings (id, last_check_at, last_check_ok, last_check_message)
        VALUES (1, NOW(), ?, ?)
    ');
    $insert->execute([$ok ? 1 : 0, $text]);
}

function szamlazz_normalize_account(string $raw): string
{
    $trimmed = trim($raw);
    if ($trimmed === '') {
        return '';
    }
    $digits = preg_replace('/\D+/', '', $trimmed) ?? '';
    if (strlen($digits) === 16) {
        return substr($digits, 0, 8) . '-' . substr($digits, 8, 8);
    }
    if (strlen($digits) === 24) {
        return substr($digits, 0, 8) . '-' . substr($digits, 8, 8) . '-' . substr($digits, 16, 8);
    }

    throw new InvalidArgumentException('A bankszámlaszám 16 vagy 24 számjegy, kötőjellel tagolva.');
}

function szamlazz_money(int $amount): string
{
    return number_format($amount, 1, '.', '');
}

function szamlazz_encrypt(string $secret): string
{
    require_once __DIR__ . '/../../includes/email.php';

    return email_jelszo_titkosit($secret);
}

function szamlazz_decrypt(string $stored): string
{
    require_once __DIR__ . '/../../includes/email.php';
    try {
        return email_jelszo_visszafejt($stored);
    } catch (Throwable $e) {
        error_log('szamlazz_decrypt: ' . $e->getMessage());

        return '';
    }
}

function szamlazz_header_text(string $value): string
{
    $value = trim(rawurldecode($value));
    if ($value === '') {
        return '';
    }
    if (!mb_check_encoding($value, 'UTF-8')) {
        $value = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-2');
    }

    return trim($value);
}

function szamlazz_redact(string $text, string $agentKey): string
{
    $secrets = [
        $agentKey,
        trim((string) (defined('SZAMLAZZ_AGENT_KEY') ? SZAMLAZZ_AGENT_KEY : '')),
    ];
    foreach ($secrets as $secret) {
        if ($secret !== '') {
            $text = str_replace($secret, '***', $text);
        }
    }
    $text = trim((string) preg_replace('/\s+/u', ' ', $text));
    if (mb_strlen($text) > 400) {
        return mb_substr($text, 0, 400) . '…';
    }

    return $text;
}
