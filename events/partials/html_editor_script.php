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
