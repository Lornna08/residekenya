    </div><!-- /admin-body -->
  </div><!-- /admin-main -->
</div><!-- /admin-shell -->
<?php $__inbox = ($unreadInquiries ?? 0) + ($pendingBookings ?? 0) + ($newSubmissions ?? 0); $__a = $activeAdmin ?? ''; ?>
<nav class="tabbar" aria-label="App">
  <a href="<?= e(url('admin/index.php')) ?>" class="<?= $__a === 'dashboard' ? 'on' : '' ?>"><svg viewBox="0 0 24 24"><path d="M3 11.5 12 4l9 7.5M5.5 10v10h13V10"/></svg><span>Home</span></a>
  <a href="<?= e(url('admin/inquiries.php')) ?>" class="<?= in_array($__a, ['inquiries','bookings','submissions'], true) ? 'on' : '' ?>"><svg viewBox="0 0 24 24"><path d="M4 5h16v11H8l-4 4z"/></svg><span>Inbox</span><b class="tb-badge" data-badge="inbox" <?= $__inbox ? '' : 'hidden' ?>><?= $__inbox > 99 ? '99+' : $__inbox ?></b></a>
  <a href="<?= e(url('admin/quick-listing.php')) ?>" class="tb-add <?= $__a === 'quick-listing' ? 'on' : '' ?>" aria-label="New listing"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></a>
  <a href="<?= e(url('admin/properties.php')) ?>" class="<?= in_array($__a, ['properties','property-edit'], true) ? 'on' : '' ?>"><svg viewBox="0 0 24 24"><path d="M4 20V9l8-5 8 5v11zM9 20v-6h6v6"/></svg><span>Listings</span></a>
  <a href="#" id="tabMore"><svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg><span>More</span></a>
</nav>
<script>
(function(){
  var t = document.getElementById('adminToggle'), s = document.getElementById('adminSide');
  if(t&&s){ t.addEventListener('click', function(){ s.classList.toggle('open'); }); }
  // Confirm destructive actions
  document.querySelectorAll('form[data-confirm]').forEach(function(f){
    f.addEventListener('submit', function(e){ if(!confirm(f.getAttribute('data-confirm'))) e.preventDefault(); });
  });
})();
</script>
<script src="<?= e(url('assets/js/upload-resize.js')) ?>"></script>
<script src="/admin/assets/app.js"></script>
<script src="/admin/assets/photo-uploader.js"></script>
<?= $extraAdminFoot ?? '' ?>
</body>
</html>
