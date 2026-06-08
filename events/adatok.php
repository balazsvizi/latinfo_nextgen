<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/nextgen/includes/auth.php';
require_once __DIR__ . '/lib/data_tables.php';
requireSuperadmin();

$db = getDb();
$hiba = '';
$purgeConfirm = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('events_adatok')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet. Töltsd újra az oldalt.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $table = trim((string) ($_POST['table'] ?? ''));

        if ($action === 'purge_cancel') {
            unset($_SESSION['events_data_purge']);
            redirect(events_url('adatok.php'));
        }

        if ($action === 'purge_preview') {
            if (!events_data_table_is_allowed($table) || !db_table_exists($db, $table)) {
                $hiba = 'Érvénytelen vagy nem létező tábla.';
            } else {
                try {
                    $q = events_data_quote_table($table);
                    $cnt = (int) $db->query('SELECT COUNT(*) FROM ' . $q)->fetchColumn();
                    $_SESSION['events_data_purge'] = [
                        'table' => $table,
                        'row_count' => $cnt,
                        'token' => bin2hex(random_bytes(16)),
                    ];
                    redirect(events_url('adatok.php?purge=1'));
                } catch (Throwable $e) {
                    error_log('events adatok purge_preview: ' . $e->getMessage());
                    $hiba = 'Nem sikerült előkészíteni az ürítést.';
                }
            }
        } elseif ($action === 'purge_execute') {
            $token = (string) ($_POST['purge_token'] ?? '');
            $password = trim((string) ($_POST['confirm_password'] ?? ''));
            $sess = $_SESSION['events_data_purge'] ?? null;
            if (!is_array($sess) || ($sess['table'] ?? '') !== $table || !isset($sess['token']) || !hash_equals((string) $sess['token'], $token)) {
                $hiba = 'A megerősítés érvénytelen vagy lejárt. Indítsd újra az ürítést.';
                unset($_SESSION['events_data_purge']);
            } elseif (!events_data_table_is_allowed($table) || !db_table_exists($db, $table)) {
                $hiba = 'Érvénytelen vagy nem létező tábla.';
                unset($_SESSION['events_data_purge']);
            } elseif ($password === '') {
                $hiba = 'Add meg a megerősítő jelszót.';
            } elseif (!hash_equals((string) ($sess['row_count'] ?? ''), $password)) {
                $hiba = 'Hibás megerősítő jelszó.';
            } else {
                try {
                    $deleted = events_data_truncate_table($db, $table);
                    unset($_SESSION['events_data_purge']);
                    $meta = events_data_tables_registry()[$table];
                    rendszer_log('adatok', null, 'Tábla ürítve', $table . ': ' . $deleted . ' sor');
                    flash('success', 'Ürítve: „' . ($meta['label'] ?? $table) . '” (' . $table . ') – ' . $deleted . ' sor törölve.');
                    redirect(events_url('adatok.php'));
                } catch (Throwable $e) {
                    error_log('events adatok purge_execute: ' . $e->getMessage());
                    $hiba = 'Ürítési hiba történt. Lehet, hogy más táblákra hivatkozó adatok blokkolják.';
                }
            }
        }
    }
}

if (isset($_GET['purge']) && (string) $_GET['purge'] === '1') {
    $sess = $_SESSION['events_data_purge'] ?? null;
    if (is_array($sess) && isset($sess['table'], $sess['token']) && events_data_table_is_allowed((string) $sess['table'])) {
        $tbl = (string) $sess['table'];
        $purgeConfirm = [
            'table' => $tbl,
            'label' => events_data_tables_registry()[$tbl]['label'] ?? $tbl,
            'token' => (string) $sess['token'],
        ];
    } else {
        unset($_SESSION['events_data_purge']);
    }
}

$overview = events_data_tables_overview($db);
$grouped = [];
foreach ($overview as $row) {
    $grouped[$row['group']][] = $row;
}

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Adatok';
require_once dirname(__DIR__) . '/nextgen/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>
<?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>

<div class="card events-admin-card events-data-admin">
    <div class="events-list-head">
        <h2 class="events-list-title">Adattáblák</h2>
        <div class="events-list-actions">
            <a href="<?= h(events_url('events_admin.php')) ?>" class="btn btn-secondary">Események listája</a>
        </div>
    </div>

    <p class="help events-data-admin__intro">Az esemény modul adattáblái és rekordszámaik. Az ürítés visszavonhatatlan: a tábla minden sora törlődik. Csak superadmin láthatja ezt az oldalt.</p>

    <?php if ($purgeConfirm !== null): ?>
        <div class="events-data-purge-confirm" role="region" aria-labelledby="events-data-purge-title">
            <h3 class="events-data-purge-confirm__title" id="events-data-purge-title">Tábla ürítése</h3>
            <p class="events-data-purge-confirm__target">
                <strong><?= h($purgeConfirm['label']) ?></strong>
                <code class="events-data-purge-confirm__code"><?= h($purgeConfirm['table']) ?></code>
            </p>
            <p class="help events-data-purge-confirm__warn">A művelet visszavonhatatlan. Add meg a megerősítő jelszót a folytatáshoz.</p>
            <form method="post" class="events-data-purge-confirm__form">
                <?= csrf_input('events_adatok') ?>
                <input type="hidden" name="action" value="purge_execute">
                <input type="hidden" name="table" value="<?= h($purgeConfirm['table']) ?>">
                <input type="hidden" name="purge_token" value="<?= h($purgeConfirm['token']) ?>">
                <div class="form-group">
                    <label for="confirm_password">Megerősítő jelszó</label>
                    <input type="password" id="confirm_password" name="confirm_password" required autocomplete="off" inputmode="numeric">
                </div>
                <div class="toolbar">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Biztosan üríted ezt a táblát? A művelet nem vonható vissza.');">Tábla ürítése</button>
                </div>
            </form>
            <form method="post" class="events-data-purge-cancel-form">
                <?= csrf_input('events_adatok') ?>
                <input type="hidden" name="action" value="purge_cancel">
                <button type="submit" class="btn btn-secondary">Mégse</button>
            </form>
        </div>
    <?php endif; ?>

    <?php foreach ($grouped as $groupName => $rows): ?>
        <section class="events-data-group">
            <h3 class="events-data-group__title"><?= h($groupName) ?></h3>
            <div class="table-wrap events-admin-table-wrap">
                <table class="events-admin-table events-data-table">
                    <thead>
                        <tr>
                            <th scope="col">Tábla</th>
                            <th scope="col">Megnevezés</th>
                            <th scope="col" class="events-data-table__count-col">Rekordok</th>
                            <th scope="col" class="events-data-table__action-col">Művelet</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr class="<?= $row['exists'] ? '' : 'events-data-table__row--missing' ?>">
                                <td><code><?= h($row['table']) ?></code></td>
                                <td><?= h($row['label']) ?></td>
                                <td class="events-data-table__count-col">
                                    <?php if (!$row['exists']): ?>
                                        <span class="events-data-table__missing">— nincs telepítve</span>
                                    <?php elseif ($row['count'] === null): ?>
                                        <span class="events-data-table__missing">?</span>
                                    <?php else: ?>
                                        <span class="events-data-table__count"><?= (int) $row['count'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="events-data-table__action-col">
                                    <?php if ($row['exists']): ?>
                                        <form method="post" class="events-data-purge-preview-form">
                                            <?= csrf_input('events_adatok') ?>
                                            <input type="hidden" name="action" value="purge_preview">
                                            <input type="hidden" name="table" value="<?= h($row['table']) ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Ürítés…</button>
                                        </form>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endforeach; ?>
</div>

<?php require_once dirname(__DIR__) . '/nextgen/partials/footer.php'; ?>
