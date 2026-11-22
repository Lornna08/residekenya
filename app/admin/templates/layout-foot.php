  </main>
</div>
<script>
(function () {
  var more = document.getElementById('lxMore'), drawer = document.getElementById('lxDrawer');
  function closeMore() { more.hidden = true; }
  function openDrawer() { drawer.hidden = false; document.documentElement.classList.add('is-locked'); requestAnimationFrame(function () { drawer.classList.add('is-in'); }); }
  function closeDrawer() { drawer.classList.remove('is-in'); document.documentElement.classList.remove('is-locked'); setTimeout(function () { drawer.hidden = true; }, 260); }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-lx]');
    if (b && b.dataset.lx === 'more') {
      e.stopPropagation();
      if (!more.hidden) return closeMore();
      more.hidden = false;
      var r = b.getBoundingClientRect(), w = more.offsetWidth;
      more.style.left = Math.max(8, Math.min(r.right - w, innerWidth - w - 8)) + 'px';
      more.style.top = (r.bottom + 6) + 'px';
      return;
    }
    if (b && b.dataset.lx === 'drawer') return openDrawer();
    if (b && b.dataset.lx === 'close') return closeDrawer();
    if (!e.target.closest('#lxMore')) closeMore();
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeMore(); if (!drawer.hidden) closeDrawer(); } });
  addEventListener('scroll', function () { closeMore(); document.querySelector('.top').classList.toggle('is-stuck', scrollY > 4); }, { passive: true });
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!confirm(f.getAttribute('data-confirm'))) e.preventDefault(); });
  });
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('/admin/sw.js', { scope: '/admin/' }).catch(function () {});
})();
</script>
<script src="<?= e(url('assets/js/upload-resize.js')) ?>"></script>
<script src="/admin/assets/photo-uploader.js"></script>
<?= $extraAdminFoot ?? '' ?>
</body>
</html>
