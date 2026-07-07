<?php
declare(strict_types=1);

require_once __DIR__ . '/PmTools.php';

function pm_tools_db(): PDO
{
    return getDb();
}

function pm_tools_asset_url(string $file): string
{
    $path = 'assets/' . ltrim(str_replace('\\', '/', $file), '/');

    return nextgen_url($path);
}

function pm_tools_api_url(): string
{
    return nextgen_url('admin/pm/api.php');
}

function pm_tools_index_url(): string
{
    return nextgen_url('admin/pm/');
}

function pm_tools_page_url(string $phpPath): string
{
    $phpPath = PmTools::normalizePhpPath($phpPath);

    return site_url(ltrim($phpPath, '/'));
}

function pm_tools_has_access(): bool
{
    return isLoggedIn() && isSuperadmin();
}

function pm_tools_require_access(): void
{
    requireSuperadmin();
}

function pm_tools_csrf_token(): string
{
    return csrf_token('pmtools');
}

function pm_tools_csrf_validate(string $token): bool
{
    $expected = (string) ($_SESSION['_csrf']['pmtools'] ?? '');

    return $token !== '' && $expected !== '' && hash_equals($expected, $token);
}

function pm_tools_current_php_path(): string
{
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if ($script === '') {
        return '/';
    }

    return PmTools::normalizePhpPath($script);
}

function pm_tools_should_skip_request(): bool
{
    $script = strtolower(PmTools::normalizePhpPath((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
    $skipSuffixes = [
        '/nextgen/admin/pm/api.php',
        '/nextgen/admin/backup/poll.php',
        '/nextgen/admin/backup/oauth.php',
        '/nextgen/admin/backup/cancel.php',
        '/nextgen/admin/backup/step.php',
    ];
    foreach ($skipSuffixes as $suffix) {
        if ($script === $suffix || str_ends_with($script, $suffix)) {
            return true;
        }
    }

    return false;
}

function pm_tools_should_inject(): bool
{
    if (pm_tools_should_skip_request()) {
        return false;
    }
    if (!pm_tools_has_access()) {
        return false;
    }
    try {
        return PmTools::isOverlayEnabled(pm_tools_db());
    } catch (Throwable) {
        return false;
    }
}

/** @return array{overlay:bool,admin:bool,active:bool} */
function pm_tools_status(): array
{
    $overlay = false;
    try {
        $overlay = PmTools::isOverlayEnabled(pm_tools_db());
    } catch (Throwable) {
    }
    $admin = pm_tools_has_access();

    return [
        'overlay' => $overlay,
        'admin' => $admin,
        'active' => $overlay && $admin,
    ];
}

function pm_tools_unanswered_badge_count(): int
{
    if (!pm_tools_has_access()) {
        return 0;
    }
    try {
        return PmTools::countTotalUnansweredPages(pm_tools_db());
    } catch (Throwable) {
        return 0;
    }
}

function pm_tools_icon_notes(): string
{
    return '<svg class="pm-tools-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">'
        . '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>'
        . '<polyline points="14 2 14 8 20 8"/>'
        . '<line x1="8" y1="13" x2="16" y2="13"/>'
        . '<line x1="8" y1="17" x2="13" y2="17"/>'
        . '</svg>';
}

function pm_tools_icon_copy(): string
{
    return '<svg class="pm-tools-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">'
        . '<rect x="9" y="9" width="13" height="13" rx="2"/>'
        . '<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>'
        . '</svg>';
}

function pm_tools_render_note_rows_html(array $notes): string
{
    if ($notes === []) {
        return '<div class="pm-tools-note-row">'
            . '<textarea class="pm-note-input" rows="2" placeholder="Jegyzet…"></textarea>'
            . '<textarea class="pm-response-input" rows="2" placeholder="Válasz…"></textarea>'
            . '<button type="button" class="pm-tools-row-del" title="Sor törlése">&times;</button>'
            . '</div>';
    }

    $html = '';
    foreach ($notes as $note) {
        $noteText = (string) ($note['note_text'] ?? '');
        $responseText = (string) ($note['response_text'] ?? '');
        $isUnanswered = PmTools::noteRowIsUnanswered($noteText, $responseText);
        $rowClass = $isUnanswered ? ' pm-tools-note-row is-unanswered-row' : ' pm-tools-note-row';
        $responseClass = $isUnanswered ? ' pm-response-input is-unanswered' : ' pm-response-input';
        $html .= '<div class="' . trim($rowClass) . '">'
            . '<textarea class="pm-note-input" rows="2">' . h($noteText) . '</textarea>'
            . '<textarea class="' . trim($responseClass) . '" rows="2" placeholder="Válasz…">' . h($responseText) . '</textarea>'
            . '<button type="button" class="pm-tools-row-del" title="Sor törlése">&times;</button>'
            . '</div>';
    }

    return $html;
}

function pm_tools_render_widget_html(): string
{
    try {
        $pdo = pm_tools_db();
        $phpPath = pm_tools_current_php_path();
        $page = PmTools::getOrCreatePage($pdo, $phpPath);
        $notes = PmTools::listNotesForPage($pdo, (int) $page['id']);
    } catch (Throwable) {
        return '';
    }

    $pageId = (int) $page['id'];
    $unansweredCount = PmTools::countUnansweredNotes($pdo, $pageId);
    $catalog = PmTools::catalogMeta($phpPath);
    $displayName = $catalog['display_name'] ?? (string) ($page['display_name'] ?? basename($phpPath));
    $purpose = $catalog['purpose'] ?? (string) ($page['purpose'] ?? '');
    $csrf = pm_tools_csrf_token();
    $apiUrl = pm_tools_api_url();
    $assetCss = h(pm_tools_asset_url('css/pmtools.css'));
    $assetJs = h(pm_tools_asset_url('js/pmtools.js'));
    $notesBtnClass = 'pm-tools-icon-btn' . ($unansweredCount > 0 ? ' has-unanswered-notes' : '');
    $purposeHtml = trim($purpose) !== ''
        ? '<p class="pm-tools-purpose-text"><span>Mire jó</span>' . h($purpose) . '</p>'
        : '';

    return '<link rel="stylesheet" href="' . $assetCss . '">'
        . '<div id="pm-tools-root"'
        . ' data-page-id="' . $pageId . '"'
        . ' data-php-path="' . h($phpPath) . '"'
        . ' data-csrf="' . h($csrf) . '"'
        . ' data-api="' . h($apiUrl) . '">'
        . '<div class="pm-tools-badge-wrap">'
        . '<button type="button" class="' . h($notesBtnClass) . '" id="pm-tools-notes-btn"'
        . ' title="Jegyzetek – ' . h($phpPath) . '"'
        . ' aria-label="Jegyzetek megnyitása: ' . h($phpPath) . '">'
        . pm_tools_icon_notes()
        . '</button>'
        . '<button type="button" class="pm-tools-icon-btn pm-tools-copy-btn" id="pm-tools-copy-badge"'
        . ' data-copy="' . h($phpPath) . '"'
        . ' title="Másolás: ' . h($phpPath) . '"'
        . ' aria-label="PHP útvonal másolása: ' . h($phpPath) . '">'
        . pm_tools_icon_copy()
        . '</button>'
        . '</div>'
        . '<div class="pm-tools-overlay" id="pm-tools-overlay" hidden>'
        . '<div class="pm-tools-modal" role="dialog" aria-labelledby="pm-tools-modal-title">'
        . '<header class="pm-tools-modal-head">'
        . '<div>'
        . '<h2 id="pm-tools-modal-title">' . h($displayName) . '</h2>'
        . '<div class="pm-tools-modal-path-row">'
        . '<p class="pm-tools-modal-path">' . h($phpPath) . '</p>'
        . '<button type="button" class="pm-tools-icon-btn pm-tools-copy-btn pm-tools-copy-btn--modal"'
        . ' data-copy="' . h($phpPath) . '" title="PHP útvonal másolása" aria-label="PHP útvonal másolása">'
        . pm_tools_icon_copy()
        . '</button>'
        . '</div>'
        . '</div>'
        . '<button type="button" class="pm-tools-close" id="pm-tools-close" aria-label="Bezárás">&times;</button>'
        . '</header>'
        . '<div class="pm-tools-modal-body">'
        . '<label class="pm-tools-field">'
        . '<span>Név</span>'
        . '<input type="text" id="pm-tools-name" value="' . h($displayName) . '">'
        . '</label>'
        . $purposeHtml
        . '<div class="pm-tools-notes-wrap">'
        . '<div class="pm-tools-notes-head">'
        . '<span>Jegyzet</span><span>Válasz</span><span></span>'
        . '</div>'
        . '<div id="pm-tools-notes-rows">'
        . pm_tools_render_note_rows_html($notes)
        . '</div>'
        . '<button type="button" class="pm-tools-add-row" id="pm-tools-add-row">+ Új sor hozzáadása</button>'
        . '</div>'
        . '</div>'
        . '<footer class="pm-tools-modal-foot">'
        . '<span class="pm-tools-status" id="pm-tools-status"></span>'
        . '<button type="button" class="pm-tools-btn pm-tools-btn-primary" id="pm-tools-save">Mentés</button>'
        . '</footer>'
        . '</div>'
        . '</div>'
        . '</div>'
        . '<script src="' . $assetJs . '" defer></script>';
}

function pm_tools_render_footer(): void
{
    if (!pm_tools_should_inject()) {
        return;
    }
    echo pm_tools_render_widget_html();
}

function pm_tools_register_footer_output_buffer(): void
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
        if (!pm_tools_should_inject()) {
            return $buffer;
        }
        $widget = pm_tools_render_widget_html();
        if ($widget === '') {
            return $buffer;
        }

        return (string) preg_replace('/<\/body>/i', $widget . '</body>', $buffer, 1);
    });
}
