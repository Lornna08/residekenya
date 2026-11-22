<?php
/** about.php — About Us page. */
require __DIR__ . '/includes/bootstrap.php';

$seo = [
    'title'       => 'About Us — ' . SITE_NAME,
    'description' => 'Reside Kenya is a premium Kenyan real estate platform connecting people with quality homes, rentals, commercial spaces, land and Airbnb stays through a trusted, transparent experience.',
    'canonical'   => url('about'),
];
$activeNav = 'About';

$aboutImg = c('about_image') ?: ph_url('living', 1400);

include __DIR__ . '/templates/header.php';
?>
<section class="page-hero">
  <div class="wrap">
    <p class="eyebrow"><?= e(c('about_eyebrow')) ?></p>
    <h1 class="display"><?= e(c('about_title')) ?></h1>
    <p><?= e(c('about_intro')) ?></p>
  </div>
</section>

<div class="wrap section">
  <div class="about-split">
    <p class="about-lead"><?= nl2br(e(c('about_lead'))) ?></p>
    <figure class="about-photo"><img src="<?= e(img_src($aboutImg, 1080)) ?>" srcset="<?= e(ph_srcset($aboutImg, [640, 1080, 1400]) ?: img_srcset($aboutImg, [640, 1080, 1200])) ?>" sizes="(max-width: 900px) 100vw, 46vw" alt="" loading="lazy" decoding="async" width="1080" height="720"></figure>
  </div>

  <div class="hairline" style="margin:clamp(2rem,4vw,3rem) 0"></div>

  <div class="stat-band">
    <div class="stat"><div class="num"><?= e(c('about_stat1_value')) ?></div><div class="lbl"><?= e(c('about_stat1_label')) ?></div></div>
    <div class="stat"><div class="num"><?= e(c('about_stat2_value')) ?></div><div class="lbl"><?= e(c('about_stat2_label')) ?></div></div>
    <div class="stat"><div class="num"><?= e(c('about_stat3_value')) ?></div><div class="lbl"><?= e(c('about_stat3_label')) ?></div></div>
    <div class="stat"><div class="num"><?= e(c('about_stat4_value')) ?></div><div class="lbl"><?= e(c('about_stat4_label')) ?></div></div>
  </div>
</div>

<div class="wrap section section--tight">
  <div class="section-head"><div><p class="eyebrow">What guides us</p><h2 class="display"><?= e(c('about_values_title')) ?></h2></div></div>
  <div class="hairline" style="margin-bottom:2rem"></div>
  <div class="value-grid">
    <div class="value-card">
      <div class="feature__icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2 4 6v6c0 5 3.4 8.5 8 10 4.6-1.5 8-5 8-10V6l-8-4z"/><path d="m9 12 2 2 4-4"/></svg></div>
      <h3><?= e(c('about_v1_title')) ?></h3>
      <p><?= e(c('about_v1_text')) ?></p>
    </div>
    <div class="value-card">
      <div class="feature__icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg></div>
      <h3><?= e(c('about_v2_title')) ?></h3>
      <p><?= e(c('about_v2_text')) ?></p>
    </div>
    <div class="value-card">
      <div class="feature__icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2v20M2 12h20"/><circle cx="12" cy="12" r="9"/></svg></div>
      <h3><?= e(c('about_v3_title')) ?></h3>
      <p><?= e(c('about_v3_text')) ?></p>
    </div>
    <div class="value-card">
      <div class="feature__icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2 15 9l7 .5-5.5 4.5L18 21l-6-3.8L6 21l1.5-7L2 9.5 9 9z"/></svg></div>
      <h3><?= e(c('about_v4_title')) ?></h3>
      <p><?= e(c('about_v4_text')) ?></p>
    </div>
  </div>
</div>

<section class="cta-band">
  <div class="wrap center">
    <h2 class="display"><?= e(c('about_cta_title')) ?></h2>
    <p class="lede"><?= e(c('about_cta_text')) ?></p>
    <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;margin-top:1.6rem">
      <a class="btn btn--gold" href="<?= e(url('listings.php')) ?>">Browse Listings</a>
      <a class="btn btn--light" href="<?= e(url('list-property.php')) ?>">List Your Property</a>
    </div>
  </div>
</section>
<?php include __DIR__ . '/templates/footer.php'; ?>
