<?php
declare(strict_types=1);
/** @var array{today: list<array<string, mixed>>, soon: list<array<string, mixed>>, past: list<array<string, mixed>>} $listPartition */
/** @var array<int, list<array{color: string, name: string}>> $categoriesByEventId */
/** @var array<string, string> $D */

$listPartitionLabels = [
    'today' => (string) ($D['list_nav_today'] ?? 'Ma'),
    'soon' => (string) ($D['list_nav_soon'] ?? 'Hamarosan'),
    'past' => (string) ($D['list_nav_past'] ?? 'Lezajlott'),
];
$listPartitionAria = (string) ($D['list_partition_aria'] ?? 'Eseménylista szekciók');

$sections = [
    ['id' => 'events-list-today', 'key' => 'today', 'title' => $D['list_section_today'] ?? 'Ma', 'empty' => $D['list_section_today_empty'] ?? '', 'past' => false],
    ['id' => 'events-list-soon', 'key' => 'soon', 'title' => $D['list_section_soon'] ?? 'Hamarosan', 'empty' => $D['list_section_soon_empty'] ?? '', 'past' => false],
    ['id' => 'events-list-past', 'key' => 'past', 'title' => $D['list_section_past'] ?? 'Lezajlott', 'empty' => $D['list_section_past_empty'] ?? '', 'past' => true],
];

$totalCount = count($listPartition['today']) + count($listPartition['soon']) + count($listPartition['past']);
?>
<?php if ($totalCount === 0): ?>
    <p class="home-public__empty"><?= h((string) ($D['list_empty'] ?? 'Nincs találat a szűrésre.')) ?></p>
<?php else: ?>
    <?php require __DIR__ . '/events_list_partition_nav.php'; ?>
    <?php foreach ($sections as $section): ?>
        <?php
        $sectionRows = $listPartition[$section['key']] ?? [];
        $sectionClass = 'events-list-partition-section home-public__list-section';
        if ($section['past']) {
            $sectionClass .= ' events-list-partition-section--past home-public__list-section--past';
        }
        ?>
        <section class="<?= h($sectionClass) ?>" id="<?= h((string) $section['id']) ?>" aria-labelledby="<?= h((string) $section['id']) ?>-title">
            <h2 class="events-list-partition-section__title home-public__list-section-title" id="<?= h((string) $section['id']) ?>-title"><?= h((string) $section['title']) ?></h2>
            <?php
            $sectionEmpty = $section['empty'];
            require __DIR__ . '/public_event_list_cards.php';
            ?>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
