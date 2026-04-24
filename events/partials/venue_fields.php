<?php
declare(strict_types=1);
/** @var array<string, string> $v űrlap mezők: name, slug, description, address */
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
<div class="form-group">
    <label for="venue_address">Cím</label>
    <textarea id="venue_address" name="address" rows="4"><?= h($v['address']) ?></textarea>
</div>
