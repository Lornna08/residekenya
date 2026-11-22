<?php
/**
 * property.php?slug=... — single property detail page.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/templates/card.php';

$slug = trim((string) input('slug', ''));
$p = $slug !== '' ? Property::findBySlug($slug) : null;

if (!$p || in_array($p['status'], ['archived', 'draft'], true)) {
    http_response_code(404);
    $seo = ['title' => 'Property not found — ' . SITE_NAME];
    include __DIR__ . '/templates/header.php';
    echo '<div class="wrap section" style="text-align:center"><p class="eyebrow">404</p><h1 class="display">Property not found</h1><p class="lede">This listing may have been removed or sold.</p><p style="margin-top:1.5rem"><a class="btn btn--gold" href="' . e(url('listings.php')) . '">Browse all listings</a></p></div>';
    include __DIR__ . '/templates/footer.php';
    exit;
}

// Views are counted by a tiny beacon at the end of the page (the page itself may be served from cache).
$images    = Property::images((int) $p['id'], false);
$floorplans = Property::images((int) $p['id'], true);
$amenities = Property::amenities((int) $p['id']);
$openHouses = Property::openHouses((int) $p['id']);
$related   = Property::related($p, 3);

$cur = $p['currency'] ?? 'KES';
$loc = trim(($p['town_name'] ?? '') . (!empty($p['county_name']) ? ', ' . $p['county_name'] : ''), ', ');
[$badgeLabel, $badgeClass] = purpose_badge($p['purpose']);
$isAirbnb = $p['purpose'] === 'airbnb';

// Price for display + mortgage default
$displayPrice = (float) ($p['purpose'] === 'rent' || $isAirbnb ? ($p['rent_price'] ?? $p['price']) : ($p['sale_price'] ?? $p['price']));

// Gallery source (fallback to featured image)
$galleryImgs = $images;
if (!$galleryImgs && !empty($p['featured_image'])) {
    $galleryImgs = [['file_path' => $p['featured_image'], 'alt_text' => $p['title']]];
}

$pageUrl = url('property.php?slug=' . eurl($p['slug']));
$metaDesc = $p['meta_description'] ?: mb_substr(strip_tags($p['description'] ?? ''), 0, 155);
$seo = [
    'title'       => ($p['meta_title'] ?: $p['title']) . ' — ' . SITE_NAME,
    'description' => $metaDesc,
    'canonical'   => $pageUrl,
    'image'       => !empty($galleryImgs) ? asset_url($galleryImgs[0]['file_path']) : url('assets/img/og-default.jpg'),
    'type'        => 'article',
    'keywords'    => $p['seo_keywords'] ?? '',
];
$extraHead = schema_property($p, $galleryImgs);

include __DIR__ . '/templates/header.php';
?>
<div class="wrap pdp">

  <?= breadcrumbs([
      ['label' => 'Home', 'url' => url('/')],
      ['label' => ucfirst($p['purpose']), 'url' => url('listings.php?purpose=' . eurl($p['purpose']))],
      ['label' => $p['title']],
  ]) ?>

  <?php if ($f = flash('success')): ?><div class="flash flash--success" style="margin-top:1rem"><?= e($f) ?></div><?php endif; ?>
  <?php if ($f = flash('error')): ?><div class="flash flash--error" style="margin-top:1rem"><?= e($f) ?></div><?php endif; ?>

  <div class="pdp-head">
    <div>
      <div class="card__badges" style="position:static;margin-bottom:.4rem">
        <?php if (!empty($p['is_featured'])): ?><span class="badge badge--featured">Featured</span><?php endif; ?>
        <span class="badge badge--<?= e($badgeClass) ?>"><?= e($badgeLabel) ?></span>
        <?php if (!empty($p['is_luxury'])): ?><span class="badge badge--featured">Luxury</span><?php endif; ?>
      </div>
      <h1><?= e($p['title']) ?></h1>
      <?php if ($loc || $p['address']): ?>
      <p class="pdp-loc">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 21s-6-5.3-6-10a6 6 0 1 1 12 0c0 4.7-6 10-6 10z"/><circle cx="12" cy="11" r="2"/></svg>
        <?= e($p['address'] ? $p['address'] . ($loc ? ' · ' . $loc : '') : $loc) ?>
      </p>
      <?php endif; ?>
    </div>
    <div class="pdp-price">
      <div class="amt"><?= money($displayPrice ?: null, $cur) ?><?php if ($p['purpose'] === 'rent'): ?><span> / <?= e($p['rent_period'] ?? 'month') ?></span><?php elseif ($isAirbnb): ?><span> / night</span><?php endif; ?></div>
      <div class="ref">Ref <?= e($p['ref_code']) ?></div>
    </div>
  </div>

  <!-- Gallery -->
  <?php if ($galleryImgs): ?>
  <div class="gallery" id="gallery">
    <?php
    $shown = array_slice($galleryImgs, 0, 5);
    foreach ($shown as $i => $img):
        $src = asset_url($img['file_path']);
        $alt = $img['alt_text'] ?: $p['title'];
        $isLast = ($i === 4 && count($galleryImgs) > 5);
    ?>
      <a href="<?= e($src) ?>" class="<?= $i === 0 ? 'g-main' : '' ?>" data-lb="<?= $i ?>" aria-label="View image <?= $i + 1 ?>">
        <img src="<?= e(img_src($img['file_path'], $i === 0 ? 1200 : 828)) ?>" srcset="<?= e(img_srcset($img['file_path'], $i === 0 ? [828, 1200, 1920] : [640, 828])) ?>" sizes="<?= $i === 0 ? '(max-width: 900px) 100vw, 60vw' : '(max-width: 900px) 50vw, 25vw' ?>" alt="<?= e($alt) ?>" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>" decoding="async"<?= $i === 0 ? ' fetchpriority="high"' : '' ?>>
        <?php if ($isLast): ?><span class="g-more">+<?= count($galleryImgs) - 5 ?> photos</span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="pdp-grid">
    <!-- Main column -->
    <div class="pdp-main">

      <!-- Key specs -->
      <div class="pdp-block">
        <div class="spec-grid">
          <?php
          $specs = [];
          if (!empty($p['bedrooms']))       $specs['Bedrooms'] = (int) $p['bedrooms'];
          if (!empty($p['bathrooms']))      $specs['Bathrooms'] = (int) $p['bathrooms'];
          if (!empty($p['area_sqm']))       $specs['Floor Area'] = number_format((float) $p['area_sqm']) . ' m²';
          if (!empty($p['land_size_sqm']))  $specs['Land Size'] = number_format((float) $p['land_size_sqm']) . ' m²';
          if (!empty($p['parking_spaces'])) $specs['Parking'] = (int) $p['parking_spaces'];
          if (!empty($p['living_rooms']))   $specs['Living Rooms'] = (int) $p['living_rooms'];
          if (!empty($p['kitchens']))       $specs['Kitchens'] = (int) $p['kitchens'];
          if (!empty($p['floors']))         $specs['Floors'] = (int) $p['floors'];
          if (!empty($p['year_built']))     $specs['Year Built'] = (int) $p['year_built'];
          if (!empty($p['category_name']))  $specs['Type'] = $p['category_name'];
          if ($isAirbnb && !empty($p['airbnb_max_guests'])) $specs['Max Guests'] = (int) $p['airbnb_max_guests'];
          foreach ($specs as $k => $v):
          ?>
            <div class="spec"><div class="k"><?= e($k) ?></div><div class="v"><?= e((string) $v) ?></div></div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Description -->
      <?php if (!empty($p['description'])): ?>
      <div class="pdp-block">
        <h2>About this property</h2>
        <div class="pdp-desc"><?php
          $paras = preg_split('/\n{2,}/', trim($p['description']));
          foreach ($paras as $para) echo '<p>' . nl2br(e(trim($para))) . '</p>';
        ?></div>
      </div>
      <?php endif; ?>

      <!-- Features -->
      <?php if (!empty($p['features'])): ?>
      <div class="pdp-block">
        <h2>Property features</h2>
        <ul class="feature-list">
          <?php foreach (preg_split('/[\n,]+/', $p['features']) as $feat): $feat = trim($feat); if ($feat === '') continue; ?>
            <li><?= e($feat) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <!-- Amenities -->
      <?php if ($amenities): ?>
      <div class="pdp-block">
        <h2>Amenities</h2>
        <ul class="amenity-list">
          <?php foreach ($amenities as $am): ?><li><?= e($am['name']) ?></li><?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <!-- Airbnb house rules -->
      <?php if ($isAirbnb && !empty($p['airbnb_house_rules'])): ?>
      <div class="pdp-block">
        <h2>House rules</h2>
        <div class="pdp-desc"><p><?= nl2br(e($p['airbnb_house_rules'])) ?></p></div>
        <?php if (!empty($p['airbnb_cleaning_fee'])): ?>
          <p style="color:var(--slate);font-size:.9rem;margin-top:.6rem">Cleaning fee: <?= e(money((float) $p['airbnb_cleaning_fee'], $cur)) ?></p>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Floor plans -->
      <?php if ($floorplans): ?>
      <div class="pdp-block">
        <h2>Floor plans</h2>
        <div class="grid grid--2">
          <?php foreach ($floorplans as $fp): ?>
            <a href="<?= e(asset_url($fp['file_path'])) ?>" target="_blank" rel="noopener">
              <img src="<?= e(asset_url($fp['file_path'])) ?>" alt="Floor plan" loading="lazy" style="width:100%;border:1px solid var(--line);border-radius:var(--r)">
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Video / tours -->
      <?php if (!empty($p['youtube_url']) || !empty($p['virtual_tour_url']) || !empty($p['tour_360_url'])): ?>
      <div class="pdp-block">
        <h2>Video &amp; virtual tour</h2>
        <?php
        // Build a YouTube embed if it's a youtube link
        if (!empty($p['youtube_url'])) {
            $yt = $p['youtube_url'];
            if (preg_match('~(?:youtu\.be/|v=|embed/)([A-Za-z0-9_-]{6,})~', $yt, $m)) {
                echo '<iframe class="video-embed" src="https://www.youtube.com/embed/' . e($m[1]) . '" title="Property video" loading="lazy" allowfullscreen></iframe>';
            } else {
                echo '<p><a class="btn btn--ghost btn--sm" href="' . e($yt) . '" target="_blank" rel="noopener">Watch video</a></p>';
            }
        }
        ?>
        <div class="tour-links" style="margin-top:1rem">
          <?php if (!empty($p['virtual_tour_url'])): ?><a class="btn btn--ghost btn--sm" href="<?= e($p['virtual_tour_url']) ?>" target="_blank" rel="noopener">Virtual tour</a><?php endif; ?>
          <?php if (!empty($p['tour_360_url'])): ?><a class="btn btn--ghost btn--sm" href="<?= e($p['tour_360_url']) ?>" target="_blank" rel="noopener">360° tour</a><?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Nearby -->
      <?php if (!empty($p['nearby_schools']) || !empty($p['nearby_hospitals']) || !empty($p['nearby_shopping'])): ?>
      <div class="pdp-block">
        <h2>What's nearby</h2>
        <div class="nearby-cols">
          <?php foreach (['Schools' => 'nearby_schools', 'Hospitals' => 'nearby_hospitals', 'Shopping' => 'nearby_shopping'] as $lbl => $key): if (empty($p[$key])) continue; ?>
            <div>
              <h3><?= e($lbl) ?></h3>
              <ul class="feature-list" style="grid-template-columns:1fr">
                <?php foreach (preg_split('/[\n,]+/', $p[$key]) as $n): $n = trim($n); if ($n === '') continue; ?><li><?= e($n) ?></li><?php endforeach; ?>
              </ul>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Map -->
      <?php if (!empty($p['latitude']) && !empty($p['longitude'])): ?>
      <div class="pdp-block">
        <h2>Location</h2>
        <iframe class="map-embed" loading="lazy" title="Map"
          src="https://maps.google.com/maps?q=<?= e($p['latitude']) ?>,<?= e($p['longitude']) ?>&z=15&output=embed"></iframe>
      </div>
      <?php endif; ?>

      <!-- Open houses -->
      <?php if ($openHouses): ?>
      <div class="pdp-block">
        <h2>Open house dates</h2>
        <ul class="feature-list" style="grid-template-columns:1fr">
          <?php foreach ($openHouses as $oh): ?>
            <li><?= e(date('l, d M Y · g:i A', strtotime($oh['start_at']))) ?> – <?= e(date('g:i A', strtotime($oh['end_at']))) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <!-- Share -->
      <div class="pdp-block">
        <div class="share-row">
          <span style="font-size:.85rem;color:var(--slate);margin-right:.4rem">Share</span>
          <a href="https://wa.me/?text=<?= eurl($p['title'] . ' ' . $pageUrl) ?>" target="_blank" rel="noopener" aria-label="Share on WhatsApp"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.4A10 10 0 1 0 12 2z"/></svg></a>
          <a href="https://www.facebook.com/sharer/sharer.php?u=<?= eurl($pageUrl) ?>" target="_blank" rel="noopener" aria-label="Share on Facebook"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M14 9h3l.5-3H14V4.5c0-.8.3-1.5 1.5-1.5H18V.2C17.5.1 16.3 0 15 0c-2.7 0-4 1.6-4 4.3V6H8v3h3v9h3V9z"/></svg></a>
          <a href="https://twitter.com/intent/tweet?url=<?= eurl($pageUrl) ?>&text=<?= eurl($p['title']) ?>" target="_blank" rel="noopener" aria-label="Share on X"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h3l-7 8 8 12h-6l-5-7-5 7H2l8-9L2 2h6l4 6 6-6z"/></svg></a>
          <button type="button" data-copy="<?= e($pageUrl) ?>" aria-label="Copy link"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg></button>
          <button type="button" onclick="window.print()" aria-label="Print listing"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2h-2M6 14h12v8H6z"/></svg></button>
          <?php if (!empty($p['brochure_pdf'])): ?>
            <a href="<?= e(asset_url($p['brochure_pdf'])) ?>" target="_blank" rel="noopener" style="width:auto;border-radius:20px;padding:0 1rem;gap:.4rem;font-size:.85rem">Brochure PDF</a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Sidebar -->
    <aside class="pdp-side">
      <?php $waNumber = $p['agent_whatsapp'] ?: setting('whatsapp_number', '254117959105'); ?>

      <!-- Agent / contact card -->
      <div class="agent-card">
        <?php if (!empty($p['agent_name'])): ?>
        <div class="who">
          <img src="<?= e(asset_url($p['agent_photo'] ?? null)) ?>" alt="<?= e($p['agent_name']) ?>" onerror="this.style.display='none'">
          <div>
            <div class="nm"><?= e($p['agent_name']) ?></div>
            <div class="rl"><?= e($p['agent_title'] ?? 'Property Consultant') ?></div>
          </div>
        </div>
        <?php else: ?>
        <div class="who"><div><div class="nm">Reside Kenya</div><div class="rl">Property Consultant</div></div></div>
        <?php endif; ?>

        <div class="contact-actions">
          <a class="btn btn--gold btn--block" href="https://wa.me/<?= e(preg_replace('/\D/', '', $waNumber)) ?>?text=<?= eurl('Hello, I am interested in ' . $p['title'] . ' (' . $p['ref_code'] . ')') ?>" target="_blank" rel="noopener">WhatsApp Enquiry</a>
          <?php if (!empty($p['agent_phone'])): ?>
            <a class="btn btn--ghost btn--block" href="tel:<?= e(preg_replace('/\s+/', '', $p['agent_phone'])) ?>">Call <?= e($p['agent_phone']) ?></a>
          <?php endif; ?>
        </div>

        <hr style="border:0;border-top:1px solid var(--line);margin:1.2rem 0">

        <!-- Contact agent form -->
        <form action="<?= e(url('api/inquiry.php')) ?>" method="post" class="inq-form">
          <?= csrf_field() ?>
          <input type="hidden" name="type" value="agent">
          <input type="hidden" name="property_id" value="<?= (int) $p['id'] ?>">
          <input type="hidden" name="redirect" value="property.php?slug=<?= e(eurl($p['slug'])) ?>">
          <div class="field"><label>Name</label><input class="input" type="text" name="name" required></div>
          <div class="field"><label>Email</label><input class="input" type="email" name="email"></div>
          <div class="field"><label>Phone</label><input class="input" type="tel" name="phone"></div>
          <div class="field"><label>Message</label><textarea class="textarea" name="message" style="min-height:90px"><?= 'I am interested in ' . e($p['title']) . ' (' . e($p['ref_code']) . '). Please send more details.' ?></textarea></div>
          <?php if (RECAPTCHA_SITE_KEY): ?><div class="g-recaptcha" data-sitekey="<?= e(RECAPTCHA_SITE_KEY) ?>" style="margin-bottom:1rem"></div><?php endif; ?>
          <button class="btn btn--gold btn--block" type="submit">Send Enquiry</button>
        </form>
      </div>

      <?php if ($isAirbnb): ?>
      <!-- Booking widget -->
      <div class="agent-card">
        <h3 style="font-family:var(--display);font-size:1.3rem;margin-bottom:.2rem">Request to book</h3>
        <p style="color:var(--slate);font-size:.85rem;margin-bottom:1rem"><?= e(money($displayPrice ?: null, $cur)) ?> / night<?php if (!empty($p['airbnb_cleaning_fee'])): ?> · <?= e(money((float) $p['airbnb_cleaning_fee'], $cur)) ?> cleaning<?php endif; ?></p>
        <form action="<?= e(url('api/booking.php')) ?>" method="post" id="bookingForm"
              data-nightly="<?= e((string) ($displayPrice ?: 0)) ?>" data-cleaning="<?= e((string) ($p['airbnb_cleaning_fee'] ?? 0)) ?>" data-currency="<?= e($cur) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="property_id" value="<?= (int) $p['id'] ?>">
          <input type="hidden" name="redirect" value="property.php?slug=<?= e(eurl($p['slug'])) ?>">
          <div class="form-grid">
            <div class="field"><label>Check-in</label><input class="input" type="date" name="check_in" id="checkIn" required></div>
            <div class="field"><label>Check-out</label><input class="input" type="date" name="check_out" id="checkOut" required></div>
          </div>
          <div class="field"><label>Guests</label><input class="input" type="number" name="guests" min="1" max="<?= (int) ($p['airbnb_max_guests'] ?: 10) ?>" value="1" id="guestCount"></div>
          <div class="field"><label>Full name</label><input class="input" type="text" name="guest_name" required></div>
          <div class="field"><label>Email</label><input class="input" type="email" name="guest_email"></div>
          <div class="field"><label>Phone</label><input class="input" type="tel" name="guest_phone"></div>
          <div id="bookingSummary" style="display:none;background:var(--paper);border:1px solid var(--line);border-radius:var(--r-sm);padding:.8rem;margin-bottom:1rem;font-size:.88rem"></div>
          <button class="btn btn--gold btn--block" type="submit">Request Booking</button>
        </form>
      </div>
      <?php else: ?>
      <!-- Schedule viewing -->
      <div class="agent-card">
        <h3 style="font-family:var(--display);font-size:1.3rem;margin-bottom:1rem">Schedule a viewing</h3>
        <form action="<?= e(url('api/inquiry.php')) ?>" method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="type" value="viewing">
          <input type="hidden" name="property_id" value="<?= (int) $p['id'] ?>">
          <input type="hidden" name="redirect" value="property.php?slug=<?= e(eurl($p['slug'])) ?>">
          <div class="field"><label>Name</label><input class="input" type="text" name="name" required></div>
          <div class="field"><label>Phone</label><input class="input" type="tel" name="phone" required></div>
          <div class="field"><label>Preferred date</label><input class="input" type="date" name="preferred_date"></div>
          <button class="btn btn--ghost btn--block" type="submit">Request Viewing</button>
        </form>
      </div>
      <?php endif; ?>

      <!-- Mortgage calculator (non-airbnb, sale) -->
      <?php if ($p['purpose'] === 'sale' && $displayPrice > 0): ?>
      <div class="mortgage" id="mortgage" data-price="<?= e((string) $displayPrice) ?>" data-currency="<?= e($cur) ?>">
        <h3 style="font-family:var(--display);font-size:1.3rem;margin-bottom:.4rem">Mortgage estimate</h3>
        <div class="field" style="margin-bottom:.6rem">
          <label>Deposit: <span id="mDepositLabel">10%</span></label>
          <input type="range" id="mDeposit" min="0" max="50" value="10" step="5">
        </div>
        <div class="field" style="margin-bottom:.6rem">
          <label>Interest rate: <span id="mRateLabel">13%</span></label>
          <input type="range" id="mRate" min="5" max="25" value="13" step="0.5">
        </div>
        <div class="field" style="margin-bottom:.4rem">
          <label>Term: <span id="mTermLabel">20 yrs</span></label>
          <input type="range" id="mTerm" min="5" max="30" value="20" step="1">
        </div>
        <div class="row"><span style="color:var(--slate);font-size:.85rem">Est. monthly repayment</span><output id="mResult">—</output></div>
        <p style="font-size:.72rem;color:var(--slate);margin-top:.6rem">Indicative only. Speak to your lender for exact figures.</p>
      </div>
      <?php endif; ?>
    </aside>
  </div>

  <!-- Related -->
  <?php if ($related): ?>
  <section class="section section--tight" style="padding-top:clamp(2rem,5vw,4rem)">
    <div class="section-head"><div><p class="eyebrow">Explore more</p><h2 class="display">Related properties</h2></div></div>
    <div class="hairline"></div>
    <div class="grid grid--3" style="margin-top:1.6rem">
      <?php foreach ($related as $r) { $r['currency'] = $r['currency'] ?? 'KES'; render_card($r); } ?>
    </div>
  </section>
  <?php endif; ?>
</div>

<!-- Lightbox -->
<div class="lightbox" id="lightbox" aria-hidden="true">
  <button class="lb-close" aria-label="Close">&times;</button>
  <button class="lb-prev" aria-label="Previous">&#8249;</button>
  <img src="" alt="" id="lbImg">
  <button class="lb-next" aria-label="Next">&#8250;</button>
</div>

<?php
$galleryJson = json_encode(array_map(fn($i) => img_src($i['file_path'], 1920), $galleryImgs), JSON_UNESCAPED_SLASHES);
$extraFoot = <<<HTML
<script>
(function(){
  // Lightbox
  var imgs = {$galleryJson};
  var lb = document.getElementById('lightbox'), lbImg = document.getElementById('lbImg'), idx = 0;
  function show(i){ idx = (i + imgs.length) % imgs.length; lbImg.src = imgs[idx]; }
  document.querySelectorAll('#gallery a[data-lb]').forEach(function(a){
    a.addEventListener('click', function(e){ e.preventDefault(); show(parseInt(a.dataset.lb,10)||0); lb.classList.add('open'); lb.setAttribute('aria-hidden','false'); });
  });
  if (lb) {
    lb.querySelector('.lb-close').addEventListener('click', function(){ lb.classList.remove('open'); lb.setAttribute('aria-hidden','true'); });
    lb.querySelector('.lb-next').addEventListener('click', function(){ show(idx+1); });
    lb.querySelector('.lb-prev').addEventListener('click', function(){ show(idx-1); });
    lb.addEventListener('click', function(e){ if(e.target===lb){ lb.classList.remove('open'); lb.setAttribute('aria-hidden','true'); } });
    document.addEventListener('keydown', function(e){
      if(!lb.classList.contains('open')) return;
      if(e.key==='Escape'){ lb.classList.remove('open'); lb.setAttribute('aria-hidden','true'); }
      if(e.key==='ArrowRight') show(idx+1);
      if(e.key==='ArrowLeft') show(idx-1);
    });
  }

  // Copy link
  document.querySelectorAll('[data-copy]').forEach(function(b){
    b.addEventListener('click', function(){
      var t = b.getAttribute('data-copy');
      if(navigator.clipboard){ navigator.clipboard.writeText(t).then(function(){ b.style.color='#1f4d3a'; }); }
    });
  });

  // Mortgage calculator
  var m = document.getElementById('mortgage');
  if (m) {
    var price = parseFloat(m.dataset.price)||0, cur = m.dataset.currency||'KES';
    var dep = document.getElementById('mDeposit'), rate = document.getElementById('mRate'), term = document.getElementById('mTerm');
    var out = document.getElementById('mResult');
    function fmt(n){ return cur + ' ' + Math.round(n).toLocaleString(); }
    function calc(){
      document.getElementById('mDepositLabel').textContent = dep.value + '%';
      document.getElementById('mRateLabel').textContent = rate.value + '%';
      document.getElementById('mTermLabel').textContent = term.value + ' yrs';
      var principal = price * (1 - dep.value/100);
      var r = (parseFloat(rate.value)/100)/12;
      var n = parseInt(term.value,10)*12;
      var pay = r === 0 ? principal/n : (principal * r) / (1 - Math.pow(1+r,-n));
      out.textContent = fmt(pay);
    }
    [dep,rate,term].forEach(function(el){ el.addEventListener('input', calc); });
    calc();
  }

  // Booking summary + date guards
  var bf = document.getElementById('bookingForm');
  if (bf) {
    var ci = document.getElementById('checkIn'), co = document.getElementById('checkOut');
    var today = new Date().toISOString().split('T')[0];
    ci.min = today;
    var nightly = parseFloat(bf.dataset.nightly)||0, cleaning = parseFloat(bf.dataset.cleaning)||0, cur = bf.dataset.currency||'KES';
    var sum = document.getElementById('bookingSummary');
    function upd(){
      if(ci.value){ co.min = ci.value; }
      if(ci.value && co.value){
        var d1 = new Date(ci.value), d2 = new Date(co.value);
        var nights = Math.round((d2-d1)/86400000);
        if(nights > 0){
          var total = nightly*nights + cleaning;
          sum.style.display='block';
          sum.innerHTML = cur+' '+nightly.toLocaleString()+' × '+nights+' night'+(nights>1?'s':'')+
            (cleaning?'<br>+ '+cur+' '+cleaning.toLocaleString()+' cleaning':'')+
            '<br><strong>Total: '+cur+' '+Math.round(total).toLocaleString()+'</strong>';
        } else { sum.style.display='none'; }
      }
    }
    ci.addEventListener('change', upd); co.addEventListener('change', upd);
  }
})();
</script>
HTML;
if (RECAPTCHA_SITE_KEY) {
    $extraFoot .= '<script src="https://www.google.com/recaptcha/api.js" async defer></script>';
}
$extraFoot = ($extraFoot ?? '') . '<script>try{navigator.sendBeacon("/api/view.php?id=' . (int) $p['id'] . '")}catch(e){}</script>';
include __DIR__ . '/templates/footer.php';
