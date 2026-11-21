/*
 * Shrinks photos in the browser before a form is uploaded.
 * The hosting accepts at most ~4.5 MB per request, so large phone photos
 * are resized (max 2000px, JPEG) first. Small files are left untouched.
 */
(function () {
  var MAX_SIDE = 2000, QUALITY = 0.82, SKIP_UNDER = 600 * 1024, LIMIT = 4.2 * 1024 * 1024;
  if (!window.DataTransfer || !window.FileReader) return;

  function shrink(file) {
    return new Promise(function (resolve) {
      if (!/^image\/(jpeg|png|webp)$/.test(file.type) || file.size < SKIP_UNDER) return resolve(file);
      var img = new Image(), url = URL.createObjectURL(file);
      img.onload = function () {
        var w = img.naturalWidth, h = img.naturalHeight, s = Math.min(1, MAX_SIDE / Math.max(w, h));
        var c = document.createElement('canvas');
        c.width = Math.round(w * s); c.height = Math.round(h * s);
        var ctx = c.getContext('2d');
        ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height);
        ctx.drawImage(img, 0, 0, c.width, c.height);
        URL.revokeObjectURL(url);
        c.toBlob(function (b) {
          if (!b || b.size >= file.size) return resolve(file);
          resolve(new File([b], file.name.replace(/\.(png|webp|jpe?g)$/i, '') + '.jpg', { type: 'image/jpeg' }));
        }, 'image/jpeg', QUALITY);
      };
      img.onerror = function () { URL.revokeObjectURL(url); resolve(file); };
      img.src = url;
    });
  }

  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (ev.defaultPrevented || form.__resized || !form.querySelector('input[type=file]')) return;
    var inputs = [].slice.call(form.querySelectorAll('input[type=file]')).filter(function (i) { return i.files && i.files.length; });
    if (!inputs.length) return;
    ev.preventDefault();
    var btn = form.querySelector('[type=submit]');
    if (btn) { btn.disabled = true; btn.__t = btn.textContent; btn.textContent = 'Preparing photos…'; }
    Promise.all(inputs.map(function (input) {
      return Promise.all([].slice.call(input.files).map(shrink)).then(function (files) {
        var dt = new DataTransfer(); files.forEach(function (f) { dt.items.add(f); }); input.files = dt.files;
      });
    })).then(function () {
      var total = 0;
      inputs.forEach(function (i) { [].forEach.call(i.files, function (f) { total += f.size; }); });
      if (btn) { btn.disabled = false; btn.textContent = btn.__t; }
      if (total > LIMIT) {
        alert('These files add up to ' + (total / 1048576).toFixed(1) + ' MB, which is more than one upload can take (about 4 MB). Please upload fewer photos at a time.');
        return;
      }
      form.__resized = true;
      if (ev.submitter && ev.submitter.name) {
        var h = document.createElement('input'); h.type = 'hidden'; h.name = ev.submitter.name; h.value = ev.submitter.value; form.appendChild(h);
      }
      HTMLFormElement.prototype.submit.call(form);
    });
  });
})();
