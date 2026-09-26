<?php
declare(strict_types=1);

/**
 * CMS cikklista (admin).
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/events/lib/event_request.php';
requireLogin();

$db = getDb();
cms_ensure_schema($db);

$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? 'all')),
    'theme_id' => (int) ($_GET['theme_id'] ?? 0),
    'tag_id' => (int) ($_GET['tag_id'] ?? 0),
];
if ($filters['status'] !== 'all' && !in_array($filters['status'], cms_allowed_statuses(), true)) {
    $filters['status'] = 'all';
}

$posts = cms_posts_list($db, $filters, 200, 0);
$themes = cms_themes_options($db, false);
$tags = function_exists('events_load_tag_options') ? events_load_tag_options($db) : [];
$counts = cms_posts_status_counts($db);

$pageTitle = 'CMS cikkek';
$mainContentClass = 'main-content main-content--fullwidth';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="card">
    <div class="events-stat-page-head" style="margin-bottom:1rem;">
        <div>
            <h1 class="events-stat-page-title">Cikkek</h1>
            <p class="events-stat-page-lead">
                <?= (int) $counts['total'] ?> cikk ·
                <?= (int) $counts['publish'] ?> publikus ·
                <?= (int) $counts['draft'] ?> draft ·
                <?= (int) $counts['other'] ?> egyéb
            </p>
        </div>
        <div class="events-stat-page-actions">
            <a href="<?= h(cms_url('letrehoz.php')) ?>" class="btn btn-primary">Új cikk</a>
            <a href="<?= h(cms_url('temak.php')) ?>" class="btn btn-secondary btn-sm">Témák</a>
            <a href="<?= h(cms_url('stat.php')) ?>" class="btn btn-secondary btn-sm">Stat</a>
        </div>
    </div>

    <form method="get" class="cms-filters-bar">
        <div class="form-group">
            <label for="cms-q">Keresés</label>
            <input type="search" id="cms-q" name="q" value="<?= h($filters['q']) ?>" placeholder="Cím, slug…">
        </div>
        <div class="form-group">
            <label for="cms-status">Státusz</label>
            <select id="cms-status" name="status">
                <option value="all"<?= $filters['status'] === 'all' ? ' selected' : '' ?>>Mind</option>
                <?php foreach (cms_allowed_statuses() as $st): ?>
                <option value="<?= h($st) ?>"<?= $filters['status'] === $st ? ' selected' : '' ?>><?= h(cms_status_label($st)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="cms-theme">Téma</label>
            <select id="cms-theme" name="theme_id">
                <option value="0">Mind</option>
                <?php foreach ($themes as $tid => $tname): ?>
                <option value="<?= (int) $tid ?>"<?= $filters['theme_id'] === (int) $tid ? ' selected' : '' ?>><?= h($tname) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($tags !== []): ?>
        <div class="form-group">
            <label for="cms-tag">Címke</label>
            <select id="cms-tag" name="tag_id">
                <option value="0">Mind</option>
                <?php foreach ($tags as $tgId => $tgName): ?>
                <option value="<?= (int) $tgId ?>"<?= $filters['tag_id'] === (int) $tgId ? ' selected' : '' ?>><?= h($tgName) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="form-group cms-filters-bar__actions">
            <label class="visually-hidden" for="cms-filter-submit">Szűrés</label>
            <button type="submit" id="cms-filter-submit" class="btn btn-secondary">Szűrés</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Cím</th>
                    <th>Téma</th>
                    <th>Státusz</th>
                    <th>Frissítve</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($posts === []): ?>
                <tr><td colspan="5" class="help">Nincs találat.</td></tr>
            <?php else: ?>
                <?php foreach ($posts as $p): ?>
                <?php
                    $pid = (int) ($p['id'] ?? 0);
                    $status = (string) ($p['status'] ?? '');
                    $slug = (string) ($p['slug'] ?? '');
                ?>
                <tr>
                    <td>
                        <a href="<?= h(cms_url('szerkeszt.php?id=' . $pid)) ?>"><strong><?= h((string) ($p['title'] ?? '')) ?></strong></a>
                        <?php if ($slug !== ''): ?>
                            <div class="help"><code><?= h($slug) ?></code></div>
                        <?php endif; ?>
                    </td>
                    <td><?= h((string) ($p['theme_name'] ?? '—')) ?></td>
                    <td><span class="<?= h(cms_status_badge_class($status)) ?>"><?= h(cms_status_label($status)) ?></span></td>
                    <td><?= h((string) ($p['updated_at'] ?? '')) ?></td>
                    <td class="table-actions">
                        <a class="btn btn-secondary btn-sm" href="<?= h(cms_url('szerkeszt.php?id=' . $pid)) ?>">Szerkeszt</a>
                        <a class="btn btn-secondary btn-sm" href="<?= h(cms_url('letrehoz.php?copy_from=' . $pid)) ?>">Másolás</a>
                        <?php if ($status === cms_status_publish() && $slug !== ''): ?>
                        <a class="btn btn-secondary btn-sm" href="<?= h(cms_public_post_url($slug)) ?>" target="_blank" rel="noopener">Megnyitás</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
