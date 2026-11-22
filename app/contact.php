<?php
/** contact.php — contact page with form, details, and map. */
require __DIR__ . '/includes/bootstrap.php';

$seo = [
    'title'       => 'Contact Us — ' . SITE_NAME,
    'description' => 'Get in touch with Reside Kenya. Speak to our property consultants about buying, renting, Airbnb stays, or listing your property across Kenya.',
    'canonical'   => url('contact.php'),
];
$activeNav = 'Contact';

$phone    = setting('contact_phone', '+254 117 959 105');
$email    = setting('contact_email', ADMIN_EMAIL);
$ig       = setting('instagram', 'reside254');
$tt       = setting('tiktok', 'reside254');
$wa       = setting('whatsapp_number', '254117959105');
$mapEmbed = setting('office_map_embed', '');
$address  = setting('office_address', 'Nairobi, Kenya');

include __DIR__ . '/templates/header.php';
?>
<section class="page-hero">
  <div class="wrap">
    <p class="eyebrow">We'd love to hear from you</p>
    <h1 class="display">Get in touch</h1>
    <p>Whether you're buying, renting, booking a stay, or listing a property — our team is here to help you every step of the way.</p>
  </div>
</section>

<div class="wrap section">
  <?php if ($f = flash('success')): ?><div class="flash flash--success"><?= e($f) ?></div><?php endif; ?>
  <?php if ($f = flash('error')): ?><div class="flash flash--error"><?= e($f) ?></div><?php endif; ?>

  <div class="contact-split">
    <div>
      <h2 class="display" style="font-size:1.8rem;margin-bottom:1.4rem">Reach us directly</h2>
      <ul class="contact-lines">
        <li>
          <span class="ic"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.6A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.4-1.2a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/></svg></span>
          <div><div class="k">Phone</div><a class="v" href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>"><?= e($phone) ?></a></div>
        </li>
        <li>
          <span class="ic"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></span>
          <div><div class="k">Email</div><a class="v" href="mailto:<?= e($email) ?>"><?= e($email) ?></a></div>
        </li>
        <li>
          <span class="ic"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.4A10 10 0 1 0 12 2z"/></svg></span>
          <div><div class="k">WhatsApp</div><a class="v" href="https://wa.me/<?= e(preg_replace('/\D/', '', $wa)) ?>" target="_blank" rel="noopener">Chat with us</a></div>
        </li>
        <li>
          <span class="ic"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 21s-6-5.3-6-10a6 6 0 1 1 12 0c0 4.7-6 10-6 10z"/><circle cx="12" cy="11" r="2"/></svg></span>
          <div><div class="k">Location</div><span class="v"><?= e($address) ?></span></div>
        </li>
        <li>
          <span class="ic"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg></span>
          <div><div class="k">Social</div><a class="v" href="https://instagram.com/<?= e($ig) ?>" target="_blank" rel="noopener">@<?= e($ig) ?></a> · <a class="v" href="https://tiktok.com/@<?= e($tt) ?>" target="_blank" rel="noopener">TikTok</a></div>
        </li>
      </ul>

      <?php if ($mapEmbed): ?>
        <div style="margin-top:2rem;border-radius:var(--r);overflow:hidden;border:1px solid var(--line)"><?= $mapEmbed ?></div>
      <?php else: ?>
        <iframe class="map-embed" style="margin-top:2rem" loading="lazy" title="Map"
          src="https://maps.google.com/maps?q=<?= eurl($address) ?>&z=11&output=embed"></iframe>
      <?php endif; ?>
    </div>

    <div class="form-card">
      <h2 class="display" style="font-size:1.8rem;margin-bottom:1.4rem">Send us a message</h2>
      <form action="<?= e(url('api/inquiry.php')) ?>" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="type" value="contact">
        <input type="hidden" name="redirect" value="contact.php">
        <div class="form-grid">
          <div class="field"><label>Full name</label><input class="input" type="text" name="name" required></div>
          <div class="field"><label>Phone</label><input class="input" type="tel" name="phone"></div>
        </div>
        <div class="field"><label>Email</label><input class="input" type="email" name="email" required></div>
        <div class="field"><label>Message</label><textarea class="textarea" name="message" placeholder="Tell us what you're looking for…" required></textarea></div>
        <?php if (RECAPTCHA_SITE_KEY): ?><div class="g-recaptcha" data-sitekey="<?= e(RECAPTCHA_SITE_KEY) ?>" style="margin-bottom:1rem"></div><?php endif; ?>
        <button class="btn btn--gold btn--block" type="submit">Send Message</button>
      </form>
    </div>
  </div>
</div>
<?php
$extraFoot = RECAPTCHA_SITE_KEY ? '<script src="https://www.google.com/recaptcha/api.js" async defer></script>' : '';
include __DIR__ . '/templates/footer.php';
