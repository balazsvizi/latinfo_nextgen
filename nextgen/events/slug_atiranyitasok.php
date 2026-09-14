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
            if ($action === 'save_delay') {
                $raw = trim((string) ($_POST['save_delay_minutes'] ?? ''));
                if ($raw === '' || !preg_match('/^\d+$/', $raw)) {
                    $hiba = 'A késleltetés csak egész perc lehet.';
                } else {
                    $minutes = events_slug_normalize_delay_minutes((int) $raw);
                    events_slug_save_delay_minutes_save($db, $minutes);
                    if (function_exists('rendszer_log')) {
                        rendszer_log('slug_átirányítás', null, 'Késleltetés mentve', $minutes . ' perc');
                    }
                    flash('success', 'A slug rögzítési késleltetés mentve: ' . $minutes . ' perc.');
                    redirect($selfUrl);
                }
            } elseif ($action === 'delete_redirect') {
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

$delayMinutes = $schemaOk ? events_slug_save_delay_minutes($db) : EVENTS_SLUG_SAVE_DELAY_DEFAULT;
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
        <h2 class="events-list-title">Slug rögzítés</h2>
        <div class="events-list-actions">
            <a href="<?= h(events_url('events_admin.php')) ?>" class="btn btn-secondary">Események</a>
        </div>
    </div>
    <p class="help">
        Új eseménynél a slug a közzététel után még nem rögzül. A megadott perc elteltével az aktuális slug mentődik:
        későbbi slug-csere 301-es átirányítást hoz létre a régi URL-ről az újra.
    </p>
    <?php if (!$schemaOk): ?>
        <p class="alert alert-error">A slug táblák nem jöttek létre. Próbáld újra, vagy futtasd: <code>events/sql/migration_slug_redirects.sql</code></p>
    <?php else: ?>
        <form method="post" class="events-slug-delay-form">
            <?= csrf_input('events_slug_redirects') ?>
            <input type="hidden" name="action" value="save_delay">
            <div class="form-row">
                <div class="form-group">
                    <label for="save_delay_minutes">Rögzítés a publikálás után (perc)</label>
                    <input
                        type="number"
                        id="save_delay_minutes"
                        name="save_delay_minutes"
                        min="<?= EVENTS_SLUG_SAVE_DELAY_MIN ?>"
                        max="<?= EVENTS_SLUG_SAVE_DELAY_MAX ?>"
                        step="1"
                        required
                        value="<?= (int) $delayMinutes ?>"
                    >
                    <p class="help">Alapértelmezés: <?= EVENTS_SLUG_SAVE_DELAY_DEFAULT ?> perc. 0 = a slug a következő mentéstől rögzül.</p>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Késleltetés mentése</button>
        </form>
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
