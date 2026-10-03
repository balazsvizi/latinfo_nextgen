<?php
declare(strict_types=1);
?>
<script>
(function () {
    var container = document.getElementById('partner-email-rows');
    var addBtn = document.getElementById('partner-email-add');
    var template = document.getElementById('partner-email-row-template');
    if (!container || !addBtn || !template) {
        return;
    }

    function reindexEmailRows() {
        var rows = container.querySelectorAll('[data-partner-email-row]');
        rows.forEach(function (row, index) {
            row.querySelectorAll('[name]').forEach(function (el) {
                if (!el.name) {
                    return;
                }
                el.name = el.name.replace(/^email_rows\[\d+]/, 'email_rows[' + index + ']');
            });
            row.querySelectorAll('[id]').forEach(function (el) {
                if (!el.id) {
                    return;
                }
                el.id = el.id
                    .replace(/partner-email-\d+$/, 'partner-email-' + index)
                    .replace(/partner-email-event-\d+$/, 'partner-email-event-' + index)
                    .replace(/partner-email-stat-\d+$/, 'partner-email-stat-' + index);
            });
            row.querySelectorAll('[for]').forEach(function (el) {
                if (!el.htmlFor) {
                    return;
                }
                el.htmlFor = el.htmlFor
                    .replace(/partner-email-\d+$/, 'partner-email-' + index)
                    .replace(/partner-email-event-\d+$/, 'partner-email-event-' + index)
                    .replace(/partner-email-stat-\d+$/, 'partner-email-stat-' + index);
            });
            var input = row.querySelector('.partner-email-row__input');
            if (input) {
                if (index === 0) {
                    input.required = true;
                    input.placeholder = 'Bejelentkezési e-mail *';
                } else {
                    input.required = false;
                    input.placeholder = 'További e-mail';
                }
            }
        });
    }

    function bindRemove(row) {
        var btn = row.querySelector('[data-partner-email-remove]');
        if (!btn) {
            return;
        }
        btn.addEventListener('click', function () {
            var rows = container.querySelectorAll('[data-partner-email-row]');
            if (rows.length <= 1) {
                var input = row.querySelector('.partner-email-row__input');
                if (input) {
                    input.value = '';
                }
                row.querySelectorAll('.partner-email-switch__input').forEach(function (cb) {
                    cb.checked = true;
                });
                return;
            }
            row.remove();
            reindexEmailRows();
        });
    }

    container.querySelectorAll('[data-partner-email-row]').forEach(bindRemove);

    addBtn.addEventListener('click', function () {
        var index = container.querySelectorAll('[data-partner-email-row]').length;
        var html = template.innerHTML.replace(/__INDEX__/g, String(index));
        var wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        var row = wrap.firstElementChild;
        if (!row) {
            return;
        }
        container.appendChild(row);
        reindexEmailRows();
        bindRemove(row);
        var input = row.querySelector('.partner-email-row__input');
        if (input) {
            input.focus();
        }
    });
})();
</script>
