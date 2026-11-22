<?php
/**
 * admin/quick-listing.php — post a property in one short, phone-friendly form.
 * Photos upload one at a time in the background (see photo-uploader.js).
 * The full form (property-edit.php) is still there for every other detail.
 */
require __DIR__ . '/../includes/bootstrap.php';
require_login();
ensure_schema();

$categories = Database::all("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY sort_order, name");
$counties   = Database::all("SELECT id, name FROM counties WHERE is_active = 1 ORDER BY sort_order, name");
$amenities  = Database::all("SELECT id, name FROM amenities ORDER BY name");

$purposes = ['sale' => 'For Sale', 'rent' => 'For Rent', 'airbnb' => 'Airbnb', 'commercial' => 'Commercial', 'land' => 'Land'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $title = trim((string) input('title', ''));
    if ($title === '') { flash('error', 'Please give the listing a title.'); redirect('admin/quick-listing.php'); }

    $purpose = array_key_exists((string) input('purpose'), $purposes) ? (string) input('purpose') : 'sale';
    $price   = input_float('price');
    $period  = in_array(input('rent_period'), ['month', 'year', 'night', 'week'], true) ? input('rent_period') : ($purpose === 'airbnb' ? 'night' : 'month');
    $publish = input('publish') !== 'draft';

    $photos = array_values(array_filter(array_map('strval', (array) input('gallery_paths', [])), 'is_stored_upload'));
    $cover  = (string) input('featured_path', '');
    if (!in_array($cover, $photos, true)) $cover = $photos[0] ?? null;
    // Cover first, then the rest in the order they were added.
    if ($cover) $photos = array_values(array_unique(array_merge([$cover], $photos)));

    $data = [
        'ref_code'      => Property::generateRef(),
        'title'         => $title,
        'slug'          => unique_slug('properties', $title),
        'description'   => trim((string) input('description', '')) ?: null,
        'purpose'       => $purpose,
        'category_id'   => input_int('category_id') ?: null,
        'status'        => $publish ? 'available' : 'draft',
        'currency'      => 'KES',
        'price'         => $price,
        'sale_price'    => in_array($purpose, ['sale', 'land', 'commercial'], true) ? $price : null,
        'rent_price'    => in_array($purpose, ['rent', 'airbnb'], true) ? $price : null,
        'rent_period'   => $period,
        'county_id'     => input_int('county_id') ?: null,
        'town_id'       => input_int('town_id') ?: null,
        'address'       => trim((string) input('address', '')) ?: null,
        'bedrooms'      => input_int('bedrooms'),
        'bathrooms'     => input_int('bathrooms'),
        'parking_spaces'=> input_int('parking_spaces'),
        'area_sqm'      => input_float('area_sqm'),
        'land_size_sqm' => input_float('land_size_sqm'),
        'featured_image'=> $cover,
        'is_featured'   => input('is_featured') ? 1 : 0,
        'owner_name'    => trim((string) input('owner_name', '')) ?: null,
        'owner_contact' => trim((string) input('owner_contact', '')) ?: null,
        'source_url'    => filter_var(trim((string) input('source_url', '')), FILTER_VALIDATE_URL) ?: null,
        'created_by'    => current_user()['id'],
    ];
    $cols = array_keys($data);
    Database::run(
        "INSERT INTO properties (" . implode(',', $cols) . ") VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")",
        array_values($data)
    );
    $propId = (int) Database::conn()->lastInsertId();

    foreach ($photos as $i => $path) {
        Database::run("INSERT INTO property_images (property_id, file_path, alt_text, sort_order) VALUES (?,?,?,?)", [$propId, $path, mb_substr($title, 0, 190), $i]);
    }
    foreach ((array) input('amenities', []) as $aid) {
        if ((int) $aid) Database::run("INSERT INTO property_amenities (property_id, amenity_id) VALUES (?,?) ON CONFLICT DO NOTHING", [$propId, (int) $aid]);
    }
    log_activity('create_property', 'property', $propId);

    $_SESSION['quick_done'] = ['id' => $propId, 'slug' => $data['slug'], 'title' => $title, 'published' => $publish, 'photos' => count($photos)];
    redirect('admin/quick-listing.php?done=1');
}

$done = (input('done') && !empty($_SESSION['quick_done'])) ? $_SESSION['quick_done'] : null;
unset($_SESSION['quick_done']);

$pageTitle = 'Quick Listing';
$activeAdmin = 'quick-listing';
include __DIR__ . '/templates/layout-head.php';
?>
<?php if ($done): ?>
<div class="panel ql-done">
  <div class="ql-done-icon">✓</div>
  <h2><?= $done['published'] ? 'Listing is live' : 'Saved as draft' ?></h2>
  <p class="muted"><?= e($done['title']) ?> · <?= (int) $done['photos'] ?> photo<?= $done['photos'] == 1 ? '' : 's' ?></p>
  <div class="ql-done-actions">
    <?php if ($done['published']): ?><a class="a-btn" href="<?= e(url('property/' . $done['slug'])) ?>" target="_blank" rel="noopener">View on website</a><?php endif; ?>
    <a class="a-btn" href="<?= e(url('admin/property-edit.php?id=' . (int) $done['id'])) ?>">Add more details</a>
    <a class="a-btn gold" href="<?= e(url('admin/quick-listing.php')) ?>">+ Add another listing</a>
  </div>
</div>
<?php else: ?>
<form method="post" class="ql" id="quickForm" autocomplete="off">
  <?= csrf_field() ?>

  <section class="panel ql-sec">
    <h2 class="ql-h"><span>1</span> Photos</h2>
    <label class="ql-drop">
      <input type="file" accept="image/*" multiple data-photo-uploader="gallery_paths" data-cover="featured_path">
      <svg viewBox="0 0 24 24"><path d="M4 7h3l2-2h6l2 2h3v12H4z"/><circle cx="12" cy="13" r="3.5"/></svg>
      <b>Add photos</b>
      <small>Take or choose as many as you like. The first one is the cover — tap “Cover” on any photo to change it.</small>
    </label>
  </section>

  <section class="panel ql-sec">
    <h2 class="ql-h"><span>2</span> The basics</h2>
    <div class="ql-seg" role="radiogroup" aria-label="Listing type">
      <?php foreach ($purposes as $k => $lbl): ?>
        <label><input type="radio" name="purpose" value="<?= $k ?>" <?= $k === 'sale' ? 'checked' : '' ?>><span><?= $lbl ?></span></label>
      <?php endforeach; ?>
    </div>
    <div class="a-field"><label>Title</label><input class="a-input" type="text" name="title" required maxlength="190" placeholder="e.g. 4 Bedroom Townhouse for Sale in Runda"></div>
    <div class="ql-row">
      <div class="a-field"><label id="priceLabel">Price (KES)</label><input class="a-input" type="text" inputmode="numeric" name="price" placeholder="e.g. 25,000,000" data-money></div>
      <div class="a-field" id="periodField" hidden><label>Per</label>
        <select class="a-select" name="rent_period"><option value="month">Month</option><option value="night">Night</option><option value="week">Week</option><option value="year">Year</option></select>
      </div>
    </div>
    <div class="a-field"><label>Property type</label>
      <select class="a-select" name="category_id"><option value="">Choose…</option>
        <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
  </section>

  <section class="panel ql-sec">
    <h2 class="ql-h"><span>3</span> Location</h2>
    <div class="ql-row">
      <div class="a-field"><label>County</label>
        <select class="a-select" name="county_id" id="qlCounty"><option value="">Choose…</option>
          <?php foreach ($counties as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="a-field"><label>Town / area</label><select class="a-select" name="town_id" id="qlTown" disabled><option value="">Choose county first</option></select></div>
    </div>
    <div class="a-field"><label>Estate / street (optional)</label><input class="a-input" type="text" name="address" placeholder="e.g. Membley Estate, Ruiru"></div>
  </section>

  <section class="panel ql-sec">
    <h2 class="ql-h"><span>4</span> Details</h2>
    <div class="ql-steps">
      <?php foreach (['bedrooms' => 'Bedrooms', 'bathrooms' => 'Bathrooms', 'parking_spaces' => 'Parking'] as $k => $lbl): ?>
      <div class="ql-step"><span><?= $lbl ?></span>
        <div><button type="button" data-step="-1" aria-label="Fewer">−</button><input type="number" name="<?= $k ?>" min="0" max="50" inputmode="numeric" placeholder="–"><button type="button" data-step="1" aria-label="More">+</button></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="ql-row">
      <div class="a-field"><label>Floor area (m²)</label><input class="a-input" type="text" inputmode="decimal" name="area_sqm" placeholder="optional"></div>
      <div class="a-field"><label>Land size (m²)</label><input class="a-input" type="text" inputmode="decimal" name="land_size_sqm" placeholder="optional"></div>
    </div>
    <div class="a-field"><label>Description</label><textarea class="a-textarea" name="description" rows="6" placeholder="Describe the home, finishes, surroundings, what makes it special…"></textarea></div>
    <?php if ($amenities): ?>
    <div class="a-field"><label>Amenities</label>
      <div class="ql-chips"><?php foreach ($amenities as $a): ?><label><input type="checkbox" name="amenities[]" value="<?= (int) $a['id'] ?>"><span><?= e($a['name']) ?></span></label><?php endforeach; ?></div>
    </div>
    <?php endif; ?>
  </section>

  <section class="panel ql-sec">
    <h2 class="ql-h"><span>5</span> Private notes <small class="muted">— never shown on the website</small></h2>
    <div class="ql-row">
      <div class="a-field"><label>Owner / agent name</label><input class="a-input" type="text" name="owner_name"></div>
      <div class="a-field"><label>Their phone or email</label><input class="a-input" type="text" name="owner_contact" inputmode="tel"></div>
    </div>
    <div class="a-field"><label>Link to where you found it (optional)</label><input class="a-input" type="url" name="source_url" placeholder="https://…"></div>
  </section>

  <div class="ql-bar">
    <label class="a-check" style="margin:0"><input type="checkbox" name="is_featured" value="1"> Feature on homepage</label>
    <div class="ql-bar-btns">
      <button class="a-btn" type="submit" name="publish" value="draft">Save draft</button>
      <button class="a-btn gold" type="submit" name="publish" value="live">Publish listing</button>
    </div>
  </div>
</form>
<?php endif; ?>

<script>
(function(){
  var f = document.getElementById('quickForm'); if (!f) return;
  // Price label follows the listing type
  var lbl = document.getElementById('priceLabel'), per = document.getElementById('periodField'), sel = per.querySelector('select');
  function syncPurpose(){
    var p = (f.querySelector('input[name=purpose]:checked') || {}).value;
    var rent = p === 'rent' || p === 'airbnb';
    per.hidden = !rent; lbl.textContent = rent ? 'Rent (KES)' : 'Price (KES)';
    if (p === 'airbnb') sel.value = 'night'; else if (p === 'rent' && sel.value === 'night') sel.value = 'month';
  }
  f.querySelectorAll('input[name=purpose]').forEach(function(r){ r.addEventListener('change', syncPurpose); }); syncPurpose();
  // Thousands separators while typing; plain digits are sent
  f.querySelectorAll('[data-money]').forEach(function(i){
    i.addEventListener('input', function(){ var d = i.value.replace(/[^\d]/g,''); i.value = d ? Number(d).toLocaleString('en-US') : ''; });
  });
  f.addEventListener('submit', function(){ f.querySelectorAll('[data-money]').forEach(function(i){ i.value = i.value.replace(/[^\d.]/g,''); }); });
  // County -> towns
  var county = document.getElementById('qlCounty'), town = document.getElementById('qlTown');
  county.addEventListener('change', function(){
    town.disabled = true; town.innerHTML = '<option value="">Loading…</option>';
    if (!county.value) { town.innerHTML = '<option value="">Choose county first</option>'; return; }
    fetch('/api/towns.php?county_id=' + county.value).then(function(r){ return r.json(); }).then(function(list){
      town.innerHTML = '<option value="">' + (list.length ? 'Choose…' : 'No towns yet') + '</option>' + list.map(function(t){ return '<option value="'+t.id+'">'+t.name.replace(/</g,'&lt;')+'</option>'; }).join('');
      town.disabled = false;
    });
  });
  // − / + steppers
  f.querySelectorAll('.ql-step button').forEach(function(b){
    b.addEventListener('click', function(){ var i = b.parentNode.querySelector('input'); var v = Math.max(0, (parseInt(i.value,10)||0) + parseInt(b.dataset.step,10)); i.value = v; });
  });
})();
</script>
<?php include __DIR__ . '/templates/layout-foot.php'; ?>
