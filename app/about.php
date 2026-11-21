<?php
/** about.php — About Us page. */
require __DIR__ . '/includes/bootstrap.php';

$seo = [
    'title'       => 'About Us — ' . SITE_NAME,
    'description' => 'Reside Ke is a premium Kenyan real estate platform connecting people with quality homes, rentals, commercial spaces, land and Airbnb stays through a trusted, transparent experience.',
    'canonical'   => url('about.php'),
];
$activeNav = 'About';

// Live stats
// About-page stats are editable in admin (Settings). Fall back to sensible defaults.
$statValue1 = setting('about_stat1_value', '300+');
$statLabel1 = setting('about_stat1_label', 'Listings');
$statValue2 = setting('about_stat2_value', '20+');
$statLabel2 = setting('about_stat2_label', 'Counties covered');
$statValue3 = setting('about_stat3_value', '20+');
$statLabel3 = setting('about_stat3_label', 'Consultants');
$statValue4 = setting('about_stat4_value', '100%');
$statLabel4 = setting('about_stat4_label', 'Verified process');

include __DIR__ . '/templates/header.php';
?>
<section class="page-hero">
  <div class="wrap">
    <p class="eyebrow">Our story</p>
    <h1 class="display">Property, done properly</h1>
    <p>Reside Ke was built on a simple belief: finding a home, an investment, or the perfect stay should feel effortless, transparent, and genuinely premium.</p>
  </div>
</section>

<div class="wrap section">
  <p class="about-lead">
    We connect people across Kenya with quality homes, rentals, commercial spaces, land, and curated Airbnb stays — pairing a refined, editorial browsing experience with the honesty and responsiveness that property decisions deserve.
  </p>

  <div class="hairline" style="margin:clamp(2rem,4vw,3rem) 0"></div>

  <div class="stat-band">
    <div class="stat"><div class="num"><?= e($statValue1) ?></div><div class="lbl"><?= e($statLabel1) ?></div></div>
    <div class="stat"><div class="num"><?= e($statValue2) ?></div><div class="lbl"><?= e($statLabel2) ?></div></div>
    <div class="stat"><div class="num"><?= e($statValue3) ?></div><div class="lbl"><?= e($statLabel3) ?></div></div>
    <div class="stat"><div class="num"><?= e($statValue4) ?></div><div class="lbl"><?= e($statLabel4) ?></div></div>
  </div>
</div>

<div class="wrap section section--tight">
  <div class="section-head"><div><p class="eyebrow">What guides us</p><h2 class="display">Our values</h2></div></div>
  <div class="hairline" style="margin-bottom:2rem"></div>
  <div class="value-grid">
    <div class="value-card">
      <div class="feature__icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2 4 6v6c0 5 3.4 8.5 8 10 4.6-1.5 8-5 8-10V6l-8-4z"/><path d="m9 12 2 2 4-4"/></svg></div>
      <h3>Trust &amp; transparency</h3>
      <p>Every listing is presented honestly, with clear pricing and accurate detail — no surprises, no inflated promises.</p>
    </div>
    <div class="value-card">
      <div class="feature__icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg></div>
      <h3>People first</h3>
      <p>Behind every search is a real goal — a first home, a smart investment, a memorable stay. We treat each one with care.</p>
    </div>
    <div class="value-card">
      <div class="feature__icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2v20M2 12h20"/><circle cx="12" cy="12" r="9"/></svg></div>
      <h3>Nationwide reach</h3>
      <p>From Nairobi to the coast and beyond, we bring quality property from across Kenya into one refined platform.</p>
    </div>
    <div class="value-card">
      <div class="feature__icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2 15 9l7 .5-5.5 4.5L18 21l-6-3.8L6 21l1.5-7L2 9.5 9 9z"/></svg></div>
      <h3>Premium standard</h3>
      <p>A considered, elegant experience — because browsing property should feel as good as owning it.</p>
    </div>
  </div>
</div>

<section class="cta-band">
  <div class="wrap center">
    <h2 class="display">Ready to find your next address?</h2>
    <p class="lede">Browse our curated collection or list your own property with Reside Ke today.</p>
    <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;margin-top:1.6rem">
      <a class="btn btn--gold" href="<?= e(url('listings.php')) ?>">Browse Listings</a>
      <a class="btn btn--light" href="<?= e(url('list-property.php')) ?>">List Your Property</a>
    </div>
  </div>
</section>
<?php include __DIR__ . '/templates/footer.php'; ?>
