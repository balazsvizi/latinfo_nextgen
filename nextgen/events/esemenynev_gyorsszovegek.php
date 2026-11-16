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
$stickyLabel = '';
$isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('events_name_snippets')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet. Töltsd újra az oldalt.';
    } elseif (!$schemaOk) {
        $hiba = 'A gyorsszöveg tábla nem érhető el.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $idRaw = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        $id = ($idRaw === false || $idRaw < 0) ? 0 : $idRaw;
        $label = (string) ($_POST['label'] ?? '');
        try {
            if ($action === 'reorder') {
                $rawOrder = $_POST['order'] ?? [];
                $orderIds = [];
                if (is_array($rawOrder)) {
                    foreach ($rawOrder as $rawId) {
                        $parsed = filter_var($rawId, FILTER_VALIDATE_INT);
                        if ($parsed !== false && $parsed > 0) {
                            $orderIds[] = $parsed;
                        }
                    }
                }
                $saved = events_name_snippets_reorder($db, $orderIds);
                if ($saved && function_exists('rendszer_log')) {
                    rendszer_log('eseménynév_gyorsszöveg', null, 'Sorrend', implode(',', $orderIds));
                }
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    http_response_code($saved ? 200 : 400);
                    echo json_encode([
                        'ok' => $saved,
                        'error' => $saved ? null : 'A sorrend mentése nem sikerült.',
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                if ($saved) {
                    flash('success', 'Sorrend mentve.');
                    redirect($selfUrl);
                }
                $hiba = 'A sorrend mentése nem sikerült.';
            } elseif ($action === 'save') {
                $result = events_name_snippet_save($db, $id, $label);
                if (!$result['ok']) {
                    $hiba = (string) ($result['error'] ?? 'A mentés nem sikerült.');
                    if ($id === 0) {
                        $stickyLabel = events_name_snippet_normalize_label($label);
                    }
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

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => $hiba !== '' ? $hiba : 'A sorrend mentése nem sikerült.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$snippets = $schemaOk ? events_name_snippets_list($db) : [];

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
        Kattintásra a kurzor helyére kerülnek az esemény nevébe. A sorrendet a fogantyúnál fogva, húzással állíthatod: a felső szöveg jelenik meg először.
    </p>
    <?php if (!$schemaOk): ?>
        <p class="alert alert-error">A gyorsszöveg tábla nem jött létre. Frissítsd az oldalt, vagy ellenőrizd az adatbázis-jogosultságokat.</p>
    <?php else: ?>
        <form method="post" class="events-name-snippets-admin__add">
            <?= csrf_input('events_name_snippets') ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="0">
            <div class="form-group">
                <label for="snippet_label_new">Új gyorsszöveg</label>
                <input type="text" id="snippet_label_new" name="label" required maxlength="<?= (int) EVENTS_NAME_SNIPPET_LABEL_MAX ?>" value="<?= h($stickyLabel) ?>" placeholder="pl. Táncház">
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
    <?php if ($snippets === []): ?>
        <p class="help">Még nincs gyorsszöveg. Add hozzá az elsőt a fenti mezőben.</p>
    <?php else: ?>
        <p class="events-name-snippets-admin__status" id="events-name-snippets-status" role="status" aria-live="polite"></p>
        <ul
            class="events-name-snippets-admin__list"
            id="events-name-snippets-sort"
            data-save-url="<?= h($selfUrl) ?>"
            data-csrf="<?= h(csrf_token('events_name_snippets')) ?>"
        >
            <?php foreach ($snippets as $snippet): ?>
                <?php
                $snippetId = (int) $snippet['id'];
                $snippetLabel = (string) $snippet['label'];
                ?>
                <li class="events-name-snippets-admin__item" data-id="<?= $snippetId ?>">
                    <button type="button" class="events-name-snippets-admin__handle" aria-label="Áthelyezés: <?= h($snippetLabel) ?>">⋮⋮</button>
                    <form method="post" class="events-name-snippets-admin__row">
                        <?= csrf_input('events_name_snippets') ?>
                        <input type="hidden" name="id" value="<?= $snippetId ?>">
                        <input type="text" name="label" required maxlength="<?= (int) EVENTS_NAME_SNIPPET_LABEL_MAX ?>" value="<?= h($snippetLabel) ?>" aria-label="Gyorsszöveg">
                        <button type="submit" class="btn btn-primary btn-sm" name="action" value="save">Mentés</button>
                        <button type="submit" class="btn btn-secondary btn-sm" name="action" value="delete" formnovalidate onclick="return confirm('Törlöd ezt a gyorsszöveget?');">Törlés</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        <script>
        (function () {
            var list = document.getElementById('events-name-snippets-sort');
            var statusEl = document.getElementById('events-name-snippets-status');
            if (!list) return;

            var dragging = null;
            var startOrder = '';
            var saving = false;

            function rows() {
                return Array.prototype.filter.call(list.children, function (el) {
                    return el.hasAttribute('data-id');
                });
            }

            function orderKey() {
                return rows().map(function (el) { return el.getAttribute('data-id'); }).join(',');
            }

            function setStatus(message, isError) {
                if (!statusEl) return;
                statusEl.textContent = message;
                statusEl.classList.toggle('is-error', !!isError);
            }

            function placeAt(clientY) {
                var items = rows().filter(function (el) { return el !== dragging; });
                for (var i = 0; i < items.length; i++) {
                    var rect = items[i].getBoundingClientRect();
                    if (clientY < rect.top + rect.height / 2) {
                        list.insertBefore(dragging, items[i]);
                        return;
                    }
                }
                list.appendChild(dragging);
            }

            function restore(order) {
                var byId = {};
                rows().forEach(function (el) {
                    byId[el.getAttribute('data-id')] = el;
                });
                order.split(',').forEach(function (id) {
                    if (byId[id]) list.appendChild(byId[id]);
                });
            }

            function saveOrder(previous) {
                if (saving) return;
                saving = true;
                setStatus('Sorrend mentése…', false);
                var body = new URLSearchParams();
                body.set('_csrf', list.getAttribute('data-csrf') || '');
                body.set('action', 'reorder');
                rows().forEach(function (el) {
                    body.append('order[]', el.getAttribute('data-id') || '');
                });
                fetch(list.getAttribute('data-save-url') || window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: body.toString(),
                    credentials: 'same-origin'
                }).then(function (res) {
                    return res.json().then(function (data) {
                        if (!res.ok || !data || !data.ok) {
                            throw new Error((data && data.error) || '');
                        }
                        startOrder = orderKey();
                        setStatus('Sorrend mentve.', false);
                    });
                }).catch(function () {
                    restore(previous);
                    setStatus('A sorrend mentése nem sikerült.', true);
                }).finally(function () {
                    saving = false;
                });
            }

            list.addEventListener('pointerdown', function (e) {
                var handle = e.target.closest('.events-name-snippets-admin__handle');
                if (!handle || !list.contains(handle) || saving) return;
                var row = handle.closest('[data-id]');
                if (!row) return;
                e.preventDefault();
                dragging = row;
                startOrder = orderKey();
                row.classList.add('is-dragging');
                try {
                    if (handle.setPointerCapture) handle.setPointerCapture(e.pointerId);
                } catch (err) {}
            });

            list.addEventListener('pointermove', function (e) {
                if (!dragging) return;
                placeAt(e.clientY);
            });

            function finishDrag() {
                if (!dragging) return;
                var row = dragging;
                dragging = null;
                row.classList.remove('is-dragging');
                var next = orderKey();
                if (next === startOrder) return;
                saveOrder(startOrder);
            }

            list.addEventListener('pointerup', finishDrag);
            list.addEventListener('pointercancel', finishDrag);
        })();
        </script>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
