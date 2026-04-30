<script src="https://cdn.ckeditor.com/ckeditor5/40.2.0/classic/ckeditor.js"></script>
<script>
(function () {
    function buildEnhancedEditor(textarea) {
        var wrapper = document.createElement('div');
        wrapper.className = 'html-editor';
        textarea.parentNode.insertBefore(wrapper, textarea);
        wrapper.appendChild(textarea);

        var toolbar = document.createElement('div');
        toolbar.className = 'html-editor-toolbar';
        toolbar.innerHTML = ''
            + '<button type="button" class="js-toggle-source">Forráskód</button>'
            + '<button type="button" class="js-toggle-preview">Előnézet</button>';

        var preview = document.createElement('div');
        preview.className = 'html-editor-preview';
        preview.hidden = true;
        var sourceBox = document.createElement('textarea');
        sourceBox.className = 'html-editor-source';
        sourceBox.rows = Math.max(parseInt(textarea.getAttribute('rows') || '14', 10), 10);
        sourceBox.hidden = true;
        sourceBox.value = textarea.value || '';

        wrapper.insertBefore(toolbar, textarea);
        wrapper.insertBefore(preview, textarea);
        wrapper.insertBefore(sourceBox, textarea);

        var sourceMode = false;
        var editorInstance = null;
        var hasCk = typeof window.ClassicEditor !== 'undefined';

        function syncPreview() {
            if (sourceMode) {
                preview.innerHTML = sourceBox.value || '';
                return;
            }
            preview.innerHTML = editorInstance ? editorInstance.getData() : (textarea.value || '');
        }

        function setSourceMode(enabled) {
            sourceMode = enabled;
            if (sourceMode) {
                if (editorInstance) {
                    sourceBox.value = editorInstance.getData();
                } else {
                    sourceBox.value = textarea.value || '';
                }
                sourceBox.hidden = false;
            } else {
                if (editorInstance) {
                    editorInstance.setData(sourceBox.value || '');
                } else {
                    textarea.value = sourceBox.value || '';
                }
                sourceBox.hidden = true;
            }
            toolbar.querySelector('.js-toggle-source').classList.toggle('is-active', sourceMode);
            if (sourceMode) {
                preview.hidden = true;
                toolbar.querySelector('.js-toggle-preview').classList.remove('is-active');
            }
        }

        toolbar.addEventListener('click', function (e) {
            var btn = e.target.closest('button');
            if (!btn) return;
            if (btn.classList.contains('js-toggle-source')) {
                setSourceMode(!sourceMode);
                return;
            }
            if (btn.classList.contains('js-toggle-preview')) {
                if (editorInstance && !sourceMode) {
                    textarea.value = editorInstance.getData();
                } else if (sourceMode) {
                    textarea.value = sourceBox.value || '';
                }
                syncPreview();
                preview.hidden = !preview.hidden;
                btn.classList.toggle('is-active', !preview.hidden);
            }
        });

        var form = textarea.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                if (editorInstance && !sourceMode) {
                    textarea.value = editorInstance.getData();
                } else if (sourceMode) {
                    textarea.value = sourceBox.value || '';
                }
            });
        }

        if (!hasCk) {
            textarea.hidden = false;
            return;
        }

        window.ClassicEditor.create(textarea, {
            toolbar: [
                'heading', '|',
                'bold', 'italic', 'underline', 'strikethrough', '|',
                'bulletedList', 'numberedList', 'outdent', 'indent', '|',
                'link', 'blockQuote', 'insertTable', '|',
                'undo', 'redo'
            ],
            heading: {
                options: [
                    { model: 'paragraph', title: 'Bekezdés', class: 'ck-heading_paragraph' },
                    { model: 'heading1', view: 'h2', title: 'Címsor 1', class: 'ck-heading_heading1' },
                    { model: 'heading2', view: 'h3', title: 'Címsor 2', class: 'ck-heading_heading2' }
                ]
            },
            link: {
                decorators: {
                    openInNewTab: {
                        mode: 'manual',
                        label: 'Új lapon nyíljon',
                        defaultValue: true,
                        attributes: {
                            target: '_blank',
                            rel: 'noopener noreferrer'
                        }
                    }
                }
            }
        }).then(function (editor) {
            editorInstance = editor;
            textarea.hidden = true;
        }).catch(function () {
            textarea.hidden = false;
        });
    }

    document.querySelectorAll('.js-html-editor-source').forEach(buildEnhancedEditor);
})();
</script>
