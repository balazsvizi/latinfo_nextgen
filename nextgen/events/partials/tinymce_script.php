<?php
declare(strict_types=1);

$tinymceImageUploadUrl = $tinymceImageUploadUrl ?? events_url('ajax_cms_image_upload.php');
$tinymceImageUploadCsrf = csrf_token('events_cms_image');
?>
<script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js" referrerpolicy="origin"></script>
<script>
(function () {
    if (typeof tinymce === 'undefined') return;

    var uploadUrl = <?= json_encode($tinymceImageUploadUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var uploadCsrf = <?= json_encode($tinymceImageUploadCsrf, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    tinymce.init({
        selector: 'textarea.js-tinymce',
        height: 320,
        menubar: false,
        plugins: 'lists link image table code autoresize',
        toolbar: 'undo redo | blocks | bold italic underline | bullist numlist | link image table | removeformat code',
        content_style: 'body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; font-size: 15px; line-height: 1.55; } img { max-width: 100%; height: auto; }',
        branding: false,
        promotion: false,
        license_key: 'gpl',
        convert_urls: false,
        relative_urls: false,
        automatic_uploads: true,
        images_file_types: 'jpeg,jpg,png,gif,webp',
        file_picker_types: 'image',
        image_title: true,
        images_upload_handler: function (blobInfo) {
            return new Promise(function (resolve, reject) {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', uploadUrl);
                xhr.onload = function () {
                    var raw = xhr.responseText || '';
                    var json = null;
                    try {
                        json = JSON.parse(raw);
                    } catch (e) {
                        reject('A feltöltés válasza érvénytelen.');
                        return;
                    }
                    if (xhr.status < 200 || xhr.status >= 300) {
                        var msg = (json && json.error && json.error.message) ? json.error.message : 'Feltöltés sikertelen.';
                        reject(msg);
                        return;
                    }
                    if (!json || !json.location) {
                        reject('A feltöltés nem adott vissza kép URL-t.');
                        return;
                    }
                    resolve(json.location);
                };
                xhr.onerror = function () {
                    reject('Hálózati hiba a kép feltöltésekor.');
                };
                var fd = new FormData();
                fd.append('file', blobInfo.blob(), blobInfo.filename());
                fd.append('_csrf', uploadCsrf);
                xhr.send(fd);
            });
        },
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
