<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/dance_school_import.php';
requireLogin();

$db = getDb();
dance_schools_ensure_schema($db);

$backUrl = events_url('tanciskolak_admin.php');
$selfUrl = events_url('tanciskola_import.php');
if (!isSuperadmin()) {
    flash('error', 'Nincs jogosultságod ehhez a művelethez.');
    redirect($backUrl);
}

$sessionKey = dance_school_import_session_key();
$hiba = '';

/**
 * @param array<string, mixed>|null $file
 */
$uploadError = static function (?array $file): string {
    if ($file === null || !isset($file['error'])) {
        return 'Válassz egy xlsx fájlt.';
    }
    $code = (int) $file['error'];
    if ($code === UPLOAD_ERR_NO_FILE) {
        return 'Válassz egy xlsx fájlt.';
    }
    if ($code !== UPLOAD_ERR_OK) {
        return 'A feltöltés sikertelen.';
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return 'A feltöltés sikertelen.';
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > 2_000_000) {
        return 'A fájl legfeljebb 2 MB lehet.';
    }
    $name = (string) ($file['name'] ?? '');
    if (!preg_match('/\.xlsx$/i', $name)) {
        return 'Csak xlsx fájl tölthető fel.';
    }
    $head = (string) file_get_contents($tmp, false, null, 0, 4);
    if ($head !== "PK\x03\x04") {
        return 'A fájl nem olvasható Excel (xlsx).';
    }

    return '';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dance_school_import_cancel'])) {
    csrf_require('dance_school_import', '_csrf', $selfUrl);
    unset($_SESSION[$sessionKey]);
    redirect($selfUrl);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dance_school_import_apply'])) {
    csrf_require('dance_school_import', '_csrf', $selfUrl);
    $stored = $_SESSION[$sessionKey] ?? null;
    $token = (string) ($_POST['import_token'] ?? '');
    $valid = is_array($stored)
        && is_string($stored['token'] ?? null)
        && hash_equals($stored['token'], $token)
        && (time() - (int) ($stored['at'] ?? 0)) < 1800
        && is_array($stored['records'] ?? null);
    if (!$valid) {
        unset($_SESSION[$sessionKey]);
        flash('error', 'Az előnézet lejárt. Töltsd fel újra a fájlt.');
        redirect($selfUrl);
    }
    @set_time_limit(120);
    $result = dance_school_import_apply($db, $stored['records']);
    unset($_SESSION[$sessionKey]);
    $summary = 'XLSX import kész. Új: ' . (int) $result['created']
        . ', üres mező kitöltve: ' . (int) $result['filled']
        . ', változatlan: ' . (int) $result['unchanged']
        . ', kihagyva: ' . (int) $result['skipped']
        . ', hiba: ' . (int) $result['errors'] . '.';
    if ((int) $result['errors'] > 0) {
        $extra = $result['messages'] !== [] ? ' ' . implode(' ', array_slice($result['messages'], 0, 3)) : '';
        flash('error', $summary . $extra);
    } else {
        flash('success', $summary);
    }
    redirect($backUrl);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dance_school_import_preview'])) {
    csrf_require('dance_school_import', '_csrf', $selfUrl);
    $file = isset($_FILES['xlsx']) && is_array($_FILES['xlsx']) ? $_FILES['xlsx'] : null;
    $hiba = $uploadError($file);
    if ($hiba === '' && is_array($file)) {
        $parsed = dance_school_import_records_from_xlsx((string) $file['tmp_name']);
        if ($parsed['error'] !== '') {
            $hiba = $parsed['error'];
        } else {
            $_SESSION[$sessionKey] = [
                'token' => bin2hex(random_bytes(16)),
                'at' => time(),
                'sheet' => $parsed['sheet'],
                'records' => $parsed['records'],
            ];
            redirect($selfUrl);
        }
    }
}

$stored = $_SESSION[$sessionKey] ?? null;
$preview = null;
if (is_array($stored) && is_array($stored['records'] ?? null) && (time() - (int) ($stored['at'] ?? 0)) < 1800) {
    try {
        $preview = dance_school_import_plan($db, $stored['records']);
        $preview['sheet'] = (string) ($stored['sheet'] ?? '');
        $preview['token'] = (string) ($stored['token'] ?? '');
    } catch (Throwable $ex) {
        error_log('tanciskola_import preview: ' . $ex->getMessage());
        $hiba = 'Az előnézet nem készíthető el.';
        unset($_SESSION[$sessionKey]);
    }
} elseif (is_array($stored)) {
    unset($_SESSION[$sessionKey]);
}

$pageTitle = 'Tánciskola import';
$mainContentClass = 'main-content main-content--fullwidth';
require_once dirname(__DIR__) . '/partials/header.php';

$counts = is_array($preview) ? ($preview['counts'] ?? []) : [];
$canApply = is_array($preview) && ((int) ($counts['create'] ?? 0) + (int) ($counts['fill'] ?? 0)) > 0;
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>
<?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>

<div class="card events-admin-card" style="margin-bottom:1rem;">
    <div class="events-list-head">
        <div class="events-list-head__start">
            <h1 class="events-list-title card-title" style="margin:0;">Tánciskola XLSX import</h1>
            <p class="help" style="margin:0.35rem 0 0;">A „Tánciskolák” lap kerül be. Új iskola Import státuszt kap, a nyilvános listán nem jelenik meg. Meglévő iskolánál csak az üres mező töltődik. Az „Igen” stílus kínálat lesz, a „?” és a „Nem” nem. Perjeles összevont név kimarad.</p>
        </div>
        <div class="events-list-actions">
            <a href="<?= h($backUrl) ?>" class="btn btn-secondary">← Tánciskolák listája</a>
        </div>
    </div>
    <form method="post" action="<?= h($selfUrl) ?>" enctype="multipart/form-data" class="events-admin-form" style="margin-top:1rem;">
        <?= csrf_input('dance_school_import') ?>
        <div class="events-filters-grid">
            <div class="events-filter-field">
                <label class="events-filter-label" for="dance-school-xlsx">Excel fájl (.xlsx)</label>
                <input class="events-filter-input" type="file" name="xlsx" id="dance-school-xlsx" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
            </div>
        </div>
        <div class="events-list-actions" style="margin-top:0.75rem;">
            <button type="submit" name="dance_school_import_preview" value="1" class="btn btn-primary">Előnézet</button>
        </div>
    </form>
</div>

<?php if (is_array($preview)): ?>
<div class="card events-admin-card">
    <p class="help" style="margin-top:0;">
        Lap: <?= h((string) ($preview['sheet'] ?? '')) ?>.
        Új: <?= (int) ($counts['create'] ?? 0) ?>,
        üres mező: <?= (int) ($counts['fill'] ?? 0) ?>,
        nincs teendő: <?= (int) ($counts['unchanged'] ?? 0) ?>,
        összevont név: <?= (int) ($counts['skip_combined'] ?? 0) ?>,
        bizonytalan: <?= (int) ($counts['skip_uncertain'] ?? 0) ?>,
        hibás: <?= (int) ($counts['invalid'] ?? 0) ?>.
    </p>
    <div class="table-wrap events-admin-table-wrap">
        <table class="sortable-table events-admin-table">
            <thead>
                <tr>
                    <th>Sor</th>
                    <th>Név</th>
                    <th>Művelet</th>
                    <th>Egyezés</th>
                    <th>Változás</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($preview['rows'] as $row): ?>
                    <?php
                    if (!is_array($row)) {
                        continue;
                    }
                    $action = (string) ($row['action'] ?? '');
                    $schoolId = (int) ($row['school_id'] ?? 0);
                    $matched = trim((string) ($row['matched_name'] ?? ''));
                    $changes = is_array($row['changes'] ?? null) ? $row['changes'] : [];
                    $warnings = is_array($row['warnings'] ?? null) ? $row['warnings'] : [];
                    $changeText = implode('; ', array_map('strval', $changes));
                    if ($warnings !== []) {
                        $changeText = trim($changeText . ($changeText !== '' ? ' ' : '') . implode(' ', array_map('strval', $warnings)));
                    }
                    ?>
                    <tr>
                        <td><?= (int) ($row['line'] ?? 0) ?></td>
                        <td><?= h((string) ($row['name'] ?? '')) ?></td>
                        <td><?= h(dance_school_import_action_label($action)) ?></td>
                        <td>
                            <?php if ($schoolId > 0): ?>
                                <a href="<?= h(events_url('tanciskola_szerkeszt.php?id=') . $schoolId) ?>"><?= h($matched !== '' ? $matched : ('#' . $schoolId)) ?></a>
                            <?php elseif ($matched !== ''): ?>
                                <?= h($matched) ?>
                            <?php else: ?>
                                <?= h((string) ($row['reason'] ?? '')) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= h($changeText !== '' ? $changeText : (string) ($row['reason'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <form method="post" action="<?= h($selfUrl) ?>" style="margin-top:1rem;">
        <?= csrf_input('dance_school_import') ?>
        <input type="hidden" name="import_token" value="<?= h((string) ($preview['token'] ?? '')) ?>">
        <div class="events-list-actions">
            <?php if ($canApply): ?>
                <button type="submit" name="dance_school_import_apply" value="1" class="btn btn-primary">Import végrehajtása</button>
            <?php endif; ?>
            <button type="submit" name="dance_school_import_cancel" value="1" class="btn btn-secondary">Előnézet elvetése</button>
        </div>
    </form>
</div>
<?php endif; ?>
<?php
require_once dirname(__DIR__) . '/partials/footer.php';
