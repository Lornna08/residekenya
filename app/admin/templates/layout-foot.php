    <?php if (!empty($previewUrl)): ?>
      </div><!-- /ed__main -->
      <aside class="ed__pv" id="edPv" aria-label="Live preview">
        <div class="ed__bar">
          <span class="ed__live"><i></i>Live preview</span>
          <span class="ed__sp"></span>
          <div class="ed__dev" role="group" aria-label="Preview size"><button type="button" data-dev="desk" class="is-on" title="Desktop"><?= a_icon('grid', 14) ?></button><button type="button" data-dev="phone" title="Phone"><?= a_icon('phone', 14) ?></button></div>
          <button type="button" class="ed__btn" data-pv="reload" title="Reload"><?= a_icon('refresh', 14) ?></button>
          <a class="ed__btn" href="<?= e($previewUrl) ?>" target="_blank" rel="noopener" title="Open in a new tab"><?= a_icon('out', 14) ?></a>
          <button type="button" class="ed__btn ed__close" data-pv="close" title="Close preview"><?= a_icon('x', 14) ?></button>
        </div>
        <div class="ed__frame"><iframe id="edFrame" src="<?= e($previewUrl . (str_contains($previewUrl, '?') ? '&' : '?') . 'preview=1') ?>" title="Website preview" loading="eager"></iframe></div>
      </aside>
      </div><!-- /ed -->
      <button type="button" class="ed__open" data-pv="open"><?= a_icon('eye', 16) ?> Preview</button>
    <?php endif; ?>
  </main>
  </div><!-- /main -->
</div><!-- /app -->
<a class="chat-fab" href="/admin/#/chat" aria-label="Open team chat"><?= a_icon('chat', 22) ?></a>
<script>
(function () {
  var d = document.documentElement;
  function ls(k, v) { try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) { return null; } }
  function theme(t) {
    t = t === 'dark' ? 'dark' : 'light'; d.dataset.theme = t; ls('reside_admin_theme', t);
    document.querySelectorAll('[data-lx=theme]').forEach(function (b) { b.classList.toggle('is-on', b.dataset.v === t); });
    var m = document.querySelector('meta[name=theme-color]'); if (m) m.content = t === 'dark' ? '#0F1115' : '#EEF2F8';
  }
  theme(d.dataset.theme);
  (function () { var nv = document.querySelector('.side__nav'), on = nv && nv.querySelector('.side__a.is-on'); if (on && on.offsetTop > nv.clientHeight - 60) nv.scrollTop = on.offsetTop - nv.clientHeight / 2; })();
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-lx]');
    if (e.target.closest('.side a')) d.classList.remove('is-nav');
    if (!b) return;
    var k = b.dataset.lx;
    if (k === 'drawer') d.classList.add('is-nav');
    if (k === 'close') d.classList.remove('is-nav');
    if (k === 'theme') theme(b.dataset.v);
    if (k === 'themeflip') theme(d.dataset.theme === 'dark' ? 'light' : 'dark');
    if (k === 'sidemin') { var mn = !d.classList.contains('is-side-min'); d.classList.toggle('is-side-min', mn); ls('reside_side_min', mn ? '1' : '0'); }
    if (k === 'grp') {
      if (d.classList.contains('is-side-min') && innerWidth > 900) { d.classList.remove('is-side-min'); ls('reside_side_min', '0'); }
      var n = {}; try { n = JSON.parse(ls('reside_nav') || '{}') || {}; } catch (er) { }
      n[b.dataset.g] = !n[b.dataset.g]; ls('reside_nav', JSON.stringify(n)); d.classList.toggle('g-' + b.dataset.g + '-off', n[b.dataset.g]);
    }
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') d.classList.remove('is-nav'); });
  addEventListener('scroll', function () { var t = document.querySelector('.top'); if (t) t.classList.toggle('is-stuck', scrollY > 4); }, { passive: true });
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!confirm(f.getAttribute('data-confirm'))) e.preventDefault(); });
  });
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('/admin/sw.js', { scope: '/admin/' }).catch(function () {});

  // ---- Live preview: unsaved edits are sent as a draft and the page reloads beside the form ----
  var frame = document.getElementById('edFrame'), pv = document.getElementById('edPv');
  if (frame && pv) {
    var form = document.querySelector('form[data-preview]'), state = document.querySelector('[data-pv-state]'), t = null, busy = false, again = false;
    function reload() { try { var y = frame.contentWindow.scrollY; frame.onload = function () { try { frame.contentWindow.scrollTo(0, y); } catch (e) { } }; frame.contentWindow.location.reload(); } catch (e) { frame.src = frame.src; } }
    function push() {
      if (!form) return reload();
      if (busy) { again = true; return; }
      busy = true; if (state) state.textContent = 'Updating preview…';
      var fd = new FormData(form); fd.append('_draft', '1');
      [].slice.call(fd.entries()).forEach(function (en) { if (en[1] instanceof File) fd.delete(en[0]); });
      fetch(form.getAttribute('action') || location.href, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function () { reload(); if (state) state.textContent = 'Preview shows your unsaved changes — click Save to publish.'; })
        .catch(function () { if (state) state.textContent = 'Preview couldn’t update — your changes are still in the form.'; })
        .then(function () { busy = false; if (again) { again = false; push(); } });
    }
    if (form) {
      form.addEventListener('input', function (e) { if (e.target.type === 'file') return; clearTimeout(t); t = setTimeout(push, 700); });
      form.addEventListener('change', function (e) { if (e.target.type === 'file') return; clearTimeout(t); t = setTimeout(push, 300); });
    }
    pv.addEventListener('click', function (e) {
      var b = e.target.closest('[data-dev],[data-pv]'); if (!b) return;
      if (b.dataset.dev) { pv.classList.toggle('is-phone', b.dataset.dev === 'phone'); pv.querySelectorAll('[data-dev]').forEach(function (x) { x.classList.toggle('is-on', x === b); }); }
      if (b.dataset.pv === 'reload') reload();
      if (b.dataset.pv === 'close') d.classList.remove('is-pv');
    });
    // Desktop preview: render the page at laptop width and scale it to fit the panel.
    function fit() {
      var box = frame.parentNode, w = box.clientWidth, h = box.clientHeight;
      if (pv.classList.contains('is-phone') || !w) { frame.style.transform = ''; frame.style.height = ''; return; }
      var sc = Math.min(1, w / 1280); frame.style.transform = 'scale(' + sc + ')'; frame.style.height = (h / sc) + 'px';
    }
    fit(); addEventListener('resize', fit); pv.addEventListener('click', function () { setTimeout(fit, 30); });
    var open = document.querySelector('.ed__open'); if (open) open.onclick = function () { d.classList.add('is-pv'); reload(); setTimeout(fit, 60); };
  }
})();
</script>
<script src="<?= e(url('assets/js/upload-resize.js')) ?>"></script>
<script src="/admin/assets/photo-uploader.js"></script>
<?= $extraAdminFoot ?? '' ?>
</body>
</html>
