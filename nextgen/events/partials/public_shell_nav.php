<?php
declare(strict_types=1);

/**
 * Nyilvános főmenü: desktopon vízszintes sáv, mobilon a hamburger gomb nyitja.
 *
 * @var string $lang
 * @var array<string, string> $N events_public_nav_strings()
 */
require_once __DIR__ . '/../lib/public_nav_menu.php';

$navItems = events_public_nav_menu_items($lang);
$navActiveKeys = events_public_nav_active_keys();
?>
<nav class="event-nav" id="event-primary-nav" aria-label="<?= h($N['nav_aria']) ?>">
    <?php events_public_nav_render_items($navItems, $navActiveKeys, $N); ?>
</nav>
<script>
(function () {
    var nav = document.getElementById('event-primary-nav');
    var toggle = document.getElementById('event-nav-toggle');
    if (!nav) return;

    var labelOpen = <?= json_encode($N['toggle_open'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var labelClose = <?= json_encode($N['toggle_close'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var mobileQuery = window.matchMedia('(max-width: 899px)');
    var BRANCH_SELECTOR = ':scope > .event-nav__row > .event-nav__branch';

    function setNavOpen(open) {
        nav.classList.toggle('is-open', open);
        if (!toggle) return;
        toggle.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? labelClose : labelOpen);
        toggle.setAttribute('title', open ? labelClose : labelOpen);
    }

    function setBranchOpen(item, open) {
        if (!item) return;
        item.classList.toggle('is-open', open);
        var branch = item.querySelector(BRANCH_SELECTOR);
        if (branch) branch.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    function closeBranches(scope) {
        (scope || nav).querySelectorAll('.event-nav__item.is-open').forEach(function (item) {
            setBranchOpen(item, false);
        });
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            setNavOpen(!nav.classList.contains('is-open'));
        });
    }

    // Mobilon az aktuális oldal ága nyitva indul; desktopon a lenyíló hoverre jön.
    function syncViewport() {
        if (mobileQuery.matches) {
            nav.querySelectorAll('.event-nav__item--parent.is-active').forEach(function (item) {
                setBranchOpen(item, true);
            });
        } else {
            closeBranches();
            setNavOpen(false);
        }
    }
    syncViewport();
    if (typeof mobileQuery.addEventListener === 'function') {
        mobileQuery.addEventListener('change', syncViewport);
    }

    nav.addEventListener('click', function (e) {
        var branch = e.target.closest ? e.target.closest('.event-nav__branch') : null;
        if (!branch || !nav.contains(branch)) return;
        e.preventDefault();
        var item = branch.closest('.event-nav__item');
        if (!item) return;
        var willOpen = !item.classList.contains('is-open');
        var siblings = item.parentNode ? item.parentNode.children : [];
        for (var i = 0; i < siblings.length; i++) {
            closeBranches(siblings[i]);
            setBranchOpen(siblings[i], false);
        }
        setBranchOpen(item, willOpen);
    });

    document.addEventListener('click', function (e) {
        if (nav.contains(e.target) || (toggle && toggle.contains(e.target))) return;
        closeBranches();
        if (mobileQuery.matches) setNavOpen(false);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        closeBranches();
        if (mobileQuery.matches && nav.classList.contains('is-open')) {
            setNavOpen(false);
            if (toggle) toggle.focus();
        }
    });
})();
</script>
