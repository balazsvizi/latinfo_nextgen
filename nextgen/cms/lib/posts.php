<?php
declare(strict_types=1);

/**
 * CMS cikk CRUD segédek, címke kapcsolatok (events_tags), slug, másolás.
 */

require_once __DIR__ . '/status.php';

function cms_post_slugify(string $title): string
{
    $lower = mb_strtolower(trim($title), 'UTF-8');
    $map = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o', 'ő' => 'o',
        'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
    ];
    $s = strtr($lower, $map);
    $s = preg_replace('/[^a-z0-9]+/u', '-', (string) $s);
    $s = trim((string) $s, '-');

    return $s !== '' ? $s : 'cikk';
}

function cms_post_slug_exists(PDO $db, string $slug, ?int $excludeId): bool
{
    $sql = 'SELECT 1 FROM `cms_posts` WHERE `slug` = ?';
    $params = [$slug];
    if ($excludeId !== null) {
        $sql .= ' AND `id` != ?';
        $params[] = $excludeId;
    }
    $st = $db->prepare($sql . ' LIMIT 1');
    $st->execute($params);

    return (bool) $st->fetchColumn();
}

function cms_ensure_unique_post_slug(PDO $db, string $base, ?int $excludeId): string
{
    $slug = $base;
    $n = 2;
    while (cms_post_slug_exists($db, $slug, $excludeId)) {
        $slug = $base . '-' . $n;
        $n++;
        if ($n > 500) {
            $slug = $base . '-' . bin2hex(random_bytes(4));
            break;
        }
    }

    return $slug;
}

/**
 * @return array<string, mixed>
 */
function cms_post_defaults(): array
{
    return [
        'theme_id' => null,
        'title' => '',
        'slug' => '',
        'excerpt' => '',
        'content_html' => '',
        'status' => cms_status_draft(),
        'published_at' => null,
        'featured_image_url' => null,
        'seo_title' => null,
        'seo_description' => null,
        'tag_ids' => [],
    ];
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function cms_post_for_form(array $row): array
{
    $d = cms_post_defaults();
    foreach ($d as $k => $v) {
        if (array_key_exists($k, $row)) {
            $d[$k] = $row[$k];
        }
    }
    $d['theme_id'] = isset($row['theme_id']) && $row['theme_id'] !== null && (int) $row['theme_id'] > 0
        ? (int) $row['theme_id']
        : null;
    $d['status'] = cms_normalize_status((string) ($d['status'] ?? ''));
    $d['tag_ids'] = array_values(array_map('intval', is_array($d['tag_ids'] ?? null) ? $d['tag_ids'] : []));

    return $d;
}

/**
 * @return list<int>
 */
function cms_tag_ids_from_post(): array
{
    $raw = $_POST['tag_ids'] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    $ids = [];
    foreach ($raw as $v) {
        $i = (int) $v;
        if ($i > 0 && !in_array($i, $ids, true)) {
            $ids[] = $i;
        }
    }

    return $ids;
}

/**
 * @return list<int>
 */
function cms_load_post_tag_ids(PDO $db, int $postId): array
{
    if ($postId <= 0) {
        return [];
    }
    try {
        $st = $db->prepare('SELECT `tag_id` FROM `cms_post_tags` WHERE `post_id` = ? ORDER BY `tag_id` ASC');
        $st->execute([$postId]);
        $ids = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $tid = (int) ($row['tag_id'] ?? 0);
            if ($tid > 0) {
                $ids[] = $tid;
            }
        }

        return $ids;
    } catch (Throwable $e) {
        error_log('cms_load_post_tag_ids: ' . $e->getMessage());

        return [];
    }
}

/**
 * @param list<int> $tagIds
 */
function cms_save_post_tags(PDO $db, int $postId, array $tagIds): void
{
    $db->prepare('DELETE FROM `cms_post_tags` WHERE `post_id` = ?')->execute([$postId]);
    if ($tagIds === []) {
        return;
    }

    $valid = [];
    if (function_exists('events_tags_tables_available') && events_tags_tables_available($db)) {
        $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
        $st = $db->prepare("SELECT `id` FROM `events_tags` WHERE `id` IN ($placeholders)");
        $st->execute($tagIds);
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $valid[] = (int) $row['id'];
        }
    } else {
        $valid = $tagIds;
    }

    if ($valid === []) {
        return;
    }
    $ins = $db->prepare('INSERT INTO `cms_post_tags` (`post_id`, `tag_id`) VALUES (?, ?)');
    foreach ($valid as $tid) {
        $ins->execute([$postId, $tid]);
    }
}

/**
 * @return array{0: array<string, mixed>, 1: ?string, 2: list<int>} [row, error, tagIds]
 */
function cms_post_from_request(PDO $db, array $defaults, ?int $excludeId): array
{
    $title = trim((string) ($_POST['title'] ?? ''));
    $slugRaw = trim((string) ($_POST['slug'] ?? ''));
    $excerpt = trim((string) ($_POST['excerpt'] ?? ''));
    $content = (string) ($_POST['content_html'] ?? '');
    $status = cms_normalize_status((string) ($_POST['status'] ?? cms_status_draft()));
    $themeId = (int) ($_POST['theme_id'] ?? 0);
    $themeId = $themeId > 0 ? $themeId : null;
    $featured = trim((string) ($_POST['featured_image_url'] ?? ''));
    $seoTitle = trim((string) ($_POST['seo_title'] ?? ''));
    $seoDesc = trim((string) ($_POST['seo_description'] ?? ''));
    $tagIds = cms_tag_ids_from_post();

    if (function_exists('events_sanitize_html_fragment')) {
        $content = events_sanitize_html_fragment($content);
    }

    $row = cms_post_for_form($defaults);
    $row['title'] = $title;
    $row['excerpt'] = $excerpt !== '' ? $excerpt : null;
    $row['content_html'] = $content;
    $row['status'] = $status;
    $row['theme_id'] = $themeId;
    $row['seo_title'] = $seoTitle !== '' ? $seoTitle : null;
    $row['seo_description'] = $seoDesc !== '' ? $seoDesc : null;
    $row['tag_ids'] = $tagIds;

    [$featPickPath, $featPickErr] = cms_uploads_normalize_selected((string) ($_POST['featured_image_pick'] ?? ''));
    if ($featPickErr !== null) {
        $row['featured_image_url'] = $featured !== '' ? $featured : null;

        return [$row, $featPickErr, $tagIds];
    }
    // Külső / kézi URL elsőbbség, ha nem CMS uploads path; különben a galéria pick.
    $featFromCmsUrl = cms_uploads_extract_selected_from_featured($featured);
    if ($featured !== '' && $featFromCmsUrl === '') {
        $row['featured_image_url'] = $featured;
    } elseif ($featPickPath !== null) {
        $row['featured_image_url'] = $featPickPath;
    } elseif ($featured !== '') {
        $row['featured_image_url'] = $featured;
    } else {
        $row['featured_image_url'] = null;
    }

    if ($title === '') {
        return [$row, 'A cím kötelező.', $tagIds];
    }

    $baseSlug = $slugRaw !== '' ? cms_post_slugify($slugRaw) : cms_post_slugify($title);
    $row['slug'] = cms_ensure_unique_post_slug($db, $baseSlug, $excludeId);

    if ($themeId !== null) {
        $chk = $db->prepare('SELECT 1 FROM `cms_themes` WHERE `id` = ? LIMIT 1');
        $chk->execute([$themeId]);
        if (!(bool) $chk->fetchColumn()) {
            return [$row, 'A választott téma nem található.', $tagIds];
        }
    }

    $publishedAt = $defaults['published_at'] ?? null;
    if ($status === cms_status_publish()) {
        if ($publishedAt === null || trim((string) $publishedAt) === '') {
            $publishedAt = date('Y-m-d H:i:s');
        }
    }
    $row['published_at'] = $publishedAt;

    return [$row, null, $tagIds];
}

/**
 * @return array<string, mixed>|null
 */
function cms_load_post(PDO $db, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $st = $db->prepare('SELECT * FROM `cms_posts` WHERE `id` = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

/**
 * @return array<string, mixed>|null
 */
function cms_load_post_by_slug(PDO $db, string $slug): ?array
{
    $slug = trim($slug);
    if ($slug === '') {
        return null;
    }
    $st = $db->prepare('SELECT * FROM `cms_posts` WHERE `slug` = ? LIMIT 1');
    $st->execute([$slug]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

/**
 * Másolási sablon: draft státusz, új slug, cím „(másolat)”.
 *
 * @return array<string, mixed>|null
 */
function cms_load_post_copy_template(PDO $db, int $sourceId): ?array
{
    $src = cms_load_post($db, $sourceId);
    if ($src === null) {
        return null;
    }
    $form = cms_post_for_form($src);
    $form['title'] = rtrim((string) $form['title']) . ' (másolat)';
    $form['slug'] = '';
    $form['status'] = cms_status_draft();
    $form['published_at'] = null;
    $form['tag_ids'] = cms_load_post_tag_ids($db, $sourceId);

    return $form;
}

/**
 * @return array{0: string, 1: list<mixed>} [whereSql, params]
 */
function cms_posts_list_where(array $filters): array
{
    $where = ['1=1'];
    $params = [];

    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $where[] = '(`p`.`title` LIKE ? OR `p`.`slug` LIKE ? OR `p`.`excerpt` LIKE ?)';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $status = trim((string) ($filters['status'] ?? ''));
    if ($status !== '' && $status !== 'all' && in_array($status, cms_allowed_statuses(), true)) {
        $where[] = '`p`.`status` = ?';
        $params[] = $status;
    }

    $themeId = (int) ($filters['theme_id'] ?? 0);
    if ($themeId > 0) {
        $where[] = '`p`.`theme_id` = ?';
        $params[] = $themeId;
    }

    $tagId = (int) ($filters['tag_id'] ?? 0);
    if ($tagId > 0) {
        $where[] = 'EXISTS (SELECT 1 FROM `cms_post_tags` `cpt` WHERE `cpt`.`post_id` = `p`.`id` AND `cpt`.`tag_id` = ?)';
        $params[] = $tagId;
    }

    return [implode(' AND ', $where), $params];
}

/**
 * @return list<array<string, mixed>>
 */
function cms_posts_list(PDO $db, array $filters = [], int $limit = 100, int $offset = 0): array
{
    [$whereSql, $params] = cms_posts_list_where($filters);
    $limit = max(1, min(500, $limit));
    $offset = max(0, $offset);
    $sql = "
        SELECT `p`.*, `t`.`name` AS `theme_name`
        FROM `cms_posts` `p`
        LEFT JOIN `cms_themes` `t` ON `t`.`id` = `p`.`theme_id`
        WHERE $whereSql
        ORDER BY `p`.`updated_at` DESC, `p`.`id` DESC
        LIMIT $limit OFFSET $offset
    ";
    $st = $db->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    return is_array($rows) ? $rows : [];
}

function cms_posts_count(PDO $db, array $filters = []): int
{
    [$whereSql, $params] = cms_posts_list_where($filters);
    $st = $db->prepare("SELECT COUNT(*) FROM `cms_posts` `p` WHERE $whereSql");
    $st->execute($params);

    return (int) $st->fetchColumn();
}

/**
 * @return array{total:int, draft:int, publish:int, other:int}
 */
function cms_posts_status_counts(PDO $db): array
{
    $out = ['total' => 0, 'draft' => 0, 'publish' => 0, 'other' => 0];
    try {
        $st = $db->query('SELECT `status`, COUNT(*) AS cnt FROM `cms_posts` GROUP BY `status`');
        if ($st === false) {
            return $out;
        }
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $status = cms_normalize_status((string) ($row['status'] ?? ''));
            $cnt = (int) ($row['cnt'] ?? 0);
            $out['total'] += $cnt;
            if (isset($out[$status])) {
                $out[$status] += $cnt;
            }
        }
    } catch (Throwable $e) {
        error_log('cms_posts_status_counts: ' . $e->getMessage());
    }

    return $out;
}

/**
 * @return array<int, int> tag_id => cms post count
 */
function cms_tag_post_count_map(PDO $db): array
{
    $map = [];
    try {
        $st = $db->query('
            SELECT `tag_id`, COUNT(*) AS cnt
            FROM `cms_post_tags`
            GROUP BY `tag_id`
        ');
        if ($st === false) {
            return [];
        }
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $tid = (int) ($row['tag_id'] ?? 0);
            if ($tid > 0) {
                $map[$tid] = (int) ($row['cnt'] ?? 0);
            }
        }
    } catch (Throwable $e) {
        // tábla még nincs
    }

    return $map;
}

function cms_build_log_details(array $row, array $tagIds = []): string
{
    $parts = [
        'Cím: ' . (string) ($row['title'] ?? ''),
        'Slug: ' . (string) ($row['slug'] ?? ''),
        'Státusz: ' . cms_status_label((string) ($row['status'] ?? '')),
    ];
    if (!empty($row['theme_id'])) {
        $parts[] = 'Téma ID: ' . (int) $row['theme_id'];
    }
    if ($tagIds !== []) {
        $parts[] = 'Címkék: ' . implode(', ', $tagIds);
    }

    return implode("\n", $parts);
}
