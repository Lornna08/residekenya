<?php
/** admin/property-edit.php — add or edit a property (full form). */
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$id = input_int('id');
$editing = false;
$p = [];

if ($id) {
    $p = Property::findById($id);
    if (!$p) { flash('error', 'Property not found.'); redirect('admin/properties.php'); }
    $editing = true;
}

$counties   = Database::all("SELECT id, name FROM counties ORDER BY sort_order, name");
$categories = Database::all("SELECT id, name FROM categories ORDER BY sort_order, name");
$agents     = Database::all("SELECT id, name FROM agents WHERE is_active=1 ORDER BY name");
$amenities  = Database::all("SELECT id, name FROM amenities ORDER BY name");
$towns      = $editing && $p['county_id'] ? Database::all("SELECT id, name FROM towns WHERE county_id=? ORDER BY name", [$p['county_id']]) : [];
$selAmenities = $editing ? array_column(Property::amenities($id), 'id') : [];
$gallery    = $editing ? Property::images($id) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $title = trim((string) input('title', ''));
    if ($title === '') { flash('error', 'Title is required.'); redirect($_SERVER['REQUEST_URI']); }

    $data = [
        'title'            => $title,
        'description'      => trim((string) input('description', '')) ?: null,
        'purpose'          => in_array(input('purpose'), ['sale', 'rent', 'airbnb', 'commercial', 'land'], true) ? input('purpose') : 'sale',
        'category_id'      => input_int('category_id') ?: null,
        'status'           => in_array(input('status'), ['available', 'under_offer', 'sold', 'rented', 'archived', 'draft'], true) ? input('status') : 'available',
        'currency'         => trim((string) input('currency', 'KES')) ?: 'KES',
        'price'            => input_float('price'),
        'sale_price'       => input_float('sale_price'),
        'rent_price'       => input_float('rent_price'),
        'rent_period'      => in_array(input('rent_period'), ['month', 'year', 'night', 'week'], true) ? input('rent_period') : 'month',
        'county_id'        => input_int('county_id') ?: null,
        'town_id'          => input_int('town_id') ?: null,
        'estate_id'        => input_int('estate_id') ?: null,
        'address'          => trim((string) input('address', '')) ?: null,
        'latitude'         => input_float('latitude'),
        'longitude'        => input_float('longitude'),
        'bedrooms'         => input_int('bedrooms'),
        'bathrooms'        => input_int('bathrooms'),
        'parking_spaces'   => input_int('parking_spaces'),
        'kitchens'         => input_int('kitchens'),
        'living_rooms'     => input_int('living_rooms'),
        'floors'           => input_int('floors'),
        'year_built'       => input_int('year_built'),
        'area_sqm'         => input_float('area_sqm'),
        'land_size_sqm'    => input_float('land_size_sqm'),
        'virtual_tour_url' => trim((string) input('virtual_tour_url', '')) ?: null,
        'tour_360_url'     => trim((string) input('tour_360_url', '')) ?: null,
        'youtube_url'      => trim((string) input('youtube_url', '')) ?: null,
        'nearby_schools'   => trim((string) input('nearby_schools', '')) ?: null,
        'nearby_hospitals' => trim((string) input('nearby_hospitals', '')) ?: null,
        'nearby_shopping'  => trim((string) input('nearby_shopping', '')) ?: null,
        'features'         => trim((string) input('features', '')) ?: null,
        'agent_id'         => input_int('agent_id') ?: null,
        'owner_name'       => trim((string) input('owner_name', '')) ?: null,
        'owner_contact'    => trim((string) input('owner_contact', '')) ?: null,
        'is_featured'      => input('is_featured') ? 1 : 0,
        'is_luxury'        => input('is_luxury') ? 1 : 0,
        'airbnb_max_guests'   => input_int('airbnb_max_guests'),
        'airbnb_cleaning_fee' => input_float('airbnb_cleaning_fee'),
        'airbnb_house_rules'  => trim((string) input('airbnb_house_rules', '')) ?: null,
        'meta_title'       => trim((string) input('meta_title', '')) ?: null,
        'meta_description' => trim((string) input('meta_description', '')) ?: null,
        'seo_keywords'     => trim((string) input('seo_keywords', '')) ?: null,
    ];

    // Featured image upload
    if (!empty($_FILES['featured_image']['name'])) {
        $stored = save_upload($_FILES['featured_image'], 'properties', ALLOWED_IMAGE_EXT);
        if ($stored) $data['featured_image'] = $stored;
    }
    // Brochure PDF
    if (!empty($_FILES['brochure_pdf']['name'])) {
        $storedPdf = save_upload($_FILES['brochure_pdf'], 'brochures', ALLOWED_DOC_EXT);
        if ($storedPdf) $data['brochure_pdf'] = $storedPdf;
    }

    if ($editing) {
        // Keep slug stable; regenerate only if title changed and slug empty
        $sets = [];
        $vals = [];
        foreach ($data as $k => $v) { $sets[] = "$k = ?"; $vals[] = $v; }
        $vals[] = $id;
        Database::run("UPDATE properties SET " . implode(', ', $sets) . " WHERE id = ?", $vals);
        $propId = $id;
        log_activity('update_property', 'property', $id);
        flash('success', 'Property updated.');
    } else {
        $data['ref_code'] = Property::generateRef();
        $data['slug'] = unique_slug('properties', $title);
        $data['created_by'] = current_user()['id'];
        // Set base price fallback
        if ($data['price'] === null) $data['price'] = $data['sale_price'] ?? $data['rent_price'];
        $cols = array_keys($data);
        $ph = implode(',', array_fill(0, count($cols), '?'));
        Database::run("INSERT INTO properties (" . implode(',', $cols) . ") VALUES ($ph)", array_values($data));
        $propId = (int) Database::conn()->lastInsertId();
        log_activity('create_property', 'property', $propId);
        flash('success', 'Property created.');
    }

    // Amenities pivot
    Database::run("DELETE FROM property_amenities WHERE property_id = ?", [$propId]);
    foreach ((array) input('amenities', []) as $aid) {
        $aid = (int) $aid;
        if ($aid) Database::run("INSERT INTO property_amenities (property_id, amenity_id) VALUES (?,?) ON CONFLICT DO NOTHING", [$propId, $aid]);
    }

    // Gallery images (multiple)
    if (!empty($_FILES['gallery']['name']) && is_array($_FILES['gallery']['name'])) {
        $n = count($_FILES['gallery']['name']);
        for ($i = 0; $i < $n; $i++) {
            if (($_FILES['gallery']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            $one = [
                'name' => $_FILES['gallery']['name'][$i], 'type' => $_FILES['gallery']['type'][$i],
                'tmp_name' => $_FILES['gallery']['tmp_name'][$i], 'error' => $_FILES['gallery']['error'][$i], 'size' => $_FILES['gallery']['size'][$i],
            ];
            $stored = save_upload($one, 'properties', ALLOWED_IMAGE_EXT);
            if ($stored) Database::run("INSERT INTO property_images (property_id, file_path, sort_order) VALUES (?,?,?)", [$propId, $stored, $i]);
        }
    }
    // Floorplan images
    if (!empty($_FILES['floorplans']['name']) && is_array($_FILES['floorplans']['name'])) {
        $n = count($_FILES['floorplans']['name']);
        for ($i = 0; $i < $n; $i++) {
            if (($_FILES['floorplans']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            $one = [
                'name' => $_FILES['floorplans']['name'][$i], 'type' => $_FILES['floorplans']['type'][$i],
                'tmp_name' => $_FILES['floorplans']['tmp_name'][$i], 'error' => $_FILES['floorplans']['error'][$i], 'size' => $_FILES['floorplans']['size'][$i],
            ];
            $stored = save_upload($one, 'floorplans', ALLOWED_IMAGE_EXT);
            if ($stored) Database::run("INSERT INTO property_images (property_id, file_path, is_floorplan, sort_order) VALUES (?,?,1,?)", [$propId, $stored, $i]);
        }
    }

    redirect('admin/property-edit.php?id=' . $propId);
}

// Delete a gallery image (GET action)
if (($del = input_int('del_image')) && $editing) {
    $img = Database::row("SELECT * FROM property_images WHERE id=? AND property_id=?", [$del, $id]);
    if ($img) {
        delete_upload($img['file_path']);
        Database::run("DELETE FROM property_images WHERE id=?", [$del]);
        flash('success', 'Image removed.');
    }
    redirect('admin/property-edit.php?id=' . $id);
}

$val = fn($k, $d = '') => e((string) ($p[$k] ?? $d));
$pageTitle = $editing ? 'Edit Property' : 'Add Property';
$activeAdmin = $editing ? 'properties' : 'property-edit';
include __DIR__ . '/templates/layout-head.php';
?>
<form method="post" enctype="multipart/form-data" action="<?= e(url('admin/property-edit.php' . ($editing ? '?id=' . $id : ''))) ?>">
  <?= csrf_field() ?>

  <div class="panel">
    <div class="panel-head"><h2>Basic details</h2>
      <?php if ($editing): ?><a class="a-btn sm" href="<?= e(url('property.php?slug=' . eurl($p['slug']))) ?>" target="_blank" rel="noopener">View live →</a><?php endif; ?>
    </div>
    <div class="panel-body">
      <div class="a-field"><label>Title *</label><input class="a-input" type="text" name="title" value="<?= $val('title') ?>" required></div>
      <div class="a-field"><label>Description</label><textarea class="a-textarea" name="description" rows="6"><?= $val('description') ?></textarea></div>
      <div class="a-grid-3">
        <div class="a-field"><label>Purpose</label><select class="a-select" name="purpose">
          <?php foreach (['sale' => 'For Sale', 'rent' => 'For Rent', 'airbnb' => 'Airbnb', 'commercial' => 'Commercial', 'land' => 'Land'] as $k => $lbl): ?>
            <option value="<?= $k ?>" <?= ($p['purpose'] ?? 'sale') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select></div>
        <div class="a-field"><label>Category <button type="button" class="quick-add-btn" data-qa="category">+ Add new</button></label><select class="a-select" name="category_id" id="categorySelect"><option value="">—</option>
          <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= ($p['category_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select></div>
        <div class="a-field"><label>Status</label><select class="a-select" name="status">
          <?php foreach (['available', 'under_offer', 'sold', 'rented', 'archived', 'draft'] as $s): ?><option value="<?= $s ?>" <?= ($p['status'] ?? 'available') === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option><?php endforeach; ?>
        </select></div>
      </div>
      <div class="checkbox-grid" style="margin-top:.4rem">
        <label class="a-check"><input type="checkbox" name="is_featured" value="1" <?= !empty($p['is_featured']) ? 'checked' : '' ?>> Featured</label>
        <label class="a-check"><input type="checkbox" name="is_luxury" value="1" <?= !empty($p['is_luxury']) ? 'checked' : '' ?>> Luxury collection</label>
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Pricing</h2></div>
    <div class="panel-body a-grid-3">
      <div class="a-field"><label>Currency</label><input class="a-input" type="text" name="currency" value="<?= $val('currency', 'KES') ?>"></div>
      <div class="a-field"><label>Display / base price</label><input class="a-input" type="number" step="0.01" name="price" value="<?= $val('price') ?>"></div>
      <div class="a-field"><label>Sale price</label><input class="a-input" type="number" step="0.01" name="sale_price" value="<?= $val('sale_price') ?>"></div>
      <div class="a-field"><label>Rent / nightly price</label><input class="a-input" type="number" step="0.01" name="rent_price" value="<?= $val('rent_price') ?>"></div>
      <div class="a-field"><label>Rent period</label><select class="a-select" name="rent_period">
        <?php foreach (['month', 'year', 'week', 'night'] as $rp): ?><option value="<?= $rp ?>" <?= ($p['rent_period'] ?? 'month') === $rp ? 'selected' : '' ?>><?= ucfirst($rp) ?></option><?php endforeach; ?>
      </select></div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Location</h2></div>
    <div class="panel-body">
      <div class="a-grid-3">
        <div class="a-field"><label>County <button type="button" class="quick-add-btn" data-qa="county">+ Add new</button></label><select class="a-select" name="county_id" id="countySelect" data-county-select><option value="">—</option>
          <?php foreach ($counties as $c): ?><option value="<?= (int) $c['id'] ?>" <?= ($p['county_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select></div>
        <div class="a-field"><label>Town <button type="button" class="quick-add-btn" data-qa="town">+ Add new</button></label><select class="a-select" name="town_id" id="townSelect" data-town-select><option value="">Any town</option>
          <?php foreach ($towns as $t): ?><option value="<?= (int) $t['id'] ?>" <?= ($p['town_id'] ?? '') == $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?>
        </select></div>
        <div class="a-field"><label>Address</label><input class="a-input" type="text" name="address" value="<?= $val('address') ?>"></div>
        <div class="a-field"><label>Latitude</label><input class="a-input" type="text" name="latitude" value="<?= $val('latitude') ?>"></div>
        <div class="a-field"><label>Longitude</label><input class="a-input" type="text" name="longitude" value="<?= $val('longitude') ?>"></div>
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Specifications</h2></div>
    <div class="panel-body a-grid-3">
      <div class="a-field"><label>Bedrooms</label><input class="a-input" type="number" name="bedrooms" value="<?= $val('bedrooms') ?>"></div>
      <div class="a-field"><label>Bathrooms</label><input class="a-input" type="number" name="bathrooms" value="<?= $val('bathrooms') ?>"></div>
      <div class="a-field"><label>Parking spaces</label><input class="a-input" type="number" name="parking_spaces" value="<?= $val('parking_spaces') ?>"></div>
      <div class="a-field"><label>Kitchens</label><input class="a-input" type="number" name="kitchens" value="<?= $val('kitchens') ?>"></div>
      <div class="a-field"><label>Living rooms</label><input class="a-input" type="number" name="living_rooms" value="<?= $val('living_rooms') ?>"></div>
      <div class="a-field"><label>Floors</label><input class="a-input" type="number" name="floors" value="<?= $val('floors') ?>"></div>
      <div class="a-field"><label>Year built</label><input class="a-input" type="number" name="year_built" value="<?= $val('year_built') ?>"></div>
      <div class="a-field"><label>Floor area (m²)</label><input class="a-input" type="number" step="0.01" name="area_sqm" value="<?= $val('area_sqm') ?>"></div>
      <div class="a-field"><label>Land size (m²)</label><input class="a-input" type="number" step="0.01" name="land_size_sqm" value="<?= $val('land_size_sqm') ?>"></div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Amenities &amp; features</h2></div>
    <div class="panel-body">
      <label style="font-size:.8rem;color:var(--a-muted)">Amenities</label>
      <div class="checkbox-grid" style="margin:.5rem 0 1.2rem">
        <?php foreach ($amenities as $am): ?>
          <label class="a-check"><input type="checkbox" name="amenities[]" value="<?= (int) $am['id'] ?>" <?= in_array($am['id'], $selAmenities) ? 'checked' : '' ?>> <?= e($am['name']) ?></label>
        <?php endforeach; ?>
      </div>
      <div class="a-field"><label>Features (one per line or comma-separated)</label><textarea class="a-textarea" name="features"><?= $val('features') ?></textarea></div>
      <div class="a-grid-3">
        <div class="a-field"><label>Nearby schools</label><textarea class="a-textarea" name="nearby_schools" style="min-height:80px"><?= $val('nearby_schools') ?></textarea></div>
        <div class="a-field"><label>Nearby hospitals</label><textarea class="a-textarea" name="nearby_hospitals" style="min-height:80px"><?= $val('nearby_hospitals') ?></textarea></div>
        <div class="a-field"><label>Nearby shopping</label><textarea class="a-textarea" name="nearby_shopping" style="min-height:80px"><?= $val('nearby_shopping') ?></textarea></div>
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Media</h2></div>
    <div class="panel-body">
      <div class="a-grid">
        <div class="a-field"><label>Featured image</label><input class="a-input" type="file" name="featured_image" accept="image/*">
          <?php if (!empty($p['featured_image'])): ?><div class="img-preview"><div class="ip"><img src="<?= e(asset_url($p['featured_image'])) ?>" alt=""></div></div><?php endif; ?>
        </div>
        <div class="a-field"><label>Brochure (PDF)</label><input class="a-input" type="file" name="brochure_pdf" accept="application/pdf">
          <?php if (!empty($p['brochure_pdf'])): ?><a class="muted" href="<?= e(asset_url($p['brochure_pdf'])) ?>" target="_blank" rel="noopener">Current brochure</a><?php endif; ?>
        </div>
      </div>
      <div class="a-field"><label>Gallery images (multiple)</label><input class="a-input" type="file" name="gallery[]" accept="image/*" multiple></div>
      <div class="a-field"><label>Floor plan images (multiple)</label><input class="a-input" type="file" name="floorplans[]" accept="image/*" multiple></div>
      <?php if ($gallery): ?>
        <label style="font-size:.8rem;color:var(--a-muted)">Existing gallery</label>
        <div class="img-preview">
          <?php foreach ($gallery as $g): ?>
            <div class="ip">
              <img src="<?= e(asset_url($g['file_path'])) ?>" alt="">
              <button type="button" onclick="location.href='<?= e(url('admin/property-edit.php?id=' . $id . '&del_image=' . (int) $g['id'])) ?>'" title="Remove">&times;</button>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="a-grid-3" style="margin-top:1rem">
        <div class="a-field"><label>YouTube URL</label><input class="a-input" type="url" name="youtube_url" value="<?= $val('youtube_url') ?>"></div>
        <div class="a-field"><label>Virtual tour URL</label><input class="a-input" type="url" name="virtual_tour_url" value="<?= $val('virtual_tour_url') ?>"></div>
        <div class="a-field"><label>360° tour URL</label><input class="a-input" type="url" name="tour_360_url" value="<?= $val('tour_360_url') ?>"></div>
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Airbnb settings</h2></div>
    <div class="panel-body a-grid-3">
      <div class="a-field"><label>Max guests</label><input class="a-input" type="number" name="airbnb_max_guests" value="<?= $val('airbnb_max_guests') ?>"></div>
      <div class="a-field"><label>Cleaning fee</label><input class="a-input" type="number" step="0.01" name="airbnb_cleaning_fee" value="<?= $val('airbnb_cleaning_fee') ?>"></div>
      <div class="a-field full"><label>House rules</label><textarea class="a-textarea" name="airbnb_house_rules" style="min-height:80px"><?= $val('airbnb_house_rules') ?></textarea></div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Agent &amp; owner</h2></div>
    <div class="panel-body a-grid-3">
      <div class="a-field"><label>Agent</label><select class="a-select" name="agent_id"><option value="">—</option>
        <?php foreach ($agents as $a): ?><option value="<?= (int) $a['id'] ?>" <?= ($p['agent_id'] ?? '') == $a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option><?php endforeach; ?>
      </select></div>
      <div class="a-field"><label>Owner name (private)</label><input class="a-input" type="text" name="owner_name" value="<?= $val('owner_name') ?>"></div>
      <div class="a-field"><label>Owner contact (private)</label><input class="a-input" type="text" name="owner_contact" value="<?= $val('owner_contact') ?>"></div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>SEO</h2></div>
    <div class="panel-body">
      <div class="a-field"><label>Meta title</label><input class="a-input" type="text" name="meta_title" value="<?= $val('meta_title') ?>"></div>
      <div class="a-field"><label>Meta description</label><textarea class="a-textarea" name="meta_description" style="min-height:70px"><?= $val('meta_description') ?></textarea></div>
      <div class="a-field"><label>Keywords</label><input class="a-input" type="text" name="seo_keywords" value="<?= $val('seo_keywords') ?>"></div>
    </div>
  </div>

  <div style="display:flex;gap:.8rem;position:sticky;bottom:0;background:var(--a-bg);padding:1rem 0;border-top:1px solid var(--a-line)">
    <button class="a-btn gold" type="submit"><?= $editing ? 'Save Changes' : 'Create Property' ?></button>
    <a class="a-btn ghost" href="<?= e(url('admin/properties.php')) ?>">Cancel</a>
  </div>
</form>
<script src="<?= e(url('assets/js/main.js')) ?>" defer></script>
<script>
(function(){
  var CSRF = <?= json_encode(csrf_token()) ?>;
  var QA_URL = <?= json_encode(url('admin/quick-add.php')) ?>;
  document.querySelectorAll('.quick-add-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      var kind = btn.getAttribute('data-qa');
      var label = kind.charAt(0).toUpperCase()+kind.slice(1);
      var name = prompt('New '+label+' name:');
      if(!name || !name.trim()) return;
      var body = new URLSearchParams();
      body.append('_csrf', CSRF);
      body.append('kind', kind);
      body.append('name', name.trim());
      if(kind==='town'){
        var cs = document.getElementById('countySelect');
        if(!cs || !cs.value){ alert('Please choose a county first, then add the town.'); return; }
        body.append('county_id', cs.value);
      }
      btn.disabled = true; btn.textContent = 'Adding…';
      fetch(QA_URL,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body.toString()})
        .then(function(r){return r.json();})
        .then(function(d){
          btn.disabled=false; btn.textContent='+ Add new';
          if(!d.ok){ alert(d.error||'Could not add.'); return; }
          var sel = document.getElementById(kind==='category'?'categorySelect':(kind==='county'?'countySelect':'townSelect'));
          var opt = document.createElement('option');
          opt.value = d.id; opt.textContent = d.name; opt.selected = true;
          sel.appendChild(opt);
        })
        .catch(function(){ btn.disabled=false; btn.textContent='+ Add new'; alert('Network error.'); });
    });
  });
})();
</script>
<?php include __DIR__ . '/templates/layout-foot.php'; ?>
