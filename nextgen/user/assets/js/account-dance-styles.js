(function () {
    'use strict';

    var form = document.querySelector('[data-dance-styles]');
    if (!form) {
        return;
    }

    var levelLabels = {
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

    var likingLabels = {
        0: 'Nem adom meg',
        1: 'Egyáltalán nem',
        2: 'Alig',
        3: 'Kicsit',
        4: 'Közepesen',
        5: 'Eléggé',
        6: 'Szeretem',
        7: 'Nagyon',
        8: 'Nagyon szeretem',
        9: 'Imádom',
        10: 'A kedvencem'
    };

    function clampScore(raw) {
        var score = parseInt(raw, 10);
        if (isNaN(score) || score < 0) {
            return 0;
        }
        if (score > 10) {
            return 10;
        }
        return score;
    }

    function labelFor(kind, score) {
        if (kind === 'liking') {
            return likingLabels[score] || 'Nem adom meg';
        }
        return levelLabels[score] || 'Nem adom meg';
    }

    function syncMetric(input) {
        var metric = input.closest('[data-dance-style-metric]');
        if (!metric) {
            return;
        }
        var kind = input.getAttribute('data-dance-style-kind') || 'level';
        var score = clampScore(input.value);
        var label = labelFor(kind, score);
        var valueEl = metric.querySelector('[data-dance-style-value]');
        var labelEl = metric.querySelector('[data-dance-style-label]');
        var track = metric.querySelector('.user-dance-style__track');

        if (valueEl) {
            valueEl.textContent = score > 0 ? String(score) : '—';
        }
        if (labelEl) {
            labelEl.textContent = label;
        }
        if (track) {
            track.style.setProperty(
                '--level-pct',
                score <= 0 ? '0%' : String(Math.round((score / 10) * 100)) + '%'
            );
        }

        metric.classList.toggle('is-set', score > 0 && !input.disabled);
        input.setAttribute('aria-valuenow', String(score));
        input.setAttribute('aria-valuetext', score > 0 ? label + ' (' + score + ')' : label);
    }

    function setCollapsed(row, collapsed) {
        var panel = row.querySelector('[data-dance-style-collapse]');
        row.classList.toggle('is-collapsed', collapsed);
        row.dataset.collapsed = collapsed ? '1' : '0';

        if (panel) {
            panel.setAttribute('aria-hidden', collapsed ? 'true' : 'false');
        }
    }

    function syncRow(row) {
        var ignore = row.querySelector('[data-dance-style-ignore]');
        var ignored = !!(ignore && ignore.checked);
        var fields = row.querySelector('[data-dance-style-fields]');
        var ranges = row.querySelectorAll('[data-dance-style-range]');
        var ignoreLabel = row.querySelector('.user-dance-style__ignore');
        var anySet = false;

        if (ignoreLabel) {
            ignoreLabel.classList.toggle('is-on', ignored);
        }
        if (ignore) {
            ignore.setAttribute('aria-expanded', ignored ? 'false' : 'true');
        }

        ranges.forEach(function (input) {
            input.disabled = ignored;
            syncMetric(input);
            if (!ignored && clampScore(input.value) > 0) {
                anySet = true;
            }
        });

        if (fields) {
            fields.setAttribute('aria-disabled', ignored ? 'true' : 'false');
        }

        setCollapsed(row, ignored);
        row.classList.toggle('is-ignored', ignored);
        row.classList.toggle('is-set', !ignored && anySet);
    }

    form.querySelectorAll('[data-dance-style-row]').forEach(function (row) {
        syncRow(row);

        var ignore = row.querySelector('[data-dance-style-ignore]');
        if (ignore) {
            ignore.addEventListener('change', function () {
                syncRow(row);
            });
        }

        row.querySelectorAll('[data-dance-style-range]').forEach(function (input) {
            input.addEventListener('input', function () {
                syncMetric(input);
                syncRow(row);
            });
            input.addEventListener('change', function () {
                syncMetric(input);
                syncRow(row);
            });
        });
    });
})();
