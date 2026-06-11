<script>
(function () {
    var toggle = document.getElementById('event_change_active');
    var fields = document.getElementById('events-edit-change-fields');
    var typeSelect = document.getElementById('event_change_type');
    if (!toggle || !fields) return;

    function applyChangeUi() {
        var on = toggle.checked;
        fields.hidden = !on;
        if (typeSelect) {
            typeSelect.required = on;
        }
    }

    toggle.addEventListener('change', applyChangeUi);
    applyChangeUi();
})();
</script>
