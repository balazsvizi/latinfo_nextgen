<?php
declare(strict_types=1);

/**
 * Finance teszt: Barion sandbox fizetés és szamlazz.hu számla / előnézet.
 * Az éles Barion API-t ez a modul nem hívja.
 */

const FINANCE_PAYMENT_TEST_BARION_API = 'https://api.test.barion.com';
const FINANCE_PAYMENT_TEST_BARION_GATEWAY_HOST = 'secure.test.barion.com';
const FINANCE_PAYMENT_TEST_SZAMLAZZ_URL = 'https://www.szamlazz.hu/szamla/';

function finance_payment_test_ensure_schema(PDO $db): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS `finance_payment_tests` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `payment_request_id` VARCHAR(40) NOT NULL,
            `barion_payment_id` CHAR(36) NULL,
            `amount_ft` INT UNSIGNED NOT NULL,
            `buyer_name` VARCHAR(120) NOT NULL,
            `buyer_email` VARCHAR(190) NOT NULL,
            `buyer_zip` VARCHAR(10) NOT NULL DEFAULT '',
            `buyer_city` VARCHAR(100) NOT NULL DEFAULT '',
            `buyer_address` VARCHAR(200) NOT NULL DEFAULT '',
            `buyer_tax_number` VARCHAR(20) NULL,
            `status` VARCHAR(32) NOT NULL DEFAULT 'prepared',
            `barion_status` VARCHAR(32) NULL,
            `invoice_after_payment` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            `invoice_live` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            `invoice_state` VARCHAR(16) NULL,
            `invoice_number` VARCHAR(40) NULL,
            `invoice_error` VARCHAR(400) NULL,
            `invoice_pdf` VARCHAR(64) NULL,
            `last_error` VARCHAR(400) NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_finance_payment_tests_request` (`payment_request_id`),
            KEY `idx_finance_payment_tests_barion` (`barion_payment_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $done = true;
}

/**
 * @return array{
 *   barion_ready: bool,
 *   barion_key_valid: bool,
 *   payee: string,
 *   szamlazz_ready: bool,
 *   vat_code: string,
 *   seller_bank: bool,
 *   callback_url: string,
 *   return_url: string
 * }
 */
function finance_payment_test_status(): array
{
    $payee = trim((string) (defined('BARION_PAYEE_EMAIL') ? BARION_PAYEE_EMAIL : ''));
    $posKey = trim((string) (defined('BARION_POS_KEY') ? BARION_POS_KEY : ''));
    $agentKey = trim((string) (defined('SZAMLAZZ_AGENT_KEY') ? SZAMLAZZ_AGENT_KEY : ''));
    $vat = finance_payment_test_vat_code();
    $keyValid = finance_payment_test_is_guid($posKey);
    $payeeValid = filter_var($payee, FILTER_VALIDATE_EMAIL) !== false;

    return [
        'barion_ready' => $keyValid && $payeeValid,
        'barion_key_valid' => $posKey === '' || $keyValid,
        'payee' => $payee,
        'szamlazz_ready' => $agentKey !== '',
        'vat_code' => $vat,
        'seller_bank' => trim((string) (defined('SZAMLAZZ_SELLER_BANK') ? SZAMLAZZ_SELLER_BANK : '')) !== ''
            && trim((string) (defined('SZAMLAZZ_SELLER_ACCOUNT') ? SZAMLAZZ_SELLER_ACCOUNT : '')) !== '',
        'callback_url' => finance_payment_test_absolute_url('finance/fizetes_teszt/callback.php'),
        'return_url' => finance_payment_test_absolute_url('finance/fizetes_teszt/vissza.php'),
    ];
}

function finance_payment_test_vat_code(): string
{
    $code = strtoupper(trim((string) (defined('SZAMLAZZ_VAT_CODE') ? SZAMLAZZ_VAT_CODE : 'AAM')));

    return $code === '27' ? '27' : 'AAM';
}

/**
 * @param array<string, mixed> $post
 * @return array{name: string, email: string, zip: string, city: string, address: string, tax_number: ?string}
 */
function finance_payment_test_buyer_from_post(array $post, bool $requireAddress): array
{
    $name = trim((string) ($post['buyer_name'] ?? ''));
    $email = trim((string) ($post['buyer_email'] ?? ''));
    $zip = trim((string) ($post['buyer_zip'] ?? ''));
    $city = trim((string) ($post['buyer_city'] ?? ''));
    $address = trim((string) ($post['buyer_address'] ?? ''));
    $taxRaw = trim((string) ($post['buyer_tax_number'] ?? ''));

    if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
        throw new InvalidArgumentException('A név 2–120 karakter legyen.');
    }
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 190) {
        throw new InvalidArgumentException('Érvényes e-mail-cím kell.');
    }
    if (mb_strlen($city) > 100 || mb_strlen($address) > 200) {
        throw new InvalidArgumentException('A város vagy a cím túl hosszú.');
    }
    if ($requireAddress && ($zip === '' || $city === '' || $address === '')) {
        throw new InvalidArgumentException('Számlához irányítószám, város és cím kell.');
    }
    if ($zip !== '' && preg_match('/^\d{4}$/', $zip) !== 1) {
        throw new InvalidArgumentException('Az irányítószám 4 számjegy.');
    }

    $tax = null;
    if ($taxRaw !== '') {
        $digits = preg_replace('/\D+/', '', $taxRaw) ?? '';
        if (strlen($digits) === 11) {
            $taxRaw = substr($digits, 0, 8) . '-' . substr($digits, 8, 1) . '-' . substr($digits, 9, 2);
        }
        if (preg_match('/^\d{8}-\d-\d{2}$/', $taxRaw) !== 1) {
            throw new InvalidArgumentException('Az adószám formátuma 12345678-1-12.');
        }
        $tax = $taxRaw;
    }

    return [
        'name' => $name,
        'email' => $email,
        'zip' => $zip,
        'city' => $city,
        'address' => $address,
        'tax_number' => $tax,
    ];
}

function finance_payment_test_amount_from_post(array $post): int
{
    $raw = trim((string) ($post['amount_ft'] ?? ''));
    if (preg_match('/^\d+$/', $raw) !== 1) {
        throw new InvalidArgumentException('Az összeg egész forint legyen.');
    }
    $amount = (int) $raw;
    if ($amount < 100 || $amount > 100000) {
        throw new InvalidArgumentException('A tesztösszeg 100 és 100 000 Ft között lehet.');
    }

    return $amount;
}

/**
 * @param array{name: string, email: string, zip: string, city: string, address: string, tax_number: ?string} $buyer
 * @return array{id: int, gateway_url: ?string, error: ?string}
 */
function finance_payment_test_start(PDO $db, array $buyer, int $amount, bool $invoiceAfter, bool $invoiceLive): array
{
    $ready = finance_payment_test_status();
    if (!$ready['barion_ready']) {
        throw new InvalidArgumentException('A Barion sandbox POSKey vagy a tárca e-mailje hiányzik a config.local.php fájlból.');
    }

    $requestId = 'LT' . date('ymdHis') . bin2hex(random_bytes(3));
    $insert = $db->prepare('
        INSERT INTO finance_payment_tests
            (payment_request_id, amount_ft, buyer_name, buyer_email, buyer_zip, buyer_city, buyer_address,
             buyer_tax_number, status, invoice_after_payment, invoice_live)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $insert->execute([
        $requestId,
        $amount,
        $buyer['name'],
        $buyer['email'],
        $buyer['zip'],
        $buyer['city'],
        $buyer['address'],
        $buyer['tax_number'],
        'prepared',
        $invoiceAfter ? 1 : 0,
        $invoiceLive ? 1 : 0,
    ]);
    $id = (int) $db->lastInsertId();

    try {
        $started = finance_payment_test_barion_start($requestId, $amount, $buyer);
    } catch (Throwable $e) {
        error_log('finance_payment_test_start: ' . $e->getMessage());
        finance_payment_test_mark_error($db, $id, 'failed', 'A Barion sandbox nem válaszolt.');

        return ['id' => $id, 'gateway_url' => null, 'error' => 'A Barion sandbox nem válaszolt.'];
    }

    if ($started['error'] !== null || $started['gateway_url'] === null || $started['payment_id'] === null) {
        $message = $started['error'] ?? 'A Barion nem adott fizetési címet.';
        finance_payment_test_mark_error($db, $id, 'failed', $message);

        return ['id' => $id, 'gateway_url' => null, 'error' => $message];
    }

    $update = $db->prepare('
        UPDATE finance_payment_tests
        SET barion_payment_id = ?, status = ?, barion_status = ?, last_error = NULL
        WHERE id = ?
    ');
    $update->execute([$started['payment_id'], 'pending', $started['status'] ?? 'Prepared', $id]);

    return ['id' => $id, 'gateway_url' => $started['gateway_url'], 'error' => null];
}

/**
 * @param array{name: string, email: string, zip: string, city: string, address: string, tax_number: ?string} $buyer
 * @return array{id: int, error: ?string}
 */
function finance_payment_test_invoice_only(PDO $db, array $buyer, int $amount, bool $live): array
{
    if (!finance_payment_test_status()['szamlazz_ready']) {
        throw new InvalidArgumentException('A szamlazz.hu Agent kulcs hiányzik a config.local.php fájlból.');
    }

    $requestId = 'LT' . date('ymdHis') . bin2hex(random_bytes(3));
    $insert = $db->prepare('
        INSERT INTO finance_payment_tests
            (payment_request_id, amount_ft, buyer_name, buyer_email, buyer_zip, buyer_city, buyer_address,
             buyer_tax_number, status, invoice_after_payment, invoice_live)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)
    ');
    $insert->execute([
        $requestId,
        $amount,
        $buyer['name'],
        $buyer['email'],
        $buyer['zip'],
        $buyer['city'],
        $buyer['address'],
        $buyer['tax_number'],
        'invoice_only',
        $live ? 1 : 0,
    ]);
    $id = (int) $db->lastInsertId();
    $issued = finance_payment_test_issue($db, $id, $live);

    return ['id' => $id, 'error' => $issued['error']];
}

/**
 * @return array{ok: bool, error: ?string}
 */
function finance_payment_test_issue(PDO $db, int $id, bool $live): array
{
    if (!finance_payment_test_status()['szamlazz_ready']) {
        return ['ok' => false, 'error' => 'A szamlazz.hu Agent kulcs nincs beállítva.'];
    }

    $existing = finance_payment_test_find($db, $id);
    if ($existing === null) {
        return ['ok' => false, 'error' => 'A tesztbefizetés nem található.'];
    }
    if (!finance_payment_test_has_address($existing)) {
        $message = 'Számlához irányítószám, város és cím kell.';
        if ((string) ($existing['invoice_error'] ?? '') !== $message) {
            finance_payment_test_save_invoice_failure($db, $id, $message);
        }

        return ['ok' => false, 'error' => $message];
    }

    $claim = $db->prepare("
        UPDATE finance_payment_tests
        SET invoice_state = 'pending', invoice_error = NULL, invoice_live = ?
        WHERE id = ?
          AND invoice_number IS NULL
          AND status IN ('succeeded', 'invoice_only')
          AND (
                invoice_state IS NULL
                OR invoice_state = 'failed'
                OR invoice_state = 'preview'
                OR (invoice_state = 'pending' AND updated_at < (NOW() - INTERVAL 3 MINUTE))
          )
    ");
    $claim->execute([$live ? 1 : 0, $id]);
    if ($claim->rowCount() === 0) {
        $row = finance_payment_test_find($db, $id);
        if ($row === null) {
            return ['ok' => false, 'error' => 'A tesztbefizetés nem található.'];
        }
        if ((string) ($row['invoice_number'] ?? '') !== '') {
            return ['ok' => true, 'error' => null];
        }
        if (!in_array((string) $row['status'], ['succeeded', 'invoice_only'], true)) {
            return ['ok' => false, 'error' => 'Számla csak sikeres fizetés vagy számlateszt után állítható ki.'];
        }

        return ['ok' => false, 'error' => 'A számlázás már folyamatban van. Pár perc múlva újra próbálható.'];
    }

    $row = finance_payment_test_find($db, $id);
    if ($row === null) {
        return ['ok' => false, 'error' => 'A tesztbefizetés nem található.'];
    }

    try {
        $result = finance_payment_test_szamlazz_create($row, $live);
    } catch (Throwable $e) {
        error_log('finance_payment_test_issue: ' . $e->getMessage());
        finance_payment_test_save_invoice_failure($db, $id, 'A szamlazz.hu nem válaszolt.');

        return ['ok' => false, 'error' => 'A szamlazz.hu nem válaszolt.'];
    }

    if ($result['error'] !== null) {
        finance_payment_test_save_invoice_failure($db, $id, $result['error']);

        return ['ok' => false, 'error' => $result['error']];
    }

    $pdfName = null;
    if ($result['pdf'] !== '') {
        $pdfName = finance_payment_test_store_pdf($id, $result['pdf']);
    }

    $number = $result['invoice_number'];
    $state = $live ? 'done' : 'preview';
    if ($live && $number === '') {
        $state = 'failed';
        finance_payment_test_save_invoice_failure($db, $id, 'A szamlazz.hu nem adott számlaszámot.');

        return ['ok' => false, 'error' => 'A szamlazz.hu nem adott számlaszámot.'];
    }

    $save = $db->prepare('
        UPDATE finance_payment_tests
        SET invoice_state = ?, invoice_number = ?, invoice_error = NULL, invoice_pdf = ?
        WHERE id = ?
    ');
    $save->execute([$state, $number !== '' ? $number : null, $pdfName, $id]);

    return ['ok' => true, 'error' => null];
}

/**
 * @return array<string, mixed>|null
 */
function finance_payment_test_sync_barion(PDO $db, string $paymentId, bool $issueInvoice): ?array
{
    if (!finance_payment_test_is_guid($paymentId)) {
        return null;
    }

    $find = $db->prepare('SELECT * FROM finance_payment_tests WHERE barion_payment_id = ? LIMIT 1');
    $find->execute([$paymentId]);
    $row = $find->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        return null;
    }

    return finance_payment_test_refresh_row($db, $row, $issueInvoice);
}

/**
 * @return array<string, mixed>|null
 */
function finance_payment_test_sync_id(PDO $db, int $id, bool $issueInvoice): ?array
{
    $row = finance_payment_test_find($db, $id);
    if ($row === null) {
        return null;
    }
    $paymentId = trim((string) ($row['barion_payment_id'] ?? ''));
    if ($paymentId === '') {
        return $row;
    }

    return finance_payment_test_refresh_row($db, $row, $issueInvoice);
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function finance_payment_test_refresh_row(PDO $db, array $row, bool $issueInvoice): array
{
    $paymentId = trim((string) ($row['barion_payment_id'] ?? ''));
    if ($paymentId === '' || !finance_payment_test_status()['barion_ready']) {
        return finance_payment_test_find($db, (int) $row['id']) ?? $row;
    }

    try {
        $remote = finance_payment_test_barion_state($paymentId);
    } catch (Throwable $e) {
        error_log('finance_payment_test_refresh_row: ' . $e->getMessage());
        finance_payment_test_mark_error($db, (int) $row['id'], (string) $row['status'], 'A Barion állapotlekérdezés sikertelen.');

        return finance_payment_test_find($db, (int) $row['id']) ?? $row;
    }

    if ($remote['error'] !== null) {
        finance_payment_test_mark_error($db, (int) $row['id'], (string) $row['status'], $remote['error']);

        return finance_payment_test_find($db, (int) $row['id']) ?? $row;
    }

    $mapped = finance_payment_test_map_status((string) $remote['status']);
    $current = (string) $row['status'];
    if ($current === 'succeeded' && $mapped === 'pending') {
        $mapped = 'succeeded';
    }

    $update = $db->prepare('
        UPDATE finance_payment_tests
        SET status = ?, barion_status = ?, last_error = NULL
        WHERE id = ?
    ');
    $update->execute([$mapped, $remote['status'], (int) $row['id']]);

    $fresh = finance_payment_test_find($db, (int) $row['id']) ?? $row;
    if (
        $issueInvoice
        && $mapped === 'succeeded'
        && (int) ($fresh['invoice_after_payment'] ?? 0) === 1
        && trim((string) ($fresh['invoice_number'] ?? '')) === ''
        && (string) ($fresh['invoice_state'] ?? '') !== 'preview'
    ) {
        finance_payment_test_issue($db, (int) $fresh['id'], (int) ($fresh['invoice_live'] ?? 0) === 1);
        $fresh = finance_payment_test_find($db, (int) $fresh['id']) ?? $fresh;
    }

    return $fresh;
}

/**
 * @return array<string, mixed>|null
 */
function finance_payment_test_find(PDO $db, int $id): ?array
{
    $stmt = $db->prepare('SELECT * FROM finance_payment_tests WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

/**
 * @return list<array<string, mixed>>
 */
function finance_payment_test_list(PDO $db): array
{
    $stmt = $db->query('
        SELECT id, payment_request_id, amount_ft, buyer_name, buyer_email, status, barion_status,
               invoice_state, invoice_number, invoice_pdf, created_at
        FROM finance_payment_tests
        ORDER BY id DESC
        LIMIT 40
    ');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return is_array($rows) ? $rows : [];
}

function finance_payment_test_status_label(string $status): string
{
    return match ($status) {
        'prepared' => 'Előkészítve',
        'pending' => 'Fizetés alatt',
        'succeeded' => 'Sikeres',
        'failed' => 'Sikertelen',
        'canceled' => 'Megszakítva',
        'expired' => 'Lejárt',
        'invoice_only' => 'Csak számla',
        default => $status !== '' ? $status : '–',
    };
}

function finance_payment_test_invoice_label(?string $state, ?string $number): string
{
    $number = trim((string) $number);
    if ($number !== '') {
        return $number;
    }

    return match ((string) $state) {
        'preview' => 'Előnézet',
        'pending' => 'Folyamatban',
        'failed' => 'Sikertelen',
        'done' => 'Kész',
        default => '–',
    };
}

function finance_payment_test_pdf_file(int $id): ?string
{
    $dir = finance_payment_test_pdf_dir();
    $path = $dir . DIRECTORY_SEPARATOR . $id . '.pdf';
    $realDir = realpath($dir);
    $realFile = is_file($path) ? realpath($path) : false;
    if ($realDir === false || $realFile === false) {
        return null;
    }
    $prefix = rtrim($realDir, '\\/') . DIRECTORY_SEPARATOR;
    if (!str_starts_with($realFile, $prefix)) {
        return null;
    }

    return $realFile;
}

/**
 * @param array{name: string, email: string, zip: string, city: string, address: string, tax_number: ?string} $buyer
 * @return array{payment_id: ?string, gateway_url: ?string, status: ?string, error: ?string}
 */
function finance_payment_test_barion_start(string $requestId, int $amount, array $buyer): array
{
    $payload = [
        'POSKey' => trim((string) BARION_POS_KEY),
        'PaymentType' => 'Immediate',
        'GuestCheckOut' => true,
        'FundingSources' => ['All'],
        'PaymentRequestId' => $requestId,
        'OrderNumber' => $requestId,
        'RedirectUrl' => finance_payment_test_absolute_url('finance/fizetes_teszt/vissza.php'),
        'CallbackUrl' => finance_payment_test_absolute_url('finance/fizetes_teszt/callback.php'),
        'Currency' => 'HUF',
        'Locale' => 'hu-HU',
        'PaymentWindow' => '00:30:00',
        'PayerHint' => $buyer['email'],
        'Transactions' => [[
            'POSTransactionId' => $requestId . '-1',
            'Payee' => trim((string) BARION_PAYEE_EMAIL),
            'Total' => $amount,
            'Comment' => 'Latinfo teszt tamogatas',
            'Items' => [[
                'Name' => 'Latinfo.hu tamogatas (teszt)',
                'Description' => 'Online fizetes teszt',
                'Quantity' => 1,
                'Unit' => 'db',
                'UnitPrice' => $amount,
                'ItemTotal' => $amount,
                'SKU' => 'LATINFO-TEST',
            ]],
        ]],
    ];
    $hint = mb_substr($buyer['name'], 0, 45);
    if (mb_strlen($hint) >= 2) {
        $payload['CardHolderNameHint'] = $hint;
    }
    if ($buyer['zip'] !== '' && $buyer['city'] !== '' && $buyer['address'] !== '') {
        $payload['BillingAddress'] = [
            'Country' => 'HU',
            'Region' => '',
            'City' => mb_substr($buyer['city'], 0, 100),
            'Zip' => $buyer['zip'],
            'Street' => mb_substr($buyer['address'], 0, 100),
            'Street2' => '',
            'Street3' => '',
            'FullName' => mb_substr($buyer['name'], 0, 45),
        ];
    }

    $response = finance_payment_test_http_json(
        'POST',
        FINANCE_PAYMENT_TEST_BARION_API . '/v2/Payment/Start',
        $payload
    );
    $body = $response['json'];
    $error = finance_payment_test_barion_error_text($body, $response['error']);
    $gateway = is_array($body) ? trim((string) ($body['GatewayUrl'] ?? '')) : '';
    $paymentId = is_array($body) ? trim((string) ($body['PaymentId'] ?? '')) : '';
    if ($error === null && !finance_payment_test_is_test_gateway($gateway)) {
        $error = 'A Barion nem a sandbox fizetőoldal címét adta vissza.';
        $gateway = '';
    }
    if ($error === null && !finance_payment_test_is_guid($paymentId)) {
        $error = 'A Barion nem adott fizetési azonosítót.';
    }

    return [
        'payment_id' => $error === null ? $paymentId : null,
        'gateway_url' => $error === null ? $gateway : null,
        'status' => is_array($body) ? trim((string) ($body['Status'] ?? '')) : null,
        'error' => $error,
    ];
}

/**
 * @return array{status: string, error: ?string}
 */
function finance_payment_test_barion_state(string $paymentId): array
{
    $query = http_build_query([
        'POSKey' => trim((string) BARION_POS_KEY),
        'PaymentId' => $paymentId,
    ]);
    $response = finance_payment_test_http_json(
        'GET',
        FINANCE_PAYMENT_TEST_BARION_API . '/v2/Payment/GetPaymentState?' . $query,
        null
    );
    $body = $response['json'];
    $error = finance_payment_test_barion_error_text($body, $response['error']);
    $status = is_array($body) ? trim((string) ($body['Status'] ?? '')) : '';
    if ($error === null && $status === '') {
        $error = 'A Barion nem adott állapotot.';
    }

    return ['status' => $status, 'error' => $error];
}

/**
 * @param array<string, mixed>|null $body
 */
function finance_payment_test_barion_error_text(?array $body, ?string $transportError): ?string
{
    if ($transportError !== null) {
        return $transportError;
    }
    if (!is_array($body)) {
        return 'A Barion válasza nem értelmezhető.';
    }
    $errors = $body['Errors'] ?? null;
    if (!is_array($errors) || $errors === []) {
        return null;
    }
    $parts = [];
    foreach ($errors as $error) {
        if (!is_array($error)) {
            continue;
        }
        $text = trim((string) ($error['Description'] ?? $error['Title'] ?? $error['ErrorCode'] ?? ''));
        if ($text !== '') {
            $parts[] = $text;
        }
    }

    return finance_payment_test_redact($parts === [] ? 'A Barion elutasította a kérést.' : implode(' ', $parts));
}

/**
 * @param array<string, mixed> $row
 * @return array{invoice_number: string, pdf: string, error: ?string}
 */
function finance_payment_test_szamlazz_create(array $row, bool $live): array
{
    $gross = (int) $row['amount_ft'];
    $parts = finance_payment_test_vat_parts($gross, finance_payment_test_vat_code());
    $paid = (string) $row['status'] === 'succeeded';
    $xml = finance_payment_test_invoice_xml($row, $parts, $paid, $live);

    $tmp = tempnam(sys_get_temp_dir(), 'szla');
    if ($tmp === false) {
        return ['invoice_number' => '', 'pdf' => '', 'error' => 'Az átmeneti számlafájl nem készült el.'];
    }
    $xmlFile = $tmp . '.xml';
    rename($tmp, $xmlFile);
    file_put_contents($xmlFile, $xml);

    try {
        $response = finance_payment_test_post_xml($xmlFile);
    } finally {
        @unlink($xmlFile);
    }

    return $response;
}

/**
 * @param array<string, mixed> $row
 * @param array{net: int, vat: int, gross: int, code: string} $parts
 */
function finance_payment_test_invoice_xml(array $row, array $parts, bool $paid, bool $live): string
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

    $settings = $add($root, 'beallitasok');
    $add($settings, 'szamlaagentkulcs', trim((string) SZAMLAZZ_AGENT_KEY));
    $add($settings, 'eszamla', 'true');
    $add($settings, 'szamlaLetoltes', 'true');
    $add($settings, 'valaszVerzio', '2');

    $today = date('Y-m-d');
    $header = $add($root, 'fejlec');
    $add($header, 'keltDatum', $today);
    $add($header, 'teljesitesDatum', $today);
    $add($header, 'fizetesiHataridoDatum', $today);
    $add($header, 'fizmod', $paid ? 'Bankkártya' : 'Átutalás');
    $add($header, 'penznem', 'HUF');
    $add($header, 'szamlaNyelve', 'hu');
    $note = 'Latinfo.hu online fizetés teszt. Kérés: ' . (string) $row['payment_request_id'];
    if ($parts['gross'] !== (int) $row['amount_ft']) {
        $note .= ' Áfa-kerekítés miatt a bizonylat végösszege ' . $parts['gross'] . ' Ft.';
    }
    $add($header, 'megjegyzes', $note);
    $add($header, 'rendelesSzam', (string) $row['payment_request_id']);
    $add($header, 'fizetve', $paid ? 'true' : 'false');
    if (!$live) {
        $add($header, 'elonezetpdf', 'true');
    }

    $seller = $add($root, 'elado');
    $bank = trim((string) (defined('SZAMLAZZ_SELLER_BANK') ? SZAMLAZZ_SELLER_BANK : ''));
    $account = trim((string) (defined('SZAMLAZZ_SELLER_ACCOUNT') ? SZAMLAZZ_SELLER_ACCOUNT : ''));
    if ($bank !== '' && $account !== '') {
        $add($seller, 'bank', $bank);
        $add($seller, 'bankszamlaszam', $account);
    }

    $buyer = $add($root, 'vevo');
    $add($buyer, 'nev', (string) $row['buyer_name']);
    $add($buyer, 'orszag', 'Magyarország');
    $add($buyer, 'irsz', (string) $row['buyer_zip']);
    $add($buyer, 'telepules', (string) $row['buyer_city']);
    $add($buyer, 'cim', (string) $row['buyer_address']);
    $add($buyer, 'email', (string) $row['buyer_email']);
    $add($buyer, 'sendEmail', 'false');
    $tax = trim((string) ($row['buyer_tax_number'] ?? ''));
    $add($buyer, 'adoalany', $tax !== '' ? '1' : '-1');
    if ($tax !== '') {
        $add($buyer, 'adoszam', $tax);
    }

    $items = $add($root, 'tetelek');
    $item = $add($items, 'tetel');
    $add($item, 'megnevezes', 'Latinfo.hu önkéntes támogatás (teszt)');
    $add($item, 'mennyiseg', '1.0');
    $add($item, 'mennyisegiEgyseg', 'db');
    $add($item, 'nettoEgysegar', finance_payment_test_money($parts['net']));
    $add($item, 'afakulcs', $parts['code']);
    $add($item, 'nettoErtek', finance_payment_test_money($parts['net']));
    $add($item, 'afaErtek', finance_payment_test_money($parts['vat']));
    $add($item, 'bruttoErtek', finance_payment_test_money($parts['gross']));

    return (string) $doc->saveXML();
}

/**
 * @return array{net: int, vat: int, gross: int, code: string}
 */
function finance_payment_test_vat_parts(int $grossFt, string $vatCode): array
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
function finance_payment_test_post_xml(string $xmlFile): array
{
    if (!function_exists('curl_init')) {
        return ['invoice_number' => '', 'pdf' => '', 'error' => 'A PHP cURL bővítmény hiányzik.'];
    }

    $headers = [];
    $ch = curl_init(FINANCE_PAYMENT_TEST_SZAMLAZZ_URL);
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

    $headerError = trim((string) ($headers['szlahu_error'] ?? ''));
    $headerCode = trim((string) ($headers['szlahu_error_code'] ?? ''));
    if ($headerError !== '' || ($headerCode !== '' && $headerCode !== '0')) {
        $message = $headerError !== '' ? $headerError : ('Szamlazz.hu hiba: ' . $headerCode);

        return ['invoice_number' => '', 'pdf' => '', 'error' => finance_payment_test_redact($message)];
    }

    if (str_starts_with($raw, '%PDF')) {
        $number = trim((string) ($headers['szlahu_szamlaszam'] ?? ''));

        return ['invoice_number' => $number, 'pdf' => $raw, 'error' => null];
    }

    $doc = new DOMDocument();
    if (@$doc->loadXML($raw) === false) {
        error_log('finance_payment_test_post_xml: nem XML és nem PDF válasz');

        return ['invoice_number' => '', 'pdf' => '', 'error' => 'A szamlazz.hu válasza nem értelmezhető.'];
    }

    $success = strtolower(finance_payment_test_xml_text($doc, 'sikeres'));
    $message = finance_payment_test_xml_text($doc, 'hibauzenet');
    $code = finance_payment_test_xml_text($doc, 'hibakod');
    if ($success !== 'true') {
        $text = $message !== '' ? $message : ($code !== '' ? ('Szamlazz.hu hiba: ' . $code) : 'A szamlazz.hu elutasította a bizonylatot.');

        return ['invoice_number' => '', 'pdf' => '', 'error' => finance_payment_test_redact($text)];
    }

    $pdf = base64_decode(finance_payment_test_xml_text($doc, 'pdf'), true);
    if ($pdf === false) {
        $pdf = base64_decode(finance_payment_test_xml_text($doc, 'szamlaPdf'), true);
    }
    if ($pdf === false) {
        $pdf = '';
    }

    return [
        'invoice_number' => finance_payment_test_xml_text($doc, 'szamlaszam'),
        'pdf' => $pdf,
        'error' => null,
    ];
}

function finance_payment_test_xml_text(DOMDocument $doc, string $local): string
{
    $nodes = $doc->getElementsByTagName($local);
    if ($nodes->length === 0) {
        return '';
    }

    return trim((string) $nodes->item(0)?->textContent);
}

/**
 * @param array<string, mixed>|null $payload
 * @return array{json: ?array<string, mixed>, error: ?string}
 */
function finance_payment_test_http_json(string $method, string $url, ?array $payload): array
{
    if (!function_exists('curl_init')) {
        return ['json' => null, 'error' => 'A PHP cURL bővítmény hiányzik.'];
    }
    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
    if ($host !== 'api.test.barion.com') {
        return ['json' => null, 'error' => 'A tesztoldal csak a Barion sandbox API-t hívhatja.'];
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return ['json' => null, 'error' => 'A Barion hívás nem indult el.'];
    }
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ];
    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
    curl_setopt_array($ch, $options);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    curl_close($ch);
    if ($errno !== 0 || !is_string($raw) || $raw === '') {
        return ['json' => null, 'error' => 'A Barion sandbox nem válaszolt.'];
    }
    try {
        $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        error_log('finance_payment_test_http_json: ' . $e->getMessage());

        return ['json' => null, 'error' => 'A Barion válasza nem értelmezhető.'];
    }

    return ['json' => is_array($decoded) ? $decoded : null, 'error' => null];
}

function finance_payment_test_store_pdf(int $id, string $binary): ?string
{
    if ($binary === '' || !str_starts_with($binary, '%PDF')) {
        return null;
    }
    $dir = finance_payment_test_pdf_dir();
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        error_log('finance_payment_test_store_pdf: a mappa nem hozható létre');

        return null;
    }
    $name = $id . '.pdf';
    $path = $dir . DIRECTORY_SEPARATOR . $name;
    if (file_put_contents($path, $binary) === false) {
        return null;
    }

    return $name;
}

function finance_payment_test_pdf_dir(): string
{
    $upload = defined('UPLOAD_PATH') ? (string) UPLOAD_PATH : (BASE_PATH . '/nextgen/uploads/szamlak');

    return dirname($upload) . DIRECTORY_SEPARATOR . 'payment_tests';
}

function finance_payment_test_mark_error(PDO $db, int $id, string $status, string $message): void
{
    $stmt = $db->prepare('UPDATE finance_payment_tests SET status = ?, last_error = ? WHERE id = ?');
    $stmt->execute([$status, finance_payment_test_redact($message), $id]);
}

function finance_payment_test_save_invoice_failure(PDO $db, int $id, string $message): void
{
    $stmt = $db->prepare("
        UPDATE finance_payment_tests
        SET invoice_state = 'failed', invoice_error = ?
        WHERE id = ? AND invoice_number IS NULL
    ");
    $stmt->execute([finance_payment_test_redact($message), $id]);
}

function finance_payment_test_map_status(string $barionStatus): string
{
    return match ($barionStatus) {
        'Succeeded' => 'succeeded',
        'Failed', 'PartiallySucceeded' => 'failed',
        'Canceled' => 'canceled',
        'Expired' => 'expired',
        default => 'pending',
    };
}

function finance_payment_test_is_guid(string $value): bool
{
    return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
}

function finance_payment_test_is_test_gateway(string $url): bool
{
    $parts = parse_url($url);
    if (!is_array($parts)) {
        return false;
    }
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));

    return $scheme === 'https' && $host === FINANCE_PAYMENT_TEST_BARION_GATEWAY_HOST;
}

function finance_payment_test_absolute_url(string $path): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '' || preg_match('/[\s\/\\\\]/', $host) === 1) {
        $configured = defined('APP_PUBLIC_URL') ? rtrim((string) APP_PUBLIC_URL, '/') : '';
        $origin = $configured !== '' ? $configured : 'http://localhost';
    } else {
        $origin = ($https ? 'https' : 'http') . '://' . $host;
    }

    return $origin . nextgen_url($path);
}

function finance_payment_test_redact(string $text): string
{
    $secrets = [
        trim((string) (defined('BARION_POS_KEY') ? BARION_POS_KEY : '')),
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

function finance_payment_test_money(int $amount): string
{
    return number_format($amount, 1, '.', '');
}

function finance_payment_test_has_address(array $row): bool
{
    return trim((string) ($row['buyer_zip'] ?? '')) !== ''
        && trim((string) ($row['buyer_city'] ?? '')) !== ''
        && trim((string) ($row['buyer_address'] ?? '')) !== '';
}
