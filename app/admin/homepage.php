<?php
/** admin/homepage.php — control homepage sections: visibility, order, text, hero. */
require __DIR__ . '/../includes/bootstrap.php';
require_role(['super_admin', 'admin']);

/**
 * The canonical list of homepage sections. Order here is the default order.
 * Each has an editable eyebrow, heading, and (some) subtext.
 */
$SECTIONS = [
    'featured'   => ['label' => 'Featured Listings', 'eyebrow' => 'Handpicked', 'heading' => 'Featured Listings', 'sub' => ''],
    'types'      => ['label' => 'Browse by Property Type', 'eyebrow' => 'Explore', 'heading' => 'Browse by Property Type', 'sub' => ''],
    'counties'   => ['label' => 'Browse by County', 'eyebrow' => 'Where to', 'heading' => 'Browse by County', 'sub' => ''],
    'luxury'     => ['label' => 'Luxury Collection', 'eyebrow' => 'The finest', 'heading' => 'Luxury Collection', 'sub' => ''],
    'latest'     => ['label' => 'Latest Listings', 'eyebrow' => 'Just added', 'heading' => 'Latest Listings', 'sub' => ''],
    'airbnb'     => ['label' => 'Airbnb & Holiday Homes', 'eyebrow' => 'Short stays', 'heading' => 'Airbnb & Holiday Homes', 'sub' => ''],
    'why'        => ['label' => 'Why Reside Kenya', 'eyebrow' => 'Why Reside Kenya', 'heading' => 'Property, done properly.', 'sub' => ''],
    'testimonials' => ['label' => 'Testimonials', 'eyebrow' => 'Kind words', 'heading' => 'Loved by our clients', 'sub' => ''],
    'cta'        => ['label' => 'Call to action (Sell/Rent)', 'eyebrow' => 'Sell or rent with us', 'heading' => 'Looking to sell or rent your property?', 'sub' => ''],
];

// Load saved config (JSON in settings) or fall back to defaults
$saved = json_decode((string) setting('homepage_config', ''), true);
if (!is_array($saved)) $saved = [];

// Build working config: ensure every section present, preserve saved order
$config = [];
$order = $saved['order'] ?? array_keys($SECTIONS);
// Drop unknown keys, append any missing
$order = array_values(array_filter($order, fn($k) => isset($SECTIONS[$k])));
foreach (array_keys($SECTIONS) as $k) if (!in_array($k, $order, true)) $order[] = $k;

foreach ($order as $key) {
    $s = $saved['sections'][$key] ?? [];
    $config[$key] = [
        'visible' => array_key_exists('visible', $s) ? (bool) $s['visible'] : true,
        'eyebrow' => $s['eyebrow'] ?? $SECTIONS[$key]['eyebrow'],
        'heading' => $s['heading'] ?? $SECTIONS[$key]['heading'],
        'sub'     => $s['sub'] ?? $SECTIONS[$key]['sub'],
    ];
}

// Hero fields (also editable here)
$hero = [
    'eyebrow' => setting('hero_eyebrow', "Kenya's Premium Property Platform"),
    'heading' => setting('hero_heading', 'Find a home worth coming home to.'),
    'sub'     => setting('hero_sub', 'Houses, apartments, villas, land, commercial spaces and Airbnb stays — curated, verified, and beautifully presented across Kenya.'),
];

// Custom blocks
$customBlocks = json_decode((string) setting('homepage_custom_blocks', ''), true);
if (!is_array($customBlocks)) $customBlocks = [];

// Property picker data
$allProps = Database::all("SELECT id, title, ref_code, featured_image, purpose FROM properties WHERE status IN ('available','under_offer','draft') ORDER BY created_at DESC LIMIT 500");
$propMap = [];
foreach ($allProps as $pp) $propMap[(int) $pp['id']] = $pp;
$sel = [
    'featured' => array_filter(array_map('intval', explode(',', (string) setting('hp_featured_ids', '')))),
    'luxury'   => array_filter(array_map('intval', explode(',', (string) setting('hp_luxury_ids', '')))),
    'latest'   => array_filter(array_map('intval', explode(',', (string) setting('hp_latest_ids', '')))),
];
$counts = [
    'featured' => (int) setting('hp_featured_count', 6) ?: 6,
    'luxury'   => (int) setting('hp_luxury_count', 3) ?: 3,
    'latest'   => (int) setting('hp_latest_count', 6) ?: 6,
    'airbnb'   => (int) setting('hp_airbnb_count', 3) ?: 3,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    begin_site_save();

    // Hero
    setting_save('hero_eyebrow', trim((string) input('hero_eyebrow', '')));
    setting_save('hero_heading', trim((string) input('hero_heading', '')));
    setting_save('hero_sub', trim((string) input('hero_sub', '')));

    // Hero image upload
    if (input('remove_hero_image')) setting_save('hero_image', '');
    if (input('remove_cta_image')) setting_save('cta_image', '');
    if (!empty($_FILES['cta_image']['name'])) {
        $stored = save_upload($_FILES['cta_image'], 'properties', ALLOWED_IMAGE_EXT);
        if ($stored) setting_save('cta_image', $stored);
    }
    if (!empty($_FILES['hero_image']['name'])) {
        $stored = save_upload($_FILES['hero_image'], 'properties', ALLOWED_IMAGE_EXT);
        if ($stored) setting_save('hero_image', $stored);
    }

    // Sections: order comes as a comma-separated hidden field, updated by JS on reorder
    $postedOrder = array_filter(explode(',', (string) input('section_order', '')));
    $postedOrder = array_values(array_filter($postedOrder, fn($k) => isset($SECTIONS[$k])));
    foreach (array_keys($SECTIONS) as $k) if (!in_array($k, $postedOrder, true)) $postedOrder[] = $k;

    $newSections = [];
    foreach ($postedOrder as $key) {
        $newSections[$key] = [
            'visible' => input("visible_$key") ? true : false,
            'eyebrow' => trim((string) input("eyebrow_$key", '')),
            'heading' => trim((string) input("heading_$key", '')),
            'sub'     => trim((string) input("sub_$key", '')),
        ];
    }
    setting_save('homepage_config', json_encode(['order' => $postedOrder, 'sections' => $newSections]));

    // ---- Custom blocks (user-created text/image sections) ----
    $customBlocks = [];
    $cbTitles   = (array) input('cb_title', []);
    $cbBodies   = (array) input('cb_body', []);
    $cbVisible  = (array) input('cb_visible', []);
    $cbExisting = (array) input('cb_image_existing', []);
    foreach ($cbTitles as $idx => $title) {
        $title = trim((string) $title);
        $body  = trim((string) ($cbBodies[$idx] ?? ''));
        if ($title === '' && $body === '') continue; // skip empty
        $img = $cbExisting[$idx] ?? '';
        // handle a newly uploaded image for this block
        if (!empty($_FILES['cb_image']['name'][$idx])) {
            $one = [
                'name' => $_FILES['cb_image']['name'][$idx], 'type' => $_FILES['cb_image']['type'][$idx],
                'tmp_name' => $_FILES['cb_image']['tmp_name'][$idx], 'error' => $_FILES['cb_image']['error'][$idx], 'size' => $_FILES['cb_image']['size'][$idx],
            ];
            $stored = save_upload($one, 'properties', ALLOWED_IMAGE_EXT);
            if ($stored) $img = $stored;
        }
        $customBlocks[] = [
            'id'      => 'cb_' . $idx . '_' . substr(md5($title . microtime()), 0, 6),
            'title'   => $title,
            'body'    => $body,
            'image'   => $img,
            'visible' => !empty($cbVisible[$idx]),
        ];
    }
    setting_save('homepage_custom_blocks', json_encode($customBlocks));

    // ---- Property selections & counts ----
    setting_save('hp_featured_ids', trim((string) input('hp_featured_ids', '')));
    setting_save('hp_luxury_ids', trim((string) input('hp_luxury_ids', '')));
    setting_save('hp_latest_ids', trim((string) input('hp_latest_ids', '')));
    setting_save('hp_featured_count', (string) max(1, min(12, (int) input_int('hp_featured_count', 6))));
    setting_save('hp_luxury_count', (string) max(1, min(12, (int) input_int('hp_luxury_count', 3))));
    setting_save('hp_latest_count', (string) max(1, min(12, (int) input_int('hp_latest_count', 6))));
    setting_save('hp_airbnb_count', (string) max(1, min(12, (int) input_int('hp_airbnb_count', 3))));

    log_activity('update_homepage', 'settings', null);
    flash('success', 'Homepage updated.');
    redirect('admin/homepage.php');
}

$previewUrl = '/';
$pageTitle = 'Homepage editor';
$pageLead = 'Changes appear in the preview as you type — Save publishes them';
$activeAdmin = 'homepage';
include __DIR__ . '/templates/layout-head.php';
?>
<form method="post" enctype="multipart/form-data" data-preview>
  <?= csrf_field() ?>
  <input type="hidden" name="section_order" id="sectionOrder" value="<?= e(implode(',', array_keys($config))) ?>">

  <div class="panel">
    <div class="panel-head"><h2>Hero section</h2><span class="muted" style="font-size:.8rem">The big banner at the top</span></div>
    <div class="panel-body">
      <div class="a-field"><label>Eyebrow (small text above heading)</label><input class="a-input" name="hero_eyebrow" value="<?= e($hero['eyebrow']) ?>"></div>
      <div class="a-field"><label>Heading</label><input class="a-input" name="hero_heading" value="<?= e($hero['heading']) ?>"></div>
      <div class="a-field"><label>Sub-text</label><textarea class="a-textarea" name="hero_sub" style="min-height:70px"><?= e($hero['sub']) ?></textarea></div>
      <?php $hi = setting('hero_image', ''); $ci = setting('cta_image', ''); ?>
      <div class="a-grid">
        <div class="a-field"><label>Hero background photo</label>
          <div class="img-field"><img src="<?= e($hi ? asset_url($hi) : '/assets/img/hero.jpg') ?>" alt="" width="200" height="112">
            <div><?php if (!$hi): ?><p class="ph-note"><?= a_icon('info', 14) ?><span>This is a <b>sample photo</b>. Upload your own (1920×1080 works best).</span></p><?php endif; ?>
              <input type="file" name="hero_image" accept="image/jpeg,image/png,image/webp">
              <?php if ($hi): ?><label class="a-check" style="margin-top:8px"><input type="checkbox" name="remove_hero_image" value="1"> Use the sample photo instead</label><?php endif; ?></div></div>
        </div>
        <div class="a-field"><label>“Sell or rent with us” banner photo</label>
          <div class="img-field"><img src="<?= e($ci ? asset_url($ci) : ph_url('living', 400)) ?>" alt="" width="200" height="112">
            <div><?php if (!$ci): ?><p class="ph-note"><?= a_icon('info', 14) ?><span>This is a <b>placeholder photo</b>. Upload your own to replace it.</span></p><?php endif; ?>
              <input type="file" name="cta_image" accept="image/jpeg,image/png,image/webp">
              <?php if ($ci): ?><label class="a-check" style="margin-top:8px"><input type="checkbox" name="remove_cta_image" value="1"> Remove this photo</label><?php endif; ?></div></div>
        </div>
      </div>
      <p class="ed-note" style="margin:4px 0 0"><?= a_icon('info', 15) ?><span>Property-type tiles without listings show placeholder photos until you add a photo for that type under <a href="/admin/categories">Property types</a>.</span></p>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Sections</h2><span class="muted" style="font-size:.8rem">Drag to reorder · toggle to show/hide · edit text</span></div>
    <div class="panel-body">
      <div id="sectionList">
        <?php foreach ($config as $key => $s): ?>
          <div class="hp-section" data-key="<?= e($key) ?>" draggable="true">
            <div class="hp-head">
              <span class="hp-drag" title="Drag to reorder">⠿</span>
              <strong><?= e($SECTIONS[$key]['label']) ?></strong>
              <label class="a-check" style="margin:0 0 0 auto"><input type="checkbox" name="visible_<?= e($key) ?>" value="1" <?= $s['visible'] ? 'checked' : '' ?>> Visible</label>
            </div>
            <div class="hp-body">
              <div class="a-grid">
                <div class="a-field"><label>Eyebrow</label><input class="a-input" name="eyebrow_<?= e($key) ?>" value="<?= e($s['eyebrow']) ?>"></div>
                <div class="a-field"><label>Heading</label><input class="a-input" name="heading_<?= e($key) ?>" value="<?= e($s['heading']) ?>"></div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="muted" style="font-size:.8rem;margin-top:1rem">Note: which <em>properties</em> appear in Featured / Luxury is controlled by the Featured and Luxury flags on each property (set them in Properties).</p>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Properties on the homepage</h2><span class="muted" style="font-size:.8rem">Choose exactly which show, and drag to order</span></div>
    <div class="panel-body">
      <p class="muted" style="font-size:.84rem;margin-bottom:1.2rem">For each section, leave empty to show automatically (by Featured/Luxury flag or newest), or pick specific properties and drag them into the order you want.</p>

      <?php
      $pickerSections = [
          'featured' => 'Featured Listings',
          'luxury'   => 'Luxury Collection',
          'latest'   => 'Latest Listings',
      ];
      foreach ($pickerSections as $skey => $slabel):
      ?>
        <div class="picker-block" style="margin-bottom:1.8rem">
          <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:.6rem">
            <strong><?= e($slabel) ?></strong>
            <label class="muted" style="font-size:.8rem;display:flex;align-items:center;gap:.4rem">Auto count:
              <input class="a-input" type="number" min="1" max="12" name="hp_<?= e($skey) ?>_count" value="<?= (int) $counts[$skey] ?>" style="width:70px;padding:.35rem .5rem">
            </label>
            <span class="muted" style="font-size:.76rem">(used only when no properties are picked below)</span>
          </div>

          <input type="hidden" name="hp_<?= e($skey) ?>_ids" id="ids_<?= e($skey) ?>" value="<?= e(implode(',', $sel[$skey])) ?>">

          <div class="picker-chosen" id="chosen_<?= e($skey) ?>" data-section="<?= e($skey) ?>">
            <?php foreach ($sel[$skey] as $pid): if (!isset($propMap[$pid])) continue; $pp = $propMap[$pid]; ?>
              <div class="pchip" draggable="true" data-id="<?= (int) $pid ?>">
                <img src="<?= e(asset_url($pp['featured_image'] ?? null)) ?>" alt="">
                <span><?= e($pp['title']) ?></span>
                <button type="button" class="pchip-x" title="Remove">×</button>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="picker-add">
            <select class="a-select picker-select" data-section="<?= e($skey) ?>">
              <option value="">+ Add a property…</option>
              <?php foreach ($allProps as $pp): ?>
                <option value="<?= (int) $pp['id'] ?>" data-img="<?= e(asset_url($pp['featured_image'] ?? null)) ?>" data-title="<?= e($pp['title']) ?>"><?= e($pp['title']) ?> <?= $pp['ref_code'] ? '(' . e($pp['ref_code']) . ')' : '' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      <?php endforeach; ?>

      <div class="picker-block">
        <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
          <strong>Airbnb &amp; Holiday Homes</strong>
          <label class="muted" style="font-size:.8rem;display:flex;align-items:center;gap:.4rem">Show count:
            <input class="a-input" type="number" min="1" max="12" name="hp_airbnb_count" value="<?= (int) $counts['airbnb'] ?>" style="width:70px;padding:.35rem .5rem">
          </label>
          <span class="muted" style="font-size:.76rem">(shows newest Airbnb listings automatically)</span>
        </div>
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Custom blocks</h2><span class="muted" style="font-size:.8rem">Add your own text + image sections</span></div>
    <div class="panel-body">
      <p class="muted" style="font-size:.84rem;margin-bottom:1rem">Custom blocks appear on the homepage after the built-in sections. Each has a heading, text, and optional image.</p>
      <div id="cbList">
        <?php foreach ($customBlocks as $i => $cb): ?>
          <div class="hp-section" style="padding:1rem">
            <div class="a-grid">
              <div class="a-field"><label>Block heading</label><input class="a-input" name="cb_title[]" value="<?= e($cb['title'] ?? '') ?>"></div>
              <div class="a-field"><label>Visible</label><label class="a-check"><input type="checkbox" name="cb_visible[<?= $i ?>]" value="1" <?= !empty($cb['visible']) ? 'checked' : '' ?>> Show on homepage</label></div>
            </div>
            <div class="a-field"><label>Body text</label><textarea class="a-textarea" name="cb_body[]"><?= e($cb['body'] ?? '') ?></textarea></div>
            <div class="a-field"><label>Image (optional)</label><input class="a-input" type="file" name="cb_image[]" accept="image/*">
              <input type="hidden" name="cb_image_existing[]" value="<?= e($cb['image'] ?? '') ?>">
              <?php if (!empty($cb['image'])): ?><div style="margin-top:.5rem"><img src="<?= e(asset_url($cb['image'])) ?>" style="width:120px;height:76px;object-fit:cover;border-radius:6px;border:1px solid var(--a-line)"></div><?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <template id="cbTemplate">
        <div class="hp-section" style="padding:1rem">
          <div class="a-grid">
            <div class="a-field"><label>Block heading</label><input class="a-input" name="cb_title[]" value=""></div>
            <div class="a-field"><label>Visible</label><label class="a-check"><input type="checkbox" name="cb_visible[__IDX__]" value="1" checked> Show on homepage</label></div>
          </div>
          <div class="a-field"><label>Body text</label><textarea class="a-textarea" name="cb_body[]"></textarea></div>
          <div class="a-field"><label>Image (optional)</label><input class="a-input" type="file" name="cb_image[]" accept="image/*"><input type="hidden" name="cb_image_existing[]" value=""></div>
        </div>
      </template>
      <button type="button" class="a-btn" id="addCbBtn">+ Add custom block</button>
    </div>
  </div>

  <div class="ed-save"><span class="muted" data-pv-state>Changes show in the preview — they go live when you save.</span><button class="a-btn gold" type="submit"><?= a_icon('check', 15) ?> Save &amp; publish</button></div>
</form>

<style>
.hp-section { border: 1px solid var(--a-line); border-radius: var(--a-r-sm); margin-bottom: .8rem; background: var(--a-surface2); }
.hp-section.dragover { border-color: var(--a-gold); }
.hp-head { display: flex; align-items: center; gap: .7rem; padding: .7rem 1rem; }
.hp-drag { cursor: grab; color: var(--a-muted); font-size: 1.1rem; user-select: none; }
.hp-body { padding: 0 1rem 1rem; }
.picker-chosen { display: flex; flex-wrap: wrap; gap: .5rem; min-height: 48px; padding: .5rem; border: 1px dashed var(--a-line); border-radius: var(--a-r-sm); margin-bottom: .6rem; }
.pchip { display: inline-flex; align-items: center; gap: .5rem; background: var(--a-surface2); border: 1px solid var(--a-line); border-radius: 999px; padding: .25rem .5rem .25rem .25rem; cursor: grab; font-size: .82rem; }
.pchip.dragging { opacity: .5; }
.pchip img { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; }
.pchip span { max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pchip-x { background: none; border: 0; color: var(--a-muted); cursor: pointer; font-size: 1rem; line-height: 1; padding: 0 .2rem; }
.pchip-x:hover { color: var(--a-danger); }
.picker-select { max-width: 340px; }
</style>
<script>
(function(){
  var list = document.getElementById('sectionList');
  var orderInput = document.getElementById('sectionOrder');
  var dragEl = null;
  function refreshOrder(){
    var keys = Array.prototype.map.call(list.querySelectorAll('.hp-section'), function(el){ return el.getAttribute('data-key'); });
    orderInput.value = keys.join(',');
  }
  list.querySelectorAll('.hp-section').forEach(function(el){
    el.addEventListener('dragstart', function(){ dragEl = el; el.style.opacity = '.5'; });
    el.addEventListener('dragend', function(){ el.style.opacity = ''; refreshOrder(); });
    el.addEventListener('dragover', function(e){ e.preventDefault(); el.classList.add('dragover'); });
    el.addEventListener('dragleave', function(){ el.classList.remove('dragover'); });
    el.addEventListener('drop', function(e){
      e.preventDefault(); el.classList.remove('dragover');
      if(dragEl && dragEl !== el){
        var rect = el.getBoundingClientRect();
        var after = (e.clientY - rect.top) > rect.height/2;
        list.insertBefore(dragEl, after ? el.nextSibling : el);
        refreshOrder();
      }
    });
  });
})();
</script>
<script>
(function(){
  var addBtn = document.getElementById('addCbBtn');
  var list = document.getElementById('cbList');
  var tpl = document.getElementById('cbTemplate');
  var idx = <?= count($customBlocks) ?>;
  if(addBtn){
    addBtn.addEventListener('click', function(){
      var html = tpl.innerHTML.replace('__IDX__', idx);
      var wrap = document.createElement('div');
      wrap.innerHTML = html;
      list.appendChild(wrap.firstElementChild);
      idx++;
    });
  }
})();
</script>
<script>
/* Property picker: add / remove / drag-reorder chips, sync hidden id field */
(function(){
  function sync(section){
    var chosen = document.getElementById('chosen_'+section);
    var hidden = document.getElementById('ids_'+section);
    var ids = Array.prototype.map.call(chosen.querySelectorAll('.pchip'), function(c){ return c.getAttribute('data-id'); });
    hidden.value = ids.join(',');
  }
  function wireChip(chip, section){
    chip.querySelector('.pchip-x').addEventListener('click', function(){ chip.remove(); sync(section); });
    chip.addEventListener('dragstart', function(){ chip.classList.add('dragging'); });
    chip.addEventListener('dragend', function(){ chip.classList.remove('dragging'); sync(section); });
  }
  document.querySelectorAll('.picker-chosen').forEach(function(chosen){
    var section = chosen.getAttribute('data-section');
    chosen.querySelectorAll('.pchip').forEach(function(chip){ wireChip(chip, section); });
    chosen.addEventListener('dragover', function(e){
      e.preventDefault();
      var dragging = chosen.querySelector('.pchip.dragging');
      if(!dragging) return;
      var after = null;
      chosen.querySelectorAll('.pchip:not(.dragging)').forEach(function(c){
        var r = c.getBoundingClientRect();
        if(e.clientX < r.left + r.width/2 && after===null){ after = c; }
      });
      if(after) chosen.insertBefore(dragging, after); else chosen.appendChild(dragging);
    });
  });
  document.querySelectorAll('.picker-select').forEach(function(sel){
    sel.addEventListener('change', function(){
      var id = sel.value; if(!id) return;
      var section = sel.getAttribute('data-section');
      var chosen = document.getElementById('chosen_'+section);
      if(chosen.querySelector('.pchip[data-id="'+id+'"]')){ sel.value=''; return; }
      var opt = sel.options[sel.selectedIndex];
      var chip = document.createElement('div');
      chip.className = 'pchip'; chip.setAttribute('draggable','true'); chip.setAttribute('data-id', id);
      chip.innerHTML = '<img src="'+opt.getAttribute('data-img')+'" alt=""><span>'+opt.getAttribute('data-title')+'</span><button type="button" class="pchip-x" title="Remove">×</button>';
      chosen.appendChild(chip);
      wireChip(chip, section);
      sync(section);
      sel.value = '';
    });
  });
})();
</script>
<?php include __DIR__ . '/templates/layout-foot.php'; ?>
