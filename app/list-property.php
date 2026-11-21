<?php
/** list-property.php — client-facing "List Your Property" submission form. */
require __DIR__ . '/includes/bootstrap.php';

$seo = [
    'title'       => 'List Your Property — ' . SITE_NAME,
    'description' => 'List your property with Reside Ke. Sell, rent, or offer your home as an Airbnb stay. Reach serious buyers and guests across Kenya.',
    'canonical'   => url('list-property.php'),
];
$activeNav = '';

$counties = Database::all("SELECT id, name FROM counties WHERE is_active = 1 ORDER BY sort_order, name");
$categories = Database::all("SELECT name FROM categories WHERE is_active = 1 ORDER BY sort_order, name");

include __DIR__ . '/templates/header.php';
?>
<section class="page-hero">
  <div class="wrap">
    <p class="eyebrow">Sell · Rent · Host</p>
    <h1 class="display">List your property</h1>
    <p>Reach serious buyers, tenants, and guests across Kenya. Share a few details and our team will take it from there.</p>
  </div>
</section>

<div class="wrap section">
  <?php if ($f = flash('success')): ?><div class="flash flash--success"><?= e($f) ?></div><?php endif; ?>
  <?php if ($f = flash('error')): ?><div class="flash flash--error"><?= e($f) ?></div><?php endif; ?>

  <div class="form-card" style="max-width:820px;margin:0 auto">
    <form action="<?= e(url('api/submit-property.php')) ?>" method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>

      <h2 class="display" style="font-size:1.5rem;margin-bottom:1.2rem">Your details</h2>
      <div class="form-grid">
        <div class="field"><label>Full name *</label><input class="input" type="text" name="full_name" required></div>
        <div class="field"><label>Phone *</label><input class="input" type="tel" name="phone" required></div>
        <div class="field full"><label>Email</label><input class="input" type="email" name="email"></div>
      </div>

      <div class="hairline" style="margin:1.4rem 0"></div>
      <h2 class="display" style="font-size:1.5rem;margin-bottom:1.2rem">About the property</h2>
      <div class="form-grid">
        <div class="field">
          <label>I want to *</label>
          <select class="select" name="property_status" required>
            <option value="selling">Sell it</option>
            <option value="renting">Rent it out</option>
            <option value="airbnb">Offer as Airbnb</option>
          </select>
        </div>
        <div class="field">
          <label>Property type</label>
          <select class="select" name="property_type">
            <option value="">Select type</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= e($c['name']) ?>"><?= e($c['name']) ?></option>
            <?php endforeach; ?>
            <option value="Other">Other</option>
          </select>
        </div>
        <div class="field">
          <label>County</label>
          <select class="select" name="county_id">
            <option value="">Select county</option>
            <?php foreach ($counties as $c): ?>
              <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Area / estate</label><input class="input" type="text" name="location" placeholder="e.g. Kilimani, Nairobi"></div>
        <div class="field"><label>Bedrooms</label><input class="input" type="number" name="bedrooms" min="0" max="50"></div>
        <div class="field"><label>Bathrooms</label><input class="input" type="number" name="bathrooms" min="0" max="50"></div>
        <div class="field"><label>Expected price (KES)</label><input class="input" type="number" name="expected_price" min="0" step="1000"></div>
        <div class="field"><label>Google Maps link</label><input class="input" type="url" name="maps_url" placeholder="https://maps.google.com/…"></div>
        <div class="field full"><label>Description</label><textarea class="textarea" name="description" placeholder="Describe the property — key features, condition, why it stands out…"></textarea></div>
      </div>

      <div class="hairline" style="margin:1.4rem 0"></div>
      <h2 class="display" style="font-size:1.5rem;margin-bottom:1.2rem">Photos</h2>
      <div class="field full">
        <label class="filedrop" for="photos">
          <strong>Click to upload photos</strong><br>
          <span class="hint">Up to 8 images · JPG, PNG or WebP · max 8MB each</span>
        </label>
        <input id="photos" type="file" name="photos[]" accept="image/*" multiple style="display:none">
        <div id="fileList" class="hint" style="margin-top:.6rem"></div>
      </div>

      <div class="field full">
        <label>Preferred contact method</label>
        <select class="select" name="preferred_contact">
          <option value="phone">Phone call</option>
          <option value="whatsapp">WhatsApp</option>
          <option value="email">Email</option>
        </select>
      </div>

      <?php if (RECAPTCHA_SITE_KEY): ?><div class="g-recaptcha" data-sitekey="<?= e(RECAPTCHA_SITE_KEY) ?>" style="margin-bottom:1rem"></div><?php endif; ?>
      <button class="btn btn--gold btn--block" type="submit">Submit Property</button>
      <p class="hint" style="text-align:center;margin-top:1rem">By submitting, you agree to be contacted by Reside Ke about your listing.</p>
    </form>
  </div>
</div>
<?php
$extraFoot = <<<'HTML'
<script>
(function(){
  var input = document.getElementById('photos'), list = document.getElementById('fileList');
  if(input){
    input.addEventListener('change', function(){
      if(!input.files.length){ list.textContent=''; return; }
      var names = Array.prototype.slice.call(input.files).slice(0,8).map(function(f){ return f.name; });
      list.textContent = input.files.length + ' file(s) selected: ' + names.join(', ');
    });
  }
})();
</script>
HTML;
if (RECAPTCHA_SITE_KEY) $extraFoot .= '<script src="https://www.google.com/recaptcha/api.js" async defer></script>';
include __DIR__ . '/templates/footer.php';
