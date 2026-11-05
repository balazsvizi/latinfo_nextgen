<?php
declare(strict_types=1);

/**
 * Szervezőnkénti havi médiaérték és leszámlázott összeg.
 *
 * @var array<string, mixed> $statsData
 * @var string $statsFormAction
 */

$statsData = is_array($statsData ?? null) ? $statsData : [];
$statsFormAction = (string) ($statsFormAction ?? '');
$year = (int) ($statsData['year'] ?? (int) date('Y'));
$availableYears = is_array($statsData['available_years'] ?? null) ? $statsData['available_years'] : [];
$rates = is_array($statsData['rates'] ?? null) ? $statsData['rates'] : [];
$rows = is_array($statsData['rows'] ?? null) ? $statsData['rows'] : [];
$totals = is_array($statsData['totals'] ?? null) ? $statsData['totals'] : [];
$pageUnit = (int) ($rates['page_unit_ft'] ?? 0);
$clickUnit = (int) ($rates['click_unit_ft'] ?? 0);
$previewUnit = (int) ($rates['preview_unit_ft'] ?? 0);
$mediaYearTotal = (int) ($totals['media_year'] ?? 0);
$invoiceYearTotal = (int) ($totals['invoice_year'] ?? 0);
$rowCount = count($rows);
$colspan = 27;

$yearUrl = static function (int $targetYear) use ($statsFormAction): string {
    return $statsFormAction . '?' . http_build_query([
        'stat_year' => $targetYear,
    ]);
};

$renderAmount = static function (int $amount, string $kind, string $monthKey, bool $emphasize = false): void {
    $isZero = $amount === 0;
    $class = 'text-center org-media-invoice__cell org-media-invoice__cell--' . $kind;
    if ($emphasize) {
        $class .= ' org-media-invoice__cell--year';
    }
    if ($isZero) {
        $class .= ' org-media-invoice__cell--zero';
    }
    $text = $isZero ? '—' : events_edit_stats_format_media_ft($amount);
    ?>
    <td
        class="<?= h($class) ?>"
        data-kind="<?= h($kind) ?>"
        data-month="<?= h($monthKey) ?>"
        data-amount="<?= $amount ?>"
    ><?= h($text) ?></td>
    <?php
};
?>
<div class="card events-edit-stats org-media-invoice">
    <p class="events-edit-stats__intro">
        <?= $year ?> havi bontásban, szervezőnként: generált médiaérték és leszámlázott összeg.
        A médiaérték emberi forgalomból számol, az esemény zárónapjáig
        (előnézet <?= (int) $previewUnit ?> Ft, oldalmegnyitás <?= (int) $pageUnit ?> Ft,
        további info <?= (int) $clickUnit ?> Ft).
        <a href="<?= h(nextgen_url('admin/partnerek/mediaertek.php')) ?>">Egységárak</a>
    </p>
    <p class="events-edit-stats__intro">
        A hónap a számlázott időszak, nem a számla kelte. Több hónapos tételnél az összeg
        egyenlően oszlik szét; időszak nélküli számla a számla dátumának hónapjába kerül.
        A számla az eseményszervező sorában jelenik meg, ha a finance szervező hozzá van kötve,
        vagy egy partneren keresztül egyértelműen hozzá tartozik. Különben külön, „csak számla” sorként látszik.
        Több szervezős eseménynél a médiaérték minden szervezőnél teljes összegben szerepel.
    </p>

    <div class="events-edit-stats__presets-row" aria-label="Év váltása">
        <span class="events-filter-label">Év</span>
        <?php foreach ($availableYears as $availableYear): ?>
            <?php $availableYear = (int) $availableYear; ?>
            <a
                class="btn btn-sm <?= $availableYear === $year ? 'btn-primary' : 'btn-secondary' ?>"
                href="<?= h($yearUrl($availableYear)) ?>"
            ><?= $availableYear ?></a>
        <?php endforeach; ?>
    </div>

    <div class="events-edit-stats__cards">
        <div class="events-edit-stats__card">
            <p class="events-edit-stats__card-label">Szervezők</p>
            <p class="events-edit-stats__card-value"><?= $rowCount ?></p>
            <p class="events-edit-stats__card-hint">van médiaérték vagy számla ebben az évben</p>
        </div>
        <div class="events-edit-stats__card">
            <p class="events-edit-stats__card-label">Éves médiaérték</p>
            <p class="events-edit-stats__card-value"><?= h(events_edit_stats_format_media_ft($mediaYearTotal)) ?></p>
            <p class="events-edit-stats__card-hint">generált, szervezőnként összeadva</p>
        </div>
        <div class="events-edit-stats__card">
            <p class="events-edit-stats__card-label">Éves leszámlázott</p>
            <p class="events-edit-stats__card-value"><?= h(events_edit_stats_format_media_ft($invoiceYearTotal)) ?></p>
            <p class="events-edit-stats__card-hint">a <?= $year ?> hónapjaira jutó rész</p>
        </div>
    </div>

    <?php if ($rows === []): ?>
        <p class="help">Ebben az évben nincs generált médiaérték és nincs leszámlázott összeg.</p>
    <?php else: ?>
        <div class="org-media-invoice__toolbar">
            <div class="form-group" style="max-width:18rem;margin:0;">
                <label class="events-filter-label" for="org-media-invoice-search">Keresés</label>
                <input
                    class="events-filter-input"
                    type="search"
                    id="org-media-invoice-search"
                    placeholder="Szervező neve…"
                    autocomplete="off"
                >
            </div>
            <p class="org-media-invoice__count" aria-live="polite">
                <strong><span id="org-media-invoice-visible"><?= $rowCount ?></span></strong>
                / <?= $rowCount ?> szervező
            </p>
        </div>

        <div class="table-wrap events-admin-table-wrap">
            <table class="events-admin-table org-media-invoice__table" id="org-media-invoice-table">
                <thead>
                    <tr>
                        <th scope="col" rowspan="2" class="org-media-invoice__name">Szervező</th>
                        <?php for ($month = 1; $month <= 12; $month++): ?>
                            <th
                                scope="colgroup"
                                colspan="2"
                                class="th-center org-media-invoice__month"
                                title="<?= h(organizer_media_invoice_stats_month_label_full($month)) ?>"
                            ><?= h(organizer_media_invoice_stats_month_label($month)) ?></th>
                        <?php endfor; ?>
                        <th scope="colgroup" colspan="2" class="th-center org-media-invoice__month org-media-invoice__month--year">Éves szumma</th>
                    </tr>
                    <tr>
                        <?php for ($month = 1; $month <= 12; $month++): ?>
                            <th scope="col" class="th-center org-media-invoice__sub org-media-invoice__sub--media" title="Generált médiaérték">Média</th>
                            <th scope="col" class="th-center org-media-invoice__sub org-media-invoice__sub--invoice" title="Leszámlázott összeg">Számla</th>
                        <?php endfor; ?>
                        <th scope="col" class="th-center org-media-invoice__sub org-media-invoice__sub--media" title="Éves generált médiaérték">Média</th>
                        <th scope="col" class="th-center org-media-invoice__sub org-media-invoice__sub--invoice" title="Éves leszámlázott összeg">Számla</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $eventsId = (int) ($row['events_organizer_id'] ?? 0);
                        $financeId = (int) ($row['finance_organizer_id'] ?? 0);
                        $name = (string) ($row['name'] ?? '');
                        if ($eventsId > 0) {
                            $nameUrl = events_url('organizer_szerkeszt.php?id=') . $eventsId;
                            $nameTitle = 'Szervező szerkesztése';
                        } elseif ($financeId > 0) {
                            $nameUrl = nextgen_url('organizers/megtekint.php?id=') . $financeId;
                            $nameTitle = 'Finance szervező';
                        } else {
                            $nameUrl = '';
                            $nameTitle = '';
                        }
                        $searchName = mb_strtolower($name, 'UTF-8');
                        ?>
                        <tr data-name="<?= h($searchName) ?>">
                            <th scope="row" class="org-media-invoice__name" title="<?= h($name) ?>">
                                <?php if ($nameUrl !== ''): ?>
                                    <a href="<?= h($nameUrl) ?>" title="<?= h($nameTitle) ?>"><?= h($name) ?></a>
                                <?php else: ?>
                                    <?= h($name) ?>
                                <?php endif; ?>
                                <?php if ($eventsId <= 0): ?>
                                    <span class="org-media-invoice__badge">csak számla</span>
                                <?php endif; ?>
                            </th>
                            <?php for ($month = 1; $month <= 12; $month++): ?>
                                <?php $renderAmount((int) ($row['media'][$month] ?? 0), 'media', (string) $month); ?>
                                <?php $renderAmount((int) ($row['invoice'][$month] ?? 0), 'invoice', (string) $month); ?>
                            <?php endfor; ?>
                            <?php $renderAmount((int) ($row['media_year'] ?? 0), 'media', 'year', true); ?>
                            <?php $renderAmount((int) ($row['invoice_year'] ?? 0), 'invoice', 'year', true); ?>
                        </tr>
                    <?php endforeach; ?>
                    <tr id="org-media-invoice-empty" hidden>
                        <td colspan="<?= $colspan ?>" class="org-media-invoice__empty">Nincs találat a keresésre.</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row" class="org-media-invoice__name">Szumma</th>
                        <?php for ($month = 1; $month <= 12; $month++): ?>
                            <td class="text-center org-media-invoice__cell org-media-invoice__cell--media org-media-invoice__cell--total">
                                <?= h(events_edit_stats_format_media_ft((int) ($totals['media'][$month] ?? 0))) ?>
                            </td>
                            <td class="text-center org-media-invoice__cell org-media-invoice__cell--invoice org-media-invoice__cell--total">
                                <?= h(events_edit_stats_format_media_ft((int) ($totals['invoice'][$month] ?? 0))) ?>
                            </td>
                        <?php endfor; ?>
                        <td class="text-center org-media-invoice__cell org-media-invoice__cell--media org-media-invoice__cell--year org-media-invoice__cell--total">
                            <?= h(events_edit_stats_format_media_ft($mediaYearTotal)) ?>
                        </td>
                        <td class="text-center org-media-invoice__cell org-media-invoice__cell--invoice org-media-invoice__cell--year org-media-invoice__cell--total">
                            <?= h(events_edit_stats_format_media_ft($invoiceYearTotal)) ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <script>
        (function () {
            var input = document.getElementById('org-media-invoice-search');
            var rows = document.querySelectorAll('#org-media-invoice-table tbody tr[data-name]');
            var empty = document.getElementById('org-media-invoice-empty');
            var countEl = document.getElementById('org-media-invoice-visible');
            if (!input || !rows.length) {
                return;
            }
            input.addEventListener('input', function () {
                var query = input.value.trim().toLocaleLowerCase('hu-HU');
                var visible = 0;
                rows.forEach(function (row) {
                    var name = row.getAttribute('data-name') || '';
                    var show = query === '' || name.indexOf(query) !== -1;
                    row.hidden = !show;
                    if (show) {
                        visible += 1;
                    }
                });
                if (empty) {
                    empty.hidden = visible !== 0;
                }
                if (countEl) {
                    countEl.textContent = String(visible);
                }
            });
        })();
        </script>
    <?php endif; ?>
</div>
