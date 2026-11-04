<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
requireLogin();

$db = getDb();
$schemaOk = events_slug_redirects_ensure_schema($db);
$hiba = '';
$selfUrl = events_url('slug_atiranyitasok.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('events_slug_redirects')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet. Töltsd újra az oldalt.';
    } elseif (!$schemaOk) {
        $hiba = 'A slug átirányítás táblák nem érhetők el.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        try {
            if ($action === 'delete_redirect') {
                $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
                $id = ($id === false || $id <= 0) ? 0 : $id;
                if ($id <= 0) {
                    $hiba = 'Érvénytelen átirányítás.';
                } else {
                    $st = $db->prepare('SELECT `old_slug`, `event_id` FROM `events_slug_redirects` WHERE `id` = ? LIMIT 1');
                    $st->execute([$id]);
                    $row = $st->fetch(PDO::FETCH_ASSOC);
                    if (!is_array($row) || !events_slug_redirect_delete($db, $id)) {
                        $hiba = 'Az átirányítás nem található vagy már törölve.';
                    } else {
                        if (function_exists('rendszer_log')) {
                            rendszer_log(
                                'slug_átirányítás',
                                (int) ($row['event_id'] ?? 0),
                                'Törölve',
                                (string) ($row['old_slug'] ?? '')
                            );
                        }
                        flash('success', 'Átirányítás törölve.');
                        redirect($selfUrl);
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('events slug_atiranyitasok: ' . $e->getMessage());
            $hiba = 'A művelet nem sikerült.';
        }
    }
}

$redirects = $schemaOk ? events_slug_redirects_list($db) : [];

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Slug átirányítások';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>
<?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>

<div class="card events-admin-card">
    <div class="events-list-head">
        <h2 class="events-list-title">Slug csere</h2>
        <div class="events-list-actions">
            <a href="<?= h(events_url('events_admin.php')) ?>" class="btn btn-secondary">Események</a>
        </div>
    </div>
    <p class="help">
        Közzétett vagy előzetes eseménynél a slug cseréje elmenti a régi slugot.
        A régi publikus URL 301-es átirányítással az aktuális slugra mutat. Piszkozat slugja nem kerül átirányításra.
    </p>
    <?php if (!$schemaOk): ?>
        <p class="alert alert-error">A slug táblák nem jöttek létre. Próbáld újra, vagy futtasd: <code>events/sql/migration_slug_redirects.sql</code></p>
    <?php endif; ?>
</div>

<div class="card events-admin-card">
    <div class="events-list-head">
        <h2 class="events-list-title">Átirányítások</h2>
    </div>
    <p class="help">A régi slug a publikus oldalon az esemény aktuális URL-jére irányít. Törlés után a régi cím 404-et ad, a slug újra használható.</p>
    <div class="table-wrap events-admin-table-wrap">
        <table class="events-admin-table">
            <thead>
                <tr>
                    <th scope="col">Régi slug</th>
                    <th scope="col">Új slug</th>
                    <th scope="col">Esemény</th>
                    <th scope="col">Létrehozva</th>
                    <th scope="col">Művelet</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($redirects === []): ?>
                    <tr>
                        <td colspan="5">Még nincs slug átirányítás.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($redirects as $redir): ?>
                        <?php
                        $redirId = (int) ($redir['id'] ?? 0);
                        $eventId = (int) ($redir['event_id'] ?? 0);
                        $oldSlug = (string) ($redir['old_slug'] ?? '');
                        $newSlug = (string) ($redir['new_slug'] ?? '');
                        $eventName = (string) ($redir['event_name'] ?? '');
                        $created = (string) ($redir['created'] ?? '');
                        ?>
                        <tr>
                            <td>
                                <?php if ($oldSlug !== ''): ?>
                                    <a href="<?= h(events_megjelenit_url($oldSlug)) ?>" target="_blank" rel="noopener"><?= h($oldSlug) ?></a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($newSlug !== ''): ?>
                                    <a href="<?= h(events_megjelenit_url($newSlug)) ?>" target="_blank" rel="noopener"><?= h($newSlug) ?></a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($eventId > 0 && $eventName !== ''): ?>
                                    <a href="<?= h(events_url('szerkeszt.php?id=' . $eventId)) ?>"><?= h($eventName) ?></a>
                                <?php elseif ($eventId > 0): ?>
                                    #<?= $eventId ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?= $created !== '' ? h($created) : '—' ?></td>
                            <td class="events-admin-table__actions">
                                <form method="post" onsubmit="return confirm('Törlöd ezt az átirányítást?');">
                                    <?= csrf_input('events_slug_redirects') ?>
                                    <input type="hidden" name="action" value="delete_redirect">
                                    <input type="hidden" name="id" value="<?= $redirId ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm">Törlés</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
