<?php
declare(strict_types=1);

/**
 * CMS cikkek megtekintés-statisztika.
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
requireLogin();

$db = getDb();
cms_ensure_schema($db);

$params = cms_stats_params_from_request($_GET);
$data = cms_stats_summary($db, $params);
$counts = cms_posts_status_counts($db);

$pageTitle = 'CMS statisztika';
$mainContentClass = 'main-content main-content--fullwidth';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<div class="events-stat-page-head">
    <div>
        <h1 class="events-stat-page-title">CMS statisztika</h1>
        <p class="events-stat-page-lead">
            Cikk megtekintések: <?= h($params['date_from']) ?> – <?= h($params['date_to']) ?>.
            Katalógus: <?= (int) $counts['total'] ?> cikk (<?= (int) $counts['publish'] ?> publikus).
        </p>
    </div>
    <div class="events-stat-page-actions">
        <a href="<?= h(cms_url('posts.php')) ?>" class="btn btn-secondary btn-sm">Cikkek</a>
        <a href="<?= h(nextgen_url('events/events_stat.php')) ?>" class="btn btn-secondary btn-sm">Áttekintés</a>
    </div>
</div>

<form method="get" class="card cms-filters-bar cms-filters-bar--stat">
    <div class="form-group">
        <label for="date_from">Ettől</label>
        <input type="date" id="date_from" name="date_from" value="<?= h($params['date_from']) ?>">
    </div>
    <div class="form-group">
        <label for="date_to">Eddig</label>
        <input type="date" id="date_to" name="date_to" value="<?= h($params['date_to']) ?>">
    </div>
    <div class="form-group">
        <label for="visitor">Látogató</label>
        <select id="visitor" name="visitor">
            <option value="human"<?= $params['visitor'] === 'human' ? ' selected' : '' ?>>Ember</option>
            <option value="bot"<?= $params['visitor'] === 'bot' ? ' selected' : '' ?>>Bot</option>
            <option value="all"<?= $params['visitor'] === 'all' ? ' selected' : '' ?>>Mind</option>
        </select>
    </div>
    <div class="form-group cms-filters-bar__actions">
        <label class="visually-hidden" for="cms-stat-filter-submit">Szűrés</label>
        <button type="submit" id="cms-stat-filter-submit" class="btn btn-primary">Szűrés</button>
    </div>
</form>

<div class="events-stat-grid" aria-label="CMS összesítők">
    <div class="events-stat-card">
        <span class="events-stat-card__label">Megtekintések</span>
        <span class="events-stat-card__value"><?= (int) $data['total'] ?></span>
        <span class="events-stat-card__hint"><?= (int) $data['human'] ?> ember · <?= (int) $data['bot'] ?> bot</span>
    </div>
    <div class="events-stat-card">
        <span class="events-stat-card__label">Publikus cikkek</span>
        <span class="events-stat-card__value"><?= (int) $counts['publish'] ?></span>
        <span class="events-stat-card__hint"><?= (int) $counts['draft'] ?> draft · <?= (int) $counts['other'] ?> egyéb</span>
    </div>
</div>

<div class="card" style="margin-top:1rem;">
    <h2>Top cikkek</h2>
    <table class="table">
        <thead>
            <tr>
                <th>Cikk</th>
                <th>Megtekintések</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php if ($data['top_posts'] === []): ?>
            <tr><td colspan="3" class="help">Nincs adat a kiválasztott időszakra.</td></tr>
        <?php else: ?>
            <?php foreach ($data['top_posts'] as $row): ?>
            <tr>
                <td>
                    <strong><?= h($row['title']) ?></strong>
                    <div class="help"><code><?= h($row['slug']) ?></code></div>
                </td>
                <td><?= (int) $row['views'] ?></td>
                <td>
                    <a class="btn btn-secondary btn-sm" href="<?= h(cms_url('szerkeszt.php?id=' . (int) $row['post_id'])) ?>">Szerkeszt</a>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($data['by_day'] !== []): ?>
<div class="card" style="margin-top:1rem;">
    <h2>Napi bontás</h2>
    <table class="table">
        <thead>
            <tr><th>Nap</th><th>Összesen</th><th>Ember</th></tr>
        </thead>
        <tbody>
        <?php foreach ($data['by_day'] as $d): ?>
            <tr>
                <td><?= h($d['day']) ?></td>
                <td><?= (int) $d['total'] ?></td>
                <td><?= (int) $d['human'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
