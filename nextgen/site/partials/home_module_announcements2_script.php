<?php
declare(strict_types=1);

/**
 * Bejelentések 2 váltogató – egyszerre 2, DJ ajánló mintára.
 *
 * @var list<array<string, mixed>> $announcements2Cards
 * @var int $announcements2VisibleCount
 */
$announcements2Cards = is_array($announcements2Cards ?? null) ? $announcements2Cards : [];
$announcements2VisibleCount = max(1, (int) ($announcements2VisibleCount ?? 2));
if ($announcements2Cards === [] || count($announcements2Cards) <= $announcements2VisibleCount) {
    return;
}
?>
<script>
(function () {
    var list = document.getElementById('lh-announcements2-list');
    var dataTag = document.getElementById('lh-announcements2-data');
    if (!list || !dataTag) return;

    var pool;
    try {
        pool = JSON.parse(dataTag.textContent || '[]');
    } catch (e) {
        return;
    }
    if (!Array.isArray(pool) || pool.length === 0) return;

    var box = list.closest('.latinfo-home__flashes-wrap--rotate');
    var items = Array.prototype.slice.call(list.querySelectorAll('.latinfo-home__flash-item'));
    if (!box || items.length === 0) return;

    var visibleCount = Math.min(<?= (int) $announcements2VisibleCount ?>, items.length);
    var cursor = visibleCount;
    var slot = 0;
    var paused = false;
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function visibleItems() {
        return items.filter(function (li) { return !li.hidden; });
    }

    function shownTitles() {
        return visibleItems().map(function (li) {
            var link = li.querySelector('.latinfo-home__flash-link');
            return link ? (link.textContent || '') : '';
        });
    }

    function nextCard() {
        var visible = shownTitles();
        for (var i = 0; i < pool.length; i++) {
            var card = pool[cursor % pool.length];
            cursor++;
            if (card && visible.indexOf(card.title || '') === -1) return card;
        }
        return null;
    }

    function paint(li, card) {
        var flash = li.querySelector('.latinfo-home__flash');
        var kicker = li.querySelector('.latinfo-home__flash-kicker');
        var link = li.querySelector('.latinfo-home__flash-link');
        var dek = li.querySelector('.latinfo-home__flash-dek');
        if (!flash || !link || !card) return;

        var cardId = parseInt(card.id, 10) || 0;
        var title = card.title || '';
        link.setAttribute('href', card.url || '#');
        link.textContent = title;
        if (link.hasAttribute('data-lh-module-track')) {
            link.setAttribute('data-lh-item-key', cardId > 0 ? ('news2:' + cardId) : '');
            link.setAttribute('data-lh-item-label', title);
        }
        if (kicker) {
            kicker.textContent = card.kicker || '';
            kicker.hidden = !card.kicker;
        }
        if (dek) {
            dek.innerHTML = card.dek || '';
            dek.hidden = !card.dek;
        }
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

    if (!reduceMotion && pool.length > visibleCount) {
        window.setInterval(rotate, 5200);
    }
})();
</script>
