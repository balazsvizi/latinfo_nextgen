<?php
declare(strict_types=1);
/** @var string $lang */
/** @var string $listLimitValue */
/** @var int $listTotalInDb */
/** @var array<string, string> $D */
?>
<div class="catalog-public__limit-row catalog-public__limit-row--events-head">
    <?php
    $listLimitDefault = EVENTS_ADMIN_EVENTS_LIST_DEFAULT_LIMIT;
    $listLimitInForm = false;
    $listLimitStandalone = true;
    $listLimitInlineHead = true;
    $listLimitLabel = (string) ($D['list_display_label'] ?? 'Megjelenítve:');
    $listLimitAllLabel = (string) ($D['list_display_all'] ?? 'Mind');
    $listCountSuffix = $lang === 'en' ? ' shown' : ' megjelenítve';
    require __DIR__ . '/admin_list_display_limit.php';
    ?>
</div>
