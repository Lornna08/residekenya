<?php /** Footer partial. */ $__ct = site_contact(); $__so = site_socials(); ?>
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
          <?php foreach ($__so as $so): ?><a href="<?= e($so['url']) ?>" aria-label="<?= e($so['label']) ?>" title="<?= e($so['label']) ?>" target="_blank" rel="noopener"><?= social_icon_svg($so['icon']) ?></a><?php endforeach; ?>
          <a href="mailto:<?= e($__ct['email']) ?>" aria-label="Email" title="Email"><?= social_icon_svg('<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>') ?></a>
        </div>
      </div>
      <div>
        <h4>Quick Links</h4>
        <a href="<?= e(url('listings.php')) ?>">All Listings</a>
        <a href="<?= e(url('listings.php?luxury=1')) ?>">Luxury Homes</a>
        <a href="<?= e(url('listings.php?purpose=airbnb')) ?>">Airbnb Stays</a>
        <a href="<?= e(url('list-property.php')) ?>">List Your Property</a>
        <a href="<?= e(url('about')) ?>">About Us</a>
        <a href="<?= e(url('contact')) ?>">Contact</a>
      </div>
      <div>
        <h4>Property Types</h4>
        <?php foreach (Database::all("SELECT c.name, c.slug FROM categories c WHERE c.is_active = 1 AND EXISTS (SELECT 1 FROM properties p WHERE p.category_id = c.id AND p.status IN ('available','under_offer')) ORDER BY c.sort_order, c.name LIMIT 6") as $__c): ?>
        <a href="<?= e(url('listings.php?category=' . eurl($__c['slug']))) ?>"><?= e($__c['name']) ?></a>
        <?php endforeach; ?>
        <a href="<?= e(url('listings.php?purpose=land')) ?>">Land</a>
      </div>
      <div>
        <h4>Contact</h4>
        <a href="tel:<?= e(preg_replace('/\s+/', '', $__ct['phone'])) ?>"><?= e($__ct['phone']) ?></a>
        <?php if ($__ct['phone2']): ?><a href="tel:<?= e(preg_replace('/\s+/', '', $__ct['phone2'])) ?>"><?= e($__ct['phone2']) ?></a><?php endif; ?>
        <a href="mailto:<?= e($__ct['email']) ?>"><?= e($__ct['email']) ?></a>
        <a href="https://wa.me/<?= e($__ct['whatsapp']) ?>" target="_blank" rel="noopener">WhatsApp us</a>
        <span class="footer-addr"><?= e($__ct['address']) ?></span>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <?= date('Y') ?> Reside Kenya. All rights reserved.</span>
      <nav class="footer-legal" aria-label="Legal"><a href="<?= e(url('privacy')) ?>">Privacy</a><a href="<?= e(url('terms')) ?>">Terms</a><a href="<?= e(url('cookies')) ?>">Cookies</a><a href="<?= e(url('disclaimer')) ?>">Listing disclaimer</a></nav>
    </div>
  </div>
</footer>
<a href="https://wa.me/<?= e($__ct['whatsapp']) ?>" class="wa-float" aria-label="Chat on WhatsApp" target="_blank" rel="noopener"
   style="position:fixed;right:20px;bottom:20px;z-index:120;width:56px;height:56px;border-radius:50%;background:#25D366;display:grid;place-items:center;box-shadow:0 10px 30px -8px rgba(0,0,0,.4)">
  <svg width="28" height="28" viewBox="0 0 24 24" fill="#fff"><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.4A10 10 0 1 0 12 2zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .1-1.7-.1-.4-.1-1-.3-1.6-.6-2.9-1.3-4.8-4.2-4.9-4.4-.1-.2-1.2-1.6-1.2-3 0-1.4.8-2.1 1-2.4.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.3 0 .5l-.4.5c-.2.2-.3.4-.1.7.2.3.9 1.4 1.9 2.3 1.3 1.1 2.3 1.5 2.6 1.6.2.1.4.1.6-.1l.7-.9c.2-.3.4-.2.6-.1l2 1c.3.1.4.2.5.3.1.2.1.9-.1 1.6z"/></svg>
</a>
<script src="<?= e(url('assets/js/main.js')) ?>?v=<?= ASSET_V ?>" defer></script>
<script src="<?= e(url('assets/js/upload-resize.js')) ?>" defer></script>
<?= $extraFoot ?? '' ?>
</body>
</html>
