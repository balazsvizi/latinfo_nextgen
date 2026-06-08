<script>
(function () {
    var btn = document.getElementById('event-end-date-copy-start');
    var startDate = document.getElementById('event_start_date');
    var endDate = document.getElementById('event_end_date');
    if (!btn || !startDate || !endDate) return;
    btn.addEventListener('click', function () {
        if (startDate.value === '') return;
        endDate.value = startDate.value;
        endDate.dispatchEvent(new Event('change', { bubbles: true }));
    });
})();
</script>
