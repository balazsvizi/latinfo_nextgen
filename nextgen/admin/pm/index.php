<?php
declare(strict_types=1);

require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../lib/pm/bootstrap.php';

pm_tools_require_access();

$pdo = pm_tools_db();
$url = pm_tools_index_url();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_validate('pmtools_settings')) {
        flash('error', 'Biztonsági token érvénytelen.');
        redirect($url);
    }

    if (isset($_POST['toggle_overlay'])) {
        $enabled = !empty($_POST['overlay_enabled']);
        PmTools::setOverlayEnabled($pdo, $enabled);
        flash('success', $enabled ? 'Oldal jelölő bekapcsolva.' : 'Oldal jelölő kikapcsolva.');
        redirect($url);
    }

    if (!empty($_POST['scan_php'])) {
        $added = PmTools::scanPhpFiles($pdo, BASE_PATH);
        flash('success', $added > 0
            ? $added . ' új PHP oldal felvéve a listába.'
            : 'Nincs új PHP oldal – minden már szerepel.');
        redirect($url);
    }

    if (!empty($_POST['register_current'])) {
        $phpPath = pm_tools_current_php_path();
        PmTools::getOrCreatePage($pdo, $phpPath);
        flash('success', 'Aktuális oldal felvéve: ' . $phpPath);
        redirect($url . '?all=1');
    }
}

PmTools::syncCatalogMetadata($pdo);

$overlayEnabled = PmTools::isOverlayEnabled($pdo);

$showAll = !empty($_GET['all']);
$unansweredOnly = !$showAll && !empty($_GET['unanswered']);

if ($showAll) {
    $pages = PmTools::listAllPages($pdo);
} else {
    $pages = PmTools::listPagesWithNotes($pdo, $unansweredOnly);
}

$allPagesWithNotes = PmTools::listPagesWithNotes($pdo, false);
$totalUnansweredPages = PmTools::countTotalUnansweredPages($pdo);
$allRegisteredCount = count(PmTools::listAllPages($pdo));

$notesByPage = [];
foreach ($pages as $page) {
    $notesByPage[(int) $page['id']] = PmTools::listNotesForPage($pdo, (int) $page['id']);
}

$pageTitle = 'PM Tools';
$extraHead = '<link rel="stylesheet" href="' . h(pm_tools_asset_url('css/pmtools.css')) . '">';
require_once __DIR__ . '/../../partials/header.php';

$flashSuccess = flash('success');
$flashError = flash('error');
$csrf = pm_tools_csrf_token();
$apiUrl = pm_tools_api_url();
$currentPath = pm_tools_current_php_path();
?>
<h1>PM Tools</h1>
<p>Prototípus jegyzetek PHP oldalakhoz. A központi admin felületen és a jobb alsó sarokban lévő jelölőn keresztül is kezelhetők.</p>

<?php
$pmStatus = pm_tools_status();
?>
<div class="card" style="border-left:4px solid <?= $pmStatus['active'] ? '#16a34a' : '#f59e0b' ?>;">
    <h2 style="margin-top:0;font-size:1rem;">Hol látod a jelölőt?</h2>
    <p style="margin:0 0 10px;">Jobb alsó sarokban egy <strong style="color:#7c3aed;">lila gomb</strong> jelenik meg (jegyzet + másolás). Erre kattintva nyílik a jegyzet popup.</p>
    <ul style="margin:0;padding-left:1.2rem;line-height:1.7;">
        <li><?= $pmStatus['overlay'] ? '✅' : '❌' ?> <strong>PHP oldal jelölő bekapcsolva</strong> – az alábbi kapcsoló</li>
        <li><?= $pmStatus['admin'] ? '✅' : '❌' ?> <strong>Superadmin jogosultság</strong></li>
    </ul>
    <?php if (!$pmStatus['active']): ?>
    <p style="margin:12px 0 0;color:#92400e;">A fenti két feltétel mindkettője kell – utána bármely backoffice oldalon megjelenik a jelölő.</p>
    <?php endif; ?>
</div>

<?php if ($flashSuccess): ?>
<p class="msg msg-success"><?= h($flashSuccess) ?></p>
<?php endif; ?>
<?php if ($flashError): ?>
<p class="msg msg-error"><?= h($flashError) ?></p>
<?php endif; ?>

<div class="card">
    <form method="post" class="pm-admin-toggle">
        <?= csrf_input('pmtools_settings') ?>
        <input type="hidden" name="toggle_overlay" value="1">
        <input type="checkbox" name="overlay_enabled" value="1" id="overlay_enabled"
            <?= $overlayEnabled ? 'checked' : '' ?>
            onchange="this.form.submit()">
        <label for="overlay_enabled"><strong>PHP oldal jelölő</strong> – jobb alul megjelenő másolás és jegyzet popup</label>
    </form>
    <form method="post" style="margin-top:8px;">
        <?= csrf_input('pmtools_settings') ?>
        <button type="submit" name="scan_php" value="1" class="btn btn-secondary btn-sm">PHP fájlok beolvasása</button>
        <span style="font-size:0.88rem;color:#64748b;margin-left:8px;">Új .php fájlok felvétele a projektből (<?= (int) $allRegisteredCount ?> regisztrált)</span>
    </form>
    <form method="post">
        <?= csrf_input('pmtools_settings') ?>
        <button type="submit" name="register_current" value="1" class="btn btn-secondary btn-sm">Aktuális oldal felvétele</button>
        <span style="font-size:0.88rem;color:#64748b;margin-left:8px;"><code><?= h($currentPath) ?></code></span>
    </form>
</div>

<?php if ($pages === []): ?>
<div class="card">
    <p><?php if ($unansweredOnly): ?>
        Nincs megválaszolatlan jegyzet.
    <?php elseif ($showAll): ?>
        Még nincs regisztrált oldal. Futtasd a PHP fájlok beolvasását.
    <?php else: ?>
        Még nincs jegyzet. Olvasd be a PHP fájlokat, vagy vedd fel az aktuális oldalt, majd adj hozzá jegyzetet.
    <?php endif; ?></p>
</div>
<?php else: ?>
<div class="pm-admin-filters">
    <span class="pm-admin-filters-label">Szűrés:</span>
    <a href="<?= h($url) ?>" class="pm-admin-filter-btn<?= !$unansweredOnly && !$showAll ? ' is-active' : '' ?>">Jegyzetes (<?= count($allPagesWithNotes) ?>)</a>
    <a href="<?= h($url . '?unanswered=1') ?>" class="pm-admin-filter-btn<?= $unansweredOnly ? ' is-active' : '' ?>">Megválaszolatlan (<?= $totalUnansweredPages ?>)</a>
    <a href="<?= h($url . '?all=1') ?>" class="pm-admin-filter-btn<?= $showAll ? ' is-active' : '' ?>">Összes oldal (<?= $allRegisteredCount ?>)</a>
</div>
<div class="pm-admin-cards">
    <?php foreach ($pages as $page): ?>
    <?php
    $pageId = (int) $page['id'];
    $notes = $notesByPage[$pageId] ?? [];
    if ($notes === []) {
        $notes = [['note_text' => '', 'response_text' => '']];
    } else {
        $notes = array_values(array_filter(
            $notes,
            static fn (array $n): bool => $showAll || trim((string) ($n['note_text'] ?? '')) !== ''
        ));
        if ($notes === []) {
            $notes = [['note_text' => '', 'response_text' => '']];
        }
    }
    $unansweredCount = PmTools::countUnansweredNotes($pdo, $pageId);
    $pageUrl = pm_tools_page_url((string) $page['php_path']);
    ?>
    <article class="pm-admin-card<?= $unansweredCount > 0 ? ' has-unanswered' : '' ?>"
             data-page-id="<?= $pageId ?>"
             data-api="<?= h($apiUrl) ?>"
             data-csrf="<?= h($csrf) ?>">
        <p class="pm-admin-card-path">
            <a href="<?= h($pageUrl) ?>" class="pm-admin-card-open" target="_blank" rel="noopener">
                <code><?= h((string) $page['php_path']) ?></code>
            </a>
        </p>
        <h3>
            <a href="<?= h($pageUrl) ?>" class="pm-admin-card-open" target="_blank" rel="noopener">
                <?= h((string) ($page['display_name'] ?: basename((string) $page['php_path']))) ?>
            </a>
            <?php if ($unansweredCount > 0): ?>
            <span class="pm-admin-unanswered-badge"><?= $unansweredCount ?> megválaszolatlan</span>
            <?php endif; ?>
        </h3>

        <?php if (trim((string) $page['purpose']) !== ''): ?>
        <p class="pm-admin-purpose-text"><?= h((string) $page['purpose']) ?></p>
        <?php endif; ?>

        <label>Név
            <input type="text" class="pm-admin-name" value="<?= h((string) $page['display_name']) ?>">
        </label>

        <div class="pm-tools-notes-wrap">
            <div class="pm-tools-notes-head">
                <span>Jegyzet</span>
                <span>Válasz</span>
                <span></span>
            </div>
            <div class="pm-admin-notes-rows">
                <?php foreach ($notes as $note): ?>
                <?php
                $noteText = (string) ($note['note_text'] ?? '');
                $responseText = (string) ($note['response_text'] ?? '');
                $isUnanswered = PmTools::noteRowIsUnanswered($noteText, $responseText);
                ?>
                <div class="pm-tools-note-row<?= $isUnanswered ? ' is-unanswered-row' : '' ?>">
                    <textarea class="pm-note-input" rows="2" placeholder="Jegyzet…"><?= h($noteText) ?></textarea>
                    <textarea class="pm-response-input<?= $isUnanswered ? ' is-unanswered' : '' ?>" rows="2" placeholder="Válasz…"><?= h($responseText) ?></textarea>
                    <button type="button" class="pm-tools-row-del" title="Sor törlése">&times;</button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="pm-tools-add-row pm-admin-add-row">+ Új sor hozzáadása</button>
        </div>

        <div class="pm-admin-card-actions">
            <a href="<?= h($pageUrl) ?>" class="btn btn-secondary btn-sm" target="_blank" rel="noopener">Oldal megnyitása</a>
            <button type="button" class="btn btn-primary btn-sm pm-admin-save">Mentés</button>
            <span class="pm-admin-card-status"></span>
        </div>
    </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script src="<?= h(pm_tools_asset_url('js/pmtools.js')) ?>" defer></script>
<?php
require_once __DIR__ . '/../../partials/footer.php';
