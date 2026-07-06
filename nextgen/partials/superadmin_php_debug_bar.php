<?php
declare(strict_types=1);

if (!function_exists('isSuperadmin') || !isLoggedIn() || !isSuperadmin()) {
    return;
}

$phpFull = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
$phpName = $phpFull !== '' ? basename($phpFull) : '—';
$phpMtimeLabel = '—';
if ($phpFull !== '' && is_file($phpFull)) {
    $mt = @filemtime($phpFull);
    if (is_int($mt) && $mt > 0) {
        $phpMtimeLabel = date('Y-m-d H:i:s', $mt);
    }
}
$phpCopyDisabled = $phpName === '' || $phpName === '—';
?>
<style>
    #ng-php-debug-bar .ng-php-debug-copy:not(:disabled):hover { background-color: #78350f !important; }
    #ng-php-debug-bar .ng-php-debug-copy:not(:disabled):active { background-color: #451a03 !important; }
</style>
<div id="ng-php-debug-bar"
     class="pointer-events-auto fixed z-[9999] flex w-auto max-w-full items-center gap-1.5 rounded-xl px-2.5 py-1.5 font-mono text-[11px] shadow-2xl"
     style="max-width:min(calc(100vw - 1rem), 22rem); bottom:0.75rem; right:0.75rem; background-color:#1c1917; color:#fef9c3; border:1px solid #ca8a04; box-shadow:0 10px 30px rgba(0,0,0,.4); font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace; font-size:11px;"
     role="status"
     aria-label="PHP oldal: <?= h($phpName) ?> · mentve: <?= h($phpMtimeLabel) ?>">
    <div style="min-width:0;flex:1;">
        <span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-align:left;line-height:1.375;color:#fef9c3;font-weight:500;" title="<?= h($phpFull) ?>"><?= h($phpName) ?></span>
        <span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-align:left;font-size:10px;line-height:1.375;opacity:.9;color:#fde68a;" title="Utolsó mentés: <?= h($phpMtimeLabel) ?>">mentve: <?= h($phpMtimeLabel) ?></span>
    </div>
    <button type="button"
            id="ng-php-debug-copy"
            class="ng-php-debug-copy"
            style="display:inline-flex;flex-shrink:0;align-items:center;justify-content:center;border-radius:6px;padding:4px;transition:background-color .15s;background-color:#422006;border:1px solid #eab308;color:#fffbeb;cursor:pointer;"
            title="Fájlnév másolása"
            aria-label="Fájlnév másolása a vágólapra"
            data-copy="<?= h($phpName) ?>"
            <?= $phpCopyDisabled ? ' disabled' : '' ?>>
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true" style="color:inherit;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
        </svg>
    </button>
</div>
<script>
(function () {
    var btn = document.getElementById('ng-php-debug-copy');
    if (!btn || btn.disabled) return;
    btn.addEventListener('click', function () {
        var text = btn.getAttribute('data-copy') || '';
        if (!text) return;
        function done() {
            var prev = btn.getAttribute('title') || '';
            btn.setAttribute('title', 'Másolva!');
            window.setTimeout(function () { btn.setAttribute('title', prev); }, 1500);
        }
        function fallback() {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); } catch (e) {}
            document.body.removeChild(ta);
            done();
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(fallback);
        } else {
            fallback();
        }
    });
})();
</script>
