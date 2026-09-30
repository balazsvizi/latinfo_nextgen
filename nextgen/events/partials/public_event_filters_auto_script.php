<script>
(function () {
    var form = document.getElementById('events-home-filter-form');
    if (!form) return;

    var debounceTimer = null;

    function submitForm() {
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    }

    function debouncedSubmit(delay) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(submitForm, delay);
    }

    form.querySelectorAll('.events-filter-select').forEach(function (el) {
        if (el.classList.contains('events-filter-multiselect__toggle')) {
            return;
        }
        el.addEventListener('change', submitForm);
    });

    form.querySelectorAll('input.events-filter-input[type="text"]').forEach(function (el) {
        el.addEventListener('input', function () {
            debouncedSubmit(450);
        });
    });

    form.querySelectorAll('input.events-filter-input[type="date"]').forEach(function (el) {
        el.addEventListener('change', submitForm);
    });

    var rFrom = document.getElementById('ev-range-from');
    var rTo = document.getElementById('ev-range-to');
    if (rFrom && rTo) {
        var rangeTimer = null;
        rFrom.addEventListener('change', submitForm);
        rTo.addEventListener('change', submitForm);
        rFrom.addEventListener('input', function () {
            clearTimeout(rangeTimer);
            rangeTimer = setTimeout(submitForm, 500);
        });
        rTo.addEventListener('input', function () {
            clearTimeout(rangeTimer);
            rangeTimer = setTimeout(submitForm, 500);
        });
    }

    form.querySelectorAll('[data-filter-multiselect]').forEach(function (root) {
        var toggle = root.querySelector('.events-filter-multiselect__toggle');
        var panel = root.querySelector('.events-filter-multiselect__panel');
        var summary = root.querySelector('.events-filter-multiselect__summary');
        if (!toggle || !panel || !summary) {
            return;
        }
        var allLabel = root.getAttribute('data-all-label') || '';
        var countTpl = root.getAttribute('data-count-template') || '%d';
        var boxes = Array.prototype.slice.call(root.querySelectorAll('input[type="checkbox"]'));
        var childrenByParent = {};
        boxes.forEach(function (box) {
            var parentId = String(box.getAttribute('data-parent-id') || '0');
            if (!childrenByParent[parentId]) {
                childrenByParent[parentId] = [];
            }
            childrenByParent[parentId].push(box);
        });

        function selectedBoxes() {
            return boxes.filter(function (box) {
                return box.checked;
            });
        }

        function optionLabel(box) {
            var textEl = box.closest('.events-filter-multiselect__option');
            if (!textEl) {
                return box.value;
            }
            var span = textEl.querySelector('.events-filter-multiselect__option-text');
            return span ? span.textContent.trim() : textEl.textContent.trim();
        }

        function updateSummary() {
            var checked = selectedBoxes();
            if (checked.length === 0) {
                summary.textContent = allLabel;
            } else if (checked.length === 1) {
                summary.textContent = optionLabel(checked[0]);
            } else {
                summary.textContent = countTpl.replace('%d', String(checked.length));
            }
        }

        function setDescendantsChecked(parentValue, checked) {
            var stack = (childrenByParent[String(parentValue)] || []).slice();
            while (stack.length) {
                var child = stack.pop();
                child.checked = checked;
                var nested = childrenByParent[String(child.value)] || [];
                for (var i = 0; i < nested.length; i++) {
                    stack.push(nested[i]);
                }
            }
        }

        function setOpen(open) {
            root.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                panel.removeAttribute('hidden');
            } else {
                panel.setAttribute('hidden', '');
            }
        }

        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            setOpen(!root.classList.contains('is-open'));
        });

        document.addEventListener('click', function (e) {
            if (!root.contains(e.target)) {
                setOpen(false);
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && root.classList.contains('is-open')) {
                setOpen(false);
                toggle.focus();
            }
        });

        boxes.forEach(function (cb) {
            cb.addEventListener('change', function () {
                setDescendantsChecked(cb.value, cb.checked);
                updateSummary();
                debouncedSubmit(450);
            });
        });
    });

    (function favoritesFilter() {
        var btn = form.querySelector('[data-favorites-filter-btn]');
        var input = document.getElementById('ev-f-favorites');
        if (!btn || !input) {
            return;
        }

        var GUEST_KEY = 'latinfo_fav_guest';

        function setGuestOk() {
            try {
                localStorage.setItem(GUEST_KEY, '1');
            } catch (e) {
                /* ignore */
            }
        }

        function openDialog(dlg) {
            if (!dlg || typeof dlg.showModal !== 'function') {
                return Promise.resolve('');
            }
            return new Promise(function (resolve) {
                function onClose() {
                    dlg.removeEventListener('close', onClose);
                    dlg.removeEventListener('click', onBackdrop);
                    resolve(dlg.returnValue || '');
                }
                function onBackdrop(ev) {
                    if (ev.target === dlg && typeof dlg.close === 'function') {
                        dlg.close('cancel');
                    }
                }
                dlg.addEventListener('close', onClose);
                dlg.addEventListener('click', onBackdrop);
                dlg.showModal();
            });
        }

        function withFavoritesReturn(href) {
            try {
                var u = new URL(href, window.location.origin);
                var ret = u.searchParams.get('return');
                if (!ret) {
                    return href;
                }
                var retUrl = new URL(ret, window.location.origin);
                retUrl.searchParams.set('f_favorites', '1');
                u.searchParams.set('return', retUrl.pathname + retUrl.search + retUrl.hash);
                return u.pathname + u.search + u.hash;
            } catch (e) {
                return href;
            }
        }

        function ensureAuth() {
            if (btn.getAttribute('data-logged-in') === '1') {
                return Promise.resolve(true);
            }
            var dlg = document.querySelector('[data-public-favorite-auth-dialog]');
            if (!dlg) {
                return Promise.resolve(false);
            }
            var loginLink = dlg.querySelector('[data-public-favorite-login-link]');
            var signupLink = dlg.querySelector('[data-public-favorite-signup-link]');
            if (loginLink) {
                loginLink.href = withFavoritesReturn(loginLink.getAttribute('href') || loginLink.href);
            }
            if (signupLink) {
                signupLink.href = withFavoritesReturn(signupLink.getAttribute('href') || signupLink.href);
            }
            return openDialog(dlg).then(function (val) {
                if (val === 'guest') {
                    setGuestOk();
                    return true;
                }
                return false;
            });
        }

        function showEmptyDialog() {
            var dlg = document.querySelector('[data-public-favorite-empty-dialog]');
            return openDialog(dlg);
        }

        function setActive(active) {
            input.disabled = !active;
            input.value = '1';
            btn.classList.toggle('is-active', !!active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            var label = active
                ? (btn.getAttribute('data-label-clear') || '')
                : (btn.getAttribute('data-label-apply') || '');
            if (label) {
                btn.setAttribute('aria-label', label);
                btn.setAttribute('title', label);
            }
            submitForm();
        }

        function isActive() {
            return !input.disabled && input.value === '1';
        }

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (btn.disabled) {
                return;
            }

            if (isActive()) {
                setActive(false);
                return;
            }

            btn.disabled = true;
            ensureAuth()
                .then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    if (btn.getAttribute('data-has-favorites') !== '1') {
                        return showEmptyDialog();
                    }
                    setActive(true);
                })
                .finally(function () {
                    btn.disabled = false;
                });
        });
    })();
})();
</script>
