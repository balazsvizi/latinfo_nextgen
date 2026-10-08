(function () {
    'use strict';

    var form = document.querySelector('[data-dance-styles]');
    if (!form) {
        return;
    }

    var labels = {
        0: 'Nem adom meg',
        1: 'Kezdő',
        2: 'Kezdő',
        3: 'Kezdő',
        4: 'Középhaladó',
        5: 'Középhaladó',
        6: 'Középhaladó',
        7: 'Haladó',
        8: 'Haladó',
        9: 'Profi',
        10: 'Profi'
    };

    function syncRow(input) {
        var row = input.closest('[data-dance-style-row]');
        if (!row) {
            return;
        }
        var level = parseInt(input.value, 10);
        if (isNaN(level) || level < 0) {
            level = 0;
        }
        if (level > 10) {
            level = 10;
        }

        var label = labels[level] || 'Nem adom meg';
        var valueEl = row.querySelector('[data-dance-style-value]');
        var labelEl = row.querySelector('[data-dance-style-label]');
        var track = row.querySelector('.user-dance-style__track');

        if (valueEl) {
            valueEl.textContent = level > 0 ? String(level) : '—';
        }
        if (labelEl) {
            labelEl.textContent = label;
        }
        if (track) {
            track.style.setProperty('--level-pct', level <= 0 ? '0%' : String(Math.round((level / 10) * 100)) + '%');
        }

        row.classList.toggle('is-set', level > 0);
        input.setAttribute('aria-valuenow', String(level));
        input.setAttribute('aria-valuetext', level > 0 ? label + ' (' + level + ')' : label);
    }

    form.querySelectorAll('[data-dance-style-range]').forEach(function (input) {
        syncRow(input);
        input.addEventListener('input', function () {
            syncRow(input);
        });
        input.addEventListener('change', function () {
            syncRow(input);
        });
    });
})();
