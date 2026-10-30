<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/repost_images.php';
requireLogin();

$db = getDb();
$range = events_repost_images_range_from_request();
if ($range === null) {
    flash('error', 'Érvénytelen dátum. Használd a YYYY-MM-DD formátumot.');
    redirect(events_url('repost_kepek.php'));
}

$from = $range['from'];
$to = $range['to'];
$presets = events_repost_images_presets();
$rows = events_repost_images_fetch($db, $from, $to);
$brandLabel = defined('SITE_NAME') ? (string) SITE_NAME : 'Latinfo.hu';
$editBase = events_url('szerkeszt.php?id=');
$selfUrl = events_url('repost_kepek.php');

$rangeLabel = $from === $to
    ? $from
    : $from . ' – ' . $to;

$pageTitle = 'Repost képek';
$mainContentClass = 'main-content main-content--fullwidth';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="card events-admin-card events-repost-kepek">
    <div class="events-list-head">
        <div class="events-list-head__start">
            <h1 class="events-list-title card-title" style="margin:0;">Repost képek</h1>
            <p class="help events-repost-kepek__count" style="margin:0;">
                <?= count($rows) ?> kép · <?= h($rangeLabel) ?>
            </p>
        </div>
        <div class="events-list-actions">
            <a href="<?= h($selfUrl) ?>" class="btn btn-secondary">Holnap (alap)</a>
        </div>
    </div>

    <p class="help events-repost-kepek__intro">
        A kivágható blokk a kép + alatta a <strong><?= h($brandLabel) ?></strong> sáv.
        A cím és a dátum csak navigációhoz van — ne kerüljön a kivágásba.
    </p>

    <form method="get" action="<?= h($selfUrl) ?>" class="events-repost-kepek__filters" id="events-repost-filter-form">
        <div class="events-filters-grid events-repost-kepek__filter-grid">
            <div class="events-filter-field">
                <label class="events-filter-label" for="repost-from">Tól</label>
                <input class="events-filter-input events-filter-input--date" type="date" name="from" id="repost-from" value="<?= h($from) ?>" required>
            </div>
            <div class="events-filter-field">
                <label class="events-filter-label" for="repost-to">Ig</label>
                <input class="events-filter-input events-filter-input--date" type="date" name="to" id="repost-to" value="<?= h($to) ?>" required>
            </div>
            <div class="events-filter-field events-repost-kepek__filter-actions">
                <label class="events-filter-label" aria-hidden="true">&nbsp;</label>
                <button type="submit" class="btn btn-primary">Mutasd</button>
            </div>
        </div>
        <div class="events-repost-kepek__presets" role="group" aria-label="Gyors intervallumok">
            <?php foreach ($presets as $preset): ?>
                <?php
                $isActive = $from === $preset['from'] && $to === $preset['to'];
                $href = $selfUrl . '?from=' . rawurlencode($preset['from']) . '&to=' . rawurlencode($preset['to']);
                ?>
                <a
                    class="btn btn-secondary btn-sm<?= $isActive ? ' is-active' : '' ?>"
                    href="<?= h($href) ?>"
                    <?= $isActive ? 'aria-current="page"' : '' ?>
                ><?= h($preset['label']) ?></a>
            <?php endforeach; ?>
        </div>
    </form>

    <?php if ($rows === []): ?>
        <p class="help events-repost-kepek__empty">Nincs kiemelt képes, közzétett / előzetes buli ebben az intervallumban.</p>
    <?php else: ?>
        <ul class="events-repost-kepek__grid" role="list">
            <?php foreach ($rows as $row): ?>
                <?php
                $imgUrl = events_repost_images_featured_url((string) ($row['event_featured_image_url'] ?? ''));
                if ($imgUrl === '') {
                    continue;
                }
                $name = (string) ($row['event_name'] ?? '');
                $when = events_repost_images_when_label($row);
                $venueBits = array_filter([
                    trim((string) ($row['venue_city'] ?? '')),
                    trim((string) ($row['venue_name'] ?? '')),
                ], static fn (string $p): bool => $p !== '');
                $venueLine = implode(' · ', $venueBits);
                $eventId = (int) ($row['id'] ?? 0);
                ?>
                <li class="events-repost-kepek__item">
                    <div class="events-repost-kepek__meta">
                        <a class="events-repost-kepek__title" href="<?= h($editBase . $eventId) ?>" title="Szerkesztés">
                            <?= h($name) ?>
                        </a>
                        <?php if ($when !== ''): ?>
                            <span class="events-repost-kepek__when"><?= h($when) ?></span>
                        <?php endif; ?>
                        <?php if ($venueLine !== ''): ?>
                            <span class="events-repost-kepek__venue"><?= h($venueLine) ?></span>
                        <?php endif; ?>
                    </div>
                    <figure class="events-repost-kepek__card" aria-label="<?= h($name) ?> – kivágható megosztókép">
                        <div class="events-repost-kepek__media">
                            <img
                                src="<?= h($imgUrl) ?>"
                                alt=""
                                loading="lazy"
                                decoding="async"
                                referrerpolicy="no-referrer"
                            >
                        </div>
                        <figcaption class="events-repost-kepek__brand">
                            <span class="events-repost-kepek__brand-text"><?= h($brandLabel) ?></span>
                        </figcaption>
                    </figure>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
