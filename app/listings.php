<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/templates/card.php';

$filters = [
  'purpose'     => input('purpose'),
  'category'    => input('category'),
  'category_id' => input_int('category_id'),
  'county'      => input('county'),
  'county_id'   => input_int('county_id'),
  'town_id'     => input_int('town_id'),
  'bedrooms'    => input_int('bedrooms'),
  'bathrooms'   => input_int('bathrooms'),
  'min_price'   => input_float('min_price'),
  'max_price'   => input_float('max_price'),
  'min_area'    => input_float('min_area'),
  'max_area'    => input_float('max_area'),
  'featured'    => input_int('featured'),
  'luxury'      => input_int('luxury'),
  'keyword'     => input('keyword'),
  'sort'        => input('sort', ''),
  'page'        => input_int('page', 1),
  'per_page'    => 12,
];

$result = Property::search($filters);
$categories = Database::all("SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order");
$counties   = Database::all("SELECT id, name, slug FROM counties WHERE is_active=1 ORDER BY name");

$titleBits = [];
if ($filters['purpose']) $titleBits[] = ucfirst($filters['purpose']);
$pageTitle = ($titleBits ? implode(' ', $titleBits) . ' — ' : '') . 'Properties in Kenya';

$seo = [
  'title' => $pageTitle . ' | ' . setting('site_name', SITE_NAME),
  'description' => 'Browse ' . strtolower($pageTitle) . ' on Reside Kenya. Filter by county, type, price and more.',
];
$activeNav = match($filters['purpose']) {
  'sale' => 'Buy', 'rent' => 'Rent', 'airbnb' => 'Airbnb',
  'commercial' => 'Commercial', 'land' => 'Land', default => ''
};
require __DIR__ . '/templates/header.php';

// Helper to keep filters in pagination links
function qs_page(int $p): string {
  $q = $_GET; $q['page'] = $p;
  return 'listings.php?' . http_build_query($q);
}
?>
<script>window.RESIDE_BASE = "<?= e(rtrim(BASE_URL,'/')) ?>";</script>

<section class="wrap" style="padding-top:2rem">
  <?= breadcrumbs([
    ['label'=>'Home','url'=>url('/')],
    ['label'=>$pageTitle],
  ]) ?>
</section>

<section class="wrap section--tight" style="padding-top:1rem;display:grid;grid-template-columns:280px 1fr;gap:2.4rem;align-items:start">
  <!-- FILTERS -->
  <aside class="filters" style="position:sticky;top:96px;border:1px solid var(--line);border-radius:var(--r);padding:1.5rem">
    <button type="button" class="filters-toggle" onclick="this.parentNode.classList.toggle('open')" aria-expanded="false"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M7 12h10M10 18h4"/></svg>Filters &amp; search<span><?= number_format($result['total']) ?> found</span></button>
    <form method="get" action="<?= e(url('listings.php')) ?>">
      <h3 style="font-family:var(--body);font-size:.78rem;letter-spacing:.14em;text-transform:uppercase;color:var(--gold-deep);margin-bottom:1rem">Refine</h3>

      <div class="search-field" style="margin-bottom:1rem">
        <label>Purpose</label>
        <select name="purpose">
          <option value="">All</option>
          <?php foreach (['sale'=>'For Sale','rent'=>'For Rent','airbnb'=>'Airbnb','commercial'=>'Commercial','land'=>'Land'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= $filters['purpose']===$k?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="search-field" style="margin-bottom:1rem">
        <label>Property Type</label>
        <select name="category_id">
          <option value="">Any type</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>" <?= $filters['category_id']==$cat['id']?'selected':'' ?>><?= e($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="search-field" style="margin-bottom:1rem">
        <label>County</label>
        <select name="county_id" data-county-select>
          <option value="">Any county</option>
          <?php foreach ($counties as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $filters['county_id']==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem;margin-bottom:1rem">
        <div class="search-field"><label>Beds</label>
          <select name="bedrooms"><option value="">Any</option>
            <?php for($i=1;$i<=6;$i++):?><option value="<?=$i?>" <?=$filters['bedrooms']==$i?'selected':''?>><?=$i?>+</option><?php endfor;?>
          </select>
        </div>
        <div class="search-field"><label>Baths</label>
          <select name="bathrooms"><option value="">Any</option>
            <?php for($i=1;$i<=6;$i++):?><option value="<?=$i?>" <?=$filters['bathrooms']==$i?'selected':''?>><?=$i?>+</option><?php endfor;?>
          </select>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem;margin-bottom:1rem">
        <div class="search-field"><label>Min KES</label><input type="number" name="min_price" value="<?= e((string)($filters['min_price']??'')) ?>" step="100000" min="0"></div>
        <div class="search-field"><label>Max KES</label><input type="number" name="max_price" value="<?= e((string)($filters['max_price']??'')) ?>" step="100000" min="0"></div>
      </div>

      <div class="search-field" style="margin-bottom:1rem">
        <label>Keyword</label>
        <input type="text" name="keyword" value="<?= e($filters['keyword']??'') ?>" placeholder="sea view, gated…">
      </div>

      <label style="display:flex;align-items:center;gap:.5rem;font-size:.86rem;margin-bottom:.6rem">
        <input type="checkbox" name="luxury" value="1" <?= $filters['luxury']?'checked':'' ?>> Luxury only
      </label>
      <label style="display:flex;align-items:center;gap:.5rem;font-size:.86rem;margin-bottom:1.2rem">
        <input type="checkbox" name="featured" value="1" <?= $filters['featured']?'checked':'' ?>> Featured only
      </label>

      <button class="btn btn--gold btn--block" type="submit">Apply Filters</button>
      <a class="btn btn--ghost btn--block" style="margin-top:.6rem" href="<?= e(url('listings.php')) ?>">Reset</a>
    </form>
  </aside>

  <!-- RESULTS -->
  <div>
    <div class="section-head" style="margin-bottom:1.4rem">
      <div>
        <h2 style="font-size:1.8rem"><?= e($pageTitle) ?></h2>
        <p style="color:var(--slate)"><?= number_format($result['total']) ?> propert<?= $result['total']===1?'y':'ies' ?> found</p>
      </div>
      <form method="get" style="display:flex;align-items:center;gap:.5rem">
        <?php foreach ($_GET as $k=>$v): if($k==='sort')continue; ?>
          <input type="hidden" name="<?= e($k) ?>" value="<?= e(is_array($v)?'':$v) ?>">
        <?php endforeach; ?>
        <label for="sort" style="font-size:.82rem;color:var(--slate)">Sort</label>
        <select id="sort" name="sort" onchange="this.form.submit()" class="sort-select">
          <option value="">Featured</option>
          <option value="newest"     <?= $filters['sort']==='newest'?'selected':'' ?>>Newest</option>
          <option value="price_asc"  <?= $filters['sort']==='price_asc'?'selected':'' ?>>Price: Low to High</option>
          <option value="price_desc" <?= $filters['sort']==='price_desc'?'selected':'' ?>>Price: High to Low</option>
        </select>
      </form>
    </div>

    <?php if (!$result['items']): ?>
      <div style="text-align:center;padding:4rem 1rem;border:1px dashed var(--line);border-radius:var(--r)">
        <h3 style="font-size:1.4rem;margin-bottom:.5rem">No properties match your search</h3>
        <p style="color:var(--slate);margin-bottom:1.4rem">Try widening your filters or resetting them.</p>
        <a class="btn btn--ghost" href="<?= e(url('listings.php')) ?>">Reset filters</a>
      </div>
    <?php else: ?>
      <div class="grid grid--3">
        <?php foreach ($result['items'] as $p) render_card($p); ?>
      </div>

      <?php if ($result['pages'] > 1): ?>
      <nav class="pagination" aria-label="Pagination" style="display:flex;justify-content:center;gap:.4rem;margin-top:3rem;flex-wrap:wrap">
        <?php if ($result['page'] > 1): ?>
          <a class="btn btn--ghost btn--sm" href="<?= e(url(qs_page($result['page']-1))) ?>">← Prev</a>
        <?php endif; ?>
        <?php for ($i=1;$i<=$result['pages'];$i++): ?>
          <a class="btn <?= $i===$result['page']?'btn--gold':'btn--ghost' ?> btn--sm" href="<?= e(url(qs_page($i))) ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($result['page'] < $result['pages']): ?>
          <a class="btn btn--ghost btn--sm" href="<?= e(url(qs_page($result['page']+1))) ?>">Next →</a>
        <?php endif; ?>
      </nav>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<style>
@media (max-width: 860px){
  section.wrap[style*="grid-template-columns:280px"]{grid-template-columns:1fr !important}
  .filters{position:static !important}
}
</style>

<?php require __DIR__ . '/templates/footer.php'; ?>
