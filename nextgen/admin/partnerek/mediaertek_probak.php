<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/init.php';
require_once dirname(__DIR__, 2) . '/lib/partner/partners.php';
require_once dirname(__DIR__, 2) . '/lib/partner/media_value_trials.php';
require_once dirname(__DIR__, 2) . '/events/lib/event_edit_stats.php';
requireLogin();

$db = getDb();
nextgen_partner_ensure_media_value_trials_schema($db);

$partnerFilter = filter_var($_GET['partner_id'] ?? null, FILTER_VALIDATE_INT);
$partnerFilter = $partnerFilter !== false && $partnerFilter > 0 ? (int) $partnerFilter : null;

$trials = nextgen_partner_media_value_trials_list($db, $partnerFilter, 300);
$partners = nextgen_partners_table_ready($db) ? nextgen_partners_list($db, null, 'nev', 'asc') : [];

$pageTitle = 'Médiaérték próbák';
require_once dirname(__DIR__, 2) . '/partials/header.php';
?>
<div class="card">
    <div class="toolbar partners-admin-toolbar" style="justify-content:space-between;gap:1rem;flex-wrap:wrap;">
        <div>
            <h2 style="margin:0;">Médiaérték egységár-próbák</h2>
            <p class="help" style="margin:0.35rem 0 0;">
                A partnerportálon megadott saját megtekintés / átkattintás Ft-ok naplója.
                Alapértelmezés (DPV / Click-out): <?= (int) events_edit_stats_media_value_page_view_ft() ?> Ft + <?= (int) events_edit_stats_media_value_intent_click_ft() ?> Ft.
            </p>
        </div>
        <div class="toolbar">
            <a href="<?= h(nextgen_url('admin/partnerek/')) ?>" class="btn btn-secondary btn-sm">← Partnerek</a>
        </div>
    </div>

    <?php if (!nextgen_partner_media_value_trials_table_ready($db)): ?>
        <p class="alert alert-warning">A próba-napló tábla nem elérhető.</p>
    <?php else: ?>
        <form method="get" class="partners-filter-form" style="margin:1rem 0;">
            <label class="events-filter-label" for="partner_id">Partner</label>
            <select class="events-filter-input" name="partner_id" id="partner_id" onchange="this.form.submit()">
                <option value="">Összes partner</option>
                <?php foreach ($partners as $p): ?>
                    <option value="<?= (int) $p['id'] ?>"<?= $partnerFilter === (int) $p['id'] ? ' selected' : '' ?>>
                        <?= h((string) ($p['név'] ?? ('#' . (int) $p['id']))) ?>
                        <?= empty($p['aktív']) ? ' (inaktív)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($trials === []): ?>
            <p class="help">Még nincs rögzített egységár-próba.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="events-admin-table">
                    <thead>
                        <tr>
                            <th>Időpont</th>
                            <th>Partner</th>
                            <th>Kontextus</th>
                            <th>Időszak</th>
                            <th>Mód</th>
                            <th class="th-num">Megtek. Ft</th>
                            <th class="th-num">Átkatt Ft</th>
                            <th class="th-num">Ember megtek.</th>
                            <th class="th-num">Ember átkatt.</th>
                            <th class="th-num">Médiaérték</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trials as $row): ?>
                            <?php
                            $pid = (int) ($row['partner_id'] ?? 0);
                            $partnerName = trim((string) ($row['partner_nev'] ?? ''));
                            if ($partnerName === '') {
                                $partnerName = '#' . $pid;
                            }
                            $created = (string) ($row['létrehozva'] ?? '');
                            try {
                                $createdDisp = $created !== ''
                                    ? (new DateTimeImmutable($created))->format('Y.m.d. H:i')
                                    : '–';
                            } catch (Throwable) {
                                $createdDisp = $created !== '' ? $created : '–';
                            }
                            $mode = (string) ($row['stat_mode'] ?? 'smart');
                            $modeLabel = $mode === 'all' ? 'Összes' : 'Smart stat';
                            ?>
                            <tr>
                                <td><?= h($createdDisp) ?></td>
                                <td>
                                    <?php if ($pid > 0): ?>
                                        <a href="<?= h(nextgen_url('admin/partnerek/szerkeszt.php?id=') . $pid) ?>"><?= h($partnerName) ?></a>
                                    <?php else: ?>
                                        <?= h($partnerName) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= h((string) ($row['context_label'] ?? '–')) ?></td>
                                <td>
                                    <?= h((string) ($row['date_from'] ?? '')) ?>
                                    –
                                    <?= h((string) ($row['date_to'] ?? '')) ?>
                                </td>
                                <td><?= h($modeLabel) ?></td>
                                <td class="th-num"><?= h(events_edit_stats_format_media_ft((int) ($row['page_unit_ft'] ?? 0))) ?></td>
                                <td class="th-num"><?= h(events_edit_stats_format_media_ft((int) ($row['click_unit_ft'] ?? 0))) ?></td>
                                <td class="th-num"><?= (int) ($row['page_views_human'] ?? 0) ?></td>
                                <td class="th-num"><?= (int) ($row['external_clicks_human'] ?? 0) ?></td>
                                <td class="th-num"><strong><?= h(events_edit_stats_format_media_ft((int) ($row['total_ft'] ?? 0))) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php require_once dirname(__DIR__, 2) . '/partials/footer.php'; ?>
