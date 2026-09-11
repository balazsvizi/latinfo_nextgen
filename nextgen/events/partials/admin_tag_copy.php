<?php
declare(strict_types=1);

/**
 * Címke ellenőrző — miből → mi legyen.
 *
 * @var list<array{id:int,name:string}> $tagCopyPickerAll
 * @var int $tagCopyFromId
 * @var int $tagCopyToId
 * @var string|null $tagCopyCheckError
 * @var array{
 *     from: array{id:int,name:string},
 *     to: array{id:int,name:string},
 *     source_count: int,
 *     already_count: int,
 *     pending_count: int,
 *     pending_events: list<array<string,mixed>>,
 *     pending_listed: int
 * }|null $tagCopyPreview
 */
$tagCopyPickerAll = $tagCopyPickerAll ?? [];
$tagCopyFromId = (int) ($tagCopyFromId ?? 0);
$tagCopyToId = (int) ($tagCopyToId ?? 0);
$tagCopyCheckError = isset($tagCopyCheckError) && is_string($tagCopyCheckError) ? $tagCopyCheckError : null;
$tagCopyPreview = $tagCopyPreview ?? null;

if ($tagCopyPickerAll === []) {
    return;
}

$listLimitHidden = trim((string) ($_GET['list_limit'] ?? ''));
?>
<section class="events-tags-copy" aria-labelledby="events-tags-copy-title">
    <h3 class="events-tags-copy__title" id="events-tags-copy-title">Címke ellenőrző</h3>
    <p class="help events-tags-copy__lead">
        Válassz ki két címkét: <strong>miből</strong> (ami az eseményen már szerepel) és <strong>mi legyen</strong> (amit beírunk).
        A célcímke csak oda kerül, ahol még nincs. A beírásnál opcionálisan a forráscímke is levételre kerülhet.
    </p>

    <form method="get" action="<?= h(events_url('tags.php')) ?>" class="events-tags-copy__form" id="events-tags-copy-form">
        <?php if ($listLimitHidden !== ''): ?>
            <input type="hidden" name="list_limit" value="<?= h($listLimitHidden) ?>">
        <?php endif; ?>
        <div class="events-tags-copy__pickers">
            <?php
            $wpTokenId = 'tag-copy-from';
            $wpTokenLabel = 'Miből';
            $wpTokenFieldName = 'from_tag';
            $wpTokenPlaceholder = 'Forráscímke keresése…';
            $wpTokenHelp = '';
            $wpTokenManageUrl = null;
            $wpTokenManageLabel = '';
            $wpTokenAll = $tagCopyPickerAll;
            $wpTokenSelected = $tagCopyFromId > 0 ? [$tagCopyFromId] : [];
            $wpTokenAllowCreate = false;
            $wpTokenEntityType = '';
            $wpTokenSingle = true;
            $wpTokenShowPopular = false;
            $wpTokenChipLinkPattern = null;
            require __DIR__ . '/wp_token_field.php';

            $wpTokenId = 'tag-copy-to';
            $wpTokenLabel = 'Mi legyen';
            $wpTokenFieldName = 'to_tag';
            $wpTokenPlaceholder = 'Célcímke keresése…';
            $wpTokenHelp = '';
            $wpTokenManageUrl = null;
            $wpTokenManageLabel = '';
            $wpTokenAll = $tagCopyPickerAll;
            $wpTokenSelected = $tagCopyToId > 0 ? [$tagCopyToId] : [];
            $wpTokenAllowCreate = false;
            $wpTokenEntityType = '';
            $wpTokenSingle = true;
            $wpTokenShowPopular = false;
            $wpTokenChipLinkPattern = null;
            require __DIR__ . '/wp_token_field.php';
            ?>
        </div>
        <div class="events-tags-copy__actions">
            <button type="submit" class="btn btn-secondary">Ellenőrzés</button>
        </div>
    </form>

    <?php if ($tagCopyCheckError !== null && $tagCopyCheckError !== ''): ?>
        <p class="alert alert-error events-tags-copy__alert"><?= h($tagCopyCheckError) ?></p>
    <?php endif; ?>

    <?php if (is_array($tagCopyPreview)): ?>
        <?php
        $fromName = (string) $tagCopyPreview['from']['name'];
        $toName = (string) $tagCopyPreview['to']['name'];
        $fromId = (int) $tagCopyPreview['from']['id'];
        $toId = (int) $tagCopyPreview['to']['id'];
        $sourceCount = (int) $tagCopyPreview['source_count'];
        $alreadyCount = (int) $tagCopyPreview['already_count'];
        $pendingCount = (int) $tagCopyPreview['pending_count'];
        $pendingListed = (int) $tagCopyPreview['pending_listed'];
        $pendingEvents = $tagCopyPreview['pending_events'];
        $eventsWithFromUrl = events_url('events_admin.php') . '?' . http_build_query(['f_tag' => $fromId], '', '&', PHP_QUERY_RFC3986);
        ?>
        <div class="events-tags-copy__result">
            <p class="events-tags-copy__summary">
                A <strong><?= h($fromName) ?></strong> címke
                <a href="<?= h($eventsWithFromUrl) ?>"><?= (int) $sourceCount ?> eseményen</a> szerepel.
                Ebből <?= (int) $alreadyCount ?> eseményen már ott van a <strong><?= h($toName) ?></strong>.
                <?php if ($pendingCount > 0): ?>
                    <strong><?= (int) $pendingCount ?> eseményre</strong> írható be.
                <?php elseif ($sourceCount > 0): ?>
                    A célcímke minden érintett eseményen megvan; a forráscímke opcionálisan törölhető róluk.
                <?php else: ?>
                    Nincs teendő: a forráscímke egy eseményen sem szerepel.
                <?php endif; ?>
            </p>

            <?php if ($sourceCount > 0): ?>
                <form
                    method="post"
                    action="<?= h(events_url('tags.php')) ?>"
                    class="events-tags-copy__apply"
                    id="events-tags-copy-apply"
                    data-pending="<?= (int) $pendingCount ?>"
                    data-source="<?= (int) $sourceCount ?>"
                    data-from="<?= h($fromName) ?>"
                    data-to="<?= h($toName) ?>"
                >
                    <?= csrf_input('events_tags') ?>
                    <input type="hidden" name="action" value="apply_tag_copy">
                    <input type="hidden" name="from_tag" value="<?= $fromId ?>">
                    <input type="hidden" name="to_tag" value="<?= $toId ?>">
                    <?php if ($listLimitHidden !== ''): ?>
                        <input type="hidden" name="list_limit" value="<?= h($listLimitHidden) ?>">
                    <?php endif; ?>
                    <div class="events-tags-copy__apply-opts">
                        <label class="events-toggle" for="remove_source_tag">
                            <input type="checkbox" name="remove_source_tag" value="1" id="remove_source_tag" class="events-toggle__input">
                            <span class="events-toggle__ui" aria-hidden="true"></span>
                            <span class="events-toggle__label">Forráscímke törlése az eseményekről</span>
                        </label>
                        <p class="help events-tags-copy__apply-hint">
                            Ha be van kapcsolva, a <strong><?= h($fromName) ?></strong> címke lekerül az érintett eseményekről, miután a <strong><?= h($toName) ?></strong> rajtuk van. Maga a címke nem törlődik a listából.
                        </p>
                    </div>
                    <button type="submit" class="btn btn-primary" id="events-tags-copy-apply-btn">
                        <?php if ($pendingCount > 0): ?>
                            Beírás <?= (int) $pendingCount ?> eseményre
                        <?php else: ?>
                            Alkalmazás
                        <?php endif; ?>
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($pendingEvents !== []): ?>
                <div class="table-wrap events-tags-copy__table-wrap">
                    <table class="events-admin-table events-tags-copy__table">
                        <caption class="events-tags-copy__caption">
                            Események, ahová beíródna a <?= h($toName) ?>
                            <?php if ($pendingCount > $pendingListed): ?>
                                (első <?= (int) $pendingListed ?> / <?= (int) $pendingCount ?>)
                            <?php endif; ?>
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">Esemény</th>
                                <th scope="col">Időpont</th>
                                <th scope="col">Státusz</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingEvents as $ev): ?>
                                <?php $eid = (int) ($ev['id'] ?? 0); ?>
                                <tr>
                                    <td>
                                        <?php if ($eid > 0): ?>
                                            <a href="<?= h(events_url('szerkeszt.php?id=') . $eid) ?>"><?= h((string) ($ev['event_name'] ?? '')) ?></a>
                                        <?php else: ?>
                                            <?= h((string) ($ev['event_name'] ?? '')) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= h(events_admin_format_datum_cell($ev)) ?></td>
                                    <td><?= h(events_post_status_label((string) ($ev['event_status'] ?? ''))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
