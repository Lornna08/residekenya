    </div><!-- /admin-body -->
  </div><!-- /admin-main -->
</div><!-- /admin-shell -->
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
<?= $extraAdminFoot ?? '' ?>
</body>
</html>
