<script>
(function () {
    var allday = document.getElementById('event_allday');
    var startTimeWrap = document.getElementById('events-edit-start-time-wrap');
    var endTimeWrap = document.getElementById('events-edit-end-time-wrap');
    var startTime = document.getElementById('event_start_time');
    var endTime = document.getElementById('event_end_time');
    var startDate = document.getElementById('event_start_date');
    var endDate = document.getElementById('event_end_date');
    if (!allday || !startTimeWrap || !endTimeWrap) return;

    function applyAlldayUi() {
        var on = allday.checked;
        startTimeWrap.hidden = on;
        endTimeWrap.hidden = on;
        if (on) {
            if (startTime) startTime.value = '00:00';
            if (endDate && startDate && endDate.value === '' && startDate.value !== '') {
                endDate.value = startDate.value;
            }
            if (endTime) endTime.value = '23:59';
        }
    }

    allday.addEventListener('change', applyAlldayUi);
    applyAlldayUi();
})();
</script>
