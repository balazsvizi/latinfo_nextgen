<?php
declare(strict_types=1);
/** @var list<array<string, mixed>> $partnerActivityLog */
/** @var bool $partnerActivityLogGlobal */
$partnerActivityLogGlobal = $partnerActivityLogGlobal ?? false;
?>
<div class="card" id="partner-log">
    <h2>Partner napló</h2>
    <div class="log-list">
        <?php foreach ($partnerActivityLog as $l): ?>
        <div class="log-item">
            <span class="log-date">
                <?= h((string) ($l['létrehozva'] ?? '')) ?>
                – <?= h(nextgen_partner_activity_log_actor_label($l)) ?>
                <?php if ($partnerActivityLogGlobal): ?>
                    ·
                    <a href="<?= h(nextgen_url('admin/partnerek/szerkeszt.php?id=') . (int) ($l['partner_id'] ?? 0)) ?>">
                        <?= h((string) ($l['target_partner_nev'] ?? '')) ?>
                    </a>
                <?php endif; ?>
            </span>
            <p class="log-item__text" style="margin:0.25rem 0 0;">
                <strong><?= h((string) ($l['esemény'] ?? '')) ?></strong>
                <?php if (!empty($l['részletek'])): ?>
                    <span class="log-item__details"> – <?= nl2br(h((string) $l['részletek'])) ?></span>
                <?php endif; ?>
            </p>
        </div>
        <?php endforeach; ?>
        <?php if ($partnerActivityLog === []): ?>
            <p class="help">Még nincs naplóbejegyzés.</p>
        <?php endif; ?>
    </div>
</div>
