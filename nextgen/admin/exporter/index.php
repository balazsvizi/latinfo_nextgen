<?php
/**
 * Exporter – SQL lekérdezések mentése és CSV export
 */
$pageTitle = 'Exporter';
require_once __DIR__ . '/../../partials/header.php';

requireLogin();
requireSuperadmin();

$db = getDb();

// Mentett kapcsolatok (exportáláshoz)
$connections = $db->query('SELECT id, név FROM nextgen_exporter_connections ORDER BY név ASC')->fetchAll();

// Mentett lekérdezések betöltése
$saved = $db->query('SELECT id, név, query_sql, megjegyzés, módosítva FROM nextgen_exporter_queries ORDER BY módosítva DESC')->fetchAll();

$selectedId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$currentSql = '';
$currentName = '';
$currentMegjegyzes = '';
$currentConnectionId = null; // '' = alapértelmezett
if ($selectedId) {
    $stmt = $db->prepare('SELECT név, query_sql, megjegyzés, connection_id FROM nextgen_exporter_queries WHERE id = ?');
    $stmt->execute([$selectedId]);
    $row = $stmt->fetch();
    if ($row) {
        $currentName = $row['név'];
        $currentSql = $row['query_sql'];
        $currentMegjegyzes = (string) ($row['megjegyzés'] ?? '');
        $currentConnectionId = isset($row['connection_id']) && (int) $row['connection_id'] > 0 ? (int) $row['connection_id'] : '';
    }
}

$flashSuccess = flash('success');
$flashError = flash('error');
?>
<div class="card">
    <h2>SQL export</h2>
    <p>Írj vagy válassz egy lekérdezést, válaszd ki a cél adatbázist, majd exportáld CSV-be.</p>
    <div class="form-actions" style="margin-bottom: 0.5rem;">
        <a href="<?= h(nextgen_url('admin/exporter/')) ?>" class="btn btn-primary">Új lekérdezés</a>
        <a href="<?= h(nextgen_url('admin/exporter/connections.php')) ?>" class="btn btn-secondary">Adatbázis kapcsolatok</a>
    </div>

    <?php if ($flashSuccess): ?>
        <p class="msg msg-success"><?= h($flashSuccess) ?></p>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <p class="msg msg-error"><?= h($flashError) ?></p>
    <?php endif; ?>

    <div class="exporter-layout">
        <aside class="exporter-sidebar">
            <h3>Mentett lekérdezések</h3>
            <a href="<?= h(nextgen_url('admin/exporter/')) ?>" class="btn btn-sm btn-primary" style="display:inline-block; margin-bottom: 0.5rem;">+ Új lekérdezés</a>
            <ul class="exporter-list">
                <?php foreach ($saved as $q): ?>
                    <li class="<?= $selectedId === (int)$q['id'] ? 'active' : '' ?>">
                        <?php $qMeg = trim((string) ($q['megjegyzés'] ?? '')); ?>
                        <a href="?id=<?= (int)$q['id'] ?>"<?= $qMeg !== '' ? ' title="' . h($qMeg) . '"' : '' ?>><?= h($q['név']) ?></a>
                        <form method="post" action="delete.php" class="inline-form" onsubmit="return confirm('Törlöd ezt a lekérdezést?');">
                            <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
                            <button type="submit" class="btn-link btn-danger" title="Törlés">✕</button>
                        </form>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($saved)): ?>
                    <li class="muted">Nincs mentett lekérdezés.</li>
                <?php endif; ?>
            </ul>
        </aside>

        <div class="exporter-main">
            <form method="post" action="save.php" class="exporter-form">
                <div class="form-group">
                    <label for="connection_id">Adatbázis kapcsolat (exportáláshoz)</label>
                    <select id="connection_id" name="connection_id" class="form-control">
                        <option value="" <?= $currentConnectionId === '' ? 'selected' : '' ?>>Alapértelmezett (alkalmazás)</option>
                        <?php foreach ($connections as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= $currentConnectionId === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['név']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="nev">Lekérdezés neve (opcionális, mentéshez)</label>
                    <input type="text" id="nev" name="nev" class="form-control" placeholder="pl. Szervezők lista" value="<?= h($currentName) ?>">
                </div>
                <div class="form-group">
                    <label for="megjegyzes">Megjegyzés <span class="muted">(opcionális)</span></label>
                    <textarea id="megjegyzes" name="megjegyzes" class="form-control" rows="3" placeholder="pl. mire való, figyelmeztetés, forrás tábla…"><?= h($currentMegjegyzes) ?></textarea>
                </div>
                <div class="form-group">
                    <label for="query_sql">SQL lekérdezés <em>(csak SELECT)</em></label>
                    <textarea id="query_sql" name="query_sql" class="form-control code" rows="12" placeholder="SELECT * FROM finance_organizers LIMIT 100"><?= h($currentSql) ?></textarea>
                </div>
                <div class="form-actions">
                    <input type="hidden" name="id" value="<?= $selectedId ?: '' ?>">
                    <button type="submit" name="action" value="save" class="btn btn-secondary">Mentés</button>
                    <button type="submit" form="export-form" class="btn btn-primary">Export CSV</button>
                </div>
            </form>
            <form id="export-form" method="post" action="export.php" target="_blank" style="display:none;">
                <input type="hidden" name="query_sql" id="export_query_sql" value="">
                <input type="hidden" name="connection_id" id="export_connection_id" value="">
                <input type="hidden" name="query_name" id="export_query_name" value="">
            </form>
            <p class="help-text">Az „Export CSV” a fenti SQL-t futtatja és letölti az eredményt CSV-ként (pontosvessző elválasztó, UTF-8). A mentés a nevet és a lekérdezést menti a listába.</p>
        </div>
    </div>
</div>

<script>
(function() {
    var exportForm = document.getElementById('export-form');
    var sqlField = document.getElementById('query_sql');
    var exportInput = document.getElementById('export_query_sql');

    var connectionSelect = document.getElementById('connection_id');
    var exportConnectionInput = document.getElementById('export_connection_id');
    var nevField = document.getElementById('nev');
    var exportNameInput = document.getElementById('export_query_name');

    exportForm.addEventListener('submit', function(e) {
        exportInput.value = sqlField.value.trim();
        exportConnectionInput.value = connectionSelect ? connectionSelect.value : '';
        if (exportNameInput && nevField) exportNameInput.value = (nevField.value || '').trim();
        var sql = exportInput.value;
        if (!sql) {
            e.preventDefault();
            alert('Írj be egy SQL lekérdezést.');
            return false;
        }
    });
})();
</script>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
