<?php
declare(strict_types=1);

/**
 * Superadmin: aktuális PHP fájl neve + másolás (EVServiceInfo debug sáv mintájára).
 */

function ng_should_show_superadmin_php_debug_bar(): bool
{
    if (PHP_SAPI === 'cli') {
        return false;
    }

    return ng_admin_szint_resolved() === 'superadmin';
}

function ng_superadmin_php_debug_bar_html(): string
{
    if (!ng_should_show_superadmin_php_debug_bar()) {
        return '';
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
    $disabledAttr = $phpCopyDisabled ? ' disabled' : '';

    return '<style>'
        . '#ng-php-debug-bar .ng-php-debug-copy:not(:disabled):hover{background-color:#78350f!important;}'
        . '#ng-php-debug-bar .ng-php-debug-copy:not(:disabled):active{background-color:#451a03!important;}'
        . '</style>'
        . '<div id="ng-php-debug-bar"'
        . ' role="status"'
        . ' aria-label="PHP oldal: ' . h($phpName) . ' · mentve: ' . h($phpMtimeLabel) . '"'
        . ' style="position:fixed;z-index:99999;right:12px;bottom:12px;display:flex;align-items:center;gap:6px;max-width:min(calc(100vw - 1rem),22rem);padding:6px 10px;border-radius:12px;border:1px solid #ca8a04;background-color:#1c1917;color:#fef9c3;box-shadow:0 10px 30px rgba(0,0,0,.4);font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:11px;line-height:1.3;">'
        . '<div style="min-width:0;flex:1;">'
        . '<span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:500;color:#fef9c3;" title="' . h($phpFull) . '">' . h($phpName) . '</span>'
        . '<span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:10px;color:#fde68a;opacity:.9;" title="Utolsó mentés: ' . h($phpMtimeLabel) . '">mentve: ' . h($phpMtimeLabel) . '</span>'
        . '</div>'
        . '<button type="button" class="ng-php-debug-copy"'
        . ' style="display:inline-flex;flex-shrink:0;align-items:center;justify-content:center;border-radius:6px;padding:4px;background-color:#422006;border:1px solid #eab308;color:#fffbeb;cursor:pointer;"'
        . ' title="Fájlnév másolása" aria-label="Fájlnév másolása a vágólapra"'
        . ' data-copy="' . h($phpName) . '"' . $disabledAttr . '>'
        . '<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true" style="color:inherit;">'
        . '<path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>'
        . '</svg></button></div>'
        . '<script>(function(){var bar=document.getElementById("ng-php-debug-bar");'
        . 'if(!bar||bar.dataset.ngCopyBound==="1")return;bar.dataset.ngCopyBound="1";'
        . 'var btn=bar.querySelector(".ng-php-debug-copy");if(!btn||btn.disabled)return;'
        . 'btn.addEventListener("click",function(){var text=btn.getAttribute("data-copy")||"";if(!text)return;'
        . 'function done(){var prev=btn.getAttribute("title")||"";btn.setAttribute("title","Másolva!");'
        . 'window.setTimeout(function(){btn.setAttribute("title",prev);},1500);}'
        . 'function fallback(){var ta=document.createElement("textarea");ta.value=text;ta.setAttribute("readonly","");'
        . 'ta.style.position="fixed";ta.style.left="-9999px";document.body.appendChild(ta);ta.select();'
        . 'try{document.execCommand("copy");}catch(e){}document.body.removeChild(ta);done();}'
        . 'if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(text).then(done).catch(fallback);}'
        . 'else{fallback();}});})();</script>';
}

function ng_register_superadmin_php_debug_bar_output_buffer(): void
{
    static $registered = false;
    if ($registered || PHP_SAPI === 'cli') {
        return;
    }
    $registered = true;

    ob_start(static function (string $buffer): string {
        if ($buffer === '' || stripos($buffer, '</body>') === false) {
            return $buffer;
        }
        $bar = ng_superadmin_php_debug_bar_html();
        if ($bar === '') {
            return $buffer;
        }

        return (string) preg_replace('/<\/body>/i', $bar . '</body>', $buffer, 1);
    });
}
