<?php
/** @var int|null $listLimitDefault */
$listLimitDefault = $listLimitDefault ?? EVENTS_ADMIN_LIST_DEFAULT_LIMIT;
?>
<script>
(function () {
    document.querySelectorAll('[data-list-limit-select]').forEach(function (sel) {
        if (!sel || sel.getAttribute('data-list-limit-bound') === '1') {
            return;
        }
        sel.setAttribute('data-list-limit-bound', '1');
        sel.addEventListener('change', function () {
            var form = sel.closest('form');
            if (form && sel.name) {
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
                return;
            }
            var url = new URL(window.location.href);
            var val = (sel.value || '').trim();
            if (val === '' || val === '<?= (string) $listLimitDefault ?>') {
                url.searchParams.delete('list_limit');
            } else {
                url.searchParams.set('list_limit', val);
            }
            window.location.href = url.toString();
        });
    });
})();
</script>
