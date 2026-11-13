<?php
declare(strict_types=1);
/** @var list<array<string, mixed>> $sablonLogok */
?>
<div class="events-edit-panel events-edit-log">
    <h2 class="events-edit-panel__title">Napló</h2>
    <div class="log-list">
        <?php foreach ($sablonLogok as $l): ?>
        <div class="log-item">
            <?php $logDetailLines = events_log_visible_detail_lines((string) ($l['részletek'] ?? '')); ?>
            <p class="log-item__text">
                <span class="log-item__headline"><?= events_log_actor_headline_html($l) ?></span>
                <?php if ($logDetailLines !== []): ?>
                    <span class="log-item__details"><?= nl2br(h(implode("\n", $logDetailLines))) ?></span>
                <?php endif; ?>
            </p>
        </div>
        <?php endforeach; ?>
        <?php if ($sablonLogok === []): ?><p class="help">Még nincs naplóbejegyzés.</p><?php endif; ?>
    </div>
</div>
