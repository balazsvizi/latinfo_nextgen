<script>
(function () {
    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
    }

    function initWpToken(root) {
        var jsonEl = root.querySelector('.wp-token-input__json');
        var tokensEl = root.querySelector('.wp-token-input__tokens');
        var searchEl = root.querySelector('.wp-token-input__search');
        var suggEl = root.querySelector('.wp-token-input__suggestions');
        var hiddensEl = root.querySelector('.wp-token-input__hiddens');
        var innerEl = root.querySelector('.wp-token-input__inner');
        var fieldName = root.getAttribute('data-field-name') || 'ids[]';
        var placeholder = root.getAttribute('data-placeholder') || 'Hozzáadás…';
        var popularWrap = root.closest('.wp-token-field');
        var popularBox = popularWrap ? popularWrap.querySelector('[data-wp-token-popular]') : null;
        var popularList = popularWrap ? popularWrap.querySelector('.wp-token-field__popular-list') : null;
        if (!jsonEl || !tokensEl || !searchEl || !suggEl || !hiddensEl) return;

        var data;
        try { data = JSON.parse(jsonEl.textContent || '{}'); } catch (e) { data = { all: [], selected: [] }; }
        var all = Array.isArray(data.all) ? data.all : [];
        var selectedIds = Array.isArray(data.selected)
            ? data.selected.map(function (x) { return parseInt(x, 10); }).filter(function (n) { return n > 0; })
            : [];
        var nameById = {};
        all.forEach(function (row) { nameById[row.id] = row.name; });

        searchEl.placeholder = placeholder;
        var activeIdx = -1;

        function syncHiddens() {
            hiddensEl.innerHTML = '';
            selectedIds.forEach(function (id) {
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = fieldName;
                inp.value = String(id);
                hiddensEl.appendChild(inp);
            });
        }

        function renderTokens() {
            tokensEl.innerHTML = '';
            selectedIds.forEach(function (id) {
                var chip = document.createElement('span');
                chip.className = 'wp-token-input__chip';
                chip.setAttribute('data-id', String(id));
                var label = document.createElement('span');
                label.className = 'wp-token-input__chip-label';
                label.textContent = nameById[id] || ('#' + id);
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'wp-token-input__chip-remove';
                btn.setAttribute('aria-label', 'Eltávolítás: ' + (nameById[id] || id));
                btn.innerHTML = '&times;';
                btn.addEventListener('click', function (ev) {
                    ev.preventDefault();
                    removeId(id);
                });
                chip.appendChild(label);
                chip.appendChild(btn);
                tokensEl.appendChild(chip);
            });
            syncHiddens();
            renderPopular();
        }

        function renderPopular() {
            if (!popularBox || !popularList) return;
            var available = all.filter(function (row) {
                return selectedIds.indexOf(row.id) === -1;
            }).slice(0, 12);
            if (available.length === 0) {
                popularBox.hidden = true;
                return;
            }
            popularBox.hidden = false;
            popularList.innerHTML = '';
            available.forEach(function (row, i) {
                if (i > 0) {
                    popularList.appendChild(document.createTextNode(', '));
                }
                var link = document.createElement('button');
                link.type = 'button';
                link.className = 'wp-token-field__popular-link';
                link.textContent = row.name;
                link.addEventListener('click', function () {
                    addId(row.id);
                    searchEl.focus();
                });
                popularList.appendChild(link);
            });
        }

        function matchesQuery(row, q) {
            if (q === '') return true;
            return (row.name + ' ' + row.id).toLowerCase().indexOf(q) !== -1;
        }

        function suggestionsForQuery(q) {
            var taken = {};
            selectedIds.forEach(function (id) { taken[id] = true; });
            return all.filter(function (row) {
                return !taken[row.id] && matchesQuery(row, q);
            }).slice(0, 8);
        }

        function closeSuggestions() {
            suggEl.hidden = true;
            suggEl.innerHTML = '';
            activeIdx = -1;
            searchEl.setAttribute('aria-expanded', 'false');
        }

        function renderSuggestions() {
            var q = (searchEl.value || '').trim().toLowerCase();
            var items = suggestionsForQuery(q);
            suggEl.innerHTML = '';
            if (items.length === 0) {
                closeSuggestions();
                return;
            }
            items.forEach(function (row, idx) {
                var li = document.createElement('li');
                li.className = 'wp-token-input__suggestion';
                li.setAttribute('role', 'option');
                li.setAttribute('data-id', String(row.id));
                li.textContent = row.name;
                if (idx === activeIdx) {
                    li.classList.add('is-active');
                    li.setAttribute('aria-selected', 'true');
                } else {
                    li.setAttribute('aria-selected', 'false');
                }
                li.addEventListener('mousedown', function (ev) {
                    ev.preventDefault();
                });
                li.addEventListener('click', function () {
                    addId(row.id);
                    searchEl.value = '';
                    closeSuggestions();
                    searchEl.focus();
                });
                suggEl.appendChild(li);
            });
            suggEl.hidden = false;
            searchEl.setAttribute('aria-expanded', 'true');
        }

        function addId(id) {
            if (!id || selectedIds.indexOf(id) !== -1) return;
            selectedIds.push(id);
            renderTokens();
        }

        function removeId(id) {
            selectedIds = selectedIds.filter(function (x) { return x !== id; });
            renderTokens();
            renderSuggestions();
        }

        function addFromInput() {
            var raw = (searchEl.value || '').trim();
            if (raw === '') return;
            var q = raw.toLowerCase();
            var taken = {};
            selectedIds.forEach(function (id) { taken[id] = true; });
            var exact = all.find(function (row) {
                return !taken[row.id] && row.name.toLowerCase() === q;
            });
            if (exact) {
                addId(exact.id);
                searchEl.value = '';
                closeSuggestions();
                return;
            }
            var items = suggestionsForQuery(q);
            if (items.length === 1) {
                addId(items[0].id);
                searchEl.value = '';
                closeSuggestions();
                return;
            }
            if (activeIdx >= 0 && items[activeIdx]) {
                addId(items[activeIdx].id);
                searchEl.value = '';
                closeSuggestions();
            }
        }

        searchEl.addEventListener('input', function () {
            activeIdx = -1;
            renderSuggestions();
        });

        searchEl.addEventListener('focus', function () {
            renderSuggestions();
        });

        searchEl.addEventListener('keydown', function (ev) {
            var items = suggestionsForQuery((searchEl.value || '').trim().toLowerCase());
            if (ev.key === 'ArrowDown') {
                ev.preventDefault();
                if (items.length === 0) return;
                activeIdx = activeIdx < items.length - 1 ? activeIdx + 1 : 0;
                renderSuggestions();
            } else if (ev.key === 'ArrowUp') {
                ev.preventDefault();
                if (items.length === 0) return;
                activeIdx = activeIdx > 0 ? activeIdx - 1 : items.length - 1;
                renderSuggestions();
            } else if (ev.key === 'Enter' || ev.key === ',') {
                ev.preventDefault();
                addFromInput();
            } else if (ev.key === 'Escape') {
                closeSuggestions();
            } else if (ev.key === 'Backspace' && searchEl.value === '' && selectedIds.length > 0) {
                removeId(selectedIds[selectedIds.length - 1]);
            }
        });

        document.addEventListener('click', function (ev) {
            if (!root.contains(ev.target)) {
                closeSuggestions();
            }
        });

        if (innerEl) {
            innerEl.addEventListener('click', function () {
                searchEl.focus();
            });
        }

        renderTokens();
    }

    document.querySelectorAll('[data-wp-token]').forEach(initWpToken);
})();
</script>
