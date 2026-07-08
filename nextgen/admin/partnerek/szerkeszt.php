<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/init.php';
require_once dirname(__DIR__, 2) . '/events/bootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/partner/partners.php';
require_once dirname(__DIR__, 2) . '/lib/partner/messages.php';
require_once dirname(__DIR__, 2) . '/lib/partner/activity_log.php';
requireLogin();

$db = getDb();
nextgen_partner_ensure_extended_schema($db);

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
$organizerRoleLabels = nextgen_partner_organizer_role_labels();
$djRoleLabels = nextgen_partner_dj_role_labels();
$partnerOrganizerChipLinkPattern = events_url('organizer.php?id={id}');
$partnerOrganizerManageUrl = events_url('organizer_letrehoz.php');
$partnerDjChipLinkPattern = events_url('tags.php?open_tag={id}#open-tag-{id}');

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
            $jelszo = (string) ($_POST['jelszo'] ?? '');
            if ($jelszo === '') {
                $hiba = 'Add meg az új jelszót.';
            } else {
                $requireChange = !empty($_POST['jelszo_csere_kotelezo']);
                $result = nextgen_partner_update_password($db, $id, $jelszo, $requireChange);
                if ($result['ok']) {
                    flash('success', 'Jelszó frissítve.');
                    redirect(nextgen_url('admin/partnerek/szerkeszt.php?id=') . $id);
                }
                $hiba = (string) ($result['error'] ?? 'Jelszó mentése sikertelen.');
            }
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
                (string) ($_POST['egyeb_kontakt'] ?? ''),
                (string) ($_POST['egyeb_info'] ?? ''),
                (string) ($_POST['kieg_info'] ?? '')
            );
            if (!$result['ok']) {
                $hiba = (string) ($result['error'] ?? 'Profil mentése sikertelen.');
            } else {
                $organizerRows = nextgen_partner_organizer_rows_from_post($_POST['organizer_rows'] ?? []);
                $djRows = nextgen_partner_dj_rows_from_post($_POST['dj_rows'] ?? []);
                $assignResult = nextgen_partner_sync_assignments($db, $id, $organizerRows, $djRows);
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

$organizerRowsForForm = [];
$djRowsForForm = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['_action'] ?? 'save') === 'save' && $hiba !== '') {
    foreach ((array) ($_POST['organizer_rows'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $roleTypes = [];
        if (isset($row['role_types']) && is_array($row['role_types'])) {
            $roleTypes = array_values(array_map('strval', $row['role_types']));
        } elseif (isset($row['role_type'])) {
            $roleTypes = [(string) $row['role_type']];
        }
        $organizerRowsForForm[] = [
            'organizer_id' => (int) ($row['organizer_id'] ?? 0),
            'role_types' => $roleTypes,
            'role_note' => (string) ($row['role_note'] ?? ''),
        ];
    }
    foreach ((array) ($_POST['dj_rows'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $roleTypes = [];
        if (isset($row['role_types']) && is_array($row['role_types'])) {
            $roleTypes = array_values(array_map('strval', $row['role_types']));
        } elseif (isset($row['role_type'])) {
            $roleTypes = [(string) $row['role_type']];
        }
        $djRowsForForm[] = [
            'tag_id' => (int) ($row['tag_id'] ?? 0),
            'role_types' => $roleTypes,
            'role_note' => (string) ($row['role_note'] ?? ''),
        ];
    }
} else {
    $organizerRowsForForm = nextgen_partner_group_organizer_assignments_for_form($assignedOrganizers);
    $djRowsForForm = nextgen_partner_group_dj_assignments_for_form($assignedDjs);
}

if ($organizerRowsForForm === []) {
    $organizerRowsForForm[] = ['organizer_id' => 0, 'role_types' => ['event'], 'role_note' => ''];
}
if ($djRowsForForm === []) {
    $djRowsForForm[] = ['tag_id' => 0, 'role_types' => ['dj'], 'role_note' => ''];
}

$allOrganizers = nextgen_partner_selectable_events_organizers($db);
$allDjs = nextgen_partner_selectable_djs($db);
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
        <a href="<?= h(partner_url('')) ?>" class="btn btn-secondary btn-sm" target="_blank" rel="noopener">Partner portál</a>
    </p>

    <form method="post" class="venue-form" id="partner-admin-edit-form">
        <?= csrf_input('partner_admin_edit') ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="_action" value="save">
        <div class="form-row partner-name-row">
            <div class="form-group">
                <label for="nev">Név *</label>
                <input type="text" id="nev" name="nev" value="<?= h((string) ($partner['név'] ?? '')) ?>" required maxlength="255">
            </div>
            <div class="form-group">
                <label for="kieg_info">Kieg. infó</label>
                <input type="text" id="kieg_info" name="kieg_info" value="<?= h((string) ($partner['kieg_info'] ?? '')) ?>" maxlength="255" placeholder="pl. cég, szerepkör…">
            </div>
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
        <div class="form-group">
            <label for="egyeb_info">Egyéb infó</label>
            <textarea id="egyeb_info" name="egyeb_info" rows="4" placeholder="Belső megjegyzések, egyéb információk a partnerről…"><?= h((string) ($partner['egyéb_info'] ?? '')) ?></textarea>
        </div>

        <h3>Hozzárendelések</h3>

        <section class="partner-admin-assign-section">
            <div class="partner-admin-assign-section__head">
                <h4>Esemény szervezők</h4>
                <p class="help">Válaszd ki a szervezőt, majd add meg a partner jellegét. Több sorban több szervező is hozzárendelhető.</p>
            </div>
            <div id="partner-organizer-rows" class="partner-admin-assign-rows">
                <?php foreach ($organizerRowsForForm as $partnerAssignRowIndex => $partnerAssignRow): ?>
                    <?php
                    $partnerAssignAllOrganizers = $allOrganizers;
                    $partnerOrganizerRoleLabels = $organizerRoleLabels;
                    require __DIR__ . '/partials/partner_organizer_assign_row.php';
                    ?>
                <?php endforeach; ?>
            </div>
            <p class="toolbar partner-admin-assign-section__actions">
                <button type="button" class="btn btn-secondary btn-sm" id="partner-organizer-add">+ Szervező sor</button>
                <?php if ($partnerOrganizerManageUrl !== null): ?>
                    <a href="<?= h($partnerOrganizerManageUrl) ?>" class="btn btn-secondary btn-sm" target="_blank" rel="noopener noreferrer">Új szervező felvétele</a>
                <?php endif; ?>
            </p>
        </section>

        <section class="partner-admin-assign-section">
            <div class="partner-admin-assign-section__head">
                <h4>DJ-k</h4>
                <p class="help">DJ oldal kiválasztása és partner jelleg (DJ vagy Egyéb).</p>
            </div>
            <div id="partner-dj-rows" class="partner-admin-assign-rows">
                <?php foreach ($djRowsForForm as $partnerAssignRowIndex => $partnerAssignRow): ?>
                    <?php
                    $partnerAssignAllDjs = $allDjs;
                    $partnerDjRoleLabels = $djRoleLabels;
                    require __DIR__ . '/partials/partner_dj_assign_row.php';
                    ?>
                <?php endforeach; ?>
            </div>
            <p class="toolbar partner-admin-assign-section__actions">
                <button type="button" class="btn btn-secondary btn-sm" id="partner-dj-add">+ DJ sor</button>
            </p>
        </section>

        <p class="toolbar" style="margin-top:1rem;">
            <button type="submit" class="btn btn-primary">Mentés</button>
        </p>
    </form>
</div>

<template id="partner-organizer-row-template">
<?php
$partnerAssignRowIndex = '__INDEX__';
$partnerAssignRow = ['organizer_id' => 0, 'role_types' => ['event'], 'role_note' => ''];
$partnerAssignAllOrganizers = $allOrganizers;
$partnerOrganizerRoleLabels = $organizerRoleLabels;
require __DIR__ . '/partials/partner_organizer_assign_row.php';
?>
</template>

<template id="partner-dj-row-template">
<?php
$partnerAssignRowIndex = '__INDEX__';
$partnerAssignRow = ['tag_id' => 0, 'role_types' => ['dj'], 'role_note' => ''];
$partnerAssignAllDjs = $allDjs;
$partnerDjRoleLabels = $djRoleLabels;
require __DIR__ . '/partials/partner_dj_assign_row.php';
?>
</template>

<div class="card">
    <h3>Jelszó és státusz</h3>
    <?php if (!empty($partner['jelszó_csere_kötelező'])): ?>
        <p class="alert alert-warning">A partnernek kötelező új jelszót beállítania a következő belépéskor.</p>
    <?php endif; ?>
    <form method="post" class="venue-form">
        <?= csrf_input('partner_admin_edit') ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="_action" value="password">
        <div class="form-group">
            <label for="jelszo">Új jelszó</label>
            <input type="password" id="jelszo" name="jelszo" minlength="8" autocomplete="new-password">
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" name="jelszo_csere_kotelezo" value="1"<?= !empty($partner['jelszó_csere_kötelező']) ? ' checked' : '' ?>>
                Kötelező új jelszó megadása a következő belépéskor
            </label>
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

<?php require dirname(__DIR__, 2) . '/events/partials/wp_token_input_script.php'; ?>
<?php require __DIR__ . '/partials/partner_assignment_script.php'; ?>

<?php require_once dirname(__DIR__, 2) . '/partials/footer.php'; ?>
