<?php
declare(strict_types=1);

/**
 * Partner hozzárendelési sorok – dinamikus sor hozzáadás és wp-token inicializálás.
 *
 * @var string $partnerOrganizerRowsTemplateHtml
 * @var string $partnerDjRowsTemplateHtml
 */
?>
<script>
(function () {
    function reindexRows(container, rowSelector, namePrefix) {
        var rows = container.querySelectorAll(rowSelector);
        rows.forEach(function (row, index) {
            row.querySelectorAll('[name]').forEach(function (el) {
                if (!el.name) {
                    return;
                }
                el.name = el.name.replace(new RegExp('^' + namePrefix + '\\[\\d+\\]'), namePrefix + '[' + index + ']');
            });
            row.querySelectorAll('[id]').forEach(function (el) {
                if (!el.id) {
                    return;
                }
                el.id = el.id.replace(/-\d+$/, '-' + index);
            });
            row.querySelectorAll('[for]').forEach(function (el) {
                if (!el.htmlFor) {
                    return;
                }
                el.htmlFor = el.htmlFor.replace(/-\d+$/, '-' + index);
            });
            row.querySelectorAll('[data-wp-token]').forEach(function (tokenRoot) {
                var baseId = namePrefix === 'organizer_rows' ? 'partner-org-token-' : 'partner-dj-token-';
                tokenRoot.id = baseId + index;
                var fieldSuffix = namePrefix === 'organizer_rows' ? '[organizer_id]' : '[tag_id]';
                tokenRoot.setAttribute('data-field-name', namePrefix + '[' + index + ']' + fieldSuffix);
                var input = tokenRoot.querySelector('.wp-token-input__search');
                if (input) {
                    input.id = baseId + index + '-input';
                }
                var field = tokenRoot.closest('.wp-token-field');
                if (field) {
                    field.id = baseId + index + '-field';
                }
                var hiddenName = tokenRoot.getAttribute('data-field-name') || '';
                tokenRoot.querySelectorAll('.wp-token-input__hiddens input').forEach(function (inp) {
                    inp.name = hiddenName;
                });
            });
        });
    }

    function initTokenInRow(row) {
        if (typeof window.initWpTokenInput !== 'function') {
            return;
        }
        row.querySelectorAll('[data-wp-token]').forEach(function (tokenRoot) {
            if (tokenRoot.getAttribute('data-wp-token-initialized') === '1') {
                return;
            }
            window.initWpTokenInput(tokenRoot);
            tokenRoot.setAttribute('data-wp-token-initialized', '1');
        });
    }

    function bindRoleCheckboxes(row) {
        var roleFieldset = row.querySelector('[data-partner-role-checkboxes]');
        var noteWrap = row.querySelector('[data-partner-role-note-wrap]');
        if (!roleFieldset || !noteWrap) {
            return;
        }
        var checkboxes = roleFieldset.querySelectorAll('input[type="checkbox"][data-partner-role-value]');
        function syncNoteVisibility() {
            var otherChecked = false;
            checkboxes.forEach(function (cb) {
                if (cb.checked && cb.getAttribute('data-partner-role-value') === 'other') {
                    otherChecked = true;
                }
            });
            noteWrap.hidden = !otherChecked;
            if (!otherChecked) {
                var noteInput = noteWrap.querySelector('input');
                if (noteInput) {
                    noteInput.value = '';
                }
            }
        }
        checkboxes.forEach(function (cb) {
            cb.addEventListener('change', syncNoteVisibility);
        });
        syncNoteVisibility();
    }

    function resetRoleCheckboxes(row, defaultRole) {
        var roleFieldset = row.querySelector('[data-partner-role-checkboxes]');
        if (!roleFieldset) {
            return;
        }
        roleFieldset.querySelectorAll('input[type="checkbox"][data-partner-role-value]').forEach(function (cb) {
            cb.checked = cb.getAttribute('data-partner-role-value') === defaultRole;
        });
        var noteWrap = row.querySelector('[data-partner-role-note-wrap]');
        if (noteWrap) {
            noteWrap.hidden = true;
            var noteInput = noteWrap.querySelector('input');
            if (noteInput) {
                noteInput.value = '';
            }
        }
    }

    function bindRemove(row, container, rowSelector, namePrefix, defaultRole) {
        var btn = row.querySelector('[data-partner-assign-remove]');
        if (!btn) {
            return;
        }
        btn.addEventListener('click', function () {
            var rows = container.querySelectorAll(rowSelector);
            if (rows.length <= 1) {
                row.querySelectorAll('[data-wp-token]').forEach(function (tokenRoot) {
                    var hiddens = tokenRoot.querySelector('.wp-token-input__hiddens');
                    var tokens = tokenRoot.querySelector('.wp-token-input__tokens');
                    var search = tokenRoot.querySelector('.wp-token-input__search');
                    if (hiddens) {
                        hiddens.innerHTML = '';
                        var inp = document.createElement('input');
                        inp.type = 'hidden';
                        inp.name = tokenRoot.getAttribute('data-field-name') || '';
                        inp.value = '';
                        hiddens.appendChild(inp);
                    }
                    if (tokens) {
                        tokens.innerHTML = '';
                    }
                    if (search) {
                        search.value = '';
                    }
                });
                resetRoleCheckboxes(row, defaultRole);
                return;
            }
            row.remove();
            reindexRows(container, rowSelector, namePrefix);
        });
    }

    function setupSection(options) {
        var container = document.getElementById(options.containerId);
        var addBtn = document.getElementById(options.addButtonId);
        var template = document.getElementById(options.templateId);
        if (!container || !addBtn || !template) {
            return;
        }

        container.querySelectorAll(options.rowSelector).forEach(function (row) {
            initTokenInRow(row);
            bindRoleCheckboxes(row);
            bindRemove(row, container, options.rowSelector, options.namePrefix, options.defaultRole);
        });

        addBtn.addEventListener('click', function () {
            var index = container.querySelectorAll(options.rowSelector).length;
            var html = template.innerHTML.replace(/__INDEX__/g, String(index));
            var wrap = document.createElement('div');
            wrap.innerHTML = html.trim();
            var row = wrap.firstElementChild;
            if (!row) {
                return;
            }
            container.appendChild(row);
            reindexRows(container, options.rowSelector, options.namePrefix);
            initTokenInRow(row);
            bindRoleCheckboxes(row);
            bindRemove(row, container, options.rowSelector, options.namePrefix, options.defaultRole);
        });
    }

    setupSection({
        containerId: 'partner-organizer-rows',
        addButtonId: 'partner-organizer-add',
        templateId: 'partner-organizer-row-template',
        rowSelector: '[data-partner-assign-row="organizer"]',
        namePrefix: 'organizer_rows',
        defaultRole: 'event'
    });

    setupSection({
        containerId: 'partner-dj-rows',
        addButtonId: 'partner-dj-add',
        templateId: 'partner-dj-row-template',
        rowSelector: '[data-partner-assign-row="dj"]',
        namePrefix: 'dj_rows',
        defaultRole: 'dj'
    });
})();
</script>
