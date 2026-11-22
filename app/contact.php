<?php
/** contact.php — contact page with form, details, and map. */
require __DIR__ . '/includes/bootstrap.php';

$seo = [
    'title'       => 'Contact Us — ' . SITE_NAME,
    'description' => 'Get in touch with Reside Kenya. Speak to our property consultants about buying, renting, Airbnb stays, or listing your property across Kenya.',
    'canonical'   => url('contact'),
];
$activeNav = 'Contact';

$ct = site_contact();
$phone = $ct['phone']; $email = $ct['email']; $wa = $ct['whatsapp']; $mapEmbed = $ct['map']; $address = $ct['address'];
$socials = site_socials();

include __DIR__ . '/templates/header.php';
?>
<section class="page-hero">
  <div class="wrap">
    <p class="eyebrow"><?= e(c('contact_eyebrow')) ?></p>
    <h1 class="display"><?= e(c('contact_title')) ?></h1>
    <p><?= e(c('contact_intro')) ?></p>
  </div>
</section>

<div class="wrap section">
  <?php if ($f = flash('success')): ?><div class="flash flash--success"><?= e($f) ?></div><?php endif; ?>
  <?php if ($f = flash('error')): ?><div class="flash flash--error"><?= e($f) ?></div><?php endif; ?>

  <div class="contact-split">
    <div>
      <h2 class="display" style="font-size:1.8rem;margin-bottom:1.4rem"><?= e(c('contact_direct_title')) ?></h2>
      <ul class="contact-lines">
        <li>
          <span class="ic"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.6A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.4-1.2a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/></svg></span>
          <div><div class="k">Phone</div><a class="v" href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>"><?= e($phone) ?></a><?php if ($ct['phone2']): ?><br><a class="v" href="tel:<?= e(preg_replace('/\s+/', '', $ct['phone2'])) ?>"><?= e($ct['phone2']) ?></a><?php endif; ?></div>
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
          <span class="ic"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
          <div><div class="k">Office hours</div><span class="v" style="line-height:1.6"><?= nl2br(e(c('contact_hours'))) ?></span></div>
        </li>
        <?php if ($socials): ?>
        <li>
          <span class="ic"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/></svg></span>
          <div><div class="k">Follow us</div><div class="socials socials--inline"><?php foreach ($socials as $so): ?><a href="<?= e($so['url']) ?>" target="_blank" rel="noopener" aria-label="<?= e($so['label']) ?>" title="<?= e($so['label']) ?>"><?= social_icon_svg($so['icon']) ?></a><?php endforeach; ?></div></div>
        </li>
        <?php endif; ?>
      </ul>

      <?php if ($mapEmbed): ?>
        <div style="margin-top:2rem;border-radius:var(--r);overflow:hidden;border:1px solid var(--line)"><?= $mapEmbed ?></div>
      <?php else: ?>
        <iframe class="map-embed" style="margin-top:2rem" loading="lazy" title="Map"
          src="https://maps.google.com/maps?q=<?= eurl($address) ?>&z=11&output=embed"></iframe>
      <?php endif; ?>
    </div>

    <div class="form-card">
      <h2 class="display" style="font-size:1.8rem;margin-bottom:1.4rem"><?= e(c('contact_form_title')) ?></h2>
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
