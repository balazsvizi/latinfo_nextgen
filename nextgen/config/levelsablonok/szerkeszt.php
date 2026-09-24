<?php
require_once __DIR__ . '/../../../nextgen/core/database.php';
require_once __DIR__ . '/../../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../../nextgen/includes/functions.php';
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/events/lib/event_notify_email.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Hiányzó azonosító.');
    redirect(nextgen_url('config/levelsablonok/'));
}

$db = getDb();
ensure_levelsablonok_table($db);
events_notify_email_ensure_schema($db);
$stmt = $db->prepare('SELECT * FROM finance_email_templates WHERE id = ?');
$stmt->execute([$id]);
$sablon = $stmt->fetch();
if (!$sablon) {
    flash('error', 'Levélsablon nem található.');
    redirect(nextgen_url('config/levelsablonok/'));
}

$hiba = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['set_event_notify_default'])) {
        events_notify_email_set_default_template_id($db, $id);
        rendszer_log('levélsablon', $id, 'Esemény-értesítő default', null);
        flash('success', 'Ez a sablon az esemény-értesítő alapértelmezettje.');
        redirect(nextgen_url('config/levelsablonok/szerkeszt.php?id=') . $id);
    }

    $nev = trim($_POST['név'] ?? '');
    $kod = trim($_POST['kód'] ?? '');
    $targy = trim($_POST['tárgy'] ?? '');
    $megjegyzes = trim($_POST['megjegyzés'] ?? '');
    $html = trim($_POST['html_tartalom'] ?? '');

    if ($nev === '' || $kod === '' || $targy === '' || $html === '') {
        $hiba = 'Név, kód, tárgy és HTML tartalom kötelező.';
    } elseif (!preg_match('/^[a-z0-9_.-]+$/i', $kod)) {
        $hiba = 'A kód csak betűt, számot, pontot, kötőjelet és alsóvonalat tartalmazhat.';
    } else {
        try {
            $upd = $db->prepare('UPDATE finance_email_templates SET név = ?, kód = ?, tárgy = ?, megjegyzés = ?, html_tartalom = ? WHERE id = ?');
            $upd->execute([$nev, $kod, $targy, $megjegyzes ?: null, $html, $id]);
            rendszer_log('levélsablon', $id, 'Módosítva', 'Kód: ' . $kod);
            flash('success', 'Levélsablon mentve.');
            redirect(nextgen_url('config/levelsablonok/szerkeszt.php?id=') . $id);
        } catch (PDOException $e) {
            $hiba = ((string)$e->getCode() === '23000')
                ? 'Ez a kód már foglalt.'
                : ('Hiba: ' . $e->getMessage());
        }
    }

    $sablon['név'] = $nev;
    $sablon['kód'] = $kod;
    $sablon['tárgy'] = $targy;
    $sablon['megjegyzés'] = $megjegyzes;
    $sablon['html_tartalom'] = $html;
}

$isEventNotifyDefault = events_notify_email_default_template_id($db) === $id;
$placeholderCatalog = events_levelsablon_placeholder_catalog_for_code((string) ($sablon['kód'] ?? ''));
$placeholderGroups = [];
foreach ($placeholderCatalog as $row) {
    $group = (string) ($row['group'] ?? 'Mezők');
    if (!isset($placeholderGroups[$group])) {
        $placeholderGroups[$group] = [];
    }
    $placeholderGroups[$group][] = $row;
}

$logStmt = $db->prepare("
    SELECT r.*, a.név AS admin_név
    FROM nextgen_system_log r
    LEFT JOIN nextgen_admins a ON a.id = r.admin_id
    WHERE r.entitás = ? AND r.entitás_id = ?
    ORDER BY r.létrehozva DESC
    LIMIT 30
");
$logStmt->execute(['levélsablon', $id]);
$sablonLogok = $logStmt->fetchAll();

$pageTitle = 'Levélsablon szerkesztése';
require_once __DIR__ . '/../../partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<div class="card levelsablon-edit">
    <h2>Levélsablon szerkesztése</h2>
    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <?php if ($isEventNotifyDefault): ?>
        <p class="alert alert-success">Ez a sablon az <strong>esemény-értesítő</strong> alapértelmezettje.</p>
    <?php else: ?>
        <form method="post" class="toolbar" style="margin-bottom:1rem;">
            <button type="submit" name="set_event_notify_default" value="1" class="btn btn-secondary">Beállítás esemény-értesítő defaultnak</button>
        </form>
    <?php endif; ?>
    <form method="post" id="levelsablon-edit-form" class="levelsablon-edit__form">
        <div class="form-row form-row-2">
            <div class="form-group"><label for="sablon_nev">Név *</label><input type="text" id="sablon_nev" name="név" value="<?= h($sablon['név']) ?>" required></div>
            <div class="form-group"><label for="sablon_kod">Kód *</label><input type="text" id="sablon_kod" name="kód" value="<?= h($sablon['kód']) ?>" required></div>
        </div>
        <div class="form-group">
            <label for="sablon_targy">Tárgy *</label>
            <input type="text" id="sablon_targy" name="tárgy" class="js-levelsablon-insert-target" value="<?= h($sablon['tárgy'] ?? '') ?>" required>
        </div>
        <div class="form-group"><label for="sablon_megjegyzes">Megjegyzés</label><input type="text" id="sablon_megjegyzes" name="megjegyzés" value="<?= h($sablon['megjegyzés'] ?? '') ?>"></div>

        <?php if ($placeholderGroups !== []): ?>
        <div class="levelsablon-refs">
            <div class="levelsablon-refs__head">
                <strong>Referenciás mezők</strong>
                <span class="help">Kattints a mezőre: a fókuszált tárgyba vagy a HTML tartalomba kerül.</span>
            </div>
            <?php foreach ($placeholderGroups as $groupLabel => $rows): ?>
                <div class="levelsablon-refs__group">
                    <div class="levelsablon-refs__group-title"><?= h($groupLabel) ?></div>
                    <div class="levelsablon-refs__chips">
                        <?php foreach ($rows as $ph): ?>
                            <button
                                type="button"
                                class="levelsablon-refs__chip"
                                data-token="<?= h((string) $ph['token']) ?>"
                                title="<?= h((string) $ph['token']) ?>"
                            >
                                <span class="levelsablon-refs__chip-label"><?= h((string) $ph['label']) ?></span>
                                <code class="levelsablon-refs__chip-token"><?= h((string) $ph['token']) ?></code>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="form-group">
            <label for="sablon_html">HTML tartalom *</label>
            <textarea id="sablon_html" name="html_tartalom" class="js-levelsablon-html" rows="16" required><?= h($sablon['html_tartalom']) ?></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Mentés</button>
            <a href="<?= h(nextgen_url('config/levelsablonok/')) ?>" class="btn btn-secondary">Vissza</a>
        </div>
    </form>
</div>
<div class="card">
    <h2>Sablon logok</h2>
    <div class="log-list">
        <?php foreach ($sablonLogok as $l): ?>
        <div class="log-item">
            <span class="log-date">ID: <?= (int)($l['id'] ?? 0) ?> · <?= h($l['létrehozva']) ?> <?= $l['admin_név'] ? '(' . h($l['admin_név']) . ')' : '' ?></span>
            <p style="margin:0.25rem 0 0;"><?= h($l['művelet']) ?><?= !empty($l['részletek']) ? ' – ' . nl2br(h($l['részletek'])) : '' ?></p>
        </div>
        <?php endforeach; ?>
        <?php if (empty($sablonLogok)): ?><p>Még nincs naplóbejegyzés ehhez a sablonhoz.</p><?php endif; ?>
    </div>
</div>
<script>
(function () {
    var form = document.getElementById('levelsablon-edit-form');
    var subjectEl = document.getElementById('sablon_targy');
    var htmlEl = document.getElementById('sablon_html');
    if (!form || !htmlEl) return;

    var lastTarget = subjectEl || htmlEl;
    var mode = 'html';
    var visualEl = null;
    var formatBar = null;
    var btnHtml = null;
    var btnSource = null;

    function buildEditor(textarea) {
        var wrapper = document.createElement('div');
        wrapper.className = 'html-editor levelsablon-html-editor';

        var modeBar = document.createElement('div');
        modeBar.className = 'html-editor-toolbar levelsablon-html-editor__modes';
        btnHtml = document.createElement('button');
        btnHtml.type = 'button';
        btnHtml.textContent = 'HTML';
        btnHtml.className = 'is-active';
        btnSource = document.createElement('button');
        btnSource.type = 'button';
        btnSource.textContent = 'Forráskód';
        modeBar.appendChild(btnHtml);
        modeBar.appendChild(btnSource);

        formatBar = document.createElement('div');
        formatBar.className = 'html-editor-toolbar levelsablon-html-editor__format';
        formatBar.innerHTML = ''
            + '<button type="button" data-cmd="bold" title="Félkövér"><strong>B</strong></button>'
            + '<button type="button" data-cmd="italic" title="Dőlt"><em>I</em></button>'
            + '<button type="button" data-cmd="underline" title="Aláhúzott"><u>U</u></button>'
            + '<button type="button" data-cmd="insertUnorderedList">Lista</button>'
            + '<button type="button" data-cmd="insertOrderedList">Számozás</button>'
            + '<button type="button" data-cmd="createLink">Link</button>'
            + '<button type="button" data-cmd="formatBlock" data-value="h2">Címsor</button>'
            + '<button type="button" data-cmd="formatBlock" data-value="p">Bekezdés</button>';

        visualEl = document.createElement('div');
        visualEl.className = 'html-editor-area js-levelsablon-insert-target';
        visualEl.contentEditable = 'true';
        visualEl.setAttribute('role', 'textbox');
        visualEl.setAttribute('aria-multiline', 'true');
        visualEl.innerHTML = textarea.value || '';

        textarea.parentNode.insertBefore(wrapper, textarea);
        wrapper.appendChild(modeBar);
        wrapper.appendChild(formatBar);
        wrapper.appendChild(visualEl);
        wrapper.appendChild(textarea);
        textarea.classList.add('html-editor-source', 'js-levelsablon-insert-target');
        textarea.hidden = true;

        function syncToField() {
            textarea.value = visualEl.innerHTML;
        }
        function syncFromField() {
            visualEl.innerHTML = textarea.value || '';
        }
        function setMode(next) {
            if (next === mode) return;
            if (next === 'source') {
                syncToField();
                visualEl.hidden = true;
                formatBar.hidden = true;
                textarea.hidden = false;
                btnHtml.classList.remove('is-active');
                btnSource.classList.add('is-active');
            } else {
                syncFromField();
                textarea.hidden = true;
                visualEl.hidden = false;
                formatBar.hidden = false;
                btnSource.classList.remove('is-active');
                btnHtml.classList.add('is-active');
            }
            mode = next;
        }

        visualEl.addEventListener('input', syncToField);
        visualEl.addEventListener('focus', function () { lastTarget = visualEl; });
        textarea.addEventListener('focus', function () { lastTarget = textarea; });
        btnHtml.addEventListener('click', function () { setMode('html'); });
        btnSource.addEventListener('click', function () { setMode('source'); });

        formatBar.addEventListener('click', function (e) {
            var btn = e.target.closest('button[data-cmd]');
            if (!btn || mode !== 'html') return;
            e.preventDefault();
            visualEl.focus();
            lastTarget = visualEl;
            var cmd = btn.getAttribute('data-cmd');
            if (cmd === 'createLink') {
                var url = window.prompt('Link URL:', 'https://');
                if (url) document.execCommand('createLink', false, url);
            } else if (cmd === 'formatBlock') {
                document.execCommand('formatBlock', false, btn.getAttribute('data-value') || 'p');
            } else {
                document.execCommand(cmd, false, null);
            }
            syncToField();
        });

        form.addEventListener('submit', function () {
            if (mode === 'html') syncToField();
        });

        window.levelsablonHtmlEditor = {
            insertToken: function (token) {
                if (mode === 'source') {
                    insertAtCursor(textarea, token);
                    lastTarget = textarea;
                    return;
                }
                visualEl.focus();
                lastTarget = visualEl;
                try {
                    document.execCommand('insertText', false, token);
                } catch (err) {
                    visualEl.appendChild(document.createTextNode(token));
                }
                syncToField();
            },
            getMode: function () { return mode; }
        };
    }

    function insertAtCursor(input, text) {
        var start = input.selectionStart || 0;
        var end = input.selectionEnd || 0;
        var value = input.value || '';
        input.value = value.slice(0, start) + text + value.slice(end);
        var pos = start + text.length;
        input.focus();
        if (typeof input.setSelectionRange === 'function') {
            input.setSelectionRange(pos, pos);
        }
    }

    buildEditor(htmlEl);

    if (subjectEl) {
        subjectEl.addEventListener('focus', function () { lastTarget = subjectEl; });
    }

    document.querySelectorAll('.levelsablon-refs__chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            var token = chip.getAttribute('data-token') || '';
            if (!token) return;
            if (lastTarget === subjectEl || (lastTarget && lastTarget.id === 'sablon_targy')) {
                insertAtCursor(subjectEl, token);
                return;
            }
            if (window.levelsablonHtmlEditor) {
                window.levelsablonHtmlEditor.insertToken(token);
            } else {
                insertAtCursor(htmlEl, token);
            }
        });
    });
})();
</script>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
