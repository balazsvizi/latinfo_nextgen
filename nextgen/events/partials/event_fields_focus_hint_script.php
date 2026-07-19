<?php
declare(strict_types=1);
?>
<script>
(function () {
    var form = document.getElementById('events-edit-form');
    if (!form) {
        return;
    }

    var hint = document.createElement('div');
    hint.className = 'events-field-focus-hint';
    hint.setAttribute('aria-hidden', 'true');
    hint.hidden = true;
    document.body.appendChild(hint);

    var activeEl = null;

    function isSkipped(el) {
        if (!el || !form.contains(el)) {
            return true;
        }
        if (el.disabled || el.readOnly) {
            return true;
        }
        var type = (el.getAttribute('type') || '').toLowerCase();
        if (el.tagName === 'INPUT' && ['hidden', 'checkbox', 'radio', 'file', 'submit', 'button', 'reset', 'image'].indexOf(type) !== -1) {
            return true;
        }
        if (el.classList.contains('js-html-editor-source')) {
            return true;
        }
        return false;
    }

    function isFilled(el) {
        if (el.tagName === 'SELECT') {
            return String(el.value || '').trim() !== '';
        }
        return String(el.value || '').trim() !== '';
    }

    function labelIsVisible(lab) {
        if (!lab || lab.classList.contains('visually-hidden')) {
            return false;
        }
        if (lab.getAttribute('aria-hidden') === 'true') {
            return false;
        }
        var style = window.getComputedStyle(lab);
        if (style.display === 'none' || style.visibility === 'hidden' || parseFloat(style.opacity || '1') === 0) {
            return false;
        }
        var rect = lab.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0;
    }

    function labelFor(el) {
        if (!el.id) {
            return null;
        }
        var sid = (window.CSS && typeof CSS.escape === 'function') ? CSS.escape(el.id) : el.id.replace(/"/g, '\\"');
        return document.querySelector('label[for="' + sid + '"]');
    }

    function hasVisibleLabel(el) {
        var lab = labelFor(el);
        if (lab && labelIsVisible(lab)) {
            return true;
        }
        var parentLabel = el.closest('label');
        if (parentLabel && labelIsVisible(parentLabel) && !parentLabel.classList.contains('events-toggle')) {
            return true;
        }
        return false;
    }

    function resolveName(el) {
        var aria = (el.getAttribute('aria-label') || '').trim();
        if (aria) {
            return aria;
        }
        var lab = labelFor(el);
        if (lab) {
            var text = (lab.textContent || '').replace(/\s+/g, ' ').trim();
            if (text) {
                return text;
            }
        }
        var title = (el.getAttribute('title') || '').trim();
        if (title) {
            return title;
        }
        var ph = (el.getAttribute('placeholder') || '').trim().replace(/[.…]+$/u, '').trim();
        if (ph && ph !== 'https://' && ph !== '0') {
            return ph;
        }
        return '';
    }

    function positionHint(el) {
        var rect = el.getBoundingClientRect();
        hint.hidden = false;
        hint.classList.add('is-visible');
        var hintRect = hint.getBoundingClientRect();
        var top = window.scrollY + rect.top - hintRect.height - 6;
        if (top < window.scrollY + 4) {
            top = window.scrollY + rect.bottom + 6;
            hint.classList.add('is-below');
        } else {
            hint.classList.remove('is-below');
        }
        var left = window.scrollX + rect.left;
        var maxLeft = window.scrollX + document.documentElement.clientWidth - hintRect.width - 8;
        if (left > maxLeft) {
            left = Math.max(window.scrollX + 8, maxLeft);
        }
        hint.style.top = Math.round(top) + 'px';
        hint.style.left = Math.round(left) + 'px';
    }

    function hideHint() {
        activeEl = null;
        hint.hidden = true;
        hint.classList.remove('is-visible', 'is-below');
        hint.textContent = '';
    }

    function showHintFor(el) {
        if (isSkipped(el) || !isFilled(el) || hasVisibleLabel(el)) {
            hideHint();
            return;
        }
        var name = resolveName(el);
        if (!name) {
            hideHint();
            return;
        }
        activeEl = el;
        hint.textContent = name;
        positionHint(el);
    }

    form.addEventListener('focusin', function (ev) {
        var el = ev.target;
        if (!(el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement || el instanceof HTMLSelectElement)) {
            return;
        }
        showHintFor(el);
    });

    form.addEventListener('focusout', function (ev) {
        var el = ev.target;
        if (el === activeEl) {
            window.setTimeout(function () {
                if (document.activeElement !== activeEl) {
                    hideHint();
                }
            }, 0);
        }
    });

    form.addEventListener('input', function (ev) {
        var el = ev.target;
        if (el === activeEl) {
            showHintFor(el);
        }
    });

    window.addEventListener('scroll', function () {
        if (activeEl) {
            positionHint(activeEl);
        }
    }, true);

    window.addEventListener('resize', function () {
        if (activeEl) {
            positionHint(activeEl);
        }
    });
})();
</script>
