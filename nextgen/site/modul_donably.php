<?php
declare(strict_types=1);

/**
 * Donably támogatás modul – szöveg, link és kattintás-stat.
 */

require_once dirname(__DIR__) . '/init.php';
requireLogin();
require_once dirname(__DIR__) . '/events/bootstrap.php';
require_once dirname(__DIR__) . '/events/lib/event_edit_stats.php';
require_once __DIR__ . '/lib/site_modules.php';

$db = getDb();
$schemaOk = latinfo_home_modules_ensure_schema($db);
$hiba = '';
$formAction = latinfo_home_module_edit_url('donably');
$posted = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require('latinfo_home_donably', '_csrf', $formAction);
    $action = (string) ($_POST['action'] ?? '');

    if (!$schemaOk) {
        $hiba = 'A kezdőoldal táblái nem hozhatók létre.';
    } else {
        try {
            if ($action === 'save_donably') {
                latinfo_home_donably_save($db, $_POST);
                if (function_exists('rendszer_log')) {
                    rendszer_log('kezdőoldal_donably', 1, 'Módosítva', trim((string) ($_POST['title'] ?? '')));
                }
                flash('success', 'A Donably modul mentve.');
                redirect($formAction);
            } elseif ($action === 'reset_defaults') {
                $current = latinfo_home_donably_load($db);
                $defaults = latinfo_home_donably_defaults();
                $defaults['cta_url'] = $current['cta_url'];
                $defaults['show_icon'] = $current['show_icon'];
                latinfo_home_donably_save($db, $defaults);
                if (function_exists('rendszer_log')) {
                    rendszer_log('kezdőoldal_donably', 1, 'Alapértelmezett szövegek', '');
                }
                flash('success', 'Az alapértelmezett szövegek visszaállítva. A link változatlan maradt.');
                redirect($formAction);
            } else {
                $hiba = 'Ismeretlen művelet.';
            }
        } catch (InvalidArgumentException $e) {
            $hiba = $e->getMessage();
            $posted = $_POST;
        } catch (Throwable $e) {
            error_log('latinfo donably: ' . $e->getMessage());
            $hiba = 'A mentés nem sikerült.';
        }
    }
}

$settings = $schemaOk ? latinfo_home_donably_load($db) : latinfo_home_donably_defaults();
if (is_array($posted)) {
    foreach ($settings as $key => $fallback) {
        if (array_key_exists($key, $posted)) {
            $settings[$key] = trim((string) $posted[$key]);
        }
    }
}

$moduleItemStatsParams = latinfo_home_module_stats_params_from_request($_GET);
$moduleItemStatsParams['module'] = 'donably';
$moduleItemStatsData = $schemaOk
    ? latinfo_home_module_item_stats($db, 'donably', $moduleItemStatsParams)
    : ['table_ready' => false, 'totals' => [], 'items' => [], 'chart' => ['labels' => [], 'datasets' => []]];
$moduleItemStatsFormAction = $formAction;
$statsAllDateFrom = latinfo_home_module_stats_earliest_date($db);
$statsActivePreset = events_edit_stats_detect_preset($moduleItemStatsParams, $statsAllDateFrom);
$moduleItemStatsPresetLinks = [];
foreach (events_edit_stats_presets() as $preset) {
    $presetId = (string) $preset['id'];
    $moduleItemStatsPresetLinks[] = [
        'id' => $presetId,
        'label' => (string) $preset['label'],
        'url' => events_edit_stats_filter_url(
            $formAction,
            events_edit_stats_range_for_preset($presetId, $statsAllDateFrom),
            ['visitor' => $moduleItemStatsParams['visitor'] !== 'human' ? $moduleItemStatsParams['visitor'] : null]
        ),
        'active' => $statsActivePreset === $presetId,
    ];
}
$moduleItemStatsTitle = 'Donably kattintások';
$moduleItemStatsIntro = 'A kezdőoldali támogatás gombra kattintások.';

$pageTitle = 'Támogatás (Donably)';
$mainContentClass = 'main-content main-content--fullwidth';
$extraHead = '<style>
.lh-admin .form-group input,.lh-admin .form-group textarea{max-width:640px}
.lh-admin-check{display:flex;align-items:center;gap:.5rem;font-weight:500}
.lh-admin-check input{width:auto;max-width:none}
.lh-admin-actions{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center}
.lh-admin-actions form{display:inline}
</style>';

require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>

<div class="card lh-admin">
    <div class="events-list-head">
        <h2 class="events-list-title">Támogatás (Donably)</h2>
        <div class="events-list-actions">
            <a href="<?= h(latinfo_home_preview_url()) ?>" class="btn btn-secondary btn-sm">Előnézet</a>
            <a href="<?= h(latinfo_home_edit_url()) ?>" class="btn btn-secondary btn-sm">Modulok</a>
        </div>
    </div>
    <p class="text-muted" style="margin-top:0">
        A kezdőoldalon egy rövid felhívás és egy gomb jelenik meg, a Donably ajánlása szerint:
        saját szöveg, a te adományoldalad linkje, külső lapon. A gomb csak kitöltött linknél kattintható.
    </p>

    <?php if (!$schemaOk): ?>
        <p class="alert alert-error">A kezdőoldal táblái nem érhetők el.</p>
    <?php else: ?>
        <form method="post" action="<?= h($formAction) ?>">
            <?= csrf_input('latinfo_home_donably') ?>
            <input type="hidden" name="action" value="save_donably">

            <h3>Magyar szöveg</h3>
            <div class="form-group">
                <input type="hidden" name="show_icon" value="0">
                <label class="lh-admin-check">
                    <input type="checkbox" name="show_icon" value="1"<?= $settings['show_icon'] === '1' ? ' checked' : '' ?>>
                    Szív ikon a cím mellett
                </label>
            </div>
            <div class="form-group">
                <label for="donably_title">Cím</label>
                <input type="text" id="donably_title" name="title" maxlength="120" required value="<?= h($settings['title']) ?>">
            </div>
            <div class="form-group">
                <label for="donably_lead">Szöveg</label>
                <textarea id="donably_lead" name="lead" rows="3" maxlength="400"><?= h($settings['lead']) ?></textarea>
            </div>
            <div class="form-row form-row-2">
                <div class="form-group">
                    <label for="donably_cta_label">Gomb felirata</label>
                    <input type="text" id="donably_cta_label" name="cta_label" maxlength="60" required value="<?= h($settings['cta_label']) ?>">
                </div>
                <div class="form-group">
                    <label for="donably_note">Megjegyzés a gomb mellett</label>
                    <input type="text" id="donably_note" name="note" maxlength="160" value="<?= h($settings['note']) ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="donably_cta_url">Donably link</label>
                <input
                    type="text"
                    id="donably_cta_url"
                    name="cta_url"
                    maxlength="500"
                    value="<?= h($settings['cta_url']) ?>"
                    placeholder="https://www.donably.com/…"
                >
                <small class="text-muted">A Donably adományoldalad teljes címe. Külső http(s) link új lapon nyílik.</small>
            </div>

            <h3>Angol szöveg (opcionális, üresen a magyar jelenik meg)</h3>
            <div class="form-group">
                <label for="donably_title_en">Cím (EN)</label>
                <input type="text" id="donably_title_en" name="title_en" maxlength="120" value="<?= h($settings['title_en']) ?>">
            </div>
            <div class="form-group">
                <label for="donably_lead_en">Szöveg (EN)</label>
                <textarea id="donably_lead_en" name="lead_en" rows="3" maxlength="400"><?= h($settings['lead_en']) ?></textarea>
            </div>
            <div class="form-row form-row-2">
                <div class="form-group">
                    <label for="donably_cta_label_en">Gomb felirata (EN)</label>
                    <input type="text" id="donably_cta_label_en" name="cta_label_en" maxlength="60" value="<?= h($settings['cta_label_en']) ?>">
                </div>
                <div class="form-group">
                    <label for="donably_note_en">Megjegyzés (EN)</label>
                    <input type="text" id="donably_note_en" name="note_en" maxlength="160" value="<?= h($settings['note_en']) ?>">
                </div>
            </div>

            <div class="lh-admin-actions">
                <button type="submit" class="btn btn-primary">Mentés</button>
            </div>
        </form>

        <form method="post" action="<?= h($formAction) ?>" style="margin-top:.75rem" onsubmit="return confirm('Visszaállítod az alapértelmezett szövegeket? A link megmarad.');">
            <?= csrf_input('latinfo_home_donably') ?>
            <input type="hidden" name="action" value="reset_defaults">
            <button type="submit" class="btn btn-secondary btn-sm">Alapértelmezett szövegek</button>
        </form>
    <?php endif; ?>
</div>

<?php if ($schemaOk): ?>
    <?php require __DIR__ . '/partials/module_item_stats.php'; ?>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
