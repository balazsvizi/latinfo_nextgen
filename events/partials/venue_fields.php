<?php
declare(strict_types=1);
/** @var array<string, string> $v űrlap mezők */
?>
<div class="form-group">
    <label for="venue_name">Név *</label>
    <input type="text" id="venue_name" name="name" value="<?= h($v['name']) ?>" required maxlength="500">
</div>
<div class="form-group">
    <label for="venue_slug">Slug (URL)</label>
    <input type="text" id="venue_slug" name="slug" value="<?= h($v['slug']) ?>" maxlength="255" pattern="[a-z0-9\-]*" title="Kisbetű, szám és kötőjel">
    <p class="help">Ha üres, a névből generáljuk.</p>
</div>
<div class="form-group">
    <label for="venue_description">Leírás</label>
    <textarea id="venue_description" name="description" rows="8"><?= h($v['description']) ?></textarea>
</div>
<fieldset class="form-group events-venue-address-fieldset">
    <legend>Cím</legend>
    <div class="form-group">
        <label for="venue_country">Ország</label>
        <input type="text" id="venue_country" name="country" value="<?= h($v['country']) ?>" maxlength="120" placeholder="<?= h(events_venue_default_country()) ?>">
        <p class="help">Üresen: <?= h(events_venue_default_country()) ?>.</p>
    </div>
    <div class="form-group">
        <label for="venue_city">Település</label>
        <input type="text" id="venue_city" name="city" value="<?= h($v['city']) ?>" maxlength="255">
    </div>
    <div class="form-group">
        <label for="venue_postal_code">IRSZ</label>
        <input type="text" id="venue_postal_code" name="postal_code" value="<?= h($v['postal_code']) ?>" maxlength="16" inputmode="numeric" autocomplete="postal-code">
    </div>
    <div class="form-group">
        <label for="venue_address">Cím (utca, házszám)</label>
        <textarea id="venue_address" name="address" rows="3"><?= h($v['address']) ?></textarea>
    </div>
</fieldset>
