<?php
/**
 * render_card($p) — outputs a single property card.
 * $p must contain: slug, title, featured_image, price, currency, purpose,
 * bedrooms, bathrooms, area_sqm, is_featured, county_name, town_name, category_name.
 */
function purpose_badge(string $purpose): array
{
    return match ($purpose) {
        'rent'       => ['For Rent', 'rent'],
        'airbnb'     => ['Airbnb', 'airbnb'],
        'commercial' => ['Commercial', 'commercial'],
        'land'       => ['Land', 'land'],
        default      => ['For Sale', 'sale'],
    };
}

function price_display(array $p): string
{
    $cur = $p['currency'] ?? 'KES';
    if ($p['purpose'] === 'rent') {
        return money($p['rent_price'] ?? $p['price'], $cur) . '<span> / ' . e($p['rent_period'] ?? 'month') . '</span>';
    }
    if ($p['purpose'] === 'airbnb') {
        return money($p['rent_price'] ?? $p['price'], $cur) . '<span> / night</span>';
    }
    return money($p['sale_price'] ?? $p['price'], $cur);
}

function render_card(array $p): void
{
    [$badgeLabel, $badgeClass] = purpose_badge($p['purpose']);
    $loc = trim(($p['town_name'] ?? '') . (!empty($p['county_name']) ? ', ' . $p['county_name'] : ''), ', ');
    $img = asset_url($p['featured_image'] ?? null);
    $href = url('property/' . $p['slug']);
    ?>
    <article class="card reveal">
      <div class="card__media">
        <a href="<?= e($href) ?>" aria-label="<?= e($p['title']) ?>">
          <?php $__im = listing_image($p['featured_image'] ?? null, ($p['purpose'] ?? '') . ' ' . ($p['category_name'] ?? '') . ' ' . ($p['title'] ?? '')); ?><img src="<?= e(img_src($__im, 640)) ?>" srcset="<?= e(ph_srcset($__im, [384, 640, 828]) ?: img_srcset($__im, [384, 640, 828])) ?>" sizes="(max-width: 640px) 92vw, (max-width: 1100px) 46vw, 380px" alt="<?= e($p['title']) ?>" loading="lazy" decoding="async" width="640" height="480">
        </a>
        <div class="card__badges">
          <?php if (!empty($p['is_featured'])): ?><span class="badge badge--featured">Featured</span><?php endif; ?>
          <span class="badge badge--<?= e($badgeClass) ?>"><?= e($badgeLabel) ?></span>
        </div>
        <button class="card__fav" aria-label="Save property" data-fav="<?= (int)$p['id'] ?>">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 21s-7-4.4-9.3-8.5C1 9.5 2.4 6 5.7 6c2 0 3.2 1.2 4.3 2.6C11 7.2 12.2 6 14.3 6 17.6 6 19 9.5 21.3 12.5 19 16.6 12 21 12 21z"/></svg>
        </button>
      </div>
      <div class="card__body">
        <div class="card__price"><?= price_display($p) ?></div>
        <h3 class="card__title"><a href="<?= e($href) ?>"><?= e($p['title']) ?></a></h3>
        <?php if ($loc): ?>
        <p class="card__loc">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 21s-6-5.3-6-10a6 6 0 1 1 12 0c0 4.7-6 10-6 10z"/><circle cx="12" cy="11" r="2"/></svg>
          <?= e($loc) ?>
        </p>
        <?php endif; ?>
        <div class="card__meta">
          <?php if (!empty($p['bedrooms'])): ?>
          <span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 12V7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5"/><path d="M2 17h20M4 12h7v-2a2 2 0 0 1 2-2h6M4 17v2M20 17v2"/></svg><?= (int)$p['bedrooms'] ?> Bd</span>
          <?php endif; ?>
          <?php if (!empty($p['bathrooms'])): ?>
          <span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 12h16v3a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4v-3zM6 12V6a2 2 0 0 1 2-2 2 2 0 0 1 2 2"/></svg><?= (int)$p['bathrooms'] ?> Ba</span>
          <?php endif; ?>
          <?php if (!empty($p['area_sqm'])): ?>
          <span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3h18v18H3zM3 9h18M9 3v18"/></svg><?= number_format((float)$p['area_sqm']) ?> m²</span>
          <?php elseif (!empty($p['land_size_sqm'])): ?>
          <span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3h18v18H3z"/></svg><?= number_format((float)$p['land_size_sqm']) ?> m²</span>
          <?php endif; ?>
        </div>
      </div>
    </article>
    <?php
}
