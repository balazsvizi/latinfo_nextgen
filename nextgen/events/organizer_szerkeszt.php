<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/admin_event_filters.php';
require_once __DIR__ . '/lib/event_edit_stats.php';
require_once __DIR__ . '/lib/organizer_finance.php';
requireLogin();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Hiányzó azonosító.');
    redirect(events_url('organizers.php'));
}

$db = getDb();
events_organizer_finance_ensure_schema($db);
$stmt = $db->prepare('SELECT `id`, `name`, `finance_ticket_percent`, `finance_fix_amount` FROM `events_organizers` WHERE `id` = ? LIMIT 1');
$stmt->execute([$id]);
$organizer = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$organizer) {
    flash('error', 'Szervező nem található.');
    redirect(events_url('organizers.php'));
}

$hiba = '';
$name = (string) ($organizer['name'] ?? '');
$financeTicketPercent = $organizer['finance_ticket_percent'] ?? null;
$financeFixAmount = $organizer['finance_fix_amount'] ?? null;
$financeTicketPercentForm = $financeTicketPercent !== null && $financeTicketPercent !== '' ? (string) (int) $financeTicketPercent : '';
$financeFixAmountForm = $financeFixAmount !== null && $financeFixAmount !== '' ? (string) $financeFixAmount : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('organizer_szerkeszt')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet. Töltsd újra az oldalt.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $financeTicketPercentForm = trim((string) ($_POST['finance_ticket_percent'] ?? ''));
        $financeFixAmountForm = trim((string) ($_POST['finance_fix_amount'] ?? ''));
        $financeParsed = events_organizer_finance_parse_from_post();
        $financePercent = $financeParsed['percent'];
        $financeFix = $financeParsed['fix_amount'];
        $financeErr = $financeParsed['error'];
        if ($name === '') {
            $hiba = 'A név megadása kötelező.';
        } elseif ($financeErr !== null) {
            $hiba = $financeErr;
        } else {
            $dup = $db->prepare('SELECT `id` FROM `events_organizers` WHERE `name` = ? AND `id` <> ? LIMIT 1');
            $dup->execute([$name, $id]);
            if ($dup->fetchColumn() !== false) {
                $hiba = 'Már létezik ilyen nevű szervező.';
            } else {
                try {
                    $upd = $db->prepare('
                        UPDATE `events_organizers`
                        SET `name` = ?, `finance_ticket_percent` = ?, `finance_fix_amount` = ?
                        WHERE `id` = ?
                    ');
                    $upd->execute([$name, $financePercent, $financeFix, $id]);
                    rendszer_log('szervező', $id, 'Módosítva', $name);
                    flash('success', 'Mentve.');
                    redirect(events_url('organizer_szerkeszt.php?id=') . $id);
                } catch (Throwable $ex) {
                    error_log('organizer_szerkeszt: ' . $ex->getMessage());
                    $hiba = 'Mentési hiba történt. Kérlek próbáld újra.';
                }
            }
        }
    }
}

$publicUrl = events_url('organizer.php?id=') . $id;
$statsParams = events_edit_stats_params_from_request($_GET);
$statsData = events_edit_stats_for_organizer($db, $id, $statsParams);
$statsEventRows = $statsData['event_rows'] ?? [];
$draftRows = $statsData['draft_rows'] ?? [];
$pageTitle = 'Szervező szerkesztése: ' . $name;
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>
<div class="card">
    <h1 class="card-title">Szervező szerkesztése</h1>
    <?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post" class="venue-form">
        <?= csrf_input('organizer_szerkeszt') ?>
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <div class="form-group">
            <label for="organizer_name">Név *</label>
            <input type="text" id="organizer_name" name="name" value="<?= h($name) ?>" required maxlength="500" autofocus>
        </div>
        <fieldset class="events-edit-panel events-edit-panel--tone-finance organizer-finance-fieldset">
            <legend class="events-edit-panel__title">Finance</legend>
            <div class="form-row events-edit-finance-grid">
                <div class="form-group">
                    <label for="finance_ticket_percent">Belépőjegy %</label>
                    <input
                        type="number"
                        id="finance_ticket_percent"
                        name="finance_ticket_percent"
                        min="1"
                        max="500"
                        step="1"
                        value="<?= h($financeTicketPercentForm) ?>"
                        placeholder="1–500"
                    >
                    <p class="help">Opcionális. A belépő átlagának százaléka eventenként.</p>
                </div>
                <div class="form-group">
                    <label for="finance_fix_amount">Fix összeg eventenként (Ft)</label>
                    <input
                        type="number"
                        id="finance_fix_amount"
                        name="finance_fix_amount"
                        min="0"
                        step="1"
                        value="<?= h($financeFixAmountForm) ?>"
                        placeholder="0"
                    >
                    <p class="help">Szám típusú, opcionális. Ha meg van adva, elsőbbséget élvez a százalékkal szemben.</p>
                </div>
            </div>
        </fieldset>
        <p class="toolbar">
            <button type="submit" class="btn btn-primary">Mentés</button>
            <a href="<?= h($publicUrl) ?>" class="btn btn-secondary" target="_blank" rel="noopener">Nyilvános oldal</a>
            <a href="<?= h(events_url('organizers.php')) ?>" class="btn btn-secondary">Vissza a listához</a>
        </p>
    </form>
</div>

<?php require __DIR__ . '/partials/admin_organizer_portal_account.php'; ?>
<?php require __DIR__ . '/partials/admin_organizer_drafts.php'; ?>
<?php require __DIR__ . '/partials/admin_organizer_edit_stats.php'; ?>

<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
