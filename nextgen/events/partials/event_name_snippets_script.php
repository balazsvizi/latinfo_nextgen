<script>
(function () {
    var input = document.getElementById('event_name');
    var root = document.getElementById('events-name-snippets');
    if (!input || !root) return;

    var stored = null;
    var maxLen = parseInt(input.getAttribute('maxlength') || '500', 10);
    if (!maxLen || maxLen < 1) maxLen = 500;

    function remember() {
        if (typeof input.selectionStart !== 'number') return;
        stored = { start: input.selectionStart, end: input.selectionEnd };
    }

    function isSpace(ch) {
        return ch === ' ' || ch === '\n' || ch === '\t';
    }

    function sticksLeft(ch) {
        return ',.;:!?)]}'.indexOf(ch) !== -1;
    }

    function joinParts(left, mid, right) {
        var out = left;
        if (out !== '' && mid !== '' && !isSpace(out.charAt(out.length - 1)) && !isSpace(mid.charAt(0)) && !sticksLeft(mid.charAt(0))) {
            out += ' ';
        }
        out += mid;
        if (right !== '' && !isSpace(out.charAt(out.length - 1)) && !isSpace(right.charAt(0)) && !sticksLeft(right.charAt(0))) {
            out += ' ';
        }
        return out + right;
    }

    input.addEventListener('keyup', remember);
    input.addEventListener('mouseup', remember);
    input.addEventListener('select', remember);
    input.addEventListener('focus', remember);

    root.addEventListener('mousedown', function (e) {
        var btn = e.target.closest('.events-name-snippet');
        if (!btn || !root.contains(btn)) return;
        e.preventDefault();
    });

    root.addEventListener('click', function (e) {
        var btn = e.target.closest('.events-name-snippet');
        if (!btn || !root.contains(btn)) return;
        var text = btn.getAttribute('data-snippet') || '';
        if (text === '') return;

        var value = input.value || '';
        var start = value.length;
        var end = value.length;
        if (stored) {
            start = Math.max(0, Math.min(stored.start, value.length));
            end = Math.max(start, Math.min(stored.end, value.length));
        }

        var next = joinParts(value.slice(0, start), text, value.slice(end));
        if (next.length > maxLen) {
            next = next.slice(0, maxLen);
        }
        if (next === value) return;

        input.value = next;
        var caret = Math.min(next.length, joinParts(value.slice(0, start), text, '').length);
        input.focus();
        if (typeof input.setSelectionRange === 'function') {
            input.setSelectionRange(caret, caret);
        }
        stored = { start: caret, end: caret };
        input.dispatchEvent(new Event('input', { bubbles: true }));

        btn.classList.add('is-used');
        window.setTimeout(function () {
            btn.classList.remove('is-used');
        }, 450);
    });
})();
</script>
