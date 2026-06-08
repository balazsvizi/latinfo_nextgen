<script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js" referrerpolicy="origin"></script>
<script>
(function () {
    if (typeof tinymce === 'undefined') return;

    tinymce.init({
        selector: 'textarea.js-tinymce',
        height: 320,
        menubar: false,
        plugins: 'lists link image table code autoresize',
        toolbar: 'undo redo | blocks | bold italic underline | bullist numlist | link image table | removeformat code',
        content_style: 'body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; font-size: 15px; line-height: 1.55; }',
        branding: false,
        promotion: false,
        license_key: 'gpl',
        convert_urls: false,
        relative_urls: false,
        setup: function (editor) {
            editor.on('change', function () { editor.save(); });
        }
    });

    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function () {
            if (typeof tinymce !== 'undefined') {
                tinymce.triggerSave();
            }
        });
    });
})();
</script>
