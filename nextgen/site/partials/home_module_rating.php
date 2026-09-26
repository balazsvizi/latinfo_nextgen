<?php
declare(strict_types=1);

/**
 * Értékelés modul – 5 csillag.
 *
 * @var array<string, string> $ratingStrings
 * @var string $lang
 * @var string $ratingAjaxUrl
 * @var array{average?: float, count?: int} $ratingSummary
 * @var string $homeSurface
 */
$ratingStrings = is_array($ratingStrings ?? null) ? $ratingStrings : [];
$lang = (($lang ?? 'hu') === 'en') ? 'en' : 'hu';
$homeSurface = latinfo_home_normalize_surface($homeSurface ?? 'web');
$ratingAjaxUrl = (string) ($ratingAjaxUrl ?? nextgen_url('site/ajax_rating.php'));
$ratingSummary = is_array($ratingSummary ?? null) ? $ratingSummary : [];
$liveAverage = (float) ($ratingSummary['average'] ?? 0);
$liveCount = (int) ($ratingSummary['count'] ?? 0);
$avgTpl = (string) ($ratingStrings['average'] ?? 'Átlag: %s · %d értékelés alapján');
$thanksFive = trim((string) ($ratingStrings['thanks_five'] ?? ''));
?>
<section class="latinfo-home__rating" id="ertekeles" aria-label="<?= h((string) ($ratingStrings['aria'] ?? '')) ?>">
    <h2 class="latinfo-home__rating-title"><?= h((string) ($ratingStrings['title'] ?? 'Értékelés')) ?></h2>
    <p class="latinfo-home__rating-prompt" data-lh-rating-prompt><?= h((string) ($ratingStrings['prompt'] ?? '')) ?></p>
    <div class="latinfo-home__rating-stars" role="group" aria-label="<?= h((string) ($ratingStrings['aria'] ?? '')) ?>" data-lh-rating-stars>
        <?php for ($i = 1; $i <= 5; $i++): ?>
            <button
                type="button"
                class="latinfo-home__rating-star"
                data-stars="<?= $i ?>"
                aria-label="<?= h(sprintf((string) ($ratingStrings['star_aria'] ?? '%d'), $i)) ?>"
            >★</button>
        <?php endfor; ?>
    </div>
    <p class="latinfo-home__rating-result" data-lh-rating-result hidden></p>
    <?php
    $donablyEmbed = true;
    $donablyDomId = 'ertekeles-tamogatas';
    $donablyTrackItemKey = 'cta:rating';
    ob_start();
    require __DIR__ . '/home_module_donably.php';
    $ratingDonablyHtml = trim((string) ob_get_clean());
    $donablyEmbed = false;
    $donablyDomId = '';
    $donablyTrackItemKey = '';
    ?>
    <?php if ($thanksFive !== '' || $ratingDonablyHtml !== ''): ?>
        <div class="latinfo-home__rating-donably" data-lh-rating-donably hidden>
            <?php if ($thanksFive !== ''): ?>
                <p class="latinfo-home__rating-thanks-five"><?= h($thanksFive) ?></p>
            <?php endif; ?>
            <?= $ratingDonablyHtml ?>
        </div>
    <?php endif; ?>
</section>
<script>
(function () {
    var root = document.getElementById('ertekeles');
    if (!root) return;
    var ajaxUrl = <?= json_encode($ratingAjaxUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var lang = <?= json_encode($lang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var surface = <?= json_encode($homeSurface, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var avgTpl = <?= json_encode($avgTpl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var thanks = <?= json_encode((string) ($ratingStrings['thanks'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var liveAvg = <?= json_encode($liveAverage, JSON_UNESCAPED_UNICODE) ?>;
    var liveCount = <?= json_encode($liveCount, JSON_UNESCAPED_UNICODE) ?>;
    var starsWrap = root.querySelector('[data-lh-rating-stars]');
    var resultEl = root.querySelector('[data-lh-rating-result]');
    var promptEl = root.querySelector('[data-lh-rating-prompt]');
    var storageKey = 'lh_latinfo_rated_v1';
    var busy = false;

    function formatAvg(avg, count) {
        var avgStr = (Math.round(Number(avg) * 10) / 10).toFixed(1).replace('.', ',');
        return avgTpl.replace('%s', avgStr).replace('%d', String(count));
    }

    function revealDonably(stars) {
        var box = root.querySelector('[data-lh-rating-donably]');
        if (!box) return;
        box.hidden = parseInt(stars, 10) !== 5;
    }

    function showResult(avg, count, stars) {
        if (!resultEl) return;
        resultEl.hidden = false;
        resultEl.textContent = thanks + ' ' + formatAvg(avg, count);
        if (promptEl) promptEl.hidden = true;
        if (starsWrap) starsWrap.setAttribute('data-rated', '1');
        var given = parseInt(stars, 10) || 0;
        revealDonably(given);
        try {
            // Csak a saját csillagot cache-eljük; az átlag/szám mindig a szerverről jön.
            localStorage.setItem(storageKey, JSON.stringify({
                stars: given,
                at: Date.now()
            }));
        } catch (e) {}
    }

    function paintSelected(n) {
        if (!starsWrap) return;
        var buttons = starsWrap.querySelectorAll('[data-stars]');
        buttons.forEach(function (btn) {
            var v = parseInt(btn.getAttribute('data-stars') || '0', 10);
            btn.classList.toggle('is-active', v <= n);
        });
    }

    try {
        var cached = JSON.parse(localStorage.getItem(storageKey) || 'null');
        var cachedStars = cached ? (parseInt(cached.stars, 10) || 0) : 0;
        if (cachedStars >= 1 && cachedStars <= 5) {
            showResult(liveAvg, liveCount, cachedStars);
            paintSelected(cachedStars);
        }
    } catch (e) {}

    if (!starsWrap) return;
    starsWrap.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('[data-stars]') : null;
        if (!btn || busy) return;
        var stars = parseInt(btn.getAttribute('data-stars') || '0', 10);
        if (stars < 1 || stars > 5) return;
        busy = true;
        paintSelected(stars);
        revealDonably(stars);
        var body = new FormData();
        body.append('stars', String(stars));
        body.append('lang', lang);
        body.append('surface', surface || 'web');
        fetch(ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.ok) {
                    showResult(data.average || 0, data.count || 0, stars);
                }
            })
            .catch(function () {})
            .finally(function () { busy = false; });
    });

    starsWrap.addEventListener('mouseover', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('[data-stars]') : null;
        if (!btn || starsWrap.getAttribute('data-rated') === '1') return;
        paintSelected(parseInt(btn.getAttribute('data-stars') || '0', 10));
    });
    starsWrap.addEventListener('mouseleave', function () {
        if (starsWrap.getAttribute('data-rated') === '1') return;
        paintSelected(0);
    });
})();
</script>
