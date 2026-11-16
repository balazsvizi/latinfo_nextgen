<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/event_name_snippets.php';
requireLogin();

$db = getDb();
$schemaOk = events_name_snippets_ensure_schema($db);
$hiba = '';
$selfUrl = events_url('esemenynev_gyorsszovegek.php');
$sticky = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('events_name_snippets')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet. Töltsd újra az oldalt.';
    } elseif (!$schemaOk) {
        $hiba = 'A gyorsszöveg tábla nem érhető el.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $idRaw = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        $id = ($idRaw === false || $idRaw < 0) ? 0 : $idRaw;
        $sortRaw = filter_var($_POST['sort_order'] ?? 0, FILTER_VALIDATE_INT);
        $sortOrder = $sortRaw === false ? 0 : $sortRaw;
        $label = (string) ($_POST['label'] ?? '');
        try {
            if ($action === 'save') {
                $result = events_name_snippet_save($db, $id, $label, $sortOrder);
                if (!$result['ok']) {
                    $hiba = (string) ($result['error'] ?? 'A mentés nem sikerült.');
                    $sticky = [
                        'id' => $id,
                        'label' => events_name_snippet_normalize_label($label),
                        'sort_order' => max(0, min(9999, $sortOrder)),
                    ];
                } else {
                    if (function_exists('rendszer_log')) {
                        rendszer_log(
                            'eseménynév_gyorsszöveg',
                            (int) $result['id'],
                            $id > 0 ? 'Módosítva' : 'Létrehozva',
                            (string) $result['label']
                        );
                    }
                    flash('success', $id > 0 ? 'Gyorsszöveg mentve.' : 'Gyorsszöveg hozzáadva.');
                    redirect($selfUrl);
                }
            } elseif ($action === 'delete') {
                $deletedLabel = events_name_snippet_delete($db, $id);
                if ($deletedLabel === null) {
                    $hiba = 'A gyorsszöveg nem található vagy már törölve.';
                } else {
                    if (function_exists('rendszer_log')) {
                        rendszer_log('eseménynév_gyorsszöveg', $id, 'Törölve', $deletedLabel);
                    }
                    flash('success', 'Gyorsszöveg törölve.');
                    redirect($selfUrl);
                }
            }
        } catch (Throwable $e) {
            error_log('esemenynev_gyorsszovegek: ' . $e->getMessage());
            $hiba = 'A művelet nem sikerült.';
        }
    }
}

$snippets = $schemaOk ? events_name_snippets_list($db) : [];
$nextSort = $schemaOk ? events_name_snippets_next_sort($db) : 10;
$newLabel = '';
$newSort = $nextSort;
if (is_array($sticky) && (int) ($sticky['id'] ?? 0) === 0) {
    $newLabel = (string) ($sticky['label'] ?? '');
    $newSort = (int) ($sticky['sort_order'] ?? $nextSort);
}

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Eseménynév gyorsszövegek';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>
<?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>

<div class="card events-admin-card">
    <div class="events-list-head">
        <h2 class="events-list-title">Eseménynév gyorsszövegek</h2>
        <div class="events-list-actions">
            <a href="<?= h(events_url('events_admin.php')) ?>" class="btn btn-secondary">Események</a>
        </div>
    </div>
    <p class="help">
        Ezek a szövegek az esemény szerkesztőben, az „Esemény neve” felirat mellett jelennek meg.
        Kattintásra a kurzor helyére kerülnek az esemény nevébe. A kisebb sorrend kerül előrébb.
    </p>
    <?php if (!$schemaOk): ?>
        <p class="alert alert-error">A gyorsszöveg tábla nem jött létre. Frissítsd az oldalt, vagy ellenőrizd az adatbázis-jogosultságokat.</p>
    <?php else: ?>
        <form method="post" class="events-name-snippets-admin__add">
            <?= csrf_input('events_name_snippets') ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="0">
            <div class="form-row">
                <div class="form-group">
                    <label for="snippet_label_new">Új gyorsszöveg</label>
                    <input type="text" id="snippet_label_new" name="label" required maxlength="<?= (int) EVENTS_NAME_SNIPPET_LABEL_MAX ?>" value="<?= h($newLabel) ?>" placeholder="pl. Táncház">
                </div>
                <div class="form-group events-name-snippets-admin__sort">
                    <label for="snippet_sort_new">Sorrend</label>
                    <input type="number" id="snippet_sort_new" name="sort_order" min="0" max="9999" value="<?= (int) $newSort ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Hozzáadás</button>
        </form>
    <?php endif; ?>
</div>

<?php if ($schemaOk): ?>
<div class="card events-admin-card">
    <div class="events-list-head">
        <h2 class="events-list-title">Mentett szövegek</h2>
    </div>
    <div class="table-wrap events-admin-table-wrap">
        <table class="events-admin-table">
            <thead>
                <tr>
                    <th scope="col">Szöveg</th>
                    <th scope="col">Sorrend</th>
                    <th scope="col">Művelet</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($snippets === []): ?>
                    <tr>
                        <td colspan="3">Még nincs gyorsszöveg. Add hozzá az elsőt a fenti mezőben.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($snippets as $snippet): ?>
                        <?php
                        $snippetId = (int) $snippet['id'];
                        $snippetLabel = (string) $snippet['label'];
                        $snippetSort = (int) $snippet['sort_order'];
                        if (is_array($sticky) && (int) ($sticky['id'] ?? 0) === $snippetId) {
                            $snippetLabel = (string) ($sticky['label'] ?? $snippetLabel);
                            $snippetSort = (int) ($sticky['sort_order'] ?? $snippetSort);
                        }
                        ?>
                        <tr>
                            <td colspan="3">
                                <form method="post" class="events-name-snippets-admin__row">
                                    <?= csrf_input('events_name_snippets') ?>
                                    <input type="hidden" name="id" value="<?= $snippetId ?>">
                                    <input type="text" name="label" required maxlength="<?= (int) EVENTS_NAME_SNIPPET_LABEL_MAX ?>" value="<?= h($snippetLabel) ?>" aria-label="Gyorsszöveg">
                                    <input type="number" name="sort_order" min="0" max="9999" value="<?= $snippetSort ?>" aria-label="Sorrend">
                                    <button type="submit" class="btn btn-primary btn-sm" name="action" value="save">Mentés</button>
                                    <button type="submit" class="btn btn-secondary btn-sm" name="action" value="delete" formnovalidate onclick="return confirm('Törlöd ezt a gyorsszöveget?');">Törlés</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
