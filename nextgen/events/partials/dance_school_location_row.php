<?php
declare(strict_types=1);

/**
 * Tánciskola helyszín sor (+ beágyazott kínálatok).
 *
 * @var int $locIndex
 * @var array<string, mixed> $location
 * @var list<array<string, mixed>> $offerings
 * @var array<int, string> $styleOptions
 * @var array<string, string> $ageLabels
 * @var array<string, string> $levelLabels
 * @var array<string, string> $classTypeLabels
 */
$locIndex = (int) ($locIndex ?? 0);
$location = is_array($location ?? null) ? $location : [];
$offerings = is_array($offerings ?? null) ? $offerings : [];
$styleOptions = $styleOptions ?? [];
$ageLabels = $ageLabels ?? dance_school_age_group_labels();
$levelLabels = $levelLabels ?? dance_school_level_labels();
$classTypeLabels = $classTypeLabels ?? dance_school_class_type_labels();
$prefix = 'locations[' . $locIndex . ']';
$lat = $location['latitude'] ?? '';
$lng = $location['longitude'] ?? '';
if ($lat !== null && $lat !== '') {
    $lat = (string) $lat;
} else {
    $lat = '';
}
if ($lng !== null && $lng !== '') {
    $lng = (string) $lng;
} else {
    $lng = '';
}
?>
<div class="events-edit-panel dance-school-location-row" data-dance-location-row data-loc-index="<?= (int) $locIndex ?>">
    <div class="events-list-head" style="margin-bottom:0.75rem;">
        <h4 class="events-edit-panel__title" style="margin:0;">Helyszín #<?= (int) $locIndex + 1 ?></h4>
        <button type="button" class="btn btn-secondary btn-sm" data-dance-location-remove>Helyszín törlése</button>
    </div>
    <input type="hidden" name="<?= h($prefix) ?>[id]" value="<?= (int) ($location['id'] ?? 0) ?>">
    <div class="events-tag-dj-profile__grid">
        <div class="form-group">
            <label>Helyszín neve *</label>
            <input type="text" name="<?= h($prefix) ?>[name]" value="<?= h((string) ($location['name'] ?? '')) ?>" maxlength="255" placeholder="pl. Belvárosi terem">
        </div>
        <div class="form-group">
            <label>Cím</label>
            <input type="text" name="<?= h($prefix) ?>[address]" value="<?= h((string) ($location['address'] ?? '')) ?>" maxlength="500">
        </div>
        <div class="form-group">
            <label>Város</label>
            <input type="text" name="<?= h($prefix) ?>[city]" value="<?= h((string) ($location['city'] ?? '')) ?>" maxlength="128">
        </div>
        <div class="form-group">
            <label>Irányítószám</label>
            <input type="text" name="<?= h($prefix) ?>[postal_code]" value="<?= h((string) ($location['postal_code'] ?? '')) ?>" maxlength="32">
        </div>
        <div class="form-group">
            <label>Ország</label>
            <input type="text" name="<?= h($prefix) ?>[country]" value="<?= h((string) ($location['country'] ?? 'Magyarország')) ?>" maxlength="64">
        </div>
        <div class="form-group">
            <label>Szélesség (lat)</label>
            <input type="number" step="any" name="<?= h($prefix) ?>[latitude]" value="<?= h($lat) ?>" placeholder="47.4979">
        </div>
        <div class="form-group">
            <label>Hosszúság (lng)</label>
            <input type="number" step="any" name="<?= h($prefix) ?>[longitude]" value="<?= h($lng) ?>" placeholder="19.0402">
        </div>
        <div class="form-group">
            <label>Google Maps URL</label>
            <input type="text" name="<?= h($prefix) ?>[google_maps_url]" value="<?= h((string) ($location['google_maps_url'] ?? '')) ?>" maxlength="2000" placeholder="https://maps…">
        </div>
    </div>
    <div class="form-group">
        <label>Megjegyzés</label>
        <textarea name="<?= h($prefix) ?>[notes]" rows="2"><?= h((string) ($location['notes'] ?? '')) ?></textarea>
    </div>
    <div class="form-group">
        <label><input type="checkbox" name="<?= h($prefix) ?>[is_active]" value="1"<?= !empty($location['is_active']) || !isset($location['is_active']) ? ' checked' : '' ?>> Aktív helyszín</label>
    </div>

    <div class="dance-school-offerings" data-dance-offerings>
        <h5 style="margin:1rem 0 0.5rem;">Kínálat / órák</h5>
        <div data-dance-offerings-list>
            <?php
            if ($offerings === []) {
                $offIndex = 0;
                $offering = [];
                require __DIR__ . '/dance_school_offering_row.php';
            } else {
                foreach ($offerings as $offIndex => $offering) {
                    require __DIR__ . '/dance_school_offering_row.php';
                }
            }
            ?>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" data-dance-offering-add>+ Kínálat hozzáadása</button>
    </div>
</div>
