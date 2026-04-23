<?php
require_once __DIR__ . '/../../../nextgen/core/database.php';
require_once __DIR__ . '/../../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../../nextgen/includes/functions.php';
require_once __DIR__ . '/bootstrap.php';
requireLogin();

$db = getDb();
ensure_levelsablonok_table($db);
$hiba = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $stmt = $db->prepare('INSERT INTO finance_email_templates (név, kód, tárgy, megjegyzés, html_tartalom) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$nev, $kod, $targy, $megjegyzes ?: null, $html]);
            $id = (int)$db->lastInsertId();
            rendszer_log('levélsablon', $id, 'Létrehozva', 'Kód: ' . $kod);
            flash('success', 'Levélsablon létrehozva.');
            redirect(nextgen_url('config/levelsablonok/szerkeszt.php?id=') . $id);
        } catch (PDOException $e) {
            $hiba = ((string)$e->getCode() === '23000')
                ? 'Ez a kód már foglalt.'
                : ('Hiba: ' . $e->getMessage());
        }
    }
}

$pageTitle = 'Új levélsablon';
require_once __DIR__ . '/../../partials/header.php';
?>
<div class="card">
    <h2>Új levélsablon</h2>
    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post">
        <div class="form-group"><label>Név *</label><input type="text" name="név" value="<?= h($_POST['név'] ?? '') ?>" required></div>
        <div class="form-group"><label>Kód *</label><input type="text" name="kód" value="<?= h($_POST['kód'] ?? '') ?>" required placeholder="pl. szamla_kikuldes"></div>
        <div class="form-group"><label>Tárgy *</label><input type="text" name="tárgy" value="<?= h($_POST['tárgy'] ?? '') ?>" required placeholder="pl. Számla: {{szamla_szam}}"></div>
        <div class="form-group"><label>Megjegyzés</label><input type="text" name="megjegyzés" value="<?= h($_POST['megjegyzés'] ?? '') ?>"></div>
        <div class="form-group">
            <label>HTML szerkesztő *</label>
            <textarea name="html_tartalom" class="js-html-editor-source" rows="14" required placeholder="<h1>Példa</h1>"><?= h($_POST['html_tartalom'] ?? '') ?></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Mentés</button>
            <a href="<?= h(nextgen_url('config/levelsablonok/')) ?>" class="btn btn-secondary">Mégse</a>
        </div>
    </form>
</div>
<script>
(function () {
    function buildEditor(textarea) {
        var wrapper = document.createElement('div');
        wrapper.className = 'html-editor';

        var toolbar = document.createElement('div');
        toolbar.className = 'html-editor-toolbar';
        toolbar.innerHTML = ''
            + '<button type="button" data-cmd="bold"><strong>B</strong></button>'
            + '<button type="button" data-cmd="italic"><em>I</em></button>'
            + '<button type="button" data-cmd="underline"><u>U</u></button>'
            + '<button type="button" data-cmd="insertUnorderedList">Lista</button>'
            + '<button type="button" data-cmd="insertOrderedList">Számozás</button>'
            + '<button type="button" data-cmd="createLink">Link</button>'
            + '<button type="button" data-cmd="formatBlock" data-value="h2">Címsor</button>'
            + '<button type="button" data-cmd="formatBlock" data-value="p">Bekezdés</button>'
            + '<button type="button" class="js-toggle-source">Forráskód</button>'
            + '<button type="button" class="js-toggle-preview">Előnézet</button>';

        var editor = document.createElement('div');
        editor.className = 'html-editor-area';
        editor.contentEditable = 'true';
        editor.innerHTML = textarea.value || '';

        var preview = document.createElement('div');
        preview.className = 'html-editor-preview';
        preview.hidden = true;

        textarea.parentNode.insertBefore(wrapper, textarea);
        wrapper.appendChild(toolbar);
        wrapper.appendChild(editor);
        wrapper.appendChild(preview);
        wrapper.appendChild(textarea);
        textarea.classList.add('html-editor-source');
        textarea.hidden = true;

        var sourceMode = false;

        function syncToSource() {
            textarea.value = editor.innerHTML;
        }
        function syncFromSource() {
            editor.innerHTML = textarea.value;
        }

        editor.addEventListener('input', syncToSource);

        toolbar.addEventListener('click', function (e) {
            var btn = e.target.closest('button');
            if (!btn) return;

            if (btn.classList.contains('js-toggle-source')) {
                sourceMode = !sourceMode;
                if (sourceMode) {
                    syncToSource();
                    textarea.hidden = false;
                    editor.hidden = true;
                    preview.hidden = true;
                    btn.classList.add('is-active');
                } else {
                    syncFromSource();
                    textarea.hidden = true;
                    editor.hidden = false;
                    btn.classList.remove('is-active');
                }
                return;
            }

            if (btn.classList.contains('js-toggle-preview')) {
                if (sourceMode) {
                    syncFromSource();
                }
                syncToSource();
                preview.innerHTML = textarea.value;
                preview.hidden = !preview.hidden;
                btn.classList.toggle('is-active', !preview.hidden);
                return;
            }

            var cmd = btn.getAttribute('data-cmd');
            if (!cmd) return;
            editor.focus();
            if (cmd === 'createLink') {
                var url = window.prompt('Link URL:', 'https://');
                if (url) document.execCommand('createLink', false, url);
            } else if (cmd === 'formatBlock') {
                document.execCommand('formatBlock', false, btn.getAttribute('data-value') || 'p');
            } else {
                document.execCommand(cmd, false, null);
            }
            syncToSource();
        });

        var form = textarea.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                if (!sourceMode) syncToSource();
            });
        }
    }

    document.querySelectorAll('.js-html-editor-source').forEach(buildEditor);
})();
</script>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
