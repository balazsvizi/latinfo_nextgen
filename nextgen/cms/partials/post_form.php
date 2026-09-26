<?php
declare(strict_types=1);

/**
 * @var array<string, mixed> $p
 * @var array<int, string> $themes
 * @var list<array{id:int,name:string}> $tagOptions
 * @var string $formAction
 * @var string $csrfScope
 * @var bool $isCopy
 * @var int $copyFromId
 * @var string|null $hiba
 */
$isCopy = !empty($isCopy);
$copyFromId = (int) ($copyFromId ?? 0);
$selectedTags = array_values(array_map('intval', is_array($p['tag_ids'] ?? null) ? $p['tag_ids'] : []));
$themeId = isset($p['theme_id']) && $p['theme_id'] !== null ? (int) $p['theme_id'] : 0;
?>
<div class="card">
    <h2><?= h($pageTitle ?? 'CMS cikk') ?></h2>
    <form method="post" action="<?= h($formAction) ?>" class="cms-post-form" id="cms-edit-form">
        <?= csrf_input($csrfScope) ?>
        <?php if ($isCopy): ?>
            <input type="hidden" name="is_copy" value="1">
            <input type="hidden" name="copy_from_id" value="<?= $copyFromId ?>">
        <?php endif; ?>

        <div class="form-group">
            <label for="cms-title">Cím *</label>
            <input type="text" id="cms-title" name="title" required maxlength="255" value="<?= h((string) ($p['title'] ?? '')) ?>">
        </div>

        <div class="form-row form-row-2">
            <div class="form-group">
                <label for="cms-slug">Slug</label>
                <input type="text" id="cms-slug" name="slug" maxlength="200" value="<?= h((string) ($p['slug'] ?? '')) ?>" placeholder="üresen: címből generálódik">
                <p class="help">URL-azonosító. Üresen a címből készül.</p>
            </div>
            <div class="form-group">
                <label for="cms-status">Státusz</label>
                <select id="cms-status" name="status">
                    <?php foreach (cms_allowed_statuses() as $st): ?>
                    <option value="<?= h($st) ?>"<?= (string) ($p['status'] ?? '') === $st ? ' selected' : '' ?>><?= h(cms_status_label($st)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="cms-theme">Téma</label>
            <select id="cms-theme" name="theme_id">
                <option value="0">— nincs —</option>
                <?php foreach ($themes as $tid => $tname): ?>
                <option value="<?= (int) $tid ?>"<?= $themeId === (int) $tid ? ' selected' : '' ?>><?= h($tname) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="help"><a href="<?= h(cms_url('temak.php')) ?>" target="_blank" rel="noopener">Témák kezelése</a></p>
        </div>

        <div class="form-group">
            <label for="cms-excerpt">Rövid leírás</label>
            <textarea id="cms-excerpt" name="excerpt" rows="3"><?= h((string) ($p['excerpt'] ?? '')) ?></textarea>
        </div>

        <div class="form-group cms-post-form__content">
            <label for="cms-content">Szöveg (HTML)</label>
            <textarea class="js-tinymce" id="cms-content" name="content_html" rows="16"><?= h((string) ($p['content_html'] ?? '')) ?></textarea>
            <p class="help">A szövegbe ágyazott képek a CMS képtárba kerülnek (TinyMCE feltöltés).</p>
        </div>

        <?php require __DIR__ . '/featured_image.php'; ?>

        <div class="form-group">
        <?php
        $wpTokenId = 'cms-tags';
        $wpTokenLabel = 'Címkék';
        $wpTokenFieldName = 'tag_ids[]';
        $wpTokenPlaceholder = 'Címke hozzáadása…';
        $wpTokenHelp = 'Közös címkék az Event Adminnal (events_tags).';
        $wpTokenManageUrl = nextgen_url('events/tags.php');
        $wpTokenManageLabel = 'Címkék kezelése';
        $wpTokenManageNewTab = true;
        $wpTokenAll = $tagOptions;
        $wpTokenSelected = $selectedTags;
        $wpTokenAllowCreate = false;
        $wpTokenShowPopular = true;
        require dirname(__DIR__) . '/../events/partials/wp_token_field.php';
        ?>
        </div>

        <fieldset class="form-fieldset">
            <legend>SEO (opcionális)</legend>
            <div class="form-group">
                <label for="cms-seo-title">SEO cím</label>
                <input type="text" id="cms-seo-title" name="seo_title" maxlength="255" value="<?= h((string) ($p['seo_title'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label for="cms-seo-desc">SEO leírás</label>
                <textarea id="cms-seo-desc" name="seo_description" rows="2" maxlength="500"><?= h((string) ($p['seo_description'] ?? '')) ?></textarea>
            </div>
        </fieldset>

        <div class="form-actions">
            <button type="submit" name="form_action" value="save" class="btn btn-primary">Mentés</button>
            <a href="<?= h(cms_url('posts.php')) ?>" class="btn btn-secondary">Mégsem</a>
        </div>
    </form>
</div>
