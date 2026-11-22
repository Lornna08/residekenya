<?php /** Footer partial. */ ?>
</main>
<footer class="site-footer">
  <div class="wrap">
    <div class="footer-grid">
      <div class="footer-brand">
        <a class="brand" href="<?= e(url('/')) ?>" aria-label="Reside Kenya home"><img class="brand-mark" src="/assets/img/mark-light.png" alt="" width="44" height="48"><span class="brand-word">Reside <b>Kenya</b></span></a>
        <p>Connecting people with quality homes, rentals, investment properties, commercial spaces and Airbnb stays across Kenya — through a trusted, transparent platform.</p>
        <div class="newsletter">
          <form id="newsletterForm" style="display:flex;gap:.5rem;flex:1" novalidate>
            <?= csrf_field() ?>
            <input type="email" name="email" placeholder="Your email for new listings" aria-label="Email" required>
            <button class="btn btn--gold btn--sm" type="submit">Join</button>
          </form>
        </div>
        <div class="socials">
          <a href="https://instagram.com/<?= e(setting('instagram','reside254')) ?>" aria-label="Instagram" target="_blank" rel="noopener">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
          </a>
          <a href="https://tiktok.com/@<?= e(setting('tiktok','reside254')) ?>" aria-label="TikTok" target="_blank" rel="noopener">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M16 3c.3 2 1.6 3.6 3.6 3.9v2.6c-1.3.1-2.6-.3-3.6-1v6.1c0 3-2.4 5.4-5.4 5.4S5.2 17.6 5.2 14.6 7.6 9.2 10.6 9.2c.3 0 .6 0 .9.1v2.7c-.3-.1-.6-.2-.9-.2-1.4 0-2.6 1.2-2.6 2.7s1.2 2.7 2.6 2.7 2.7-1.2 2.7-2.7V3H16z"/></svg>
          </a>
          <a href="mailto:<?= e(setting('contact_email', ADMIN_EMAIL)) ?>" aria-label="Email">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
          </a>
        </div>
      </div>
      <div>
        <h4>Quick Links</h4>
        <a href="<?= e(url('listings.php')) ?>">All Listings</a>
        <a href="<?= e(url('listings.php?luxury=1')) ?>">Luxury Homes</a>
        <a href="<?= e(url('listings.php?purpose=airbnb')) ?>">Airbnb Stays</a>
        <a href="<?= e(url('list-property.php')) ?>">List Your Property</a>
        <a href="<?= e(url('about.php')) ?>">About Us</a>
      </div>
      <div>
        <h4>Property Types</h4>
        <a href="<?= e(url('listings.php?category=apartments')) ?>">Apartments</a>
        <a href="<?= e(url('listings.php?category=villas')) ?>">Luxury Villas</a>
        <a href="<?= e(url('listings.php?category=townhouses')) ?>">Townhouses</a>
        <a href="<?= e(url('listings.php?purpose=commercial')) ?>">Commercial</a>
        <a href="<?= e(url('listings.php?purpose=land')) ?>">Land</a>
      </div>
      <div>
        <h4>Contact</h4>
        <a href="tel:<?= e(preg_replace('/\s+/','',setting('contact_phone'))) ?>"><?= e(setting('contact_phone','+254 117 959 105')) ?></a>
        <a href="mailto:<?= e(setting('contact_email', ADMIN_EMAIL)) ?>"><?= e(setting('contact_email', ADMIN_EMAIL)) ?></a>
        <a href="https://instagram.com/<?= e(setting('instagram','reside254')) ?>" target="_blank" rel="noopener">@<?= e(setting('instagram','reside254')) ?> · Instagram</a>
        <a href="https://tiktok.com/@<?= e(setting('tiktok','reside254')) ?>" target="_blank" rel="noopener">@<?= e(setting('tiktok','reside254')) ?> · TikTok</a>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <?= date('Y') ?> Reside Kenya. All rights reserved.</span>
      <span>Premium property across Kenya.</span>
    </div>
  </div>
</footer>
<a href="https://wa.me/<?= e(setting('whatsapp_number','254117959105')) ?>" class="wa-float" aria-label="Chat on WhatsApp" target="_blank" rel="noopener"
   style="position:fixed;right:20px;bottom:20px;z-index:120;width:56px;height:56px;border-radius:50%;background:#25D366;display:grid;place-items:center;box-shadow:0 10px 30px -8px rgba(0,0,0,.4)">
  <svg width="28" height="28" viewBox="0 0 24 24" fill="#fff"><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.4A10 10 0 1 0 12 2zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .1-1.7-.1-.4-.1-1-.3-1.6-.6-2.9-1.3-4.8-4.2-4.9-4.4-.1-.2-1.2-1.6-1.2-3 0-1.4.8-2.1 1-2.4.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.3 0 .5l-.4.5c-.2.2-.3.4-.1.7.2.3.9 1.4 1.9 2.3 1.3 1.1 2.3 1.5 2.6 1.6.2.1.4.1.6-.1l.7-.9c.2-.3.4-.2.6-.1l2 1c.3.1.4.2.5.3.1.2.1.9-.1 1.6z"/></svg>
</a>
<script src="<?= e(url('assets/js/main.js')) ?>?v=<?= ASSET_V ?>" defer></script>
<script src="<?= e(url('assets/js/upload-resize.js')) ?>" defer></script>
<?= $extraFoot ?? '' ?>
</body>
</html>
