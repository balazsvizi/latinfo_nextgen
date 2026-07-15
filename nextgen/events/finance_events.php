<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/finance_admin.php';
requireLogin();

$db = getDb();
events_organizer_finance_ensure_schema($db);

$listLimitParsed = events_admin_list_limit_from_get(EVENTS_ADMIN_EVENTS_LIST_DEFAULT_LIMIT);
$list_limit = $listLimitParsed['sql_limit'];
$listLimitValue = $listLimitParsed['value'];
$listTotalInDb = events_admin_table_total_count($db, 'events_calendar_events');

$filters = events_finance_admin_filters_from_request();
$order = $filters['order'];
$dir_param = $filters['dir_param'];
$get_params = events_admin_list_limit_merge_get_params($filters['get_params'], $listLimitValue);

$rows = events_finance_admin_fetch($db, $filters, $list_limit);
$listDisplayedCount = count($rows);

$hasFilters = $filters['f_q'] !== ''
    || $filters['f_id'] !== ''
    || $filters['f_organizer'] !== ''
    || $filters['f_payer'] !== ''
    || $filters['f_note'] !== ''
    || $filters['f_cost_from'] !== ''
    || $filters['f_cost_to'] !== ''
    || $filters['f_fee'] !== ''
    || $filters['f_has_fee'] !== ''
    || $filters['f_status'] !== ''
    || $filters['f_start_from'] !== ''
    || $filters['f_start_to'] !== '';

$colspan = 10;
$editBase = events_url('szerkeszt.php?id=');

$pageTitle = 'Finance – Események';
$mainContentClass = 'main-content main-content--fullwidth';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="card events-admin-card">
    <form method="get" action="<?= h(events_url('finance_events.php')) ?>" class="events-admin-form" id="finance-events-filter-form">
        <input type="hidden" name="order" value="<?= h($order) ?>">
        <input type="hidden" name="dir" value="<?= h($dir_param) ?>">

        <div class="events-list-head">
            <div class="events-list-head__start">
                <h1 class="events-list-title card-title" style="margin:0;">Finance – Események</h1>
                <?php
                $listLimitInForm = true;
                $listLimitStandalone = true;
                $listLimitDefault = EVENTS_ADMIN_EVENTS_LIST_DEFAULT_LIMIT;
                require __DIR__ . '/partials/admin_list_display_limit.php';
                ?>
            </div>
            <div class="events-list-actions">
                <a href="<?= h(events_url('finance_events.php')) ?>" class="btn btn-secondary">Szűrők és rendezés törlése</a>
                <a href="<?= h(events_url('finance.php')) ?>" class="btn btn-secondary">Dashboard</a>
            </div>
        </div>

        <section class="events-filters-shell" aria-label="Szűrők">
            <div class="events-filters-grid">
                <div class="events-filter-field">
                    <label class="events-filter-label<?= $filters['f_q'] !== '' ? ' events-filter-label--active' : '' ?>" for="fin-f-q">Keresés</label>
                    <input class="events-filter-input" type="search" name="f_q" id="fin-f-q" value="<?= h($filters['f_q']) ?>" placeholder="Név, ID, megjegyzés…" autocomplete="off">
                </div>
                <div class="events-filter-field">
                    <label class="events-filter-label<?= $filters['f_id'] !== '' ? ' events-filter-label--active' : '' ?>" for="fin-f-id">ID</label>
                    <input class="events-filter-input" type="text" name="f_id" id="fin-f-id" value="<?= h($filters['f_id']) ?>" placeholder="Pontos vagy részlet" inputmode="numeric" autocomplete="off">
                </div>
                <div class="events-filter-field">
                    <label class="events-filter-label<?= $filters['f_organizer'] !== '' ? ' events-filter-label--active' : '' ?>" for="fin-f-organizer">Szervező</label>
                    <input class="events-filter-input" type="search" name="f_organizer" id="fin-f-organizer" value="<?= h($filters['f_organizer']) ?>" placeholder="Név részlet…" autocomplete="off">
                </div>
                <div class="events-filter-field">
                    <label class="events-filter-label<?= $filters['f_payer'] !== '' ? ' events-filter-label--active' : '' ?>" for="fin-f-payer">Ki fizeti</label>
                    <input class="events-filter-input" type="search" name="f_payer" id="fin-f-payer" value="<?= h($filters['f_payer']) ?>" placeholder="Név részlet…" autocomplete="off">
                </div>
                <div class="events-filter-field">
                    <label class="events-filter-label<?= $filters['f_note'] !== '' ? ' events-filter-label--active' : '' ?>" for="fin-f-note">Megjegyzés</label>
                    <input class="events-filter-input" type="search" name="f_note" id="fin-f-note" value="<?= h($filters['f_note']) ?>" placeholder="Szöveg részlet…" autocomplete="off">
                </div>
                <div class="events-filter-field">
                    <label class="events-filter-label<?= $filters['f_cost_from'] !== '' ? ' events-filter-label--active' : '' ?>" for="fin-f-cost-from">Belépő tól</label>
                    <input class="events-filter-input" type="text" name="f_cost_from" id="fin-f-cost-from" value="<?= h($filters['f_cost_from']) ?>" placeholder="Pontos Ft" inputmode="decimal" autocomplete="off">
                </div>
                <div class="events-filter-field">
                    <label class="events-filter-label<?= $filters['f_cost_to'] !== '' ? ' events-filter-label--active' : '' ?>" for="fin-f-cost-to">Belépő ig</label>
                    <input class="events-filter-input" type="text" name="f_cost_to" id="fin-f-cost-to" value="<?= h($filters['f_cost_to']) ?>" placeholder="Pontos Ft" inputmode="decimal" autocomplete="off">
                </div>
                <div class="events-filter-field">
                    <label class="events-filter-label<?= $filters['f_fee'] !== '' ? ' events-filter-label--active' : '' ?>" for="fin-f-fee">Szervezői díj</label>
                    <input class="events-filter-input" type="text" name="f_fee" id="fin-f-fee" value="<?= h($filters['f_fee']) ?>" placeholder="Pontos Ft" inputmode="decimal" autocomplete="off">
                </div>
                <div class="events-filter-field events-filter-field--status">
                    <label class="events-filter-label<?= $filters['f_has_fee'] !== '' ? ' events-filter-label--active' : '' ?>" for="fin-f-has-fee">Díj státusz</label>
                    <div class="events-filter-select-wrap">
                        <select class="events-filter-select" name="f_has_fee" id="fin-f-has-fee">
                            <option value=""<?= $filters['f_has_fee'] === '' ? ' selected' : '' ?>>Összes</option>
                            <option value="yes"<?= $filters['f_has_fee'] === 'yes' ? ' selected' : '' ?>>Van díj</option>
                            <option value="no"<?= $filters['f_has_fee'] === 'no' ? ' selected' : '' ?>>Nincs díj</option>
                        </select>
                    </div>
                </div>
                <div class="events-filter-field events-filter-field--status">
                    <label class="events-filter-label<?= $filters['f_status'] !== '' ? ' events-filter-label--active' : '' ?>" for="fin-f-status">Státusz</label>
                    <div class="events-filter-select-wrap">
                        <select class="events-filter-select" name="f_status" id="fin-f-status">
                            <option value="">Összes</option>
                            <?php foreach (events_allowed_post_statuses() as $st): ?>
                                <option value="<?= h($st) ?>"<?= $filters['f_status'] === $st ? ' selected' : '' ?>><?= h(events_post_status_label($st)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="events-filter-field">
                    <label class="events-filter-label<?= $filters['f_start_from'] !== '' ? ' events-filter-label--active' : '' ?>" for="fin-f-start-from">Kezdő dátumtól</label>
                    <input class="events-filter-input" type="date" name="f_start_from" id="fin-f-start-from" value="<?= h($filters['f_start_from']) ?>">
                </div>
                <div class="events-filter-field">
                    <label class="events-filter-label<?= $filters['f_start_to'] !== '' ? ' events-filter-label--active' : '' ?>" for="fin-f-start-to">Kezdő dátumig</label>
                    <input class="events-filter-input" type="date" name="f_start_to" id="fin-f-start-to" value="<?= h($filters['f_start_to']) ?>">
                </div>
            </div>
        </section>

        <div class="table-wrap events-admin-table-wrap">
            <table class="sortable-table events-admin-table events-finance-table">
                <thead>
                    <tr>
                        <th><?= sort_th('ID', 'id', $order, $dir_param, $get_params) ?></th>
                        <th><?= sort_th('Kezdő dátum', 'start', $order, $dir_param, $get_params) ?></th>
                        <th><?= sort_th('Esemény', 'name', $order, $dir_param, $get_params) ?></th>
                        <th><?= sort_th('Szervező', 'organizer', $order, $dir_param, $get_params) ?></th>
                        <th class="th-num"><?= sort_th('Belépő tól', 'cost_from', $order, $dir_param, $get_params) ?></th>
                        <th class="th-num"><?= sort_th('Belépő ig', 'cost_to', $order, $dir_param, $get_params) ?></th>
                        <th class="th-num"><?= sort_th('Szervezői díj', 'fee', $order, $dir_param, $get_params) ?></th>
                        <th><?= sort_th('Ki fizeti', 'payer', $order, $dir_param, $get_params) ?></th>
                        <th><?= sort_th('Megjegyzés', 'note', $order, $dir_param, $get_params) ?></th>
                        <th><?= sort_th('Státusz', 'status', $order, $dir_param, $get_params) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="<?= (int) $colspan ?>">
                                <?php if ($hasFilters): ?>
                                    Nincs a szűrésnek megfelelő esemény.
                                <?php else: ?>
                                    Nincs esemény.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $eid = (int) ($r['id'] ?? 0);
                            $editUrl = $editBase . $eid;
                            $feeRaw = $r['finance_organizer_fee'] ?? null;
                            ?>
                            <tr>
                                <td><?= $eid ?></td>
                                <td>
                                    <a class="events-cell-edit" href="<?= h($editUrl) ?>"><?= h(events_finance_format_start_date(isset($r['event_start']) ? (string) $r['event_start'] : null)) ?></a>
                                </td>
                                <td class="venues-td-name">
                                    <a class="events-cell-edit" href="<?= h($editUrl) ?>"><?= h((string) ($r['event_name'] ?? '')) ?></a>
                                </td>
                                <td><?= h((string) ($r['organizer_name'] ?? '') !== '' ? (string) $r['organizer_name'] : '—') ?></td>
                                <td class="td-num"><?= h(events_finance_format_money($r['event_cost_from'] ?? null)) ?></td>
                                <td class="td-num"><?= h(events_finance_format_money($r['event_cost_to'] ?? null)) ?></td>
                                <td class="td-num"><?= h(events_finance_format_money($feeRaw)) ?></td>
                                <td><?= h((string) ($r['payer_name'] ?? '') !== '' ? (string) $r['payer_name'] : '—') ?></td>
                                <td class="events-finance-note-cell"><?= h(trim((string) ($r['finance_note'] ?? '')) !== '' ? (string) $r['finance_note'] : '—') ?></td>
                                <td><?= h(events_post_status_label((string) ($r['event_status'] ?? ''))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>
<script>
(function () {
    var form = document.getElementById('finance-events-filter-form');
    if (!form) return;
    var debounceTimer = null;
    function submitForm() {
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    }
    form.querySelectorAll('.events-filter-select, input[type="date"]').forEach(function (el) {
        el.addEventListener('change', submitForm);
    });
    form.querySelectorAll('input.events-filter-input[type="text"], input.events-filter-input[type="search"]').forEach(function (el) {
        el.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(submitForm, 450);
        });
    });
})();
</script>
<?php require __DIR__ . '/partials/admin_list_display_limit_script.php'; ?>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
