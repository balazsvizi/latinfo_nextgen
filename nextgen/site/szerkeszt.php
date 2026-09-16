<?php
declare(strict_types=1);

/**
 * Latinfo.hu kezdőoldal szerkesztő: hero, kiemelt hírek, gyűjtők.
 */

require_once dirname(__DIR__) . '/init.php';
requireLogin();
require_once dirname(__DIR__) . '/events/bootstrap.php';
require_once __DIR__ . '/lib/site_home.php';

$db = getDb();
$schemaOk = latinfo_home_ensure_schema($db);
$hiba = '';
$tabs = ['hero' => 'Hero és sávok', 'news' => 'Kiemelt hírek', 'collectors' => 'Gyűjtők'];
$tab = (string) ($_GET['tab'] ?? 'hero');
if (!isset($tabs[$tab])) {
    $tab = 'hero';
}
$editId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
$editId = ($editId === false || $editId < 0) ? 0 : (int) $editId;
$isNew = isset($_GET['new']);
$postedNews = null;
$postedCollector = null;
$postedSettings = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require('latinfo_home', '_csrf', latinfo_home_edit_url('tab=' . rawurlencode($tab)));
    $action = (string) ($_POST['action'] ?? '');
    $postedTab = (string) ($_POST['tab'] ?? $tab);
    if (!isset($tabs[$postedTab])) {
        $postedTab = 'hero';
    }

    if (!$schemaOk) {
        $hiba = 'A kezdőoldal táblái nem hozhatók létre. Ellenőrizd az adatbázis-jogosultságokat.';
    } else {
        try {
            if ($action === 'save_settings') {
                latinfo_home_save_settings($db, $_POST);
                if (function_exists('rendszer_log')) {
                    rendszer_log('kezdőoldal', 1, 'Beállítások mentve', '');
                }
                flash('success', 'A hero és a hírlevél sáv mentve.');
                redirect(latinfo_home_edit_url('tab=hero'));
            } elseif ($action === 'save_news') {
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
                    'sort_order' => $_POST['sort_order'] ?? 0,
                ]);
                if (function_exists('rendszer_log')) {
                    rendszer_log('kezdőoldal_hír', $savedId, $id > 0 ? 'Módosítva' : 'Létrehozva', trim((string) ($_POST['title'] ?? '')));
                }
                flash('success', $id > 0 ? 'A hír mentve.' : 'Új kiemelt hír létrehozva.');
                redirect(latinfo_home_edit_url('tab=news'));
            } elseif ($action === 'delete_news') {
                $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
                $id = ($id === false || $id < 0) ? 0 : (int) $id;
                latinfo_home_news_delete($db, $id);
                if (function_exists('rendszer_log')) {
                    rendszer_log('kezdőoldal_hír', $id, 'Törölve', '');
                }
                flash('success', 'A hír törölve.');
                redirect(latinfo_home_edit_url('tab=news'));
            } elseif ($action === 'save_collector') {
                $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
                $id = ($id === false || $id < 0) ? 0 : (int) $id;
                $savedId = latinfo_home_collectors_save($db, $id, [
                    'title' => $_POST['title'] ?? '',
                    'subtitle' => $_POST['subtitle'] ?? '',
                    'url' => $_POST['url'] ?? '',
                    'image_url' => $_POST['image_url'] ?? '',
                    'accent_color' => $_POST['accent_color'] ?? '#6D8F63',
                    'is_visible' => ($_POST['is_visible'] ?? '0') === '1',
                    'sort_order' => $_POST['sort_order'] ?? 0,
                ]);
                if (function_exists('rendszer_log')) {
                    rendszer_log('kezdőoldal_gyűjtő', $savedId, $id > 0 ? 'Módosítva' : 'Létrehozva', trim((string) ($_POST['title'] ?? '')));
                }
                flash('success', $id > 0 ? 'A gyűjtő mentve.' : 'Új gyűjtő létrehozva.');
                redirect(latinfo_home_edit_url('tab=collectors'));
            } elseif ($action === 'delete_collector') {
                $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
                $id = ($id === false || $id < 0) ? 0 : (int) $id;
                latinfo_home_collectors_delete($db, $id);
                if (function_exists('rendszer_log')) {
                    rendszer_log('kezdőoldal_gyűjtő', $id, 'Törölve', '');
                }
                flash('success', 'A gyűjtő törölve.');
                redirect(latinfo_home_edit_url('tab=collectors'));
            } else {
                $hiba = 'Ismeretlen művelet.';
            }
        } catch (InvalidArgumentException $e) {
            $hiba = $e->getMessage();
            $tab = $postedTab;
            if ($action === 'save_news') {
                $postedNews = $_POST;
                $isNew = (int) ($_POST['id'] ?? 0) <= 0;
            } elseif ($action === 'save_collector') {
                $postedCollector = $_POST;
                $isNew = (int) ($_POST['id'] ?? 0) <= 0;
            } elseif ($action === 'save_settings') {
                $postedSettings = $_POST;
            }
        } catch (Throwable $e) {
            error_log('latinfo home szerkeszt: ' . $e->getMessage());
            $hiba = 'A mentés nem sikerült.';
            $tab = $postedTab;
        }
    }
}

$settings = $schemaOk ? latinfo_home_load_settings($db) : latinfo_home_settings_defaults();
if (is_array($postedSettings)) {
    foreach (latinfo_home_settings_defaults() as $key => $_fallback) {
        if (array_key_exists($key, $postedSettings)) {
            $settings[$key] = trim((string) $postedSettings[$key]);
        }
    }
}
$newsRows = $schemaOk ? latinfo_home_news_all($db, false) : [];
$collectorRows = $schemaOk ? latinfo_home_collectors_all($db, false) : [];
$editingNews = $postedNews;
if ($editingNews === null && $tab === 'news' && $editId > 0) {
    $editingNews = latinfo_home_news_get($db, $editId);
}
$editingCollector = $postedCollector;
if ($editingCollector === null && $tab === 'collectors' && $editId > 0) {
    $editingCollector = latinfo_home_collectors_get($db, $editId);
}
$showNewsForm = $tab === 'news' && ($isNew || $editingNews !== null);
$showCollectorForm = $tab === 'collectors' && ($isNew || $editingCollector !== null);

$pageTitle = 'Latinfo kezdőoldal';
$mainContentClass = 'main-content main-content--fullwidth';
$extraHead = '<style>
.lh-admin-tabs{display:flex;flex-wrap:wrap;gap:.4rem;margin:0 0 1.25rem}
.lh-admin-tabs a{display:inline-block;padding:.45rem .9rem;border:1px solid var(--border);border-radius:999px;text-decoration:none;color:inherit}
.lh-admin-tabs a.is-active{background:var(--primary,#6d8f63);color:#fff;border-color:transparent}
.lh-admin-check{display:flex;align-items:center;gap:.5rem;font-weight:500}
.lh-admin-check input{width:auto;max-width:none}
.lh-admin .form-group input,.lh-admin .form-group textarea,.lh-admin .form-group select{max-width:640px}
.lh-admin-table .lh-swatch{display:inline-block;width:14px;height:14px;border-radius:4px;vertical-align:middle;margin-right:.35rem;border:1px solid rgba(0,0,0,.15)}
.lh-admin-actions{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center}
.lh-admin-actions form{display:inline}
</style>';

require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>

<div class="card lh-admin">
    <div class="events-list-head">
        <h2 class="events-list-title">Latinfo.hu kezdőoldal</h2>
        <div class="events-list-actions">
            <a href="<?= h(latinfo_home_preview_url()) ?>" class="btn btn-secondary btn-sm">Előnézet</a>
        </div>
    </div>
    <p class="text-muted" style="margin-top:0">
        Ez a leendő nyilvános kezdőoldal. Jelenleg csak belépett adminok látják.
        Az első képernyőn 3 gyorshír, a mai és holnapi események, valamint a DJ ajánló jelenik meg. A hero szövegek a felső sávba kerülnek; a naptár a közzétett eseményekből jön.
    </p>

    <?php if (!$schemaOk): ?>
        <p class="alert alert-error">A kezdőoldal táblái nem érhetők el.</p>
    <?php else: ?>
        <nav class="lh-admin-tabs" aria-label="Kezdőoldal szerkesztő fülek">
            <?php foreach ($tabs as $tabKey => $tabLabel): ?>
                <a href="<?= h(latinfo_home_edit_url('tab=' . rawurlencode($tabKey))) ?>" class="<?= $tab === $tabKey ? 'is-active' : '' ?>"<?= $tab === $tabKey ? ' aria-current="page"' : '' ?>><?= h($tabLabel) ?></a>
            <?php endforeach; ?>
        </nav>

        <?php if ($tab === 'hero'): ?>
            <form method="post" action="<?= h(latinfo_home_edit_url('tab=hero')) ?>">
                <?= csrf_input('latinfo_home') ?>
                <input type="hidden" name="action" value="save_settings">
                <input type="hidden" name="tab" value="hero">
                <h3>Felső sáv (cím, lead, naptár gomb)</h3>
                <div class="form-row form-row-2">
                    <div class="form-group">
                        <label for="hero_kicker">Kicker</label>
                        <input type="text" id="hero_kicker" name="hero_kicker" maxlength="120" value="<?= h($settings['hero_kicker']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="hero_cta_label">Gomb felirata</label>
                        <input type="text" id="hero_cta_label" name="hero_cta_label" maxlength="80" value="<?= h($settings['hero_cta_label']) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="hero_title">Cím</label>
                    <input type="text" id="hero_title" name="hero_title" maxlength="200" value="<?= h($settings['hero_title']) ?>">
                </div>
                <div class="form-group">
                    <label for="hero_lead">Lead</label>
                    <textarea id="hero_lead" name="hero_lead" rows="3" maxlength="500"><?= h($settings['hero_lead']) ?></textarea>
                </div>
                <div class="form-group">
                    <label for="hero_cta_url">Gomb hivatkozása</label>
                    <input type="text" id="hero_cta_url" name="hero_cta_url" maxlength="500" value="<?= h($settings['hero_cta_url']) ?>" placeholder="/events/ vagy https://…">
                </div>

                <h3>Alsó sáv (hírlevél / CTA)</h3>
                <div class="form-group">
                    <label for="newsletter_title">Cím</label>
                    <input type="text" id="newsletter_title" name="newsletter_title" maxlength="160" value="<?= h($settings['newsletter_title']) ?>">
                </div>
                <div class="form-group">
                    <label for="newsletter_lead">Szöveg</label>
                    <textarea id="newsletter_lead" name="newsletter_lead" rows="2" maxlength="400"><?= h($settings['newsletter_lead']) ?></textarea>
                </div>
                <div class="form-row form-row-2">
                    <div class="form-group">
                        <label for="newsletter_cta_label">Gomb felirata</label>
                        <input type="text" id="newsletter_cta_label" name="newsletter_cta_label" maxlength="80" value="<?= h($settings['newsletter_cta_label']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="newsletter_cta_url">Gomb hivatkozása</label>
                        <input type="text" id="newsletter_cta_url" name="newsletter_cta_url" maxlength="500" value="<?= h($settings['newsletter_cta_url']) ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Mentés</button>
            </form>
        <?php elseif ($tab === 'news'): ?>
            <p class="text-muted" style="margin-top:0">A kezdőoldal tetején körgetés nélkül 3 gyorshír jelenik meg. A pipa a sorrend elejére teszi.</p>
            <div class="events-list-actions" style="margin-bottom:1rem">
                <a href="<?= h(latinfo_home_edit_url('tab=news&new=1')) ?>" class="btn btn-primary btn-sm">+ Új kiemelt hír</a>
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
                    'sort_order' => count($newsRows) + 1,
                ];
                ?>
                <form method="post" action="<?= h(latinfo_home_edit_url('tab=news')) ?>" class="card" style="box-shadow:none;border:1px solid var(--border);margin-bottom:1.5rem">
                    <?= csrf_input('latinfo_home') ?>
                    <input type="hidden" name="action" value="save_news">
                    <input type="hidden" name="tab" value="news">
                    <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                    <h3><?= (int) $n['id'] > 0 ? 'Hír szerkesztése' : 'Új kiemelt hír' ?></h3>
                    <div class="form-row form-row-2">
                        <div class="form-group">
                            <label for="news_kicker">Rovat / kicker</label>
                            <input type="text" id="news_kicker" name="kicker" maxlength="80" value="<?= h((string) $n['kicker']) ?>" placeholder="Fesztivál, Interjú…">
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
                        <input type="text" id="news_image" name="image_url" maxlength="500" value="<?= h((string) $n['image_url']) ?>" placeholder="https://… vagy /útvonal">
                        <p class="help">Opcionális. A kompakt gyorshíreken jelenleg nem jelenik meg kép.</p>
                    </div>
                    <div class="form-row form-row-2">
                        <div class="form-group">
                            <input type="hidden" name="is_hero" value="0">
                            <label class="lh-admin-check">
                                <input type="checkbox" name="is_hero" value="1"<?= !empty($n['is_hero']) ? ' checked' : '' ?>>
                                Első a 3 gyorshír között
                            </label>
                        </div>
                        <div class="form-group">
                            <input type="hidden" name="is_visible" value="0">
                            <label class="lh-admin-check">
                                <input type="checkbox" name="is_visible" value="1"<?= !empty($n['is_visible']) ? ' checked' : '' ?>>
                                Látható a kezdőoldalon
                            </label>
                        </div>
                    </div>
                    <div class="lh-admin-actions">
                        <button type="submit" class="btn btn-primary">Mentés</button>
                        <a href="<?= h(latinfo_home_edit_url('tab=news')) ?>" class="btn btn-secondary">Mégse</a>
                    </div>
                </form>
            <?php endif; ?>

            <div class="table-wrap">
                <table class="sortable-table lh-admin-table">
                    <thead>
                        <tr>
                            <th>Sorrend</th>
                            <th>Cím</th>
                            <th>Rovat</th>
                            <th>Hero</th>
                            <th>Látható</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($newsRows === []): ?>
                            <tr><td colspan="6" class="text-muted">Még nincs kiemelt hír.</td></tr>
                        <?php else: ?>
                            <?php foreach ($newsRows as $row): ?>
                                <tr>
                                    <td><?= (int) $row['sort_order'] ?></td>
                                    <td><?= h((string) $row['title']) ?></td>
                                    <td><?= h((string) $row['kicker']) ?></td>
                                    <td><?= !empty($row['is_hero']) ? 'igen' : '–' ?></td>
                                    <td><?= !empty($row['is_visible']) ? 'igen' : 'nem' ?></td>
                                    <td>
                                        <div class="lh-admin-actions">
                                            <a class="btn btn-secondary btn-sm" href="<?= h(latinfo_home_edit_url('tab=news&id=' . (int) $row['id'])) ?>">Szerkeszt</a>
                                            <form method="post" action="<?= h(latinfo_home_edit_url('tab=news')) ?>" onsubmit="return confirm('Törlöd ezt a hírt?');">
                                                <?= csrf_input('latinfo_home') ?>
                                                <input type="hidden" name="action" value="delete_news">
                                                <input type="hidden" name="tab" value="news">
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
        <?php else: ?>
            <div class="events-list-actions" style="margin-bottom:1rem">
                <a href="<?= h(latinfo_home_edit_url('tab=collectors&new=1')) ?>" class="btn btn-primary btn-sm">+ Új gyűjtő</a>
            </div>

            <?php if ($showCollectorForm): ?>
                <?php
                $c = $editingCollector ?? [
                    'id' => 0,
                    'title' => '',
                    'subtitle' => '',
                    'url' => '',
                    'image_url' => '',
                    'accent_color' => '#6D8F63',
                    'is_visible' => 1,
                    'sort_order' => count($collectorRows) + 1,
                ];
                ?>
                <form method="post" action="<?= h(latinfo_home_edit_url('tab=collectors')) ?>" class="card" style="box-shadow:none;border:1px solid var(--border);margin-bottom:1.5rem">
                    <?= csrf_input('latinfo_home') ?>
                    <input type="hidden" name="action" value="save_collector">
                    <input type="hidden" name="tab" value="collectors">
                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <h3><?= (int) $c['id'] > 0 ? 'Gyűjtő szerkesztése' : 'Új gyűjtő' ?></h3>
                    <div class="form-row form-row-2">
                        <div class="form-group">
                            <label for="col_title">Cím</label>
                            <input type="text" id="col_title" name="title" maxlength="120" required value="<?= h((string) $c['title']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="col_sort">Sorrend</label>
                            <input type="number" id="col_sort" name="sort_order" value="<?= (int) $c['sort_order'] ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="col_sub">Alcím</label>
                        <input type="text" id="col_sub" name="subtitle" maxlength="200" value="<?= h((string) $c['subtitle']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="col_url">Hivatkozás</label>
                        <input type="text" id="col_url" name="url" maxlength="500" value="<?= h((string) $c['url']) ?>" placeholder="/DJ/ vagy https://…">
                    </div>
                    <div class="form-row form-row-2">
                        <div class="form-group">
                            <label for="col_image">Kép URL</label>
                            <input type="text" id="col_image" name="image_url" maxlength="500" value="<?= h((string) $c['image_url']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="col_accent">Szín</label>
                            <input type="color" id="col_accent" name="accent_color" value="<?= h(normalize_hex_color((string) $c['accent_color'], '#6D8F63')) ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <input type="hidden" name="is_visible" value="0">
                        <label class="lh-admin-check">
                            <input type="checkbox" name="is_visible" value="1"<?= !empty($c['is_visible']) ? ' checked' : '' ?>>
                            Látható a kezdőoldalon
                        </label>
                    </div>
                    <div class="lh-admin-actions">
                        <button type="submit" class="btn btn-primary">Mentés</button>
                        <a href="<?= h(latinfo_home_edit_url('tab=collectors')) ?>" class="btn btn-secondary">Mégse</a>
                    </div>
                </form>
            <?php endif; ?>

            <div class="table-wrap">
                <table class="sortable-table lh-admin-table">
                    <thead>
                        <tr>
                            <th>Sorrend</th>
                            <th>Gyűjtő</th>
                            <th>Alcím</th>
                            <th>Látható</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($collectorRows === []): ?>
                            <tr><td colspan="5" class="text-muted">Még nincs gyűjtő.</td></tr>
                        <?php else: ?>
                            <?php foreach ($collectorRows as $row): ?>
                                <tr>
                                    <td><?= (int) $row['sort_order'] ?></td>
                                    <td>
                                        <span class="lh-swatch" style="background:<?= h(normalize_hex_color((string) $row['accent_color'], '#6D8F63')) ?>"></span>
                                        <?= h((string) $row['title']) ?>
                                    </td>
                                    <td><?= h((string) $row['subtitle']) ?></td>
                                    <td><?= !empty($row['is_visible']) ? 'igen' : 'nem' ?></td>
                                    <td>
                                        <div class="lh-admin-actions">
                                            <a class="btn btn-secondary btn-sm" href="<?= h(latinfo_home_edit_url('tab=collectors&id=' . (int) $row['id'])) ?>">Szerkeszt</a>
                                            <form method="post" action="<?= h(latinfo_home_edit_url('tab=collectors')) ?>" onsubmit="return confirm('Törlöd ezt a gyűjtőt?');">
                                                <?= csrf_input('latinfo_home') ?>
                                                <input type="hidden" name="action" value="delete_collector">
                                                <input type="hidden" name="tab" value="collectors">
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
    <?php endif; ?>
</div>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
