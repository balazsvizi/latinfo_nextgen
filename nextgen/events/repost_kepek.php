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
        <?php
        $visible = [];
        foreach ($rows as $row) {
            $imgUrl = events_repost_images_featured_url((string) ($row['event_featured_image_url'] ?? ''));
            if ($imgUrl === '') {
                continue;
            }
            $visible[] = [
                'url' => $imgUrl,
                'name' => (string) ($row['event_name'] ?? ''),
            ];
        }
        ?>
        <?php if ($visible === []): ?>
            <p class="help events-repost-kepek__empty">Nincs megjeleníthető kép ebben az intervallumban.</p>
        <?php else: ?>
            <?php
            $brandOg = events_public_brand_og_image();
            $brandUrl = (string) ($brandOg['url'] ?? '');
            $n = count($visible);
            $span3 = $n % 3 === 0 ? 3 : (3 - ($n % 3));
            $span2 = $n % 2 === 0 ? 2 : 1;
            ?>
            <div
                class="events-repost-kepek__montage"
                aria-label="Eseményképek montázs"
                style="--repost-span-3: <?= (int) $span3 ?>; --repost-span-2: <?= (int) $span2 ?>;"
            >
                <?php foreach ($visible as $item): ?>
                    <figure class="events-repost-kepek__shot">
                        <img
                            src="<?= h($item['url']) ?>"
                            alt="<?= h($item['name']) ?>"
                            loading="lazy"
                            decoding="async"
                            referrerpolicy="no-referrer"
                        >
                    </figure>
                <?php endforeach; ?>
                <?php if ($brandUrl !== ''): ?>
                    <figure class="events-repost-kepek__shot events-repost-kepek__shot--brand">
                        <img
                            src="<?= h($brandUrl) ?>"
                            alt="Latinfo.hu"
                            loading="lazy"
                            decoding="async"
                        >
                    </figure>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
