<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/admin_event_filters.php';
require_once __DIR__ . '/lib/organizers_admin.php';
requireLogin();

$db = getDb();

$listLimitParsed = events_admin_list_limit_from_get();
$list_limit = $listLimitParsed['sql_limit'];
$listLimitValue = $listLimitParsed['value'];
$listTotalInDb = events_admin_table_total_count($db, 'events_organizers');

$filters = events_organizers_admin_filters_from_request();
$f_q = $filters['f_q'];
$f_id = $filters['f_id'];
$f_events = $filters['f_events'];
$f_published = $filters['f_published'];
$order = $filters['order'];
$dir_param = $filters['dir_param'];
$get_params = events_admin_list_limit_merge_get_params($filters['get_params'], $listLimitValue);

$rows = events_organizers_admin_fetch($db, $filters, $list_limit);
$listDisplayedCount = count($rows);

$hasFilters = $f_q !== '' || $f_id !== '' || $f_events !== '' || $f_published !== '';
$colspan = 7;

$pageTitle = 'Szervezők';
$mainContentClass = 'main-content main-content--fullwidth';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="card events-admin-card">
    <form method="get" action="<?= h(events_url('organizers.php')) ?>" class="events-admin-form" id="organizers-filter-form">
        <input type="hidden" name="order" value="<?= h($order) ?>">
        <input type="hidden" name="dir" value="<?= h($dir_param) ?>">

        <div class="events-list-head">
            <div class="events-list-head__start">
                <h1 class="events-list-title card-title" style="margin:0;">Esemény szervezők</h1>
                <?php
                $listLimitInForm = true;
                $listLimitStandalone = true;
                require __DIR__ . '/partials/admin_list_display_limit.php';
                ?>
            </div>
            <div class="events-list-actions">
                <a href="<?= h(events_url('organizers.php')) ?>" class="btn btn-secondary">Szűrők és rendezés törlése</a>
                <a href="<?= h(events_url('organizer_letrehoz.php')) ?>" class="btn btn-primary">Új szervező</a>
                <a href="<?= h(events_url('events_admin.php')) ?>" class="btn btn-secondary">Események</a>
            </div>
        </div>

        <section class="events-filters-shell" aria-label="Szűrők">
            <div class="events-filters-grid">
                <div class="events-filter-field">
                    <label class="events-filter-label<?= $f_q !== '' ? ' events-filter-label--active' : '' ?>" for="org-f-q">Keresés</label>
                    <input class="events-filter-input" type="search" name="f_q" id="org-f-q" value="<?= h($f_q) ?>" placeholder="Név vagy ID…" autocomplete="off">
                </div>
                <div class="events-filter-field">
                    <label class="events-filter-label<?= $f_id !== '' ? ' events-filter-label--active' : '' ?>" for="org-f-id">ID</label>
                    <input class="events-filter-input" type="text" name="f_id" id="org-f-id" value="<?= h($f_id) ?>" placeholder="Pontos vagy részlet" inputmode="numeric" autocomplete="off">
                </div>
                <div class="events-filter-field events-filter-field--status">
                    <label class="events-filter-label<?= $f_events !== '' ? ' events-filter-label--active' : '' ?>" for="org-f-events">Események</label>
                    <div class="events-filter-select-wrap">
                        <select class="events-filter-select" name="f_events" id="org-f-events">
                            <option value=""<?= $f_events === '' ? ' selected' : '' ?>>Összes</option>
                            <option value="yes"<?= $f_events === 'yes' ? ' selected' : '' ?>>Van eseménye</option>
                            <option value="no"<?= $f_events === 'no' ? ' selected' : '' ?>>Nincs eseménye</option>
                        </select>
                    </div>
                </div>
                <div class="events-filter-field events-filter-field--status">
                    <label class="events-filter-label<?= $f_published !== '' ? ' events-filter-label--active' : '' ?>" for="org-f-published">Közzétett</label>
                    <div class="events-filter-select-wrap">
                        <select class="events-filter-select" name="f_published" id="org-f-published">
                            <option value=""<?= $f_published === '' ? ' selected' : '' ?>>Összes</option>
                            <option value="yes"<?= $f_published === 'yes' ? ' selected' : '' ?>>Van közzétett eseménye</option>
                            <option value="no"<?= $f_published === 'no' ? ' selected' : '' ?>>Nincs közzétett eseménye</option>
                        </select>
                    </div>
                </div>
            </div>
        </section>

        <div class="table-wrap events-admin-table-wrap">
            <table class="sortable-table events-admin-table">
                <thead>
                    <tr>
                        <th><?= sort_th('ID', 'id', $order, $dir_param, $get_params) ?></th>
                        <th><?= sort_th('Név', 'name', $order, $dir_param, $get_params) ?></th>
                        <th class="th-num"><?= sort_th('Események', 'events', $order, $dir_param, $get_params) ?></th>
                        <th class="th-num"><?= sort_th('Közzétéve', 'published', $order, $dir_param, $get_params) ?></th>
                        <th class="th-num"><?= sort_th('Közelgő', 'upcoming', $order, $dir_param, $get_params) ?></th>
                        <th><?= sort_th('Utolsó esemény', 'last_event', $order, $dir_param, $get_params) ?></th>
                        <th><?= sort_th('Következő', 'next_event', $order, $dir_param, $get_params) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="<?= (int) $colspan ?>">
                                <?php if ($hasFilters): ?>
                                    Nincs a szűrésnek megfelelő szervező.
                                <?php else: ?>
                                    Nincs szervező. Adj hozzá újat, vagy importálj CSV-ből.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $oid = (int) $r['id'];
                            $name = (string) ($r['name'] ?? '');
                            $editUrl = events_url('organizer_szerkeszt.php?id=') . $oid;
                            $pubUrl = events_url('organizer.php?id=') . $oid;
                            $eventsUrl = events_organizers_admin_events_filter_url($name);
                            $eventCount = (int) ($r['event_count'] ?? 0);
                            $publishedCount = (int) ($r['published_count'] ?? 0);
                            $upcomingCount = (int) ($r['upcoming_count'] ?? 0);
                            ?>
                            <tr>
                                <td><?= $oid ?></td>
                                <td class="venues-td-name">
                                    <span class="venues-name-with-action">
                                        <a class="events-cell-edit" href="<?= h($editUrl) ?>"><?= h($name) ?></a>
                                        <a href="<?= h($pubUrl) ?>" class="events-icon-action" title="Nyilvános szervező oldal (új lap)" aria-label="Nyilvános szervező oldal új lapon" target="_blank" rel="noopener">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                        </a>
                                    </span>
                                </td>
                                <td class="td-num">
                                    <?php if ($eventCount > 0): ?>
                                        <a href="<?= h($eventsUrl) ?>" class="events-cell-link" title="Események szűrése erre a szervezőre"><?= (int) $eventCount ?></a>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="td-num">
                                    <?php if ($publishedCount > 0): ?>
                                        <?= (int) $publishedCount ?>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="td-num">
                                    <?php if ($upcomingCount > 0): ?>
                                        <?= (int) $upcomingCount ?>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= h(events_organizers_admin_format_datetime(isset($r['last_event_at']) ? (string) $r['last_event_at'] : null)) ?></td>
                                <td><?= h(events_organizers_admin_format_datetime(isset($r['next_event_at']) ? (string) $r['next_event_at'] : null)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>
<?php require __DIR__ . '/partials/organizers_filter_script.php'; ?>
<?php require __DIR__ . '/partials/admin_list_display_limit_script.php'; ?>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
