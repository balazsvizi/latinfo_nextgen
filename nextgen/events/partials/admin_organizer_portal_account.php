<?php
declare(strict_types=1);
/** @var int $id */
/** @var PDO $db */
/** @var string $portalHiba */

require_once dirname(__DIR__, 2) . '/lib/organizer_accounts.php';

$portalAccount = events_organizer_account_by_organizer_id($db, $id);
$tableReady = events_organizer_accounts_table_ready($db);
$portalUrl = organizers_portal_url('login.php');
$hiba = $portalHiba ?? '';
?>
<div class="card szervezo-admin-portal-card">
    <h2 class="card-title">Szervezői portál fiók</h2>
    <p class="help">A szervező a <a href="<?= h($portalUrl) ?>" target="_blank" rel="noopener"><?= h($portalUrl) ?></a> címen éri el a saját kezdőoldalát (események, statisztikák).</p>

    <?php if (!$tableReady): ?>
        <p class="alert alert-warning">
            A portál fiók tábla még nincs telepítve. Futtasd:
            <code>nextgen/events/organizers/sql/migration_organizer_accounts.sql</code>
        </p>
    <?php elseif ($hiba !== ''): ?>
        <p class="alert alert-error"><?= h($hiba) ?></p>
    <?php endif; ?>

    <?php if ($tableReady && $portalAccount !== null): ?>
        <dl class="szervezo-admin-portal-meta">
            <dt>E-mail</dt>
            <dd><?= h((string) $portalAccount['email']) ?></dd>
            <?php if (trim((string) ($portalAccount['név'] ?? '')) !== ''): ?>
                <dt>Megjelenített név</dt>
                <dd><?= h((string) $portalAccount['név']) ?></dd>
            <?php endif; ?>
            <dt>Státusz</dt>
            <dd><?= !empty($portalAccount['aktív']) ? 'Aktív' : 'Inaktív' ?></dd>
            <dt>Létrehozva</dt>
            <dd><?= h((string) ($portalAccount['létrehozva'] ?? '')) ?></dd>
        </dl>

        <form method="post" class="venue-form szervezo-admin-portal-form">
            <?= csrf_input('organizer_portal_account') ?>
            <input type="hidden" name="_portal_action" value="reset_password">
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <div class="form-group">
                <label for="portal_jelszo_reset">Új jelszó</label>
                <input type="password" id="portal_jelszo_reset" name="portal_jelszo" required minlength="8" autocomplete="new-password">
            </div>
            <p class="toolbar">
                <button type="submit" class="btn btn-secondary btn-sm">Jelszó újraállítása</button>
            </p>
        </form>

        <form method="post" class="toolbar" style="margin-top:0.5rem;">
            <?= csrf_input('organizer_portal_account') ?>
            <input type="hidden" name="_portal_action" value="toggle_active">
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <button type="submit" class="btn btn-secondary btn-sm">
                <?= !empty($portalAccount['aktív']) ? 'Fiók deaktiválása' : 'Fiók aktiválása' ?>
            </button>
        </form>
    <?php elseif ($tableReady): ?>
        <form method="post" class="venue-form szervezo-admin-portal-form">
            <?= csrf_input('organizer_portal_account') ?>
            <input type="hidden" name="_portal_action" value="create_account">
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <div class="form-group">
                <label for="portal_email">E-mail cím *</label>
                <input type="email" id="portal_email" name="portal_email" required autocomplete="off">
            </div>
            <div class="form-group">
                <label for="portal_nev">Megjelenített név</label>
                <input type="text" id="portal_nev" name="portal_nev" maxlength="255" placeholder="Opcionális">
            </div>
            <div class="form-group">
                <label for="portal_jelszo">Jelszó *</label>
                <input type="password" id="portal_jelszo" name="portal_jelszo" required minlength="8" autocomplete="new-password">
                <p class="help">Legalább 8 karakter. A szervező ezzel jelentkezik be a portálra.</p>
            </div>
            <p class="toolbar">
                <button type="submit" class="btn btn-primary">Portál fiók létrehozása</button>
            </p>
        </form>
    <?php endif; ?>
</div>
