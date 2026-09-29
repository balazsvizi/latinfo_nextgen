<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/lib/user/favorites.php';
requireLogin();

$db = getDb();
$schemaOk = latinfo_favorites_ensure_schema($db);
$hiba = '';
$selfUrl = events_url('kedvencek_beallitas.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('events_favorites_settings')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet. Töltsd újra az oldalt.';
    } elseif (!$schemaOk) {
        $hiba = 'A kedvencek táblák nem érhetők el.';
    } else {
        $enabled = isset($_POST['public_hearts_enabled']) && (string) $_POST['public_hearts_enabled'] === '1';
        try {
            latinfo_favorites_set_public_enabled($db, $enabled);
            if (function_exists('rendszer_log')) {
                rendszer_log('kedvencek', null, 'Szívecskék', $enabled ? 'bekapcsolva' : 'kikapcsolva');
            }
            flash('success', $enabled ? 'A szívecskék megjelenése bekapcsolva.' : 'A szívecskék megjelenése kikapcsolva.');
            redirect($selfUrl);
        } catch (Throwable $e) {
            error_log('kedvencek_beallitas: ' . $e->getMessage());
            $hiba = 'A mentés nem sikerült.';
        }
    }
}

$heartsEnabled = $schemaOk && latinfo_favorites_public_enabled($db);
$totalHearts = 0;
if ($schemaOk && latinfo_favorites_table_ready($db)) {
    try {
        $totalHearts = (int) $db->query('SELECT COUNT(*) FROM `latinfo_favorites`')->fetchColumn();
    } catch (Throwable) {
        $totalHearts = 0;
    }
}

$pageTitle = 'Kedvencek (szívecskék)';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>

<div class="card events-admin-card">
    <div class="events-list-head">
        <h2 class="events-list-title">Nyilvános szívecskék</h2>
        <div class="events-list-actions">
            <a href="<?= h(events_url('events_admin.php')) ?>" class="btn btn-secondary">Események</a>
        </div>
    </div>
    <p class="help">
        A látogatók kedvencnek jelölhetnek eseményeket, szervezőket, helyszíneket és DJ-ket a publikus oldalakon.
        Bejelentkezés nélkül is működik (vendég azonosítóval); a fiókkal rendelkezők a profiljukon szerkeszthetik a kedvenceket.
    </p>
    <?php if (!$schemaOk): ?>
        <p class="alert alert-error">A kedvencek táblák nem jöttek létre. Frissítsd az oldalt, vagy ellenőrizd az adatbázis-jogosultságokat.</p>
    <?php else: ?>
        <p><strong>Összes elmentett szívecské:</strong> <?= (int) $totalHearts ?></p>
        <form method="post" class="events-favorites-settings-form">
            <?= csrf_input('events_favorites_settings') ?>
            <div class="form-group">
                <label class="lh-admin-check">
                    <input type="hidden" name="public_hearts_enabled" value="0">
                    <input type="checkbox" name="public_hearts_enabled" value="1"<?= $heartsEnabled ? ' checked' : '' ?>>
                    Szívecskék megjelenése a publikus oldalakon
                </label>
            </div>
            <button type="submit" class="btn btn-primary">Mentés</button>
        </form>
    <?php endif; ?>
</div>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
