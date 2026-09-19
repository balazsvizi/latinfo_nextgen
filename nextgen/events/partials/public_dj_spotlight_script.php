<?php
declare(strict_types=1);

/**
 * DJ ajánló váltogató. Ugyanaz a viselkedés a DJ oldalon és a Latinfo kezdőoldalon.
 *
 * @var list<array<string, mixed>> $spotlightCards
 */
$spotlightCards = is_array($spotlightCards ?? null) ? $spotlightCards : [];
if ($spotlightCards === []) {
    return;
}
?>
<script>
(function () {
    var list = document.getElementById('djs-spotlight-list');
    var dataTag = document.getElementById('djs-spotlight-data');
    if (!list || !dataTag) return;

    var pool;
    try {
        pool = JSON.parse(dataTag.textContent || '[]');
    } catch (e) {
        return;
    }
    if (!Array.isArray(pool) || pool.length === 0) return;

    var box = list.closest('.djs-public__spotlight');
    var lead = list.closest('.djs-public__lead');
    var cms = lead ? lead.querySelector('.djs-public__cms--before') : null;
    var items = Array.prototype.slice.call(list.querySelectorAll('.djs-public__spotlight-item'));
    if (!box || items.length === 0) return;

    var mobileCount = 3;
    var visibleCount = Math.min(mobileCount, items.length);
    var cursor = 0;
    var slot = 0;
    var paused = false;
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function isDesktop() {
        return window.matchMedia('(min-width: 900px)').matches && !!cms && cms.offsetHeight > 0;
    }

    function visibleItems() {
        return items.filter(function (li) { return !li.hidden; });
    }

    function shownNames() {
        return visibleItems().map(function (li) {
            var name = li.querySelector('.djs-public__spotlight-name');
            return name ? name.textContent : '';
        });
    }

    function nextCard() {
        var visible = shownNames();
        for (var i = 0; i < pool.length; i++) {
            var card = pool[cursor % pool.length];
            cursor++;
            if (card && visible.indexOf(card.name) === -1) return card;
        }
        return null;
    }

    function paint(li, card) {
        var link = li.querySelector('.djs-public__spotlight-card');
        var avatar = li.querySelector('.djs-public__spotlight-avatar');
        var name = li.querySelector('.djs-public__spotlight-name');
        var logoBox = li.querySelector('.djs-public__spotlight-logo');
        var meta = li.querySelector('.djs-public__spotlight-meta');
        if (!link || !avatar || !name || !meta || !card) return;

        link.setAttribute('href', card.href || '#');
        link.setAttribute('aria-label', card.aria || card.name || '');
        if (link.hasAttribute('data-lh-module-track')) {
            var cardId = parseInt(card.id, 10) || 0;
            link.setAttribute('data-lh-item-key', cardId > 0 ? ('dj:' + cardId) : '');
            link.setAttribute('data-lh-item-label', card.name || '');
        }
        avatar.className = 'djs-public__spotlight-avatar' + (card.isLogo ? ' djs-public__spotlight-avatar--logo' : '');
        avatar.textContent = '';
        if (card.photo) {
            var img = document.createElement('img');
            img.className = 'djs-public__spotlight-photo';
            img.src = card.photo;
            img.alt = '';
            img.loading = 'lazy';
            img.decoding = 'async';
            if (card.photoStyle) {
                img.setAttribute('style', card.photoStyle);
            }
            avatar.appendChild(img);
        } else {
            var initials = document.createElement('span');
            initials.className = 'djs-public__spotlight-initials';
            initials.textContent = card.initials || 'DJ';
            avatar.appendChild(initials);
        }
        name.textContent = card.name || '';
        if (logoBox) {
            logoBox.textContent = '';
            if (card.logo) {
                var logoImg = document.createElement('img');
                logoImg.className = 'djs-public__spotlight-logo-img';
                logoImg.src = card.logo;
                logoImg.alt = '';
                logoImg.loading = 'lazy';
                logoImg.decoding = 'async';
                if (card.logoStyle) {
                    logoImg.setAttribute('style', card.logoStyle);
                }
                logoBox.appendChild(logoImg);
                logoBox.hidden = false;
            } else {
                logoBox.hidden = true;
            }
        }
        meta.textContent = card.meta || '';
        meta.hidden = !card.meta;
    }

    function layout() {
        box.style.height = '';
        items.forEach(function (li, i) {
            li.hidden = i >= mobileCount;
        });
        visibleCount = Math.min(mobileCount, items.length);

        if (!isDesktop()) {
            return;
        }

        var minHeight = box.offsetHeight;
        var target = Math.max(minHeight, cms.offsetHeight);
        box.style.height = target + 'px';
        void box.offsetHeight;

        if (list.clientHeight < 24) {
            return;
        }

        var n = visibleCount;
        for (var i = visibleCount; i < items.length; i++) {
            items[i].hidden = false;
            if (list.scrollHeight > list.clientHeight + 1) {
                items[i].hidden = true;
                break;
            }
            n = i + 1;
        }
        visibleCount = n;
    }

    function rotate() {
        if (paused || document.hidden || reduceMotion) return;
        var vis = visibleItems();
        if (vis.length === 0 || pool.length <= vis.length) return;
        var li = vis[slot % vis.length];
        slot++;
        var card = nextCard();
        if (!li || !card) return;

        li.classList.add('is-swapping');
        window.setTimeout(function () {
            paint(li, card);
            li.classList.remove('is-swapping');
        }, 260);
    }

    ['pointerenter', 'focusin'].forEach(function (evt) {
        box.addEventListener(evt, function () { paused = true; });
    });
    ['pointerleave', 'focusout'].forEach(function (evt) {
        box.addEventListener(evt, function () { paused = false; });
    });

    var resizeTimer = 0;
    window.addEventListener('resize', function () {
        window.clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(layout, 120);
    });

    layout();
    window.addEventListener('load', layout);
    if (!reduceMotion && pool.length > mobileCount) {
        window.setInterval(rotate, 5200);
    }
})();
</script>
