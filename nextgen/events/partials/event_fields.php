<?php
declare(strict_types=1);
/** @var array $e aktuális mezőértékek */
/** @var array $organizers id => név */
/** @var array $categories id => név */
/** @var array<int, string> $venues id => név (events_load_venue_options) */
/** @var array<int, string> $tags id => név (events_load_tag_options) */
/** @var array<int, string> $styles id => név (events_load_style_options) */
if (!isset($organizers) || !is_array($organizers)) {
    $organizers = [];
}
if (!isset($venues) || !is_array($venues)) {
    $venues = [];
}
if (!isset($categories) || !is_array($categories)) {
    $categories = [];
}
if (!isset($tags) || !is_array($tags)) {
    $tags = [];
}
if (!isset($styles) || !is_array($styles)) {
    $styles = [];
}
if (!isset($db) || !($db instanceof PDO)) {
    $db = getDb();
}
if (!function_exists('events_load_organizer_finance_map')) {
    require_once __DIR__ . '/../lib/organizer_finance.php';
}
if (!isset($organizerFinanceMap) || !is_array($organizerFinanceMap)) {
    $organizerFinanceMap = events_load_organizer_finance_map($db);
}
$tagsAllowCreate = events_tags_tables_available($db);
$stylesAllowCreate = events_styles_tables_available($db);
$selOrg = isset($e['organizer_ids']) && is_array($e['organizer_ids']) ? array_values(array_unique(array_map('intval', $e['organizer_ids']))) : [];
$selFinancePayer = isset($e['finance_payer_organizer_ids']) && is_array($e['finance_payer_organizer_ids'])
    ? array_values(array_unique(array_map('intval', $e['finance_payer_organizer_ids'])))
    : [];
$selCat = isset($e['category_ids']) && is_array($e['category_ids']) ? array_values(array_unique(array_map('intval', $e['category_ids']))) : [];
$selTag = isset($e['tag_ids']) && is_array($e['tag_ids']) ? array_values(array_unique(array_map('intval', $e['tag_ids']))) : [];
$selMainStyle = isset($e['main_style_ids']) && is_array($e['main_style_ids']) ? array_values(array_unique(array_map('intval', $e['main_style_ids']))) : [];
$selSupplementaryStyle = isset($e['supplementary_style_ids']) && is_array($e['supplementary_style_ids']) ? array_values(array_unique(array_map('intval', $e['supplementary_style_ids']))) : [];
$orgPickerAll = [];
foreach ($organizers as $oid => $onev) {
    $orgPickerAll[] = ['id' => (int) $oid, 'name' => (string) $onev];
}
usort($orgPickerAll, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));
$catPickerAll = [];
foreach ($categories as $cid => $cnev) {
    $catPickerAll[] = ['id' => (int) $cid, 'name' => (string) $cnev];
}
$tagPickerAll = [];
foreach ($tags as $tid => $tnev) {
    $tagPickerAll[] = ['id' => (int) $tid, 'name' => (string) $tnev];
}
usort($tagPickerAll, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));
$stylePickerAll = [];
foreach ($styles as $sid => $snev) {
    $stylePickerAll[] = ['id' => (int) $sid, 'name' => (string) $snev];
}
usort($stylePickerAll, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));
$eventpicFiles = events_eventpics_list_files();
$selectedVenueId = (string) ($e['venue_id'] ?? '');
$selVenue = $selectedVenueId !== '' ? [(int) $selectedVenueId] : [];
$venuePickerAll = [];
foreach ($venues as $vid => $vname) {
    $venuePickerAll[] = ['id' => (int) $vid, 'name' => (string) $vname];
}
usort($venuePickerAll, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));
$eventpicsPick = (string) ($e['event_featured_image_pick'] ?? '');
$coverPreview = events_featured_image_form_preview_meta((string) ($e['event_featured_image_url'] ?? ''), $eventpicsPick);
$coverPreviewCaption = $coverPreview['source'] === 'url'
    ? 'Előnézet a „Kiemelt kép URL” mező alapján (elsőbbség az eventpics felett).'
    : ($coverPreview['source'] === 'eventpic'
        ? 'Előnézet az eventpics borító alapján (nincs kitöltött URL).'
        : '');
$canPreviewPublic = ($e['event_status'] ?? '') === events_public_post_status()
    && trim((string) ($e['event_slug'] ?? '')) !== '';
$eventFormAutoSlug = !empty($eventFormAutoSlug);
$eventSlugRefreshTitle = 'Slug frissítése (név + kezdő dátum), vágólapra másolva';
$organizerFinanceForJs = [];
foreach ($organizers as $oid => $onev) {
    $oidInt = (int) $oid;
    $fin = $organizerFinanceMap[$oidInt] ?? ['finance_ticket_percent' => null, 'finance_fix_amount' => null];
    $organizerFinanceForJs[$oidInt] = [
        'name' => (string) $onev,
        'finance_ticket_percent' => $fin['finance_ticket_percent'] ?? null,
        'finance_fix_amount' => $fin['finance_fix_amount'] ?? null,
    ];
}
$organizerFinanceJson = json_encode($organizerFinanceForJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if ($organizerFinanceJson === false) {
    $organizerFinanceJson = '{}';
}
?>
<div class="events-edit-layout">
<div class="events-edit-main">
<div class="events-edit-panel events-edit-panel--tone-title">
    <h3 class="events-edit-panel__title">Esemény neve</h3>
    <div class="events-edit-title-row">
        <label class="visually-hidden" for="event_name">Esemény neve *</label>
        <input type="text" id="event_name" name="event_name" class="events-edit-name-input" value="<?= h($e['event_name']) ?>" required maxlength="500" placeholder="Esemény címe…">
        <button type="button" class="btn btn-secondary events-edit-slug-refresh" id="event-slug-refresh" title="<?= h($eventSlugRefreshTitle) ?>" aria-label="Slug frissítése">🔄</button>
        <input type="text" id="event_slug" name="event_slug" class="events-edit-slug-input" value="<?= h($e['event_slug']) ?>" required maxlength="255" pattern="[a-z0-9][a-z0-9\-]*" title="URL slug — kisbetű, szám és kötőjel" placeholder="url-slug" aria-label="URL slug *" data-auto-slug="<?= $eventFormAutoSlug ? '1' : '0' ?>">
        <?php if ($canPreviewPublic): ?>
            <a href="<?= h(events_megjelenit_url((string) $e['event_slug'])) ?>" class="events-icon-action events-edit-preview-action" title="Nyilvános megtekintés (új lap)" aria-label="Nyilvános megtekintés új lapon" target="_blank" rel="noopener">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>
            </a>
        <?php endif; ?>
    </div>
</div>
<div class="events-edit-panel events-edit-panel--tone-dates">
    <div class="events-edit-panel__title-row events-edit-panel__title-row--datetime">
        <h3 class="events-edit-panel__title">Időpont</h3>
        <label class="events-toggle" for="event_allday">
            <input type="checkbox" name="event_allday" value="1" id="event_allday" class="events-toggle__input" <?= !empty($e['event_allday']) ? 'checked' : '' ?>>
            <span class="events-toggle__ui" aria-hidden="true"></span>
            <span class="events-toggle__label">Egész napos</span>
        </label>
    </div>
    <div class="form-row events-edit-dates-grid">
        <div class="events-edit-dates-date-row">
            <div class="form-group">
                <label for="event_start_date">Kezdő dátum *</label>
                <input type="date" id="event_start_date" name="event_start_date" value="<?= h($e['event_start_date']) ?>" required>
            </div>
            <div class="events-edit-date-copy-cell">
                <span class="events-edit-date-copy-label" aria-hidden="true">&nbsp;</span>
                <button
                    type="button"
                    class="btn btn-secondary btn-sm events-edit-date-copy-btn"
                    id="event-end-date-copy-start"
                    title="Kezdő dátum másolása záró dátumra"
                    aria-label="Kezdő dátum másolása záró dátumra"
                >→</button>
            </div>
            <div class="form-group">
                <label for="event_end_date">Záró dátum</label>
                <input type="date" id="event_end_date" name="event_end_date" value="<?= h($e['event_end_date']) ?>">
            </div>
        </div>
        <div class="events-edit-dates-time-row" id="events-edit-dates-time-row">
            <div class="form-group events-edit-time-field" id="events-edit-start-time-wrap">
                <label for="event_start_time">Kezdő idő</label>
                <input type="time" id="event_start_time" name="event_start_time" value="<?= h($e['event_start_time']) ?>">
            </div>
            <div class="form-group events-edit-time-field" id="events-edit-end-time-wrap">
                <label for="event_end_time">Záró idő</label>
                <input type="time" id="event_end_time" name="event_end_time" value="<?= h($e['event_end_time']) ?>">
            </div>
        </div>
    </div>
</div>
<div class="events-edit-panel events-edit-panel--tone-url">
    <h3 class="events-edit-panel__title">További információ</h3>
    <div class="form-group events-url-open-row">
        <input type="url" id="event_url" name="event_url" value="<?= h($e['event_url']) ?>" maxlength="2000" placeholder="https://" aria-label="További információ URL">
        <?php if (!empty($e['event_url'])): ?>
            <a class="btn btn-secondary events-url-open-btn" href="<?= h($e['event_url']) ?>" target="_blank" rel="noopener noreferrer">Megnyitás új ablakban</a>
        <?php endif; ?>
    </div>
</div>
<div class="events-edit-org-venue-grid">
<div class="events-edit-panel events-edit-panel--tone-venue">
    <h3 class="events-edit-panel__title">Helyszín</h3>
<?php
$wpTokenId = 'event-venue';
$wpTokenLabel = '';
$wpTokenFieldName = 'venue_id';
$wpTokenPlaceholder = 'Helyszín keresése vagy új neve…';
$wpTokenHelp = '';
$wpTokenManageUrl = events_url('venue_letrehoz.php');
$wpTokenManageLabel = 'Új helyszín felvétele';
$wpTokenManageNewTab = true;
$wpTokenAll = $venuePickerAll;
$wpTokenSelected = $selVenue;
$wpTokenAllowCreate = true;
$wpTokenEntityType = 'venue';
$wpTokenSingle = true;
$wpTokenShowPopular = false;
$wpTokenChipLinkPattern = events_url('venue_szerkeszt.php?id={id}');
require __DIR__ . '/wp_token_field.php';
?>
</div>
<div class="events-edit-panel events-edit-panel--tone-org">
    <h3 class="events-edit-panel__title">Szervezők</h3>
<?php
$wpTokenId = 'event-organizers';
$wpTokenLabel = '';
$wpTokenFieldName = 'organizer_ids[]';
$wpTokenPlaceholder = 'Szervező hozzáadása…';
$wpTokenHelp = '';
$wpTokenManageUrl = events_url('organizer_letrehoz.php');
$wpTokenManageLabel = 'Új szervező felvétele';
$wpTokenManageNewTab = true;
$wpTokenAll = $orgPickerAll;
$wpTokenSelected = $selOrg;
$wpTokenAllowCreate = true;
$wpTokenEntityType = 'organizer';
$wpTokenSingle = false;
$wpTokenShowPopular = false;
$wpTokenChipLinkPattern = events_url('organizer.php?id={id}');
require __DIR__ . '/wp_token_field.php';
?>
</div>
</div>
<div class="events-edit-panel events-edit-panel--tone-content">
    <h3 class="events-edit-panel__title">Leírás</h3>
    <div class="form-group">
        <label class="visually-hidden" for="event_content">Leírás (HTML) *</label>
        <textarea id="event_content" name="event_content" class="js-html-editor-source" rows="14" required><?= h($e['event_content']) ?></textarea>
    </div>
</div>
<div class="events-edit-panel events-edit-panel--tone-cost events-edit-cost-block">
    <h3 class="events-edit-panel__title">Belépő</h3>
    <div class="events-edit-cost-block__body">
        <div class="form-row events-edit-cost-grid">
            <div class="form-group">
                <label for="event_cost_from">Tól</label>
                <input type="number" id="event_cost_from" name="event_cost_from" step="0.01" min="0" value="<?= h($e['event_cost_from']) ?>" placeholder="0">
            </div>
            <div class="form-group">
                <label for="event_cost_to">Ig</label>
                <input type="number" id="event_cost_to" name="event_cost_to" step="0.01" min="0" value="<?= h($e['event_cost_to']) ?>" placeholder="0">
            </div>
        </div>
        <div class="events-edit-cost-block__payer">
            <span class="events-edit-cost-block__payer-label" id="event-finance-payer-label">Ki fizeti</span>
<?php
$wpTokenId = 'event-finance-payer';
$wpTokenLabel = '';
$wpTokenFieldName = 'finance_payer_organizer_ids[]';
$wpTokenPlaceholder = 'Szervező…';
$wpTokenHelp = '';
$wpTokenManageUrl = events_url('organizers.php');
$wpTokenManageLabel = 'Szervezők';
$wpTokenManageNewTab = true;
$wpTokenAll = $orgPickerAll;
$wpTokenSelected = $selFinancePayer;
$wpTokenAllowCreate = false;
$wpTokenEntityType = 'organizer';
$wpTokenSingle = false;
$wpTokenShowPopular = false;
$wpTokenChipLinkPattern = events_url('organizer_szerkeszt.php?id={id}');
require __DIR__ . '/wp_token_field.php';
?>
        </div>
        <textarea
            id="finance_note"
            name="finance_note"
            class="events-edit-cost-block__note"
            rows="2"
            maxlength="5000"
            placeholder="Megjegyzés…"
            aria-label="Finance megjegyzés"
        ><?= h((string) ($e['finance_note'] ?? '')) ?></textarea>
        <div class="events-edit-finance-calc" id="events-edit-finance-calc"
             data-organizer-finance="<?= h($organizerFinanceJson) ?>">
            <div class="events-edit-finance-calc__row">
                <button type="button" class="btn btn-secondary btn-sm" id="events-edit-finance-calc-btn">Szervezői díj kalkulálása</button>
                <output class="events-edit-finance-calc__result" id="events-edit-finance-calc-result" for="events-edit-finance-calc-btn" aria-live="polite">—</output>
            </div>
        </div>
    </div>
</div>
</div>
<aside class="events-edit-sidebar">
<?php
$eventFormActionsPlacement = 'sidebar';
require __DIR__ . '/event_form_actions.php';
?>
<div class="events-edit-sidebar-cover" id="eventpics-summary-preview">
    <div class="events-edit-sidebar-cover__media">
        <div class="events-edit-sidebar-cover__frame">
            <button type="button" class="events-edit-sidebar-cover__trigger" id="eventpics-summary-trigger" aria-label="Teljes kép megnyitása"<?= $coverPreview['src'] === '' ? ' disabled' : '' ?>>
                <img
                    id="eventpics-summary-img"
                    class="events-edit-sidebar-cover__img"
                    src="<?= $coverPreview['src'] !== '' ? h($coverPreview['src']) : '' ?>"
                    alt="Borító előnézet"
                    decoding="async"
                    <?= $coverPreview['src'] === '' ? 'hidden' : '' ?>
                >
            </button>
            <div class="events-edit-sidebar-cover__placeholder" id="eventpics-cover-placeholder"<?= $coverPreview['source'] !== 'none' ? ' hidden' : '' ?>>Nincs borítókép</div>
            <div class="events-edit-sidebar-cover__toolbar">
                <button type="button" class="events-edit-sidebar-cover__tool" id="eventpics-cover-gallery" title="Galéria" aria-label="Borítókép választása a galériában">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                </button>
                <button type="button" class="events-edit-sidebar-cover__tool" id="eventpics-cover-upload" title="Feltöltés" aria-label="Kép feltöltése és beállítása borítóként">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 8l4-4 4 4"/><path stroke-linecap="round" d="M4 20h16"/></svg>
                </button>
            </div>
        </div>
    </div>
    <span id="eventpics-summary-name" class="visually-hidden"><?= $coverPreview['label'] !== '' ? h($coverPreview['label']) : '' ?></span>
    <span id="eventpics-summary-source" class="visually-hidden"><?= $coverPreviewCaption !== '' ? h($coverPreviewCaption) : '' ?></span>
</div>
<div class="events-edit-panel events-edit-panel--tone-publish events-edit-panel--publish">
    <div class="form-group events-edit-panel__status-only">
        <label class="visually-hidden" for="event_status_data">Státusz</label>
        <select id="event_status_data" name="event_status" required aria-label="Státusz">
            <?php foreach (events_allowed_post_statuses() as $val): ?>
                <option value="<?= h($val) ?>" <?= ($e['event_status'] === $val) ? 'selected' : '' ?>><?= h(events_post_status_label($val)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php
    $eventFormShowPermanentDelete = !empty($eventFormShowPermanentDelete);
    if ($eventFormShowPermanentDelete):
    ?>
    <div class="events-edit-panel__danger-zone">
        <button
            type="submit"
            class="btn btn-danger btn-sm events-edit-panel__delete"
            name="form_action"
            value="permanent_delete"
            onclick="return confirm('Biztosan véglegesen törlöd ezt az eseményt? A művelet nem vonható vissza. A kapcsolódó adatok törlődnek; a borítókép csak akkor, ha máshol nem használják.');"
        >Végleges törlés</button>
    </div>
    <?php endif; ?>
</div>
<div class="events-edit-panel events-edit-panel--tone-change" id="events-edit-change-panel">
    <div class="events-edit-panel__title-row events-edit-panel__title-row--change">
        <h3 class="events-edit-panel__title">Változás / elmaradás</h3>
        <label class="events-toggle events-toggle--inline" for="event_change_active" aria-label="Változás jelzése">
            <input
                type="checkbox"
                name="event_change_active"
                value="1"
                id="event_change_active"
                class="events-toggle__input"
                <?= !empty($e['event_change_active']) ? 'checked' : '' ?>
            >
            <span class="events-toggle__ui" aria-hidden="true"></span>
        </label>
    </div>
    <div class="events-edit-change-fields" id="events-edit-change-fields" <?= empty($e['event_change_active']) ? 'hidden' : '' ?>>
        <div class="form-row">
            <div class="form-group">
                <label for="event_change_type">Típus *</label>
                <select id="event_change_type" name="event_change_type">
                    <option value="">— válassz —</option>
                    <?php foreach (events_event_change_types() as $typeKey => $typeLabel): ?>
                        <option value="<?= h($typeKey) ?>" <?= ($e['event_change_type'] ?? '') === $typeKey ? 'selected' : '' ?>><?= h($typeLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label for="event_change_note">Publikus megjegyzés</label>
            <textarea
                id="event_change_note"
                name="event_change_note"
                rows="3"
                maxlength="2000"
                placeholder="Pl. új időpont, másik helyszín, elmaradás oka…"
            ><?= h((string) ($e['event_change_note'] ?? '')) ?></textarea>
            <p class="help">A naptárban és az esemény oldalán jelenik meg.</p>
        </div>
    </div>
</div>
<div class="events-edit-panel events-edit-panel--tone-cat">
    <h3 class="events-edit-panel__title">Kategóriák</h3>
<?php
$wpTokenId = 'event-categories';
$wpTokenLabel = '';
$wpTokenFieldName = 'category_ids[]';
$wpTokenPlaceholder = 'Kategória hozzáadása…';
$wpTokenHelp = 'Hierarchikus nevek is kereshetők. Új kategória Enterrel hozható létre.';
$wpTokenManageUrl = events_url('categories.php');
$wpTokenManageLabel = 'Kategóriák kezelése';
$wpTokenAll = $catPickerAll;
$wpTokenSelected = $selCat;
$wpTokenAllowCreate = true;
$wpTokenEntityType = 'category';
$wpTokenSingle = false;
$wpTokenShowPopular = true;
require __DIR__ . '/wp_token_field.php';
?>
</div>
<div class="events-edit-panel events-edit-panel--tone-tag">
    <h3 class="events-edit-panel__title">Címkék</h3>
<?php
$wpTokenId = 'event-tags';
$wpTokenLabel = '';
$wpTokenFieldName = 'tag_ids[]';
$wpTokenPlaceholder = 'Címke hozzáadása…';
$wpTokenHelp = 'Írj be egy nevet — Enterrel kiválasztod vagy létrehozod. A chipre kattintva a címke szerkesztője nyílik meg.';
$wpTokenManageUrl = events_url('tags.php');
$wpTokenManageLabel = 'Címkék kezelése';
$wpTokenAll = $tagPickerAll;
$wpTokenSelected = $selTag;
$wpTokenAllowCreate = $tagsAllowCreate;
$wpTokenEntityType = 'tag';
$wpTokenSingle = false;
$wpTokenShowPopular = false;
$wpTokenChipLinkPattern = events_url('tags.php?open_tag={id}#open-tag-{id}');
$wpTokenChipLinkNewTab = false;
require __DIR__ . '/wp_token_field.php';
?>
</div>
<div class="events-edit-panel events-edit-panel--tone-mstyle">
    <h3 class="events-edit-panel__title">Fő stílusok</h3>
<?php
$wpTokenId = 'event-main-styles';
$wpTokenLabel = '';
$wpTokenFieldName = 'main_style_ids[]';
$wpTokenPlaceholder = 'Stílus hozzáadása…';
$wpTokenHelp = 'Nyilvánosan megjelennek, de nem kattinthatók.';
$wpTokenManageUrl = events_url('styles.php');
$wpTokenManageLabel = 'Stílusok kezelése';
$wpTokenAll = $stylePickerAll;
$wpTokenSelected = $selMainStyle;
$wpTokenAllowCreate = $stylesAllowCreate;
$wpTokenEntityType = 'style';
$wpTokenSingle = false;
$wpTokenShowPopular = true;
require __DIR__ . '/wp_token_field.php';
?>
</div>
<div class="events-edit-panel events-edit-panel--tone-sstyle">
    <h3 class="events-edit-panel__title">Kiegészítő stílusok</h3>
<?php
$wpTokenId = 'event-supplementary-styles';
$wpTokenLabel = '';
$wpTokenFieldName = 'supplementary_style_ids[]';
$wpTokenPlaceholder = 'Stílus hozzáadása…';
$wpTokenHelp = 'Kiegészítő stílusok a fő stílusok mellett.';
$wpTokenManageUrl = events_url('styles.php');
$wpTokenManageLabel = 'Stílusok kezelése';
$wpTokenAll = $stylePickerAll;
$wpTokenSelected = $selSupplementaryStyle;
$wpTokenAllowCreate = $stylesAllowCreate;
$wpTokenEntityType = 'style';
$wpTokenSingle = false;
$wpTokenShowPopular = true;
require __DIR__ . '/wp_token_field.php';
?>
</div>
<div class="events-edit-panel events-edit-panel--tone-image event-featured-card">
    <h3 class="events-edit-panel__title event-featured-card__title">Esemény képe</h3>
    <div class="form-group">
        <label for="event_featured_image_url">Kiemelt kép URL</label>
        <input type="text" id="event_featured_image_url" name="event_featured_image_url" value="<?= h($e['event_featured_image_url'] ?? '') ?>" maxlength="2000" placeholder="https://… vagy /útvonal/kép.jpg" spellcheck="false" autocomplete="off">
        <p class="help">Ha URL meg van adva, ez elsőbbséget élvez az eventpics képpel szemben.</p>
    </div>
    <div class="form-group eventpics-media-block">
        <label>Eventpics borítókép</label>
        <p class="help">A jobb oldali borítóképnél a <strong>Galéria</strong> megnyitja a választót, a <strong>Feltöltés</strong> közvetlenül feltölt és beállítja a képet.</p>
        <input type="hidden" name="event_featured_image_pick" id="event_featured_image_pick" value="<?= h($eventpicsPick) ?>">
        <div class="eventpics-form-summary" id="eventpics-form-summary" data-base="<?= h(events_url('eventpics/')) ?>">
            <div class="eventpics-form-summary__inner eventpics-form-summary__inner--toolbar">
                <p class="eventpics-form-summary__empty help" id="eventpics-summary-empty"<?= $coverPreview['source'] !== 'none' ? ' hidden' : '' ?>>Nincs borítókép (adj meg URL-t vagy válassz eventpics képet).</p>
                <div class="eventpics-form-summary__actions">
                    <button type="button" class="btn btn-secondary" id="eventpics-clear-main">Borítókép törlése</button>
                </div>
            </div>
        </div>
    </div>
</div>
</aside>
</div>

<dialog class="event-edit-cover-lightbox" id="event-edit-cover-lightbox" aria-label="Borítókép">
    <button type="button" class="event-edit-cover-lightbox__close" id="event-edit-cover-lightbox-close" aria-label="Bezárás">×</button>
    <div class="event-edit-cover-lightbox__stage">
        <img
            class="event-edit-cover-lightbox__img"
            id="event-edit-cover-lightbox-img"
            src="<?= $coverPreview['src'] !== '' ? h($coverPreview['src']) : '' ?>"
            alt="Borítókép"
            decoding="async"
        >
    </div>
</dialog>

<dialog class="eventpics-modal" id="eventpics-modal" aria-labelledby="eventpics-modal-title">
    <div class="eventpics-modal__inner">
        <header class="eventpics-modal__header">
            <h2 class="eventpics-modal__title" id="eventpics-modal-title">Borítókép kiválasztása</h2>
            <button type="button" class="eventpics-modal__x" id="eventpics-modal-x" aria-label="Bezárás">×</button>
        </header>
        <div
            class="eventpics-browser"
            id="eventpics-browser"
            data-upload-url="<?= h(events_url('ajax_eventpic_upload.php')) ?>"
            data-csrf="<?= h(csrf_token('events_eventpics')) ?>"
        >
            <div class="eventpics-toolbar">
                <button type="button" class="btn btn-primary" id="eventpics-btn-upload">Kép feltöltése</button>
                <button type="button" class="btn btn-secondary" id="eventpics-btn-clear">Nincs kiválasztva</button>
                <input type="search" class="eventpics-filter" id="eventpics-filter" placeholder="Szűrés fájlnév szerint…" autocomplete="off" spellcheck="false">
                <span class="eventpics-msg" id="eventpics-msg" role="status"></span>
            </div>
            <div class="eventpics-dropzone" id="eventpics-dropzone" tabindex="-1">
                <div class="eventpics-grid" id="eventpics-grid">
                    <?php foreach ($eventpicFiles as $picFile): ?>
                        <button
                            type="button"
                            class="eventpics-item<?= $picFile === $eventpicsPick ? ' is-selected' : '' ?>"
                            data-filename="<?= h($picFile) ?>"
                            title="<?= h($picFile) ?>"
                        >
                            <span class="eventpics-item-check" aria-hidden="true"></span>
                            <img src="<?= h(events_url('eventpics/' . rawurlencode($picFile))) ?>" alt="" loading="lazy" width="150" height="150">
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <input type="file" id="eventpics-file-input" class="visually-hidden" accept="image/jpeg,image/png,image/webp,image/gif">
        </div>
        <footer class="eventpics-modal__footer">
            <button type="button" class="btn btn-secondary" id="eventpics-modal-cancel">Mégse</button>
            <button type="button" class="btn btn-primary" id="eventpics-modal-ok">Kiválasztás</button>
        </footer>
    </div>
</dialog>
<script>
(function () {
    var dialog = document.getElementById('eventpics-modal');
    var root = document.getElementById('eventpics-browser');
    var hidden = document.getElementById('event_featured_image_pick');
    var grid = document.getElementById('eventpics-grid');
    var btnUp = document.getElementById('eventpics-btn-upload');
    var btnClear = document.getElementById('eventpics-btn-clear');
    var fileInp = document.getElementById('eventpics-file-input');
    var drop = document.getElementById('eventpics-dropzone');
    var msg = document.getElementById('eventpics-msg');
    var filter = document.getElementById('eventpics-filter');
    var btnOpen = document.getElementById('eventpics-cover-gallery');
    var btnCoverUpload = document.getElementById('eventpics-cover-upload');
    var btnClearMain = document.getElementById('eventpics-clear-main');
    var btnOk = document.getElementById('eventpics-modal-ok');
    var btnCancel = document.getElementById('eventpics-modal-cancel');
    var btnX = document.getElementById('eventpics-modal-x');
    var summary = document.getElementById('eventpics-form-summary');
    var sumPreview = document.getElementById('eventpics-summary-preview');
    var sumImg = document.getElementById('eventpics-summary-img');
    var sumName = document.getElementById('eventpics-summary-name');
    var sumSource = document.getElementById('eventpics-summary-source');
    var sumEmpty = document.getElementById('eventpics-summary-empty');
    var urlInp = document.getElementById('event_featured_image_url');
    var coverTrigger = document.getElementById('eventpics-summary-trigger');
    var coverLightbox = document.getElementById('event-edit-cover-lightbox');
    var coverLightboxImg = document.getElementById('event-edit-cover-lightbox-img');
    var coverLightboxClose = document.getElementById('event-edit-cover-lightbox-close');
    var coverPlaceholder = document.getElementById('eventpics-cover-placeholder');
    if (!dialog || !root || !hidden || !grid || !btnUp || !btnClear || !fileInp || !drop || !btnOpen || !btnCoverUpload || !btnOk || !btnCancel) return;

    var pendingPick = (hidden.value || '').trim();
    var directUploadApply = false;

    function setMsg(t, isErr) {
        if (!msg) return;
        msg.textContent = t || '';
        msg.classList.toggle('eventpics-msg--error', !!isErr);
    }

    function syncModalSelection() {
        var cur = (pendingPick || '').trim();
        grid.querySelectorAll('.eventpics-item').forEach(function (b) {
            b.classList.toggle('is-selected', cur !== '' && b.getAttribute('data-filename') === cur);
        });
    }

    function pickInModal(name) {
        var fn = (name || '').trim();
        if (fn && pendingPick === fn) {
            pendingPick = '';
        } else {
            pendingPick = fn;
        }
        syncModalSelection();
    }

    function summaryBase() {
        var b = (summary && summary.getAttribute('data-base')) || '';
        if (b && b.slice(-1) !== '/') {
            return b + '/';
        }
        return b;
    }

    function resolveCoverImgSrcFromUrlField(urlTrim) {
        var t = (urlTrim || '').trim();
        if (!t) return '';
        if (/^https?:\/\//i.test(t)) return t;
        if (t.indexOf('//') === 0) {
            return (window.location.protocol || 'https:') + t;
        }
        if (t.charAt(0) === '/') {
            try {
                return new URL(t, window.location.origin).href;
            } catch (e1) {
                return t;
            }
        }
        if (/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}(\/|$)/i.test(t)) {
            return 'https://' + t;
        }
        return t;
    }

    function truncateLabel(s, maxLen) {
        var t = (s || '').trim();
        if (!t) return '';
        if (t.length <= maxLen) return t;
        return t.slice(0, Math.max(0, maxLen - 1)) + '…';
    }

    function isEventpicsUrl(val) {
        var t = (val || '').trim();
        if (!t) return false;
        try {
            var path = t.indexOf('://') !== -1 ? (new URL(t, window.location.origin)).pathname : t;
            return /\/nextgen\/events\/eventpics\//i.test(path);
        } catch (e0) {
            return /\/nextgen\/events\/eventpics\//i.test(t);
        }
    }

    function syncMainSummary() {
        var urlTrim = urlInp ? (urlInp.value || '').trim() : '';
        var fn = (hidden.value || '').trim();
        if (!sumPreview || !sumImg || !sumName || !sumEmpty) return;
        var src = '';
        var nameText = '';
        var capUrl = 'Előnézet a „Kiemelt kép URL” mező alapján (elsőbbség az eventpics felett).';
        var capPic = 'Előnézet az eventpics borító alapján (nincs kitöltött URL).';
        if (urlTrim) {
            src = resolveCoverImgSrcFromUrlField(urlTrim);
            nameText = truncateLabel(urlTrim, 52);
        } else if (fn) {
            src = summaryBase() + encodeURIComponent(fn);
            nameText = fn;
        }
        if (src === '') {
            sumEmpty.hidden = false;
            sumImg.removeAttribute('src');
            sumImg.hidden = true;
            if (coverPlaceholder) coverPlaceholder.hidden = false;
            sumName.textContent = '';
            if (sumSource) sumSource.textContent = '';
            if (coverTrigger) coverTrigger.disabled = true;
            if (coverLightboxImg) {
                coverLightboxImg.removeAttribute('src');
                coverLightboxImg.alt = 'Borítókép';
            }
            return;
        }
        sumEmpty.hidden = true;
        sumImg.hidden = false;
        if (coverPlaceholder) coverPlaceholder.hidden = true;
        sumImg.src = src;
        sumName.textContent = nameText;
        if (sumSource) sumSource.textContent = urlTrim ? capUrl : capPic;
        if (coverTrigger) coverTrigger.disabled = false;
        if (coverLightboxImg) {
            coverLightboxImg.src = src;
            coverLightboxImg.alt = nameText || 'Borítókép';
        }
    }

    function openCoverLightbox() {
        if (!coverLightbox || !sumImg || !sumImg.getAttribute('src')) return;
        if (coverLightboxImg && sumImg.src) {
            coverLightboxImg.src = sumImg.src;
            coverLightboxImg.alt = sumName ? (sumName.textContent || 'Borítókép') : 'Borítókép';
        }
        if (typeof coverLightbox.showModal === 'function') {
            coverLightbox.showModal();
        } else {
            coverLightbox.setAttribute('open', 'open');
        }
        document.body.classList.add('event-edit-cover-lightbox-open');
    }

    function closeCoverLightbox() {
        if (!coverLightbox) return;
        if (typeof coverLightbox.close === 'function') {
            coverLightbox.close();
        } else {
            coverLightbox.removeAttribute('open');
        }
        document.body.classList.remove('event-edit-cover-lightbox-open');
    }

    if (coverTrigger) {
        coverTrigger.addEventListener('click', openCoverLightbox);
    }
    if (coverLightboxClose) {
        coverLightboxClose.addEventListener('click', closeCoverLightbox);
    }
    if (coverLightbox) {
        coverLightbox.addEventListener('click', function (e) {
            if (e.target === coverLightbox) closeCoverLightbox();
        });
        coverLightbox.addEventListener('close', function () {
            document.body.classList.remove('event-edit-cover-lightbox-open');
        });
        coverLightbox.addEventListener('cancel', function (e) {
            e.preventDefault();
            closeCoverLightbox();
        });
    }

    function hasThumb(filename) {
        return Array.prototype.some.call(grid.querySelectorAll('.eventpics-item'), function (b) {
            return (b.getAttribute('data-filename') || '') === filename;
        });
    }

    function addThumb(filename, imgUrl) {
        if (hasThumb(filename)) return;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'eventpics-item';
        btn.setAttribute('data-filename', filename);
        btn.title = filename;
        var chk = document.createElement('span');
        chk.className = 'eventpics-item-check';
        chk.setAttribute('aria-hidden', 'true');
        var img = document.createElement('img');
        img.src = imgUrl;
        img.alt = '';
        img.loading = 'lazy';
        img.width = 150;
        img.height = 150;
        btn.appendChild(chk);
        btn.appendChild(img);
        grid.insertBefore(btn, grid.firstChild);
        bindItem(btn);
    }

    function bindItem(btn) {
        btn.addEventListener('click', function () {
            var fn = btn.getAttribute('data-filename') || '';
            if (!fn) return;
            pickInModal(fn);
        });
    }
    grid.querySelectorAll('.eventpics-item').forEach(bindItem);

    btnClear.addEventListener('click', function () {
        pendingPick = '';
        syncModalSelection();
        setMsg('');
    });

    function applyPickToForm(filename) {
        var fn = (filename || '').trim();
        hidden.value = fn;
        pendingPick = fn;
        if (urlInp) {
            var urlTrim = (urlInp.value || '').trim();
            if (fn !== '' || isEventpicsUrl(urlTrim)) {
                urlInp.value = '';
            }
        }
        syncModalSelection();
        syncMainSummary();
    }

    btnUp.addEventListener('click', function () {
        directUploadApply = false;
        fileInp.click();
    });

    btnCoverUpload.addEventListener('click', function () {
        directUploadApply = true;
        fileInp.click();
    });

    if (filter) {
        filter.addEventListener('input', function () {
            var q = (filter.value || '').trim().toLowerCase();
            grid.querySelectorAll('.eventpics-item').forEach(function (b) {
                var fn = (b.getAttribute('data-filename') || '').toLowerCase();
                b.style.display = !q || fn.indexOf(q) !== -1 ? '' : 'none';
            });
        });
    }

    function uploadFiles(files) {
        var list = files ? Array.prototype.slice.call(files) : [];
        if (!list.length) return;
        if (list.length > 1) {
            setMsg('Egyszerre egy kép tölthető fel; az első kerül feldolgozásra.', false);
            list = [list[0]];
        }
        var url = root.getAttribute('data-upload-url') || '';
        var csrf = root.getAttribute('data-csrf') || '';
        if (!url || !csrf) {
            setMsg('Hiányzik a feltöltési beállítás.', true);
            return;
        }
        setMsg('Feltöltés…', false);
        var f = list[0];
        var fd = new FormData();
        fd.append('file', f, f.name);
        fd.append('eventpics_csrf', csrf);
        fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    directUploadApply = false;
                    setMsg((data && data.error) ? data.error : 'Feltöltés sikertelen.', true);
                    return;
                }
                addThumb(data.filename, data.thumb_url || data.url);
                pendingPick = data.filename;
                syncModalSelection();
                fileInp.value = '';
                if (filter) {
                    filter.value = '';
                    filter.dispatchEvent(new Event('input', { bubbles: true }));
                }
                if (directUploadApply) {
                    directUploadApply = false;
                    applyPickToForm(data.filename);
                    setMsg('');
                    return;
                }
                setMsg('Feltöltve. Nyomd meg a „Kiválasztás” gombot a mentéshez.', false);
            })
            .catch(function () {
                directUploadApply = false;
                setMsg('Hálózati hiba a feltöltéskor.', true);
            });
    }

    fileInp.addEventListener('change', function () {
        if (!fileInp.files || !fileInp.files.length) {
            directUploadApply = false;
            return;
        }
        uploadFiles(fileInp.files);
    });

    ;['dragenter', 'dragover'].forEach(function (ev) {
        drop.addEventListener(ev, function (e) {
            e.preventDefault();
            e.stopPropagation();
            drop.classList.add('eventpics-dropzone--active');
        });
    });
    ;['dragleave', 'drop'].forEach(function (ev) {
        drop.addEventListener(ev, function (e) {
            e.preventDefault();
            e.stopPropagation();
            drop.classList.remove('eventpics-dropzone--active');
        });
    });
    drop.addEventListener('drop', function (e) {
        var dt = e.dataTransfer;
        if (!dt || !dt.files || !dt.files.length) return;
        uploadFiles([dt.files[0]]);
    });

    function openModal() {
        pendingPick = (hidden.value || '').trim();
        syncModalSelection();
        setMsg('');
        if (filter) {
            filter.value = '';
            filter.dispatchEvent(new Event('input', { bubbles: true }));
        }
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', 'open');
        }
        try {
            filter.focus();
        } catch (e2) {}
    }

    function closeModal() {
        if (typeof dialog.close === 'function') {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
        }
    }

    btnOpen.addEventListener('click', openModal);
    if (btnX) btnX.addEventListener('click', closeModal);
    btnCancel.addEventListener('click', closeModal);
    btnOk.addEventListener('click', function () {
        applyPickToForm(pendingPick);
        closeModal();
    });
    if (btnClearMain) {
        btnClearMain.addEventListener('click', function () {
            hidden.value = '';
            pendingPick = '';
            if (urlInp && isEventpicsUrl(urlInp.value)) {
                urlInp.value = '';
            }
            syncModalSelection();
            syncMainSummary();
        });
    }

    if (urlInp) {
        urlInp.addEventListener('input', function () {
            var urlTrim = (urlInp.value || '').trim();
            if (urlTrim && !isEventpicsUrl(urlTrim)) {
                hidden.value = '';
                pendingPick = '';
                syncModalSelection();
            }
            syncMainSummary();
        });
        urlInp.addEventListener('change', syncMainSummary);
    }

    syncMainSummary();
})();
</script>
<?php require __DIR__ . '/wp_token_input_script.php'; ?>
<?php require __DIR__ . '/event_slug_script.php'; ?>
<?php require __DIR__ . '/event_allday_script.php'; ?>
<?php require __DIR__ . '/event_change_script.php'; ?>
<?php require __DIR__ . '/event_date_copy_script.php'; ?>
<?php require __DIR__ . '/event_finance_calc_script.php'; ?>
<?php require __DIR__ . '/event_form_validation_script.php'; ?>
