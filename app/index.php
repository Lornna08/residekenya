<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/templates/card.php';

// Homepage property selections (from Homepage Editor). Empty = automatic.
$hpFeaturedIds = array_filter(array_map('intval', explode(',', (string) setting('hp_featured_ids', ''))));
$hpLuxuryIds   = array_filter(array_map('intval', explode(',', (string) setting('hp_luxury_ids', ''))));
$hpLatestIds   = array_filter(array_map('intval', explode(',', (string) setting('hp_latest_ids', ''))));
$hpFeaturedCount = (int) setting('hp_featured_count', 6) ?: 6;
$hpLuxuryCount   = (int) setting('hp_luxury_count', 3) ?: 3;
$hpLatestCount   = (int) setting('hp_latest_count', 6) ?: 6;
$hpAirbnbCount   = (int) setting('hp_airbnb_count', 3) ?: 3;

$featured = $hpFeaturedIds ? Property::byIds($hpFeaturedIds) : Property::featured($hpFeaturedCount);
$latest   = $hpLatestIds   ? Property::byIds($hpLatestIds)   : Property::latest($hpLatestCount);
$luxury   = $hpLuxuryIds   ? Property::byIds($hpLuxuryIds)   : Property::luxury($hpLuxuryCount);
$airbnb   = Property::byPurpose('airbnb', $hpAirbnbCount);
$categories = Database::all("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order LIMIT 8");
// For each category, find a representative property image:
//   1) explicit category image (set in admin)  2) featured property in that category
//   3) any available property in that category  — used by the homepage tiles.
foreach ($categories as $i => $cat) {
    if (!empty($cat['image'])) { $categories[$i]['tile_image'] = $cat['image']; continue; }
    $pick = Database::row(
        "SELECT featured_image FROM properties
         WHERE category_id = ? AND featured_image IS NOT NULL AND featured_image <> ''
           AND status IN ('available','under_offer')
         ORDER BY is_featured DESC, views DESC, created_at DESC LIMIT 1",
        [$cat['id']]
    );
    $categories[$i]['tile_image'] = $pick['featured_image'] ?? null;
}
$allCategories = Database::all("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY sort_order, name");
$counties   = Database::all("SELECT c.*, (SELECT COUNT(*) FROM properties p WHERE p.county_id=c.id AND p.status='available') AS n FROM counties c WHERE is_active = 1 ORDER BY sort_order LIMIT 10");
$testimonials = Database::all("SELECT * FROM testimonials WHERE is_active = 1 ORDER BY sort_order LIMIT 3");
$allCounties = Database::all("SELECT id, name FROM counties WHERE is_active=1 ORDER BY name");

// ---- Homepage config (from admin Homepage Editor) ----
$DEFAULT_ORDER = ['featured', 'types', 'counties', 'luxury', 'latest', 'airbnb', 'why', 'testimonials', 'cta'];
$hpSaved = json_decode((string) setting('homepage_config', ''), true);
if (!is_array($hpSaved)) $hpSaved = [];
$hpOrder = $hpSaved['order'] ?? $DEFAULT_ORDER;
$hpOrder = array_values(array_filter($hpOrder, fn($k) => in_array($k, $DEFAULT_ORDER, true)));
foreach ($DEFAULT_ORDER as $k) if (!in_array($k, $hpOrder, true)) $hpOrder[] = $k;
$hpSections = $hpSaved['sections'] ?? [];
/** Helpers to fetch per-section config with fallback. */
function hp_visible(string $key): bool {
    global $hpSections;
    return array_key_exists($key, $hpSections) ? !empty($hpSections[$key]['visible']) : true;
}
function hp_text(string $key, string $field, string $default): string {
    global $hpSections;
    $v = $hpSections[$key][$field] ?? '';
    return $v !== '' ? $v : $default;
}
// Hero text (editable)
$heroEyebrow = setting('hero_eyebrow', "Kenya's Premium Property Platform");
$heroHeading = setting('hero_heading', 'Find a home worth coming home to.');
$heroSub = setting('hero_sub', 'Houses, apartments, villas, land, commercial spaces and Airbnb stays — curated, verified, and beautifully presented across Kenya.');
$heroImage = setting('hero_image', '') ?: 'assets/img/hero.jpg';

$seo = [
  'title' => setting('site_name', SITE_NAME) . ' — Premium Homes & Property Across Kenya',
  'description' => setting('meta_description'),
];
$activeNav = '';
require __DIR__ . '/templates/header.php';
?>
<script>window.RESIDE_BASE = "<?= e(rtrim(BASE_URL,'/')) ?>";</script>

<!-- HERO + SEARCH -->
<section class="hero">
  <div class="hero__bg">
    <img src="<?= e(asset_url($heroImage)) ?>" alt="Luxury Kenyan home" fetchpriority="high" width="1920" height="1080">
  </div>
  <div class="wrap hero__inner">
    <span class="eyebrow"><?= e($heroEyebrow) ?></span>
    <h1><?= e($heroHeading) ?></h1>
    <p class="lede"><?= e($heroSub) ?></p>

    <form class="search-panel" action="<?= e(url('listings.php')) ?>" method="get" role="search">
      <div class="search-tabs" role="tablist" aria-label="Search purpose">
        <button type="button" class="active" data-purpose="sale">Buy</button>
        <button type="button" data-purpose="rent">Rent</button>
        <button type="button" data-purpose="airbnb">Airbnb</button>
      </div>
      <input type="hidden" name="purpose" id="searchPurpose" value="sale">
      <div class="search-grid">
        <div class="search-field">
          <label for="s-county">County</label>
          <select id="s-county" name="county_id" data-county-select>
            <option value="">Any county</option>
            <?php foreach ($allCounties as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="search-field">
          <label for="s-town">Town</label>
          <select id="s-town" name="town_id" data-town-select>
            <option value="">Any town</option>
          </select>
        </div>
        <div class="search-field">
          <label for="s-type">Property Type</label>
          <select id="s-type" name="category_id">
            <option value="">Any type</option>
            <?php foreach ($allCategories as $cat): ?>
              <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="search-field">
          <label for="s-beds">Bedrooms</label>
          <select id="s-beds" name="bedrooms">
            <option value="">Any</option>
            <?php for ($i=1;$i<=6;$i++): ?><option value="<?= $i ?>"><?= $i ?>+</option><?php endfor; ?>
          </select>
        </div>
        <div class="search-advanced">
          <div class="search-field">
            <label for="s-baths">Bathrooms</label>
            <select id="s-baths" name="bathrooms">
              <option value="">Any</option>
              <?php for ($i=1;$i<=6;$i++): ?><option value="<?= $i ?>"><?= $i ?>+</option><?php endfor; ?>
            </select>
          </div>
          <div class="search-field">
            <label for="s-min">Min Price (KES)</label>
            <input id="s-min" type="number" name="min_price" placeholder="0" min="0" step="100000">
          </div>
          <div class="search-field">
            <label for="s-max">Max Price (KES)</label>
            <input id="s-max" type="number" name="max_price" placeholder="Any" min="0" step="100000">
          </div>
          <div class="search-field">
            <label for="s-area">Min Area (m²)</label>
            <input id="s-area" type="number" name="min_area" placeholder="Any" min="0">
          </div>
          <div class="search-field">
            <label for="s-kw">Keyword</label>
            <input id="s-kw" type="text" name="keyword" placeholder="e.g. sea view, gated">
          </div>
        </div>
        <div class="search-submit">
          <button type="button" class="search-toggle-adv">More filters</button>
          <button type="submit" class="btn btn--gold">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            Search Properties
          </button>
        </div>
      </div>
    </form>
  </div>
</section>

<?php
/* ---- Capture each section's HTML into $blocks, then emit in configured order ---- */
$blocks = [];
$capture = function (string $key) use (&$blocks) {
    ob_start();
    return $key;
};
$endcap = function (string $key) use (&$blocks) {
    $blocks[$key] = ob_get_clean();
};
?>

<!-- FEATURED -->
<?php $capture('featured'); ?>
<?php if ($featured): ?>
<section class="section wrap">
  <div class="section-head">
    <div>
      <span class="eyebrow"><?= e(hp_text('featured', 'eyebrow', 'Handpicked')) ?></span>
      <h2><?= e(hp_text('featured', 'heading', 'Featured Listings')) ?></h2>
    </div>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('listings.php?featured=1')) ?>">View all featured</a>
  </div>
  <div class="grid grid--3">
    <?php foreach ($featured as $p) render_card($p); ?>
  </div>
</section>
<?php endif; ?>
<?php $endcap('featured'); ?>

<!-- CATEGORIES -->
<?php $capture('types'); ?>
<section class="section section--tight" style="background:var(--paper)">
  <div class="wrap">
    <div class="section-head center" style="justify-content:center">
      <div>
        <span class="eyebrow"><?= e(hp_text('types', 'eyebrow', 'Explore')) ?></span>
        <h2><?= e(hp_text('types', 'heading', 'Browse by Property Type')) ?></h2>
      </div>
    </div>
    <div class="cat-grid">
      <?php foreach ($categories as $cat): ?>
        <a class="cat-tile reveal" href="<?= e(url('listings.php?category=' . eurl($cat['slug']))) ?>">
          <img src="<?= e(!empty($cat['tile_image']) ? asset_url($cat['tile_image']) : url('assets/img/cat/' . $cat['slug'] . '.jpg')) ?>" alt="<?= e($cat['name']) ?>" loading="lazy" width="400" height="533">
          <div>
            <h3><?= e($cat['name']) ?></h3>
            <span><?= e($cat['description'] ?? 'View listings') ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php $endcap('types'); ?>

<!-- COUNTIES -->
<?php $capture('counties'); ?>
<section class="section wrap">
  <div class="section-head">
    <div>
      <span class="eyebrow"><?= e(hp_text('counties', 'eyebrow', 'Where to')) ?></span>
      <h2><?= e(hp_text('counties', 'heading', 'Browse by County')) ?></h2>
    </div>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('listings.php')) ?>">All locations</a>
  </div>
  <div class="county-grid">
    <?php foreach ($counties as $c): ?>
      <a class="county-pill reveal" href="<?= e(url('listings.php?county=' . eurl($c['slug']))) ?>">
        <div>
          <b><?= e($c['name']) ?></b>
          <div style="font-size:.78rem;color:var(--slate)"><?= (int)$c['n'] ?> listings</div>
        </div>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php $endcap('counties'); ?>

<!-- LUXURY -->
<?php $capture('luxury'); ?>
<?php if ($luxury): ?>
<section class="section" style="background:var(--ink);color:#fff">
  <div class="wrap">
    <div class="section-head">
      <div>
        <span class="eyebrow" style="color:var(--gold)"><?= e(hp_text('luxury', 'eyebrow', 'The finest')) ?></span>
        <h2 style="color:#fff"><?= e(hp_text('luxury', 'heading', 'Luxury Collection')) ?></h2>
      </div>
      <a class="btn btn--gold btn--sm" href="<?= e(url('listings.php?luxury=1')) ?>">Explore luxury</a>
    </div>
    <div class="grid grid--3">
      <?php foreach ($luxury as $p) render_card($p); ?>
    </div>
  </div>
</section>
<?php endif; ?>
<?php $endcap('luxury'); ?>

<!-- LATEST -->
<?php $capture('latest'); ?>
<?php if ($latest): ?>
<section class="section wrap">
  <div class="section-head">
    <div>
      <span class="eyebrow"><?= e(hp_text('latest', 'eyebrow', 'Just added')) ?></span>
      <h2><?= e(hp_text('latest', 'heading', 'Latest Listings')) ?></h2>
    </div>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('listings.php?sort=newest')) ?>">See what's new</a>
  </div>
  <div class="grid grid--3">
    <?php foreach ($latest as $p) render_card($p); ?>
  </div>
</section>
<?php endif; ?>
<?php $endcap('latest'); ?>

<!-- AIRBNB -->
<?php $capture('airbnb'); ?>
<?php if ($airbnb): ?>
<section class="section section--tight" style="background:var(--paper)">
  <div class="wrap">
    <div class="section-head">
      <div>
        <span class="eyebrow"><?= e(hp_text('airbnb', 'eyebrow', 'Short stays')) ?></span>
        <h2><?= e(hp_text('airbnb', 'heading', 'Airbnb & Holiday Homes')) ?></h2>
      </div>
      <a class="btn btn--ghost btn--sm" href="<?= e(url('listings.php?purpose=airbnb')) ?>">Browse stays</a>
    </div>
    <div class="grid grid--3">
      <?php foreach ($airbnb as $p) render_card($p); ?>
    </div>
  </div>
</section>
<?php endif; ?>
<?php $endcap('airbnb'); ?>

<!-- WHY CHOOSE -->
<?php $capture('why'); ?>
<section class="section wrap">
  <div class="section-head center" style="justify-content:center">
    <div>
      <span class="eyebrow"><?= e(hp_text('why', 'eyebrow', 'Why Reside Ke')) ?></span>
      <h2><?= e(hp_text('why', 'heading', 'Property, done properly.')) ?></h2>
      <p style="margin-inline:auto">A trusted, transparent, customer-focused platform built for the Kenyan market.</p>
    </div>
  </div>
  <div class="feature-grid">
    <?php
    $feats = [
      ['Verified Listings','Every property is checked before it goes live, so what you see is what you get.','<path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/>'],
      ['Trusted Agents','Work with experienced, professional consultants who know the local market.','<path d="M16 21v-2a4 4 0 0 0-8 0v2"/><circle cx="12" cy="7" r="4"/>'],
      ['Secure Transactions','Guidance and safeguards at every step to protect your investment.','<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 1 1 8 0v3"/>'],
      ['Professional Photography','Homes presented beautifully with high-quality imagery and virtual tours.','<rect x="3" y="6" width="18" height="14" rx="2"/><circle cx="12" cy="13" r="3.5"/>'],
      ['Wide Selection','From apartments to villas, land to Airbnb — options across all of Kenya.','<path d="M3 21V9l9-6 9 6v12"/><path d="M9 21v-6h6v6"/>'],
      ['Expert Support','Real people ready to help you buy, rent, sell or list with confidence.','<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'],
    ];
    foreach ($feats as $f): ?>
      <div class="feature reveal">
        <div class="feature__icon">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><?= $f[2] ?></svg>
        </div>
        <h3><?= e($f[0]) ?></h3>
        <p><?= e($f[1]) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php $endcap('why'); ?>

<!-- TESTIMONIALS -->
<?php $capture('testimonials'); ?>
<?php if ($testimonials): ?>
<section class="section" style="background:var(--paper)">
  <div class="wrap">
    <div class="section-head center" style="justify-content:center">
      <div>
        <span class="eyebrow"><?= e(hp_text('testimonials', 'eyebrow', 'Kind words')) ?></span>
        <h2><?= e(hp_text('testimonials', 'heading', 'Loved by our clients')) ?></h2>
      </div>
    </div>
    <div class="tstack">
      <?php foreach ($testimonials as $t): ?>
        <figure class="tcard reveal">
          <div class="stars" aria-label="<?= (int)$t['rating'] ?> out of 5"><?= str_repeat('★', (int)$t['rating']) ?></div>
          <blockquote>“<?= e($t['content']) ?>”</blockquote>
          <figcaption class="who">
            <div><b><?= e($t['name']) ?></b><br><span><?= e($t['role']) ?></span></div>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
<?php $endcap('testimonials'); ?>

<!-- CTA -->
<?php $capture('cta'); ?>
<section class="section wrap">
  <div class="cta-band reveal">
    <img src="<?= e(url('assets/img/cta.jpg')) ?>" alt="" loading="lazy" width="1600" height="700">
    <span class="eyebrow" style="color:var(--gold);justify-content:center"><?= e(hp_text('cta', 'eyebrow', 'Sell or rent with us')) ?></span>
    <h2><?= e(hp_text('cta', 'heading', 'Looking to sell or rent your property?')) ?></h2>
    <p>List with Reside Ke and reach serious, ready buyers and tenants across Kenya. Professional presentation, honest advice, real results.</p>
    <a class="btn btn--gold" href="<?= e(url('list-property.php')) ?>">List Your Property</a>
  </div>
</section>
<?php $endcap('cta'); ?>

<?php
/* ---- Emit sections in configured order, skipping hidden ones ---- */
foreach ($hpOrder as $key) {
    if (!hp_visible($key)) continue;
    echo $blocks[$key] ?? '';
}

/* ---- Custom blocks (admin-created) ---- */
$customBlocks = json_decode((string) setting('homepage_custom_blocks', ''), true);
if (is_array($customBlocks)) {
    foreach ($customBlocks as $cb) {
        if (empty($cb['visible'])) continue;
        $title = trim((string) ($cb['title'] ?? ''));
        $body  = trim((string) ($cb['body'] ?? ''));
        if ($title === '' && $body === '') continue;
        $img = $cb['image'] ?? '';
        ?>
        <section class="section wrap">
          <div class="custom-block reveal">
            <?php if ($img): ?><img class="custom-block__img" src="<?= e(asset_url($img)) ?>" alt="<?= e($title) ?>" loading="lazy"><?php endif; ?>
            <div class="custom-block__text">
              <?php if ($title): ?><h2><?= e($title) ?></h2><?php endif; ?>
              <?php if ($body): ?><p><?= nl2br(e($body)) ?></p><?php endif; ?>
            </div>
          </div>
        </section>
        <?php
    }
}
?>

<?php require __DIR__ . '/templates/footer.php'; ?>
