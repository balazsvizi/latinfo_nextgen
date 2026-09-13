<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/partner_blocks.php';
require_once __DIR__ . '/lib/partnerlogos.php';
require_once __DIR__ . '/lib/event_public_lang.php';
requireLogin();

$db = getDb();
$selfUrl = events_url('partnerek_szerkeszt.php');

if (!events_partner_blocks_ensure_schema($db)) {
    $mainContentClass = 'main-content main-content--fullwidth';
    $pageTitle = 'Partnereink';
    require_once dirname(__DIR__) . '/partials/header.php';
    echo '<div class="card events-admin-card">';
    echo '<p class="alert alert-error">Hiányzik az <code>events_partner_blocks</code> tábla, és automatikusan sem jött létre. Futtasd: <code>events/sql/migration_partner_blocks.sql</code></p>';
    echo '</div>';
    require_once dirname(__DIR__) . '/partials/footer.php';
    exit;
}

/**
 * Szerkesztés után ugyanahhoz a blokkhoz térünk vissza.
 */
$blockUrl = static function (int $id) use ($selfUrl): string {
    return $id > 0 ? $selfUrl . '?open=' . $id : $selfUrl;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('events_partner_blocks')) {
        flash('error', 'Lejárt vagy érvénytelen munkamenet. Töltsd újra az oldalt.');
        redirect($selfUrl);
    }

    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'create') {
        $type = events_partner_blocks_normalize_type((string) ($_POST['block_type'] ?? 'partner'));
        try {
            $newId = events_partner_blocks_create($db, $type);
            rendszer_log('partner_blokk', $newId, 'Létrehozva', events_partner_block_type_label($type));
            flash('success', 'Új ' . mb_strtolower(events_partner_block_type_label($type)) . ' blokk létrehozva. Töltsd ki, majd mentsd.');
            redirect($blockUrl($newId));
        } catch (Throwable $e) {
            error_log('partner block create: ' . $e->getMessage());
            flash('error', 'A blokk létrehozása nem sikerült.');
            redirect($selfUrl);
        }
    }

    if ($action === 'save') {
        $block = events_partner_blocks_find($db, $id);
        if ($block === null) {
            flash('error', 'A blokk nem található.');
            redirect($selfUrl);
        }

        $input = [
            'is_visible' => !empty($_POST['is_visible']),
            'title' => (string) ($_POST['title'] ?? ''),
            'title_en' => (string) ($_POST['title_en'] ?? ''),
            'heading_level' => (int) ($_POST['heading_level'] ?? 2),
            'link_url' => (string) ($_POST['link_url'] ?? ''),
            'logo_url' => (string) ($_POST['logo_url'] ?? ''),
            'body' => (string) ($_POST['body'] ?? ''),
            'body_en' => (string) ($_POST['body_en'] ?? ''),
            'note_before' => (string) ($_POST['note_before'] ?? ''),
            'note_before_en' => (string) ($_POST['note_before_en'] ?? ''),
            'note_after' => (string) ($_POST['note_after'] ?? ''),
            'note_after_en' => (string) ($_POST['note_after_en'] ?? ''),
        ];

        $currentLogo = trim((string) ($block['logo_url'] ?? ''));
        if ((string) ($block['block_type'] ?? '') === 'partner') {
            if (!empty($_POST['logo_clear'])) {
                events_partnerlogos_delete_if_local($currentLogo);
                $input['logo_url'] = '';
            } else {
                [$uploadedPath, $uploadError] = events_partnerlogos_handle_upload($_FILES['logo_file'] ?? null);
                if ($uploadError !== null) {
                    flash('error', $uploadError);
                    redirect($blockUrl($id));
                }
                if ($uploadedPath !== null) {
                    events_partnerlogos_delete_if_local($currentLogo);
                    $input['logo_url'] = $uploadedPath;
                }
            }
        }

        try {
            events_partner_blocks_save($db, $id, $input);
            rendszer_log('partner_blokk', $id, 'Mentve', trim((string) $input['title']));
            flash('success', 'A blokk mentve.');
            redirect($blockUrl($id));
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect($blockUrl($id));
        } catch (Throwable $e) {
            error_log('partner block save: ' . $e->getMessage());
            flash('error', 'A mentés nem sikerült.');
            redirect($blockUrl($id));
        }
    }

    if ($action === 'delete') {
        $block = events_partner_blocks_find($db, $id);
        if ($block === null) {
            flash('error', 'A blokk nem található.');
            redirect($selfUrl);
        }
        try {
            events_partnerlogos_delete_if_local(trim((string) ($block['logo_url'] ?? '')));
            events_partner_blocks_delete($db, $id);
            rendszer_log('partner_blokk', $id, 'Törölve', trim((string) ($block['title'] ?? '')));
            flash('success', 'A blokk törölve.');
        } catch (Throwable $e) {
            error_log('partner block delete: ' . $e->getMessage());
            flash('error', 'A törlés nem sikerült.');
        }
        redirect($selfUrl);
    }

    if ($action === 'move_up' || $action === 'move_down') {
        if (!events_partner_blocks_move($db, $id, $action === 'move_up' ? -1 : 1)) {
            flash('error', 'A blokk nem mozgatható tovább ebbe az irányba.');
        }
        redirect($blockUrl($id));
    }

    redirect($selfUrl);
}

$blocks = events_partner_blocks_all($db);
$blockCount = count($blocks);
$visibleCount = 0;
$partnerCount = 0;
foreach ($blocks as $block) {
    if ((int) ($block['is_visible'] ?? 0) === 1) {
        $visibleCount++;
    }
    if ((string) ($block['block_type'] ?? '') === 'partner') {
        $partnerCount++;
    }
}

$openRaw = (string) ($_GET['open'] ?? '');
$openId = ctype_digit($openRaw) ? (int) $openRaw : 0;
$publicUrl = events_public_partners_page_url('hu');

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Partnereink szerkesztése';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="card events-admin-card partner-blocks-admin">
    <div class="events-list-head">
        <div class="events-list-head__start">
            <h2 class="events-list-title">Partnereink</h2>
            <span class="help">
                <?= $blockCount ?> blokk · <?= $partnerCount ?> partner · <?= $visibleCount ?> látható
            </span>
        </div>
        <div class="events-list-actions">
            <a href="<?= h($publicUrl) ?>" class="btn btn-secondary btn-sm" target="_blank" rel="noopener">Előnézet</a>
            <a href="<?= h(events_url('fooldal_szerkeszt.php')) ?>" class="btn btn-secondary btn-sm">Főoldal szövegei</a>
        </div>
    </div>

    <p class="help">
        A publikus oldal a blokkok sorrendjében jelenik meg. A <strong>partner</strong> neve és logója ugyanarra az URL-re visz,
        új ablakban. A <strong>cím</strong> és a <strong>HTML blokk</strong> a partnerek között bárhol elhelyezhető.
        Az angol mezők üresen hagyhatók: ilyenkor angol nyelven is a magyar tartalom jelenik meg.
    </p>

    <form method="post" class="partner-blocks-admin__create">
        <?= csrf_input('events_partner_blocks') ?>
        <input type="hidden" name="action" value="create">
        <span class="partner-blocks-admin__create-label">Új blokk:</span>
        <?php foreach (events_partner_block_type_labels() as $typeKey => $typeLabel): ?>
            <button type="submit" name="block_type" value="<?= h($typeKey) ?>" class="btn btn-secondary btn-sm">+ <?= h($typeLabel) ?></button>
        <?php endforeach; ?>
    </form>

    <?php if ($blocks === []): ?>
        <p class="alert alert-warning">Még nincs egyetlen blokk sem. Hozz létre egy partnert vagy címet a fenti gombokkal.</p>
    <?php endif; ?>

    <div class="partner-blocks-admin__list" id="partner-blocks-list">
        <?php foreach ($blocks as $index => $block): ?>
            <?php
            $bid = (int) $block['id'];
            $type = events_partner_blocks_normalize_type((string) ($block['block_type'] ?? 'partner'));
            $isOpen = $openId === $bid;
            $isVisible = (int) ($block['is_visible'] ?? 0) === 1;
            $title = trim((string) ($block['title'] ?? ''));
            $titleEn = trim((string) ($block['title_en'] ?? ''));
            $logoUrl = trim((string) ($block['logo_url'] ?? ''));
            $linkUrl = trim((string) ($block['link_url'] ?? ''));
            $bodyHu = (string) ($block['body'] ?? '');
            $bodyEn = (string) ($block['body_en'] ?? '');
            $summary = match ($type) {
                'html' => mb_substr(trim(strip_tags($bodyHu !== '' ? $bodyHu : $bodyEn)), 0, 90),
                default => $title,
            };
            if (trim($summary) === '') {
                $summary = '(üres)';
            }
            ?>
            <article class="partner-blocks-admin__item partner-blocks-admin__item--<?= h($type) ?><?= $isVisible ? '' : ' is-hidden-block' ?>" id="partner-block-row-<?= $bid ?>">
                <header class="partner-blocks-admin__bar">
                    <span class="partner-blocks-admin__badge partner-blocks-admin__badge--<?= h($type) ?>"><?= h(events_partner_block_type_label($type)) ?></span>
                    <span class="partner-blocks-admin__summary"><?= h($summary) ?></span>
                    <?php if (!$isVisible): ?>
                        <span class="partner-blocks-admin__flag">Rejtett</span>
                    <?php endif; ?>
                    <div class="partner-blocks-admin__bar-actions">
                        <form method="post" class="partner-blocks-admin__mini-form">
                            <?= csrf_input('events_partner_blocks') ?>
                            <input type="hidden" name="action" value="move_up">
                            <input type="hidden" name="id" value="<?= $bid ?>">
                            <button type="submit" class="btn btn-secondary btn-sm partner-blocks-admin__move" title="Feljebb" aria-label="Feljebb" <?= $index === 0 ? 'disabled' : '' ?>>↑</button>
                        </form>
                        <form method="post" class="partner-blocks-admin__mini-form">
                            <?= csrf_input('events_partner_blocks') ?>
                            <input type="hidden" name="action" value="move_down">
                            <input type="hidden" name="id" value="<?= $bid ?>">
                            <button type="submit" class="btn btn-secondary btn-sm partner-blocks-admin__move" title="Lejjebb" aria-label="Lejjebb" <?= $index === $blockCount - 1 ? 'disabled' : '' ?>>↓</button>
                        </form>
                        <button type="button" class="btn btn-secondary btn-sm" data-block-toggle="<?= $bid ?>" aria-expanded="<?= $isOpen ? 'true' : 'false' ?>" aria-controls="partner-block-<?= $bid ?>">Szerkesztés</button>
                    </div>
                </header>

                <div class="partner-blocks-admin__body" id="partner-block-<?= $bid ?>" <?= $isOpen ? '' : 'hidden' ?>>
                    <form method="post" enctype="multipart/form-data" class="events-admin-form">
                        <?= csrf_input('events_partner_blocks') ?>
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" value="<?= $bid ?>">

                        <?php if ($type === 'partner'): ?>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="title_<?= $bid ?>">Partner neve *</label>
                                    <input type="text" id="title_<?= $bid ?>" name="title" maxlength="255" required value="<?= h($title) ?>">
                                </div>
                                <div class="form-group">
                                    <label for="title_en_<?= $bid ?>">Partner neve (EN)</label>
                                    <input type="text" id="title_en_<?= $bid ?>" name="title_en" maxlength="255" value="<?= h($titleEn) ?>" placeholder="Üresen a magyar név jelenik meg">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="link_url_<?= $bid ?>">Link (a név és a logó is ide visz, új ablakban)</label>
                                <input type="url" id="link_url_<?= $bid ?>" name="link_url" maxlength="500" value="<?= h($linkUrl) ?>" placeholder="https://partner.hu">
                            </div>

                            <fieldset class="partner-blocks-admin__logo">
                                <legend>Logó</legend>
                                <div class="partner-blocks-admin__logo-grid">
                                    <div class="partner-blocks-admin__logo-preview">
                                        <?php if ($logoUrl !== ''): ?>
                                            <img src="<?= h($logoUrl) ?>" alt="<?= h($title) ?> logó" loading="lazy" decoding="async">
                                        <?php else: ?>
                                            <span class="partner-blocks-admin__logo-empty">Nincs logó</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="partner-blocks-admin__logo-fields">
                                        <div class="form-group">
                                            <label for="logo_file_<?= $bid ?>">Új logó feltöltése</label>
                                            <input type="file" id="logo_file_<?= $bid ?>" name="logo_file" accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml">
                                            <p class="help">JPG, PNG, WEBP, GIF vagy SVG, legfeljebb 4 MB.</p>
                                        </div>
                                        <div class="form-group">
                                            <label for="logo_url_<?= $bid ?>">vagy logó URL</label>
                                            <input type="text" id="logo_url_<?= $bid ?>" name="logo_url" maxlength="500" value="<?= h($logoUrl) ?>" placeholder="https://... vagy /nextgen/events/partnerlogos/...">
                                        </div>
                                        <?php if ($logoUrl !== ''): ?>
                                            <label class="partner-blocks-admin__check">
                                                <input type="checkbox" name="logo_clear" value="1">
                                                Logó törlése
                                            </label>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </fieldset>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="note_before_<?= $bid ?>">Megjegyzés a partner előtt (HTML)</label>
                                    <textarea id="note_before_<?= $bid ?>" name="note_before" rows="4" class="partner-blocks-admin__html"><?= h((string) ($block['note_before'] ?? '')) ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="note_before_en_<?= $bid ?>">Megjegyzés előtt (EN)</label>
                                    <textarea id="note_before_en_<?= $bid ?>" name="note_before_en" rows="4" class="partner-blocks-admin__html"><?= h((string) ($block['note_before_en'] ?? '')) ?></textarea>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="note_after_<?= $bid ?>">Megjegyzés a partner után (HTML)</label>
                                    <textarea id="note_after_<?= $bid ?>" name="note_after" rows="4" class="partner-blocks-admin__html"><?= h((string) ($block['note_after'] ?? '')) ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="note_after_en_<?= $bid ?>">Megjegyzés után (EN)</label>
                                    <textarea id="note_after_en_<?= $bid ?>" name="note_after_en" rows="4" class="partner-blocks-admin__html"><?= h((string) ($block['note_after_en'] ?? '')) ?></textarea>
                                </div>
                            </div>
                        <?php elseif ($type === 'heading'): ?>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="title_<?= $bid ?>">Cím *</label>
                                    <input type="text" id="title_<?= $bid ?>" name="title" maxlength="255" required value="<?= h($title) ?>">
                                </div>
                                <div class="form-group">
                                    <label for="title_en_<?= $bid ?>">Cím (EN)</label>
                                    <input type="text" id="title_en_<?= $bid ?>" name="title_en" maxlength="255" value="<?= h($titleEn) ?>" placeholder="Üresen a magyar cím jelenik meg">
                                </div>
                                <div class="form-group">
                                    <label for="heading_level_<?= $bid ?>">Szint</label>
                                    <?php $level = (int) ($block['heading_level'] ?? 2); ?>
                                    <select id="heading_level_<?= $bid ?>" name="heading_level">
                                        <option value="2"<?= $level === 2 ? ' selected' : '' ?>>Főcím (H2)</option>
                                        <option value="3"<?= $level === 3 ? ' selected' : '' ?>>Alcím (H3)</option>
                                    </select>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="form-group">
                                <label for="body_<?= $bid ?>">HTML tartalom</label>
                                <textarea id="body_<?= $bid ?>" name="body" rows="6" class="partner-blocks-admin__html"><?= h($bodyHu) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="body_en_<?= $bid ?>">HTML tartalom (EN)</label>
                                <textarea id="body_en_<?= $bid ?>" name="body_en" rows="6" class="partner-blocks-admin__html" placeholder="Üresen a magyar tartalom jelenik meg"><?= h($bodyEn) ?></textarea>
                            </div>
                        <?php endif; ?>

                        <label class="partner-blocks-admin__check">
                            <input type="checkbox" name="is_visible" value="1"<?= $isVisible ? ' checked' : '' ?>>
                            Megjelenik a publikus oldalon
                        </label>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Mentés</button>
                        </div>
                    </form>

                    <form method="post" class="partner-blocks-admin__delete" onsubmit="return confirm('Biztosan törlöd ezt a blokkot?');">
                        <?= csrf_input('events_partner_blocks') ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $bid ?>">
                        <button type="submit" class="btn btn-secondary btn-sm">Blokk törlése</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</div>

<script>
(function () {
    var list = document.getElementById('partner-blocks-list');
    if (!list) return;

    list.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('[data-block-toggle]') : null;
        if (!btn || !list.contains(btn)) return;
        var body = document.getElementById('partner-block-' + btn.getAttribute('data-block-toggle'));
        if (!body) return;
        var open = body.hidden;
        body.hidden = !open;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
})();
</script>

<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
