<?php
declare(strict_types=1);
/** @var list<array<string, mixed>> $sablonLogok */
?>
<div class="events-edit-panel events-edit-log">
    <h2 class="events-edit-panel__title">Napló</h2>
    <div class="log-list">
        <?php foreach ($sablonLogok as $l): ?>
        <div class="log-item">
            <span class="log-date"><?= h((string) ($l['létrehozva'] ?? '')) ?><?= !empty($l['admin_név']) ? ' (' . h((string) $l['admin_név']) . ')' : '' ?></span>
            <p class="log-item__text">
                <strong><?= h((string) ($l['művelet'] ?? '')) ?></strong>
                <?php if (!empty($l['részletek'])): ?>
                    <span class="log-item__details"><?= nl2br(h((string) $l['részletek'])) ?></span>
                <?php endif; ?>
            </p>
        </div>
        <?php endforeach; ?>
        <?php if ($sablonLogok === []): ?><p class="help">Még nincs naplóbejegyzés.</p><?php endif; ?>
    </div>
</div>
