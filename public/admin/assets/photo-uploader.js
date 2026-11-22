/*
 * Photo uploader for the admin.
 * <input type="file" multiple data-photo-uploader="gallery_paths" [data-cover="featured_path"]>
 * Each photo is shrunk in the browser and uploaded on its own request, so a listing
 * can have any number of photos. Uploaded paths are kept in hidden inputs
 * named gallery_paths[] (and the chosen cover in featured_path).
 */
(function () {
  var cfg = window.RESIDE_ADMIN || {};
  var MAX = 2000, Q = 0.82;

  function shrink(file) {
    return new Promise(function (resolve) {
      if (!/^image\/(jpeg|png|webp|heic|heif)$/i.test(file.type) && !/\.(jpe?g|png|webp|heic|heif)$/i.test(file.name)) return resolve(file);
      var img = new Image(), url = URL.createObjectURL(file);
      img.onload = function () {
        var w = img.naturalWidth, h = img.naturalHeight, s = Math.min(1, MAX / Math.max(w, h));
        if (s === 1 && file.size < 900 * 1024 && /jpe?g|webp/i.test(file.type)) { URL.revokeObjectURL(url); return resolve(file); }
        var c = document.createElement('canvas'); c.width = Math.round(w * s); c.height = Math.round(h * s);
        var x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, c.width, c.height); x.drawImage(img, 0, 0, c.width, c.height);
        URL.revokeObjectURL(url);
        c.toBlob(function (b) { resolve(b ? new File([b], (file.name.replace(/\.[^.]+$/, '') || 'photo') + '.jpg', { type: 'image/jpeg' }) : file); }, 'image/jpeg', Q);
      };
      img.onerror = function () { URL.revokeObjectURL(url); resolve(file); };
      img.src = url;
    });
  }

  function upload(file, onProgress) {
    return new Promise(function (resolve, reject) {
      var fd = new FormData(); fd.append('photo', file); fd.append('_csrf', cfg.csrf || '');
      var xhr = new XMLHttpRequest();
      xhr.open('POST', '/admin/upload-photo.php');
      xhr.upload.onprogress = function (e) { if (e.lengthComputable) onProgress(e.loaded / e.total); };
      xhr.onload = function () {
        var r = null; try { r = JSON.parse(xhr.responseText); } catch (_) {}
        if (xhr.status === 200 && r && r.ok) resolve(r); else reject(new Error((r && r.error) || ('Upload failed (' + xhr.status + ')')));
      };
      xhr.onerror = function () { reject(new Error('Network error — check your connection.')); };
      xhr.send(fd);
    });
  }

  document.querySelectorAll('input[type=file][data-photo-uploader]').forEach(function (input) {
    var field = input.getAttribute('data-photo-uploader');
    var coverField = input.getAttribute('data-cover');
    var form = input.form;
    var grid = document.createElement('div'); grid.className = 'pu-grid';
    (input.closest('label') || input).insertAdjacentElement('afterend', grid);
    var coverInput = null;
    if (coverField) { coverInput = document.createElement('input'); coverInput.type = 'hidden'; coverInput.name = coverField; form.appendChild(coverInput); }
    var pending = 0;

    function syncCover() {
      if (!coverInput) return;
      var tiles = grid.querySelectorAll('.pu-tile.done');
      var chosen = grid.querySelector('.pu-tile.done.cover') || tiles[0];
      grid.querySelectorAll('.pu-tile').forEach(function (t) { t.classList.toggle('is-cover', t === chosen); });
      coverInput.value = chosen ? chosen.dataset.path : '';
    }
    function setBusy() {
      form.querySelectorAll('[type=submit]').forEach(function (b) {
        if (!b.__label) b.__label = b.textContent;
        b.disabled = pending > 0; b.textContent = pending > 0 ? 'Uploading photos… (' + pending + ' left)' : b.__label;
      });
    }

    input.addEventListener('change', function () {
      var files = [].slice.call(input.files);
      input.value = '';
      files.forEach(function (file) {
        var tile = document.createElement('div'); tile.className = 'pu-tile';
        tile.innerHTML = '<img alt=""><div class="pu-bar"><i></i></div><button type="button" class="pu-x" aria-label="Remove">×</button><button type="button" class="pu-star" aria-label="Make cover photo">Cover</button><input type="hidden">';
        var img = tile.querySelector('img'), bar = tile.querySelector('.pu-bar i'), hidden = tile.querySelector('input');
        img.src = URL.createObjectURL(file);
        grid.appendChild(tile);
        pending++; setBusy();
        tile.querySelector('.pu-x').onclick = function () { tile.remove(); syncCover(); };
        tile.querySelector('.pu-star').onclick = function () {
          grid.querySelectorAll('.pu-tile').forEach(function (t) { t.classList.remove('cover'); });
          tile.classList.add('cover'); grid.prepend(tile); syncCover();
        };
        shrink(file).then(function (f) { return upload(f, function (p) { bar.style.width = Math.round(p * 100) + '%'; }); })
          .then(function (r) {
            tile.classList.add('done'); tile.dataset.path = r.path;
            hidden.name = field + '[]'; hidden.value = r.path; syncCover();
          })
          .catch(function (e) { tile.classList.add('failed'); tile.title = e.message; tile.querySelector('.pu-bar').outerHTML = '<div class="pu-err">' + e.message + '</div>'; })
          .then(function () { pending--; setBusy(); });
      });
    });

    // Drag to reorder (mouse / trackpad); on phones use "Cover" to pick the main photo.
    var drag = null;
    grid.addEventListener('dragstart', function (e) { drag = e.target.closest('.pu-tile'); });
    grid.addEventListener('dragover', function (e) {
      e.preventDefault(); var over = e.target.closest('.pu-tile');
      if (drag && over && over !== drag) { var r = over.getBoundingClientRect(); grid.insertBefore(drag, (e.clientX - r.left) > r.width / 2 ? over.nextSibling : over); }
    });
    grid.addEventListener('drop', function () { drag = null; syncCover(); });
    new MutationObserver(function () { grid.querySelectorAll('.pu-tile').forEach(function (t) { t.draggable = true; }); }).observe(grid, { childList: true });

    form.addEventListener('submit', function (e) {
      if (pending > 0) { e.preventDefault(); alert('Please wait — photos are still uploading.'); }
    });
  });
})();
