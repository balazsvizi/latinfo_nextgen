<?php
declare(strict_types=1);

/**
 * Bejelentések modul szerkesztő + kattintás-stat.
 */

require_once dirname(__DIR__) . '/init.php';
requireLogin();
require_once dirname(__DIR__) . '/events/bootstrap.php';
require_once dirname(__DIR__) . '/events/lib/event_edit_stats.php';
require_once __DIR__ . '/lib/site_modules.php';

$db = getDb();
$schemaOk = latinfo_home_modules_ensure_schema($db);
$hiba = '';
$editId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
$editId = ($editId === false || $editId < 0) ? 0 : (int) $editId;
$isNew = isset($_GET['new']);
$postedNews = null;
$formAction = latinfo_home_module_edit_url('announcements');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require('latinfo_home_announcements', '_csrf', $formAction);
    $action = (string) ($_POST['action'] ?? '');

    if (!$schemaOk) {
        $hiba = 'A kezdőoldal táblái nem hozhatók létre.';
    } else {
        try {
            if ($action === 'save_news') {
                $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
                $id = ($id === false || $id < 0) ? 0 : (int) $id;
                $savedId = latinfo_home_news_save($db, $id, [
                    'title' => $_POST['title'] ?? '',
                    'dek' => $_POST['dek'] ?? '',
                    'kicker' => $_POST['kicker'] ?? '',
                    'image_url' => $_POST['image_url'] ?? '',
                    'url' => $_POST['url'] ?? '',
                    'is_hero' => ($_POST['is_hero'] ?? '0') === '1',
                    'is_visible' => ($_POST['is_visible'] ?? '0') === '1',
                    'show_on_web' => ($_POST['show_on_web'] ?? '0') === '1',
                    'show_on_app' => ($_POST['show_on_app'] ?? '0') === '1',
                    'sort_order' => $_POST['sort_order'] ?? 0,
                ]);
                if (function_exists('rendszer_log')) {
                    rendszer_log('kezdőoldal_bejelentés', $savedId, $id > 0 ? 'Módosítva' : 'Létrehozva', trim((string) ($_POST['title'] ?? '')));
                }
                flash('success', $id > 0 ? 'A bejelentés mentve.' : 'Új bejelentés létrehozva.');
                redirect($formAction);
            } elseif ($action === 'delete_news') {
                $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
                $id = ($id === false || $id < 0) ? 0 : (int) $id;
                latinfo_home_news_delete($db, $id);
                if (function_exists('rendszer_log')) {
                    rendszer_log('kezdőoldal_bejelentés', $id, 'Törölve', '');
                }
                flash('success', 'A bejelentés törölve.');
                redirect($formAction);
            } else {
                $hiba = 'Ismeretlen művelet.';
            }
        } catch (InvalidArgumentException $e) {
            $hiba = $e->getMessage();
            $postedNews = $_POST;
            $isNew = (int) ($_POST['id'] ?? 0) <= 0;
        } catch (Throwable $e) {
            error_log('latinfo announcements: ' . $e->getMessage());
            $hiba = 'A mentés nem sikerült.';
        }
    }
}

$newsRows = $schemaOk ? latinfo_home_news_all($db, false) : [];
$editingNews = $postedNews;
if ($editingNews === null && $editId > 0) {
    $editingNews = latinfo_home_news_get($db, $editId);
}
$showNewsForm = $isNew || $editingNews !== null;

$moduleItemStatsParams = latinfo_home_module_stats_params_from_request($_GET);
$moduleItemStatsParams['module'] = 'announcements';
$moduleItemStatsData = $schemaOk
    ? latinfo_home_module_item_stats($db, 'announcements', $moduleItemStatsParams)
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
$moduleItemStatsTitle = 'Bejelentés kattintások';
$moduleItemStatsIntro = 'Az egyes bejelentésekre (gyorshírekre) kattintások a kezdőoldalon.';

$pageTitle = 'Bejelentések';
$mainContentClass = 'main-content main-content--fullwidth';
$extraHead = '<style>
.lh-admin-check{display:flex;align-items:center;gap:.5rem;font-weight:500}
.lh-admin-check input{width:auto;max-width:none}
.lh-admin .form-group input,.lh-admin .form-group textarea{max-width:640px}
.lh-admin-actions{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center}
.lh-admin-actions form{display:inline}
</style>';

require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>

<div class="card lh-admin">
    <div class="events-list-head">
        <h2 class="events-list-title">Bejelentések</h2>
        <div class="events-list-actions">
            <a href="<?= h(latinfo_home_preview_url()) ?>" class="btn btn-secondary btn-sm">Előnézet</a>
            <a href="<?= h(latinfo_home_edit_url()) ?>" class="btn btn-secondary btn-sm">Modulok</a>
        </div>
    </div>
    <p class="text-muted" style="margin-top:0">
        A kezdőoldalon legfeljebb 3 bejelentés jelenik meg. A „Első a 3 között” pipa a lista elejére teszi.
        Web és mobilapp külön állítható: melyik felületen jelenjen meg a hír.
    </p>

    <?php if (!$schemaOk): ?>
        <p class="alert alert-error">A kezdőoldal táblái nem érhetők el.</p>
    <?php else: ?>
        <div class="events-list-actions" style="margin-bottom:1rem">
            <a href="<?= h($formAction . '?new=1') ?>" class="btn btn-primary btn-sm">+ Új bejelentés</a>
        </div>

        <?php if ($showNewsForm): ?>
            <?php
            $n = $editingNews ?? [
                'id' => 0,
                'title' => '',
                'dek' => '',
                'kicker' => '',
                'image_url' => '',
                'url' => '',
                'is_hero' => 0,
                'is_visible' => 1,
                'show_on_web' => 1,
                'show_on_app' => 1,
                'sort_order' => count($newsRows) + 1,
            ];
            if (!array_key_exists('show_on_web', $n)) {
                $n['show_on_web'] = 1;
            }
            if (!array_key_exists('show_on_app', $n)) {
                $n['show_on_app'] = 1;
            }
            ?>
            <form method="post" action="<?= h($formAction) ?>" class="card" style="box-shadow:none;border:1px solid var(--border);margin-bottom:1.5rem">
                <?= csrf_input('latinfo_home_announcements') ?>
                <input type="hidden" name="action" value="save_news">
                <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                <h3><?= (int) $n['id'] > 0 ? 'Bejelentés szerkesztése' : 'Új bejelentés' ?></h3>
                <div class="form-row form-row-2">
                    <div class="form-group">
                        <label for="news_kicker">Rovat / kicker</label>
                        <input type="text" id="news_kicker" name="kicker" maxlength="80" value="<?= h((string) $n['kicker']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="news_sort">Sorrend</label>
                        <input type="number" id="news_sort" name="sort_order" value="<?= (int) $n['sort_order'] ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="news_title">Cím</label>
                    <input type="text" id="news_title" name="title" maxlength="200" required value="<?= h((string) $n['title']) ?>">
                </div>
                <div class="form-group">
                    <label for="news_dek">Lead</label>
                    <textarea id="news_dek" name="dek" rows="3" maxlength="500"><?= h((string) $n['dek']) ?></textarea>
                </div>
                <div class="form-group">
                    <label for="news_url">Hivatkozás</label>
                    <input type="text" id="news_url" name="url" maxlength="500" value="<?= h((string) $n['url']) ?>" placeholder="/events/ vagy https://…">
                </div>
                <div class="form-group">
                    <label for="news_image">Kép URL</label>
                    <input type="text" id="news_image" name="image_url" maxlength="500" value="<?= h((string) $n['image_url']) ?>">
                </div>
                <div class="form-row form-row-2">
                    <div class="form-group">
                        <input type="hidden" name="is_hero" value="0">
                        <label class="lh-admin-check">
                            <input type="checkbox" name="is_hero" value="1"<?= !empty($n['is_hero']) ? ' checked' : '' ?>>
                            Első a 3 között
                        </label>
                    </div>
                    <div class="form-group">
                        <input type="hidden" name="is_visible" value="0">
                        <label class="lh-admin-check">
                            <input type="checkbox" name="is_visible" value="1"<?= !empty($n['is_visible']) ? ' checked' : '' ?>>
                            Látható
                        </label>
                    </div>
                </div>
                <div class="form-row form-row-2">
                    <div class="form-group">
                        <input type="hidden" name="show_on_web" value="0">
                        <label class="lh-admin-check">
                            <input type="checkbox" name="show_on_web" value="1"<?= !empty($n['show_on_web']) ? ' checked' : '' ?>>
                            Weben (asztali + mobil böngésző)
                        </label>
                    </div>
                    <div class="form-group">
                        <input type="hidden" name="show_on_app" value="0">
                        <label class="lh-admin-check">
                            <input type="checkbox" name="show_on_app" value="1"<?= !empty($n['show_on_app']) ? ' checked' : '' ?>>
                            Mobilappban (PWA)
                        </label>
                    </div>
                </div>
                <div class="lh-admin-actions">
                    <button type="submit" class="btn btn-primary">Mentés</button>
                    <a href="<?= h($formAction) ?>" class="btn btn-secondary">Mégse</a>
                </div>
            </form>
        <?php endif; ?>

        <div class="table-wrap">
            <table class="sortable-table">
                <thead>
                    <tr>
                        <th>Sorrend</th>
                        <th>Cím</th>
                        <th>Rovat</th>
                        <th>Első</th>
                        <th>Látható</th>
                        <th>Web</th>
                        <th>App</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($newsRows === []): ?>
                        <tr><td colspan="8" class="text-muted">Még nincs bejelentés.</td></tr>
                    <?php else: ?>
                        <?php foreach ($newsRows as $row): ?>
                            <tr>
                                <td><?= (int) $row['sort_order'] ?></td>
                                <td><?= h((string) $row['title']) ?></td>
                                <td><?= h((string) $row['kicker']) ?></td>
                                <td><?= !empty($row['is_hero']) ? 'igen' : '–' ?></td>
                                <td><?= !empty($row['is_visible']) ? 'igen' : 'nem' ?></td>
                                <td><?= !empty($row['show_on_web'] ?? 1) ? 'igen' : 'nem' ?></td>
                                <td><?= !empty($row['show_on_app'] ?? 1) ? 'igen' : 'nem' ?></td>
                                <td>
                                    <div class="lh-admin-actions">
                                        <a class="btn btn-secondary btn-sm" href="<?= h($formAction . '?id=' . (int) $row['id']) ?>">Szerkeszt</a>
                                        <form method="post" action="<?= h($formAction) ?>" onsubmit="return confirm('Törlöd ezt a bejelentést?');">
                                            <?= csrf_input('latinfo_home_announcements') ?>
                                            <input type="hidden" name="action" value="delete_news">
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm">Töröl</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($schemaOk): ?>
    <?php require __DIR__ . '/partials/module_item_stats.php'; ?>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
