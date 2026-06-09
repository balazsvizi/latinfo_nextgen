<?php
declare(strict_types=1);
/**
 * @var array{today: list<mixed>, soon: list<mixed>, past: list<mixed>} $listPartition
 * @var array{today: string, soon: string, past: string} $listPartitionLabels
 * @var string $listPartitionAria
 */
$listPartitionAria = $listPartitionAria ?? 'Eseménylista szekciók';
?>
<nav class="events-list-partition-nav" aria-label="<?= h($listPartitionAria) ?>">
    <a class="events-list-partition-nav__link" href="#events-list-today"><?= h($listPartitionLabels['today']) ?> (<?= count($listPartition['today']) ?>)</a>
    <span class="events-list-partition-nav__sep" aria-hidden="true">|</span>
    <a class="events-list-partition-nav__link" href="#events-list-soon"><?= h($listPartitionLabels['soon']) ?> (<?= count($listPartition['soon']) ?>)</a>
    <span class="events-list-partition-nav__sep" aria-hidden="true">|</span>
    <a class="events-list-partition-nav__link" href="#events-list-past"><?= h($listPartitionLabels['past']) ?> (<?= count($listPartition['past']) ?>)</a>
</nav>
