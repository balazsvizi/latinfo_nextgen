<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/init.php';
require_once dirname(__DIR__, 2) . '/lib/partner/partners.php';
require_once dirname(__DIR__, 2) . '/lib/partner/messages.php';
require_once dirname(__DIR__, 2) . '/lib/partner/activity_log.php';
requireLogin();

$db = getDb();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Hiányzó azonosító.');
    redirect(nextgen_url('admin/partnerek/'));
}

$partner = nextgen_partner_by_id($db, $id);
if ($partner === null) {
    flash('error', 'Partner nem található.');
    redirect(nextgen_url('admin/partnerek/'));
}

$hiba = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('partner_admin_edit')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet.';
    } else {
        $action = (string) ($_POST['_action'] ?? 'save');
        if ($action === 'delete') {
            $confirmNev = trim((string) ($_POST['confirm_nev'] ?? ''));
            if ($confirmNev !== trim((string) ($partner['név'] ?? ''))) {
                $hiba = 'A törléshez pontosan írd be a partner nevét.';
            } else {
                $deleteResult = nextgen_partner_delete($db, $id);
                if ($deleteResult['ok']) {
                    flash('success', 'Partner véglegesen törölve.');
                    redirect(nextgen_url('admin/partnerek/'));
                }
                $hiba = (string) ($deleteResult['error'] ?? 'Törlés sikertelen.');
            }
        } elseif ($action === 'password') {
            $result = nextgen_partner_update_password($db, $id, (string) ($_POST['jelszo'] ?? ''));
            if ($result['ok']) {
                flash('success', 'Jelszó frissítve.');
                redirect(nextgen_url('admin/partnerek/szerkeszt.php?id=') . $id);
            }
            $hiba = (string) ($result['error'] ?? 'Jelszó mentése sikertelen.');
        } elseif ($action === 'toggle') {
            $active = empty($partner['aktív']);
            $result = nextgen_partner_set_active($db, $id, $active);
            if ($result['ok']) {
                flash('success', $active ? 'Partner aktiválva.' : 'Partner deaktiválva.');
                redirect(nextgen_url('admin/partnerek/szerkeszt.php?id=') . $id);
            }
            $hiba = (string) ($result['error'] ?? 'Státusz mentése sikertelen.');
        } else {
            $result = nextgen_partner_update_profile(
                $db,
                $id,
                (string) ($_POST['nev'] ?? ''),
                (string) ($_POST['email'] ?? ''),
                (string) ($_POST['telefon'] ?? ''),
                (string) ($_POST['egyeb_kontakt'] ?? '')
            );
            if (!$result['ok']) {
                $hiba = (string) ($result['error'] ?? 'Profil mentése sikertelen.');
            } else {
                $organizerIds = array_map('intval', (array) ($_POST['organizer_ids'] ?? []));
                $djIds = array_map('intval', (array) ($_POST['dj_ids'] ?? []));
                $financeIds = array_map('intval', (array) ($_POST['finance_ids'] ?? []));
                $assignResult = nextgen_partner_sync_assignments($db, $id, $organizerIds, $djIds, $financeIds);
                if ($assignResult['ok']) {
                    flash('success', 'Mentve.');
                    redirect(nextgen_url('admin/partnerek/szerkeszt.php?id=') . $id);
                }
                $hiba = (string) ($assignResult['error'] ?? 'Hozzárendelések mentése sikertelen.');
            }
        }
        $partner = nextgen_partner_by_id($db, $id) ?? $partner;
    }
}

$assignedOrganizers = nextgen_partner_events_organizers($db, $id);
$assignedDjs = nextgen_partner_djs($db, $id);
$assignedFinance = nextgen_partner_finance_organizers($db, $id);
$assignedOrgIds = array_map(static fn (array $r): int => (int) $r['id'], $assignedOrganizers);
$assignedDjIds = array_map(static fn (array $r): int => (int) $r['id'], $assignedDjs);
$assignedFinanceIds = array_map(static fn (array $r): int => (int) $r['id'], $assignedFinance);

$allOrganizers = nextgen_partner_selectable_events_organizers($db);
$allDjs = nextgen_partner_selectable_djs($db);
$allFinance = nextgen_partner_selectable_finance_organizers($db);
$partnerActivityLog = nextgen_partner_activity_log_for_partner($db, $id);

$pageTitle = 'Partner: ' . (string) ($partner['név'] ?? '');
require_once dirname(__DIR__, 2) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>

<div class="card">
    <h2>Partner szerkesztése</h2>
    <p class="toolbar">
        <a href="<?= h(nextgen_url('admin/partnerek/')) ?>" class="btn btn-secondary btn-sm">← Lista</a>
        <a href="<?= h(nextgen_url('admin/partnerek/uzenetek.php?partner_id=') . $id) ?>" class="btn btn-secondary btn-sm">Üzenetek</a>
        <a href="<?= h(nextgen_url('partner/login.php')) ?>" class="btn btn-secondary btn-sm" target="_blank" rel="noopener">Partner portál</a>
    </p>

    <form method="post" class="venue-form">
        <?= csrf_input('partner_admin_edit') ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="_action" value="save">
        <div class="form-group">
            <label for="nev">Név *</label>
            <input type="text" id="nev" name="nev" value="<?= h((string) ($partner['név'] ?? '')) ?>" required>
        </div>
        <div class="form-group">
            <label for="email">E-mail *</label>
            <input type="email" id="email" name="email" value="<?= h((string) ($partner['email'] ?? '')) ?>" required>
        </div>
        <div class="form-group">
            <label for="telefon">Telefon</label>
            <input type="text" id="telefon" name="telefon" value="<?= h((string) ($partner['telefon'] ?? '')) ?>">
        </div>
        <div class="form-group">
            <label for="egyeb_kontakt">Egyéb kontakt</label>
            <textarea id="egyeb_kontakt" name="egyeb_kontakt" rows="3"><?= h((string) ($partner['egyéb_kontakt'] ?? '')) ?></textarea>
        </div>

        <h3>Hozzárendelések</h3>
        <div class="partner-admin-assign-grid">
            <div>
                <h4>Esemény szervezők</h4>
                <div class="partner-admin-checkbox-list">
                    <?php foreach ($allOrganizers as $row): ?>
                        <?php $oid = (int) ($row['id'] ?? 0); ?>
                        <label>
                            <input type="checkbox" name="organizer_ids[]" value="<?= $oid ?>"<?= in_array($oid, $assignedOrgIds, true) ? ' checked' : '' ?>>
                            <span><?= h((string) ($row['name'] ?? '')) ?> <span class="text-muted">(#<?= $oid ?>)</span></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <h4>DJ oldalak</h4>
                <div class="partner-admin-checkbox-list">
                    <?php foreach ($allDjs as $row): ?>
                        <?php $tid = (int) ($row['id'] ?? 0); ?>
                        <label>
                            <input type="checkbox" name="dj_ids[]" value="<?= $tid ?>"<?= in_array($tid, $assignedDjIds, true) ? ' checked' : '' ?>>
                            <span><?= h((string) ($row['name'] ?? '')) ?> <span class="text-muted">(#<?= $tid ?>)</span></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <h4>Finance szervezők</h4>
                <div class="partner-admin-checkbox-list">
                    <?php foreach ($allFinance as $row): ?>
                        <?php $fid = (int) ($row['id'] ?? 0); ?>
                        <label>
                            <input type="checkbox" name="finance_ids[]" value="<?= $fid ?>"<?= in_array($fid, $assignedFinanceIds, true) ? ' checked' : '' ?>>
                            <span><?= h((string) ($row['name'] ?? '')) ?> <span class="text-muted">(#<?= $fid ?>)</span></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <p class="toolbar" style="margin-top:1rem;">
            <button type="submit" class="btn btn-primary">Mentés</button>
        </p>
    </form>
</div>

<div class="card">
    <h3>Jelszó és státusz</h3>
    <form method="post" class="venue-form">
        <?= csrf_input('partner_admin_edit') ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="_action" value="password">
        <div class="form-group">
            <label for="jelszo">Új jelszó</label>
            <input type="password" id="jelszo" name="jelszo" minlength="8" autocomplete="new-password">
        </div>
        <p class="toolbar"><button type="submit" class="btn btn-secondary btn-sm">Jelszó újraállítása</button></p>
    </form>
    <form method="post" class="toolbar">
        <?= csrf_input('partner_admin_edit') ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="_action" value="toggle">
        <button type="submit" class="btn btn-secondary btn-sm"><?= !empty($partner['aktív']) ? 'Deaktiválás' : 'Aktiválás' ?></button>
    </form>
</div>

<div class="card" style="border-color:#dc3545;">
    <h3>Végleges törlés</h3>
    <p class="help">A partner, üzenetei, hozzárendelései és naplója véglegesen törlődik. Ez nem vonható vissza.</p>
    <form method="post" class="venue-form" onsubmit="return confirm('Biztosan véglegesen törlöd ezt a partnert?');">
        <?= csrf_input('partner_admin_edit') ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="_action" value="delete">
        <div class="form-group">
            <label for="confirm_nev">Írd be a partner nevét a megerősítéshez: <strong><?= h((string) ($partner['név'] ?? '')) ?></strong></label>
            <input type="text" id="confirm_nev" name="confirm_nev" required autocomplete="off">
        </div>
        <p class="toolbar"><button type="submit" class="btn btn-sm" style="background:#dc3545;color:#fff;border-color:#dc3545;">Végleges törlés</button></p>
    </form>
</div>

<?php
$partnerActivityLogGlobal = false;
require __DIR__ . '/partials/activity_log.php';
?>

<?php require_once dirname(__DIR__, 2) . '/partials/footer.php'; ?>
