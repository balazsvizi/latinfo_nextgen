(function () {
    var header = document.getElementById('lh-header');
    var toggle = document.getElementById('lh-nav-toggle');
    var nav = document.getElementById('lh-nav');
    if (!header || !toggle || !nav) {
        return;
    }

    function setOpen(open) {
        header.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? 'Menü bezárása' : 'Menü megnyitása');
    }

    toggle.addEventListener('click', function () {
        setOpen(!header.classList.contains('is-open'));
    });

    nav.addEventListener('click', function (e) {
        var link = e.target.closest ? e.target.closest('a') : null;
        if (link) {
            setOpen(false);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            setOpen(false);
        }
    });
})();
