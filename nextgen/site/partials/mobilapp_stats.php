<?php
declare(strict_types=1);

/**
 * Mobilapp statisztika UI.
 *
 * @var array{date_from: string, date_to: string, visitor: string, device: string} $statsParams
 * @var array<string, mixed> $statsData
 * @var string $statsFormAction
 * @var list<array{id: string, label: string, url: string, active: bool}> $statsPresetLinks
 */

$statsParams = $statsParams ?? [
    'date_from' => '',
    'date_to' => '',
    'visitor' => 'human',
    'device' => 'all',
];
$statsData = is_array($statsData ?? null) ? $statsData : [];
$statsFormAction = (string) ($statsFormAction ?? '');
$statsPresetLinks = is_array($statsPresetLinks ?? null) ? $statsPresetLinks : [];

$funnel = is_array($statsData['funnel'] ?? null) ? $statsData['funnel'] : [];
$usage = is_array($statsData['usage'] ?? null) ? $statsData['usage'] : [];
$modules = is_array($statsData['modules'] ?? null) ? $statsData['modules'] : [];
$devicesInstall = is_array($statsData['devices_install'] ?? null) ? $statsData['devices_install'] : [];
$devicesUsage = is_array($statsData['devices_usage'] ?? null) ? $statsData['devices_usage'] : [];
$feedback = is_array($statsData['feedback'] ?? null) ? $statsData['feedback'] : [];
$feedbackDevices = is_array($feedback['devices'] ?? null) ? $feedback['devices'] : [];
$feedbackRecent = is_array($feedback['recent'] ?? null) ? $feedback['recent'] : [];
$notes = is_array($statsData['notes'] ?? null) ? $statsData['notes'] : [];
$chartPayload = is_array($statsData['chart'] ?? null) ? $statsData['chart'] : ['labels' => [], 'datasets' => []];
$hasChart = ($chartPayload['labels'] ?? []) !== [] && ($chartPayload['datasets'] ?? []) !== [];
$chartJson = json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$granularity = (string) ($statsData['granularity'] ?? 'day');
$granularityLabel = match ($granularity) {
    'month' => 'havi',
    'week' => 'heti',
    default => 'napi',
};

$installed = (int) ($funnel['app_installed'] ?? 0);
$installUnique = (int) ($funnel['install_unique'] ?? 0);
$pageViews = (int) ($funnel['page_views'] ?? 0);
$pageUnique = (int) ($funnel['page_unique'] ?? 0);
$prompt = (int) ($funnel['install_prompt'] ?? 0);
$accepted = (int) ($funnel['install_accepted'] ?? 0);
$dismissed = (int) ($funnel['install_dismissed'] ?? 0);
$usageClicks = (int) ($usage['clicks_human'] ?? 0);
$usageUnique = (int) ($usage['unique_human'] ?? 0);
$feedbackCount = (int) ($feedback['count'] ?? 0);

$deviceLabel = static function (string $device): string {
    return match ($device) {
        'mobile' => 'Mobil',
        'tablet' => 'Tablet',
        'desktop' => 'Asztali',
        default => 'Ismeretlen',
    };
};
?>
<div class="card events-edit-stats lh-mobilapp-stats">
    <p class="events-edit-stats__intro">
        Telepítési tölcsér, app kezdőoldali modulhasználat és a mobilapp oldali visszajelzések.
        Időszak: <?= h((string) $statsParams['date_from']) ?> – <?= h((string) $statsParams['date_to']) ?>
        (<?= h($granularityLabel) ?> bontás).
    </p>

    <?php foreach ($notes as $note): ?>
        <p class="text-muted" style="margin:.35rem 0 0;font-size:.9rem"><?= h((string) $note) ?></p>
    <?php endforeach; ?>

    <?php if (empty($statsData['table_ready'])): ?>
        <p class="alert alert-warning">A mobilapp / kezdőoldal statisztika táblák nem érhetők el.</p>
    <?php else: ?>
        <form method="get" action="<?= h($statsFormAction) ?>" class="events-edit-stats__filters">
            <?php if ($statsPresetLinks !== []): ?>
                <div class="events-edit-stats__presets-row">
                    <span class="events-filter-label">Időszak</span>
                    <?php foreach ($statsPresetLinks as $presetLink): ?>
                        <a
                            class="btn btn-sm <?= !empty($presetLink['active']) ? 'btn-primary' : 'btn-secondary' ?>"
                            href="<?= h((string) $presetLink['url']) ?>"
                        ><?= h((string) $presetLink['label']) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="events-edit-stats__filter-grid">
                <div class="form-group">
                    <label class="events-filter-label" for="ma_stat_from">Tól</label>
                    <input class="events-filter-input" type="date" name="stat_date_from" id="ma_stat_from" value="<?= h((string) $statsParams['date_from']) ?>">
                </div>
                <div class="form-group">
                    <label class="events-filter-label" for="ma_stat_to">Ig</label>
                    <input class="events-filter-input" type="date" name="stat_date_to" id="ma_stat_to" value="<?= h((string) $statsParams['date_to']) ?>">
                </div>
                <div class="form-group">
                    <label class="events-filter-label" for="ma_visitor">Látogató</label>
                    <select class="events-filter-input" name="visitor" id="ma_visitor">
                        <option value="human"<?= $statsParams['visitor'] === 'human' ? ' selected' : '' ?>>Csak ember</option>
                        <option value="bot"<?= $statsParams['visitor'] === 'bot' ? ' selected' : '' ?>>Csak bot</option>
                        <option value="all"<?= $statsParams['visitor'] === 'all' ? ' selected' : '' ?>>Ember + bot</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="events-filter-label" for="ma_device">Eszköz</label>
                    <select class="events-filter-input" name="device" id="ma_device">
                        <option value="all"<?= $statsParams['device'] === 'all' ? ' selected' : '' ?>>Összes</option>
                        <option value="desktop"<?= $statsParams['device'] === 'desktop' ? ' selected' : '' ?>>Asztali</option>
                        <option value="mobile"<?= $statsParams['device'] === 'mobile' ? ' selected' : '' ?>>Mobil</option>
                        <option value="tablet"<?= $statsParams['device'] === 'tablet' ? ' selected' : '' ?>>Tablet</option>
                        <option value="unknown"<?= $statsParams['device'] === 'unknown' ? ' selected' : '' ?>>Ismeretlen</option>
                    </select>
                </div>
                <div class="form-group events-edit-stats__filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Szűrés</button>
                    <a class="btn btn-secondary btn-sm" href="<?= h($statsFormAction) ?>">Szűrés törlése</a>
                </div>
            </div>
        </form>

        <h2 class="events-stat-section-title" style="margin:1.25rem 0 .65rem;font-size:1.05rem">Telepítés</h2>
        <div class="events-edit-stats__cards">
            <div class="events-edit-stats__card">
                <div class="events-edit-stats__card-label">Telepítések</div>
                <div class="events-edit-stats__card-value"><?= number_format($installed, 0, ',', ' ') ?></div>
            </div>
            <div class="events-edit-stats__card">
                <div class="events-edit-stats__card-label">Egyedi telepítő</div>
                <div class="events-edit-stats__card-value"><?= number_format($installUnique, 0, ',', ' ') ?></div>
            </div>
            <div class="events-edit-stats__card">
                <div class="events-edit-stats__card-label">Mobilapp oldal</div>
                <div class="events-edit-stats__card-value"><?= number_format($pageViews, 0, ',', ' ') ?></div>
            </div>
            <div class="events-edit-stats__card">
                <div class="events-edit-stats__card-label">Egyedi oldalnéző</div>
                <div class="events-edit-stats__card-value"><?= number_format($pageUnique, 0, ',', ' ') ?></div>
            </div>
            <div class="events-edit-stats__card">
                <div class="events-edit-stats__card-label">Telepítő prompt</div>
                <div class="events-edit-stats__card-value"><?= number_format($prompt, 0, ',', ' ') ?></div>
            </div>
            <div class="events-edit-stats__card">
                <div class="events-edit-stats__card-label">Elfogadva / elutasítva</div>
                <div class="events-edit-stats__card-value"><?= number_format($accepted, 0, ',', ' ') ?> / <?= number_format($dismissed, 0, ',', ' ') ?></div>
            </div>
        </div>

        <h2 class="events-stat-section-title" style="margin:1.25rem 0 .65rem;font-size:1.05rem">App használat (kezdőoldal)</h2>
        <div class="events-edit-stats__cards">
            <div class="events-edit-stats__card">
                <div class="events-edit-stats__card-label">Modul-kattintás</div>
                <div class="events-edit-stats__card-value"><?= number_format($usageClicks, 0, ',', ' ') ?></div>
            </div>
            <div class="events-edit-stats__card">
                <div class="events-edit-stats__card-label">Egyedi app-használó</div>
                <div class="events-edit-stats__card-value"><?= number_format($usageUnique, 0, ',', ' ') ?></div>
            </div>
            <div class="events-edit-stats__card">
                <div class="events-edit-stats__card-label">Érintett modul</div>
                <div class="events-edit-stats__card-value"><?= number_format((int) ($usage['modules_hit'] ?? 0), 0, ',', ' ') ?></div>
            </div>
            <div class="events-edit-stats__card">
                <div class="events-edit-stats__card-label">Visszajelzés</div>
                <div class="events-edit-stats__card-value"><?= number_format($feedbackCount, 0, ',', ' ') ?></div>
            </div>
        </div>

        <?php if ($hasChart && $chartJson !== false): ?>
            <div class="events-edit-stats__chart-wrap">
                <canvas id="ma-mobilapp-chart" height="140" aria-label="Telepítések és app használat"></canvas>
            </div>
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
            <script>
            (function () {
                var payload = <?= $chartJson ?>;
                var canvas = document.getElementById('ma-mobilapp-chart');
                if (!canvas || !window.Chart || !payload.labels || !payload.labels.length) return;
                var datasets = (payload.datasets || []).map(function (ds) {
                    return {
                        label: ds.label || '',
                        data: ds.data || [],
                        borderColor: ds.color || '#3d6b4f',
                        backgroundColor: 'transparent',
                        tension: 0.25
                    };
                });
                new Chart(canvas, {
                    type: 'line',
                    data: { labels: payload.labels, datasets: datasets },
                    options: {
                        responsive: true,
                        plugins: { legend: { position: 'bottom' } },
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                });
            })();
            </script>
        <?php endif; ?>

        <div class="table-wrap" style="margin-top:1rem">
            <h3 style="margin:0 0 .5rem;font-size:1rem">Modulok az appban</h3>
            <table class="sortable-table">
                <thead>
                    <tr>
                        <th>Modul</th>
                        <th>Ember</th>
                        <th>Bot</th>
                        <th>Egyedi</th>
                        <th>Összesen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($modules === []): ?>
                        <tr><td colspan="5" class="text-muted">Nincs app modul-kattintás a kiválasztott időszakban.</td></tr>
                    <?php else: ?>
                        <?php foreach ($modules as $row): ?>
                            <tr>
                                <td><?= h((string) ($row['label'] ?? '')) ?></td>
                                <td><?= number_format((int) ($row['clicks_human'] ?? 0), 0, ',', ' ') ?></td>
                                <td><?= number_format((int) ($row['clicks_bot'] ?? 0), 0, ',', ' ') ?></td>
                                <td><?= number_format((int) ($row['unique_human'] ?? 0), 0, ',', ' ') ?></td>
                                <td><?= number_format((int) ($row['clicks'] ?? 0), 0, ',', ' ') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(16rem,1fr));gap:1rem;margin-top:1.25rem">
            <div class="table-wrap">
                <h3 style="margin:0 0 .5rem;font-size:1rem">Telepítések eszköz szerint</h3>
                <table class="sortable-table">
                    <thead><tr><th>Eszköz</th><th>Db</th></tr></thead>
                    <tbody>
                        <?php if ($devicesInstall === []): ?>
                            <tr><td colspan="2" class="text-muted">Nincs telepítés.</td></tr>
                        <?php else: ?>
                            <?php foreach ($devicesInstall as $row): ?>
                                <tr>
                                    <td><?= h($deviceLabel((string) ($row['device'] ?? ''))) ?></td>
                                    <td><?= number_format((int) ($row['count'] ?? 0), 0, ',', ' ') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-wrap">
                <h3 style="margin:0 0 .5rem;font-size:1rem">App használat eszköz szerint</h3>
                <table class="sortable-table">
                    <thead><tr><th>Eszköz</th><th>Kattintás</th></tr></thead>
                    <tbody>
                        <?php if ($devicesUsage === []): ?>
                            <tr><td colspan="2" class="text-muted">Nincs használat.</td></tr>
                        <?php else: ?>
                            <?php foreach ($devicesUsage as $row): ?>
                                <tr>
                                    <td><?= h($deviceLabel((string) ($row['device'] ?? ''))) ?></td>
                                    <td><?= number_format((int) ($row['count'] ?? 0), 0, ',', ' ') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-wrap">
                <h3 style="margin:0 0 .5rem;font-size:1rem">Visszajelzett telefonok</h3>
                <table class="sortable-table">
                    <thead><tr><th>Típus</th><th>Db</th></tr></thead>
                    <tbody>
                        <?php if ($feedbackDevices === []): ?>
                            <tr><td colspan="2" class="text-muted">Nincs megadott típus.</td></tr>
                        <?php else: ?>
                            <?php foreach ($feedbackDevices as $row): ?>
                                <tr>
                                    <td><?= h((string) ($row['label'] ?? '')) ?></td>
                                    <td><?= number_format((int) ($row['count'] ?? 0), 0, ',', ' ') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="table-wrap" style="margin-top:1.25rem">
            <h3 style="margin:0 0 .5rem;font-size:1rem">Legutóbbi visszajelzések</h3>
            <table class="sortable-table">
                <thead>
                    <tr>
                        <th>Idő</th>
                        <th>Telefon</th>
                        <th>Mi tetszik</th>
                        <th>Mit javítanál</th>
                        <th>Egyéb</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($feedbackRecent === []): ?>
                        <tr><td colspan="5" class="text-muted">Nincs visszajelzés az időszakban.</td></tr>
                    <?php else: ?>
                        <?php foreach ($feedbackRecent as $row): ?>
                            <tr>
                                <td><?= h((string) ($row['created'] ?? '')) ?></td>
                                <td><?= h((string) ($row['eszkoz'] ?? '')) ?></td>
                                <td><?= h(latinfo_home_clamp((string) ($row['ilyen'] ?? ''), 120)) ?></td>
                                <td><?= h(latinfo_home_clamp((string) ($row['ne'] ?? ''), 120)) ?></td>
                                <td><?= h(latinfo_home_clamp((string) ($row['egyeb'] ?? ''), 80)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
