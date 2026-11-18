<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../nextgen/core/database.php';
require_once __DIR__ . '/../../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../../nextgen/includes/functions.php';
require_once __DIR__ . '/../lib/payment_test.php';

requireLogin();

$db = getDb();
finance_payment_test_ensure_schema($db);

$pageUrl = nextgen_url('finance/fizetes_teszt/');
$error = '';
$posted = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require('finance_payment_test', '_csrf', $pageUrl);
    $action = (string) ($posted['action'] ?? '');

    try {
        if ($action === 'szamlazz_save' || $action === 'szamlazz_test') {
            szamlazz_save_from_post($db, $posted);
            if ($action === 'szamlazz_test') {
                $check = szamlazz_connection_test($db);
                flash($check['ok'] ? 'success' : 'error', $check['message']);
            } else {
                flash('success', 'A szamlazz.hu beállítások mentve.');
            }
            redirect($pageUrl);
        }

        if ($action === 'start') {
            $buyer = finance_payment_test_buyer_from_post($posted, !empty($posted['invoice_after']));
            $amount = finance_payment_test_amount_from_post($posted);
            $result = finance_payment_test_start(
                $db,
                $buyer,
                $amount,
                !empty($posted['invoice_after']),
                !empty($posted['invoice_live'])
            );
            if ($result['gateway_url'] !== null) {
                redirect($result['gateway_url']);
            }
            flash('error', $result['error'] ?? 'A fizetés indítása sikertelen.');
            redirect($pageUrl . '?id=' . $result['id']);
        }

        if ($action === 'invoice_only') {
            $buyer = finance_payment_test_buyer_from_post($posted, true);
            $amount = finance_payment_test_amount_from_post($posted);
            $result = finance_payment_test_invoice_only($db, $buyer, $amount, !empty($posted['invoice_live']));
            if ($result['error'] !== null) {
                flash('error', $result['error']);
            } else {
                flash('success', !empty($posted['invoice_live'])
                    ? 'A teszt számla elkészült.'
                    : 'Az előnézeti PDF elkészült. A szamlazz.hu nem rögzített bizonylatot.');
            }
            redirect($pageUrl . '?id=' . $result['id']);
        }

        if ($action === 'invoice_preview' || $action === 'invoice_live' || $action === 'sync_row') {
            $id = (int) ($posted['id'] ?? 0);
            if ($id < 1) {
                throw new InvalidArgumentException('Hiányzó tesztazonosító.');
            }
            if ($action === 'sync_row') {
                $row = finance_payment_test_sync_id($db, $id, true);
                flash($row === null ? 'error' : 'success', $row === null ? 'A tétel nem található.' : 'Állapot frissítve.');
            } else {
                $live = $action === 'invoice_live';
                $issued = finance_payment_test_issue($db, $id, $live);
                if ($issued['error'] !== null) {
                    flash('error', $issued['error']);
                } else {
                    flash('success', $live ? 'A teszt számla elkészült.' : 'Az előnézeti PDF elkészült.');
                }
            }
            redirect($pageUrl . '?id=' . $id);
        }

        throw new InvalidArgumentException('Ismeretlen művelet.');
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('finance fizetes teszt: ' . $e->getMessage());
        $error = 'A művelet sikertelen.';
    }
}

$setup = finance_payment_test_status();
$szamlazzForm = szamlazz_settings($db);
unset($szamlazzForm['agent_key']);
$szamlazzVat = (string) ($posted['szamlazz_vat'] ?? $szamlazzForm['vat_code']);
$szamlazzBank = (string) ($posted['szamlazz_bank'] ?? $szamlazzForm['seller_bank']);
$szamlazzAccount = (string) ($posted['szamlazz_account'] ?? $szamlazzForm['seller_account']);
$szamlazzSourceLabel = match ($setup['szamlazz_source']) {
    'database' => 'Agent kulcs mentve',
    'config' => 'Agent kulcs a config.local.php fájlból',
    default => 'hiányzik az Agent kulcs',
};
$focusId = (int) ($_GET['id'] ?? 0);
$focus = $focusId > 0 ? finance_payment_test_find($db, $focusId) : null;
$rows = finance_payment_test_list($db);
$localhostCallback = str_contains($setup['callback_url'], '://localhost')
    || str_contains($setup['callback_url'], '://127.0.0.1')
    || str_contains($setup['callback_url'], '.test/');

$pageTitle = 'Online fizetés teszt';
require_once __DIR__ . '/../../partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p class="alert alert-error"><?= h($error) ?></p><?php endif; ?>

<div class="card">
    <h2>Barion sandbox és szamlazz.hu teszt</h2>
    <p>Ez a felület csak próbához van. A kezdőoldali támogatás gomb változatlan. A fizetés a Barion tesztkörnyezetébe megy, valós kártya és valós pénz nélkül.</p>
    <ul>
        <li>Barion: <?= $setup['barion_ready'] ? 'sandbox kulcs beállítva' : 'hiányzik a POSKey vagy a tárca e-mailje' ?><?php if ($setup['payee'] !== ''): ?> (<?= h($setup['payee']) ?>)<?php endif; ?></li>
        <?php if (!$setup['barion_key_valid']): ?><li>A POSKey nem GUID. A tesztbolt kulcsa a secure.test.barion.com oldalon van, nem az éles fiókban.</li><?php endif; ?>
        <li>Szamlazz.hu: <?= h($szamlazzSourceLabel) ?><?php if ($setup['szamlazz_hint'] !== ''): ?> (vége <?= h($setup['szamlazz_hint']) ?>)<?php endif; ?>, áfakód <?= h($setup['vat_code']) ?><?= $setup['seller_bank'] ? ', eladói bankszámla megadva' : '' ?>.</li>
    </ul>
    <p>Sikeres tesztkártya: 4444 8888 8888 5559, bármilyen jövőbeli lejárat, bármilyen háromjegyű CVC. Elutasításhoz: 4444 8888 8888 3331.</p>
    <?php if ($localhostCallback): ?>
        <p>A visszatérő oldal frissíti a fizetés állapotát. A Barion szervere a helyi címet (<?= h($setup['callback_url']) ?>) nem éri el, ezért a háttérértesítés csak nyilvános HTTPS címen működik.</p>
    <?php endif; ?>
    <p>A számla alapból előnézet: a szamlazz.hu nem rögzít bizonylatot. A valódi számla jelölő a kulcshoz tartozó fiókban valós bizonylatot hoz létre, ezért tesztfiók kulcsát használd.</p>
</div>

<div class="card">
    <h2>Szamlazz.hu kapcsolat</h2>
    <p>Az Agent kulcs a Szamlazz.hu fiókban, a Beállítások / Számla Agent oldalon van. A mentett kulcs felülírja a config.local.php értékét. Üresen hagyva a már mentett kulcs megmarad.</p>
    <?php if ($szamlazzForm['last_check_at'] !== null): ?>
        <p>Utolsó ellenőrzés <?= h((string) $szamlazzForm['last_check_at']) ?>: <?= $szamlazzForm['last_check_ok'] ? 'sikeres' : 'sikertelen' ?>. <?= h($szamlazzForm['last_check_message']) ?></p>
    <?php endif; ?>
    <form method="post" action="<?= h($pageUrl) ?>">
        <?= csrf_input('finance_payment_test') ?>
        <div class="form-group">
            <label for="szamlazz_agent_key">Agent kulcs</label>
            <input type="password" id="szamlazz_agent_key" name="szamlazz_agent_key" maxlength="200" autocomplete="new-password" value="">
        </div>
        <div class="form-group">
            <label for="szamlazz_vat">Áfakód</label>
            <select id="szamlazz_vat" name="szamlazz_vat">
                <option value="AAM" <?= $szamlazzVat === 'AAM' ? 'selected' : '' ?>>AAM (alanyi adómentes)</option>
                <option value="27" <?= $szamlazzVat === '27' ? 'selected' : '' ?>>27%</option>
            </select>
        </div>
        <div class="form-group">
            <label for="szamlazz_bank">Eladó bankja</label>
            <input type="text" id="szamlazz_bank" name="szamlazz_bank" maxlength="80" value="<?= h($szamlazzBank) ?>">
        </div>
        <div class="form-group">
            <label for="szamlazz_account">Eladó bankszámlaszáma</label>
            <input type="text" id="szamlazz_account" name="szamlazz_account" maxlength="32" inputmode="numeric" placeholder="12345678-12345678" value="<?= h($szamlazzAccount) ?>">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-secondary" name="action" value="szamlazz_save">Mentés</button>
            <button type="submit" class="btn btn-primary" name="action" value="szamlazz_test">Mentés és kapcsolat ellenőrzése</button>
        </div>
    </form>
    <p>Az ellenőrzés előnézeti PDF-et kér. A szamlazz.hu nem rögzít bizonylatot, és nem küld e-mailt.</p>
</div>

<div class="card">
    <h2>Új próba</h2>
    <form method="post" action="<?= h($pageUrl) ?>">
        <?= csrf_input('finance_payment_test') ?>
        <div class="form-group">
            <label for="amount_ft">Összeg (Ft)</label>
            <input type="number" id="amount_ft" name="amount_ft" min="100" max="100000" step="1" required value="<?= h((string) ($posted['amount_ft'] ?? '5000')) ?>">
        </div>
        <div class="form-group">
            <label for="buyer_name">Név</label>
            <input type="text" id="buyer_name" name="buyer_name" maxlength="120" required value="<?= h((string) ($posted['buyer_name'] ?? '')) ?>">
        </div>
        <div class="form-group">
            <label for="buyer_email">E-mail</label>
            <input type="email" id="buyer_email" name="buyer_email" maxlength="190" required value="<?= h((string) ($posted['buyer_email'] ?? '')) ?>">
        </div>
        <div class="form-group">
            <label for="buyer_zip">Irányítószám</label>
            <input type="text" id="buyer_zip" name="buyer_zip" maxlength="4" inputmode="numeric" value="<?= h((string) ($posted['buyer_zip'] ?? '')) ?>">
        </div>
        <div class="form-group">
            <label for="buyer_city">Város</label>
            <input type="text" id="buyer_city" name="buyer_city" maxlength="100" value="<?= h((string) ($posted['buyer_city'] ?? '')) ?>">
        </div>
        <div class="form-group">
            <label for="buyer_address">Cím</label>
            <input type="text" id="buyer_address" name="buyer_address" maxlength="200" value="<?= h((string) ($posted['buyer_address'] ?? '')) ?>">
        </div>
        <div class="form-group">
            <label for="buyer_tax_number">Adószám</label>
            <input type="text" id="buyer_tax_number" name="buyer_tax_number" maxlength="20" placeholder="12345678-1-12" value="<?= h((string) ($posted['buyer_tax_number'] ?? '')) ?>">
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="invoice_after" value="1" <?= !empty($posted['invoice_after']) ? 'checked' : '' ?>> Sikeres fizetés után számla vagy előnézet</label>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="invoice_live" value="1" <?= !empty($posted['invoice_live']) ? 'checked' : '' ?>> Valódi számla (a szamlazz.hu fiókban rögzül)</label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary" name="action" value="start" <?= $setup['barion_ready'] ? '' : 'disabled' ?>>Barion tesztfizetés</button>
            <button type="submit" class="btn btn-secondary" name="action" value="invoice_only" <?= $setup['szamlazz_ready'] ? '' : 'disabled' ?>>Csak számla teszt</button>
        </div>
    </form>
</div>

<?php if (is_array($focus)): ?>
<div class="card">
    <h2>Próba #<?= (int) $focus['id'] ?></h2>
    <p>
        <?= h(finance_payment_test_status_label((string) $focus['status'])) ?>
        <?php if (trim((string) ($focus['barion_status'] ?? '')) !== ''): ?>
            (Barion: <?= h((string) $focus['barion_status']) ?>)
        <?php endif; ?>
        · <?= number_format((int) $focus['amount_ft'], 0, ',', ' ') ?> Ft
        · <?= h((string) $focus['buyer_name']) ?>
    </p>
    <?php if (trim((string) ($focus['last_error'] ?? '')) !== ''): ?>
        <p class="alert alert-error"><?= h((string) $focus['last_error']) ?></p>
    <?php endif; ?>
    <?php if (trim((string) ($focus['invoice_error'] ?? '')) !== ''): ?>
        <p class="alert alert-error"><?= h((string) $focus['invoice_error']) ?></p>
    <?php endif; ?>
    <p>
        Számla: <?= h(finance_payment_test_invoice_label(
            isset($focus['invoice_state']) ? (string) $focus['invoice_state'] : null,
            isset($focus['invoice_number']) ? (string) $focus['invoice_number'] : null
        )) ?>
        <?php if (finance_payment_test_pdf_file((int) $focus['id']) !== null): ?>
            · <a href="<?= h(nextgen_url('finance/fizetes_teszt/letoltes.php?id=') . (int) $focus['id']) ?>">PDF letöltése</a>
        <?php endif; ?>
    </p>
    <form method="post" action="<?= h($pageUrl) ?>" class="form-actions">
        <?= csrf_input('finance_payment_test') ?>
        <input type="hidden" name="id" value="<?= (int) $focus['id'] ?>">
        <?php if (trim((string) ($focus['barion_payment_id'] ?? '')) !== ''): ?>
            <button type="submit" class="btn btn-secondary" name="action" value="sync_row">Állapot frissítése</button>
        <?php endif; ?>
        <?php if (in_array((string) $focus['status'], ['succeeded', 'invoice_only'], true) && trim((string) ($focus['invoice_number'] ?? '')) === '' && $setup['szamlazz_ready']): ?>
            <button type="submit" class="btn btn-secondary" name="action" value="invoice_preview">Előnézeti PDF</button>
            <button type="submit" class="btn btn-primary" name="action" value="invoice_live">Valódi számla</button>
        <?php endif; ?>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <h2>Korábbi próbák</h2>
    <?php if ($rows === []): ?>
        <p>Még nincs tesztbefizetés.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Időpont</th>
                        <th>Név</th>
                        <th>Összeg</th>
                        <th>Fizetés</th>
                        <th>Számla</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><a href="<?= h($pageUrl . '?id=' . (int) $row['id']) ?>"><?= (int) $row['id'] ?></a></td>
                            <td><?= h((string) $row['created_at']) ?></td>
                            <td><?= h((string) $row['buyer_name']) ?></td>
                            <td><?= number_format((int) $row['amount_ft'], 0, ',', ' ') ?></td>
                            <td><?= h(finance_payment_test_status_label((string) $row['status'])) ?></td>
                            <td>
                                <?= h(finance_payment_test_invoice_label(
                                    isset($row['invoice_state']) ? (string) $row['invoice_state'] : null,
                                    isset($row['invoice_number']) ? (string) $row['invoice_number'] : null
                                )) ?>
                                <?php if (trim((string) ($row['invoice_pdf'] ?? '')) !== ''): ?>
                                    <a href="<?= h(nextgen_url('finance/fizetes_teszt/letoltes.php?id=') . (int) $row['id']) ?>">PDF</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
