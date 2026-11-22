<?php
/**
 * admin/site-editor.php — edit website pages (About, Contact, policies) and the
 * contact details / social links shown across the site, with a live preview.
 */
require __DIR__ . '/../includes/bootstrap.php';
require_role(['super_admin', 'admin', 'editor']);

$PAGES = content_pages();
$tab = (string) input('p', 'about');
if ($tab !== 'details' && !isset($PAGES[$tab])) $tab = 'about';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    begin_site_save();
    if ($tab === 'details') {
        foreach (['contact_phone', 'contact_phone2', 'contact_email', 'whatsapp_number', 'office_address', 'office_map_embed'] as $k) {
            if (array_key_exists($k, $_POST)) setting_save($k, trim((string) $_POST[$k]));
        }
        $rows = [];
        $nets = (array) input('so_network', []); $handles = (array) input('so_handle', []); $show = (array) input('so_show', []);
        foreach ($nets as $i => $n) {
            $h = trim((string) ($handles[$i] ?? ''));
            if (!isset(SOCIAL_NETWORKS[$n]) || $h === '') continue;
            $rows[] = ['network' => $n, 'handle' => $h, 'show' => in_array((string) $i, array_map('strval', $show), true)];
        }
        setting_save('socials', json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    } else {
        foreach ($PAGES[$tab]['fields'] as $f) {
            [$key, , $kind] = $f;
            if ($kind === 'image') {
                if (input("remove_$key")) setting_save("c_$key", '');
                elseif (!empty($_FILES[$key]['name'])) {
                    $stored = save_upload($_FILES[$key], 'properties', ALLOWED_IMAGE_EXT);
                    if ($stored) setting_save("c_$key", asset_url($stored)); else flash('error', 'That photo could not be uploaded (JPG, PNG or WebP).');
                }
                continue;
            }
            if (!array_key_exists($key, $_POST)) continue;
            $v = trim((string) $_POST[$key]);
            // Saving the built-in text keeps the page on the default (so future improvements still apply).
            setting_save("c_$key", $v === trim(content_defaults()[$key] ?? '') ? '' : $v);
        }
    }
    log_activity('edit_page', 'page', null);
    flash('success', 'Saved — the website is updated.');
    redirect('admin/site-editor.php?p=' . $tab);
}

$previewUrl = $tab === 'details' ? '/contact' : $PAGES[$tab]['url'];
$pageTitle = 'Website pages';
$pageLead = 'Edit the text on every page — the preview updates as you type';
$activeAdmin = 'site-editor';
include __DIR__ . '/templates/layout-head.php';

$groups = ['Pages' => [], 'Policies' => []];
foreach ($PAGES as $k => $pg) $groups[$pg['group']][$k] = $pg['label'];
?>
<nav class="ed-tabs" aria-label="Pages">
  <a href="/admin/homepage"><?= a_icon('home', 15) ?>Homepage</a>
  <?php foreach ($groups['Pages'] as $k => $l): ?><a href="?p=<?= $k ?>" class="<?= $tab === $k ? 'is-on' : '' ?>"><?= a_icon($k === 'about' ? 'users' : 'mail', 15) ?><?= e($l) ?></a><?php endforeach; ?>
  <a href="?p=details" class="<?= $tab === 'details' ? 'is-on' : '' ?>"><?= a_icon('phone', 15) ?>Contact details &amp; socials</a>
  <span class="ed-tabs__sep"></span>
  <?php foreach ($groups['Policies'] as $k => $l): ?><a href="?p=<?= $k ?>" class="<?= $tab === $k ? 'is-on' : '' ?>"><?= a_icon('file', 15) ?><?= e($l) ?></a><?php endforeach; ?>
</nav>

<form method="post" enctype="multipart/form-data" class="panel" data-preview>
  <?= csrf_field() ?>
  <input type="hidden" name="p" value="<?= e($tab) ?>">
  <?php if ($tab === 'details'):
    $ct = site_contact();
    $rows = json_decode(setting('socials', ''), true);
    if (!is_array($rows)) $rows = array_map(fn($s) => ['network' => $s['network'], 'handle' => preg_replace('#^@#', '', $s['handle']), 'show' => true], site_socials());
  ?>
    <div class="panel-head"><h2>Contact details</h2><span class="muted">Shown in the footer, the contact page and listing pages</span></div>
    <div class="panel-body">
      <div class="a-grid">
        <div class="a-field"><label>Main phone</label><input class="a-input" name="contact_phone" value="<?= e($ct['phone']) ?>" placeholder="+254 7XX XXX XXX"></div>
        <div class="a-field"><label>Second phone <span class="muted">(optional)</span></label><input class="a-input" name="contact_phone2" value="<?= e($ct['phone2']) ?>"></div>
        <div class="a-field"><label>Email</label><input class="a-input" type="email" name="contact_email" value="<?= e($ct['email']) ?>"></div>
        <div class="a-field"><label>WhatsApp number</label><input class="a-input" name="whatsapp_number" value="<?= e($ct['whatsapp']) ?>" placeholder="2547XXXXXXXX"><small class="muted">Country code first, digits only — used by the green WhatsApp button.</small></div>
        <div class="a-field full"><label>Office address</label><input class="a-input" name="office_address" value="<?= e($ct['address']) ?>"></div>
        <div class="a-field full"><label>Map <span class="muted">(optional — paste Google Maps “Embed a map” code)</span></label><textarea class="a-textarea" name="office_map_embed" rows="3" placeholder="&lt;iframe src=&quot;https://www.google.com/maps/embed?…&quot;&gt;&lt;/iframe&gt;"><?= e($ct['map']) ?></textarea><small class="muted">Leave empty to show a map of the address above.</small></div>
      </div>
    </div>
    <div class="panel-head" style="border-top:1px solid var(--a-line)"><h2>Social links</h2><span class="muted">Tick “Show” for the ones you want on the website</span></div>
    <div class="panel-body">
      <div class="so-list" id="soList">
        <?php foreach ($rows as $i => $r): ?>
          <div class="so-row">
            <select class="a-select" name="so_network[]"><?php foreach (SOCIAL_NETWORKS as $nk => $nv): ?><option value="<?= $nk ?>"<?= ($r['network'] ?? '') === $nk ? ' selected' : '' ?>><?= e($nv[0]) ?></option><?php endforeach; ?></select>
            <input class="a-input" name="so_handle[]" value="<?= e($r['handle'] ?? '') ?>" placeholder="Username or full link">
            <label class="a-check so-show"><input type="checkbox" name="so_show[]" value="<?= $i ?>"<?= !empty($r['show']) ? ' checked' : '' ?>> Show</label>
            <button type="button" class="a-btn sm ghost danger" data-so="rm" aria-label="Remove">Remove</button>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="a-btn sm" id="soAdd"><?= a_icon('plus', 14) ?> Add a social link</button>
    </div>
    <template id="soTpl"><div class="so-row"><select class="a-select" name="so_network[]"><?php foreach (SOCIAL_NETWORKS as $nk => $nv): ?><option value="<?= $nk ?>"><?= e($nv[0]) ?></option><?php endforeach; ?></select><input class="a-input" name="so_handle[]" placeholder="Username or full link"><label class="a-check so-show"><input type="checkbox" name="so_show[]" value="__I__" checked> Show</label><button type="button" class="a-btn sm ghost danger" data-so="rm">Remove</button></div></template>
  <?php else: $pg = $PAGES[$tab]; ?>
    <div class="panel-head"><h2><?= e($pg['label']) ?></h2><a class="a-btn sm" href="<?= e($pg['url']) ?>" target="_blank" rel="noopener"><?= a_icon('out', 14) ?> Open page</a></div>
    <div class="panel-body">
      <div class="a-grid">
      <?php foreach ($pg['fields'] as $f):
        [$key, $label, $kind] = $f; $help = $f[3] ?? '';
        $val = c($key);
        $full = in_array($kind, ['textarea', 'rich', 'image'], true) || !preg_match('/stat\d_(value|label)/', $key);
      ?>
        <div class="a-field<?= $full ? ' full' : '' ?>">
          <label for="f_<?= e($key) ?>"><?= e($label) ?></label>
          <?php if ($kind === 'textarea'): ?>
            <textarea class="a-textarea" id="f_<?= e($key) ?>" name="<?= e($key) ?>" rows="3"><?= e($val) ?></textarea>
          <?php elseif ($kind === 'rich'): ?>
            <div class="md-bar" data-for="f_<?= e($key) ?>"><button type="button" data-md="h">Heading</button><button type="button" data-md="b"><b>B</b></button><button type="button" data-md="l">• List</button><button type="button" data-md="a">Link</button></div>
            <textarea class="a-textarea md" id="f_<?= e($key) ?>" name="<?= e($key) ?>" rows="18"><?= e($val) ?></textarea>
          <?php elseif ($kind === 'date'): ?>
            <input class="a-input" type="date" id="f_<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($val) ?>" style="max-width:220px">
          <?php elseif ($kind === 'image'): $img = $val ?: ph_url('living', 800); ?>
            <div class="img-field">
              <img src="<?= e($img) ?>" alt="" width="200" height="133">
              <div>
                <?php if (!$val): ?><p class="ph-note"><?= a_icon('info', 14) ?><span>This is a <b>placeholder photo</b>. Upload your own to replace it.</span></p><?php endif; ?>
                <input type="file" name="<?= e($key) ?>" accept="image/jpeg,image/png,image/webp">
                <?php if ($val): ?><label class="a-check" style="margin-top:8px"><input type="checkbox" name="remove_<?= e($key) ?>" value="1"> Remove this photo</label><?php endif; ?>
              </div>
            </div>
          <?php else: ?>
            <input class="a-input" id="f_<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($val) ?>">
          <?php endif; ?>
          <?php if ($help): ?><small class="muted"><?= e($help) ?></small><?php endif; ?>
        </div>
      <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
  <div class="ed-save"><span class="muted" data-pv-state>Changes show in the preview — they go live when you save.</span><button class="a-btn gold" type="submit"><?= a_icon('check', 15) ?> Save &amp; publish</button></div>
</form>

<script>
(function () {
  var list = document.getElementById('soList'), add = document.getElementById('soAdd'), tpl = document.getElementById('soTpl');
  if (list && add) {
    var n = list.children.length + 100;
    add.onclick = function () { var d = document.createElement('div'); d.innerHTML = tpl.innerHTML.replace('__I__', n++); list.appendChild(d.firstChild); list.lastChild.querySelector('input').focus(); list.dispatchEvent(new Event('input', { bubbles: true })); };
    list.addEventListener('click', function (e) { var b = e.target.closest('[data-so=rm]'); if (b) { b.closest('.so-row').remove(); list.dispatchEvent(new Event('input', { bubbles: true })); } });
    // keep checkbox values in step with row order
    list.closest('form').addEventListener('submit', function () { [].forEach.call(list.querySelectorAll('.so-row'), function (r, i) { r.querySelector('[name="so_show[]"]').value = i; }); });
    list.closest('form').addEventListener('input', function () { [].forEach.call(list.querySelectorAll('.so-row'), function (r, i) { r.querySelector('[name="so_show[]"]').value = i; }); }, true);
  }
  document.querySelectorAll('.md-bar').forEach(function (bar) {
    var ta = document.getElementById(bar.dataset.for);
    bar.addEventListener('click', function (e) {
      var b = e.target.closest('[data-md]'); if (!b) return;
      var s = ta.selectionStart, en = ta.selectionEnd, sel = ta.value.slice(s, en) || 'text', out = sel;
      if (b.dataset.md === 'h') out = '\n## ' + sel + '\n';
      if (b.dataset.md === 'b') out = '**' + sel + '**';
      if (b.dataset.md === 'l') out = sel.split('\n').map(function (l) { return '- ' + l; }).join('\n');
      if (b.dataset.md === 'a') { var u = prompt('Link address (https://…)'); if (!u) return; out = '[' + sel + '](' + u + ')'; }
      ta.setRangeText(out, s, en, 'end'); ta.focus(); ta.dispatchEvent(new Event('input', { bubbles: true }));
    });
  });
})();
</script>
<?php include __DIR__ . '/templates/layout-foot.php'; ?>
