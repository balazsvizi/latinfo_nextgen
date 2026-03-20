</main>
<footer class="main-footer">
    <div class="footer-inner">
        &copy; <?= date('Y') ?> <?= h(SITE_NAME) ?>
    </div>
</footer>
<script>
(function() {
    var toggle = document.getElementById('nav-toggle');
    var nav = document.getElementById('main-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', function() {
            var open = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open);
            toggle.querySelector('.icon').textContent = open ? '✕' : '☰';
            if (!open) nav.querySelectorAll('.nav-item.has-submenu.is-open').forEach(function(item) {
                item.classList.remove('is-open');
                var btn = item.querySelector('.nav-parent-arrow');
                if (btn) btn.setAttribute('aria-expanded', 'false');
            });
        });
        document.addEventListener('click', function(e) {
            if (nav.classList.contains('is-open') && !nav.contains(e.target) && !toggle.contains(e.target)) {
                nav.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.querySelector('.icon').textContent = '☰';
                closeAllSubmenus();
            }
            if (!nav.contains(e.target) && !toggle.contains(e.target)) closeAllSubmenus();
        });
    }
    function closeAllSubmenus() {
        document.querySelectorAll('.nav-item.has-submenu.is-open').forEach(function(item) {
            item.classList.remove('is-open');
            var btn = item.querySelector('.nav-parent-arrow');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        });
    }
    document.querySelectorAll('.nav-parent-arrow').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var item = this.closest('.nav-item');
            if (!item || !item.classList.contains('has-submenu')) return;
            var wasOpen = item.classList.contains('is-open');
            closeAllSubmenus();
            if (!wasOpen) {
                item.classList.add('is-open');
                this.setAttribute('aria-expanded', 'true');
            }
        });
    });
    // Összeg mezők: csak szám (jegyek, vessző/pont, szóköz)
    document.querySelectorAll('input[name="összeg"]').forEach(function(inp) {
        inp.setAttribute('inputmode', 'decimal');
        inp.addEventListener('input', function() {
            this.value = this.value.replace(/[^\d\s,.]/g, '');
        });
    });
})();
</script>
</body>
</html>
