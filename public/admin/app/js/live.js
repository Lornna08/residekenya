/* ============================================================
   Reside Kenya additions to the workspace:
   uploads (photos, documents, profile pictures), profile editor,
   phone notifications (web push) and the home-screen app.
   ============================================================ */
(function () {
  'use strict';
  var I = ES.icon, st = ES.store, esc = ES.fmt.esc;
  var BOOT = window.RESIDE_BOOT || {};

  /* ---------- uploads ---------- */
  function shrink(file, max, q) {
    return new Promise(function (resolve) {
      if (!/^image\/(jpeg|png|webp)$/i.test(file.type)) return resolve(file);
      var img = new Image(), url = URL.createObjectURL(file);
      img.onload = function () {
        var s = Math.min(1, (max || 2000) / Math.max(img.naturalWidth, img.naturalHeight));
        if (s === 1 && file.size < 900 * 1024) { URL.revokeObjectURL(url); return resolve(file); }
        var c = document.createElement('canvas'); c.width = Math.round(img.naturalWidth * s); c.height = Math.round(img.naturalHeight * s);
        var x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, c.width, c.height); x.drawImage(img, 0, 0, c.width, c.height);
        URL.revokeObjectURL(url);
        c.toBlob(function (b) { resolve(b ? new File([b], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }) : file); }, 'image/jpeg', q || 0.82);
      };
      img.onerror = function () { URL.revokeObjectURL(url); resolve(file); };
      img.src = url;
    });
  }
  function xhrUpload(url, fd, onProgress, headers, method) {
    return new Promise(function (resolve, reject) {
      var x = new XMLHttpRequest(); x.open(method || 'POST', url);
      Object.keys(headers || {}).forEach(function (k) { x.setRequestHeader(k, headers[k]); });
      if (x.upload && onProgress) x.upload.onprogress = function (e) { if (e.lengthComputable) onProgress(e.loaded / e.total); };
      x.onload = function () { var r = null; try { r = JSON.parse(x.responseText); } catch (e) { } if (x.status >= 200 && x.status < 300 && r) resolve(r); else reject(new Error((r && (r.error || r.message)) || ('Upload failed (' + x.status + ')'))); };
      x.onerror = function () { reject(new Error('Network error — check your connection.')); };
      x.send(fd);
    });
  }
  /** Upload a listing photo or a document. Resolves {url, path}. */
  ES.uploadFile = function (file, kind, onProgress) {
    kind = kind || 'photo';
    var big = kind === 'doc' && file.size > 3.5 * 1048576;
    var prep = kind === 'photo' ? shrink(file) : Promise.resolve(file);
    return prep.then(function (f) {
      if (big) {
        // Large documents go straight to file storage.
        return ES.api('blob_token', { name: f.name }).then(function (t) {
          if (!t.ok) throw new Error(t.local ? 'Files over 3.5 MB can’t be uploaded on this server.' : (t.error || 'Upload not allowed'));
          return xhrUpload('https://vercel.com/api/blob/?pathname=' + encodeURIComponent(t.pathname), f, onProgress, {
            authorization: 'Bearer ' + t.token, 'x-api-version': '13', 'x-vercel-blob-access': 'public', 'x-content-type': f.type || 'application/octet-stream',
            'x-vercel-blob-store-id': t.storeId, 'x-add-random-suffix': '0', 'x-content-length': String(f.size)
          }, 'PUT').then(function (r) { return { url: r.url, path: r.url }; });
        });
      }
      var fd = new FormData(); fd.append('file', f); fd.append('kind', kind); fd.append('_csrf', BOOT.csrf || '');
      return xhrUpload('/admin/api.php?action=upload&kind=' + kind, fd, onProgress, { 'X-CSRF-Token': BOOT.csrf || '' });
    });
  };

  /* ---------- profile ---------- */
  ES.profile = function (userId) {
    var me = st.me(), u = userId ? st.user(userId) : me, own = u.id === me.id;
    var canEdit = own || me.perm === 'super_admin' || me.perm === 'admin';
    var p = ES.open('modal', ES.head(own ? 'My profile' : u.name, own ? 'Your photo shows across the workspace and in chat' : u.role) +
      '<form class="ovl__body" id="pf" novalidate>' +
      '<div class="pf-ph"><span id="pfAv">' + ES.av(u, 84) + '</span><div><b>' + esc(u.name) + '</b><em>' + esc(u.email || '') + '</em>' +
      (canEdit ? '<label class="btn btn--sm" style="margin-top:10px">' + I('image', 14) + (u.avatar ? 'Change photo' : 'Upload photo') + '<input type="file" accept="image/*" hidden id="pfFile"></label>' : '') +
      '<span class="pf-st" id="pfSt"></span></div></div>' +
      (canEdit ? '<div class="g2" style="margin-top:18px">' +
        ES.field('Full name', '<input name="name" value="' + esc(u.name) + '" autocomplete="' + (own ? 'name' : 'off') + '">') +
        ES.field('Job title', '<input name="title" value="' + esc(u.role) + '" placeholder="e.g. Principal agent">') +
        ES.field('Phone', '<input name="phone" type="tel" value="' + esc(u.phone || '') + '" placeholder="+254 7XX XXX XXX">', '', 'span2') +
        '</div>' : '') +
      (own ? '<details class="pf-pw"><summary>' + I('key', 14) + 'Change password</summary><div class="g2" style="margin-top:12px">' +
        ES.field('Current password', '<input type="password" id="pwCur" autocomplete="current-password">') +
        ES.field('New password', '<input type="password" id="pwNew" autocomplete="new-password" placeholder="At least 8 characters">') +
        '</div><button type="button" class="btn btn--sm" id="pwSave">Update password</button></details>' : '') +
      '</form>' + (canEdit ? '<div class="ovl__foot"><button class="btn" data-close>Close</button><button class="btn btn--dark" id="pfSave">' + I('check', 15) + 'Save profile</button></div>' : ''), { label: 'Profile' });
    var pw = p.querySelector('#pwSave');
    if (pw) pw.onclick = function () {
      ES.api('password', { current: p.querySelector('#pwCur').value, new: p.querySelector('#pwNew').value }).then(function (r) {
        if (!r.ok) { ES.toast(r.error || 'Couldn’t change password', 'err'); return; }
        p.querySelector('#pwCur').value = p.querySelector('#pwNew').value = ''; ES.toast('Password updated');
      });
    };
    var file = p.querySelector('#pfFile');
    if (file) file.onchange = function () {
      var f = file.files[0]; if (!f) return;
      var stEl = p.querySelector('#pfSt'); stEl.textContent = 'Uploading…';
      shrink(f, 600, 0.88).then(function (img) {
        var fd = new FormData(); fd.append('avatar', img); fd.append('_csrf', BOOT.csrf || '');
        return xhrUpload('/admin/api.php?action=avatar&user=' + String(u.id).replace(/^u/, ''), fd, null, { 'X-CSRF-Token': BOOT.csrf || '' });
      }).then(function (r) {
        st.s.team = r.team; stEl.textContent = 'Photo saved';
        p.querySelector('#pfAv').innerHTML = ES.av(st.user(u.id), 84);
        var m = document.querySelector('.top .me'); if (m) m.innerHTML = ES.av(st.me(), 36);
        ES.app.refresh(); ES.toast('Profile photo updated');
      }).catch(function (e) { stEl.textContent = e.message; });
    };
    var save = p.querySelector('#pfSave');
    if (save) save.onclick = function () {
      var d = ES.formData(p.querySelector('#pf')); d.user = u.id;
      ES.api('profile', d).then(function (r) {
        if (!r.ok) { ES.toast(r.error || 'Couldn’t save', 'err'); return; }
        st.s.team = r.team; ES.close(); ES.app.refresh(); ES.toast('Profile saved');
      });
    };
  };

  /* ---------- phone notifications ---------- */
  var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  var isIOS = /iP(hone|ad|od)/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var hasPush = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
  var swReady = 'serviceWorker' in navigator ? navigator.serviceWorker.register('/admin/sw.js', { scope: '/admin/' }).then(function () { return navigator.serviceWorker.ready; }) : Promise.reject(new Error('no sw'));
  swReady.catch(function () { });
  function pushApi(action, payload) {
    return fetch('/admin/push.php?action=' + action, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': BOOT.csrf || '' }, body: JSON.stringify(payload || {}) }).then(function (r) { return r.json(); });
  }
  function keyBytes(b64) { var raw = atob((b64 + '='.repeat((4 - b64.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/')), o = new Uint8Array(raw.length); for (var i = 0; i < raw.length; i++) o[i] = raw.charCodeAt(i); return o; }
  ES.pushState = function () {
    if (!BOOT.vapid) return Promise.resolve('unconfigured');
    if (isIOS && !isStandalone) return Promise.resolve('install');
    if (!hasPush) return Promise.resolve('unsupported');
    if (Notification.permission === 'denied') return Promise.resolve('blocked');
    return swReady.then(function (r) { return r.pushManager.getSubscription(); }).then(function (s) { return s && Notification.permission === 'granted' ? 'on' : 'off'; }).catch(function () { return 'off'; });
  };
  ES.pushEnable = function () {
    return Notification.requestPermission().then(function (perm) {
      if (perm !== 'granted') throw new Error('Notifications were not allowed.');
      return swReady;
    }).then(function (reg) {
      return reg.pushManager.getSubscription().then(function (old) { return old || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(BOOT.vapid) }); });
    }).then(function (sub) { return pushApi('subscribe', { subscription: sub.toJSON() }); })
      .then(function (r) { if (!r.ok) throw new Error(r.error || 'Could not save'); return pushApi('test'); });
  };
  ES.pushDisable = function () {
    return swReady.then(function (r) { return r.pushManager.getSubscription(); }).then(function (sub) { if (!sub) return; return pushApi('unsubscribe', { subscription: sub.toJSON() }).then(function () { return sub.unsubscribe(); }); });
  };
  var MSG = {
    on: ['On for this device', 'You’ll get an alert for every new lead, viewing request, booking, property submission and chat message.'],
    off: ['Off on this device', 'Turn on to get alerts on this phone or computer, even when the admin is closed.'],
    install: ['Add to Home Screen first', 'On iPhone open residekenya.com/admin in <b>Safari</b>, tap <b>Share</b> → <b>Add to Home Screen</b>, then open <b>Reside Admin</b> from your home screen and turn notifications on there.'],
    blocked: ['Blocked', 'Notifications were blocked. On iPhone: Settings → Notifications → Reside Admin → Allow. On a computer: click the lock icon next to the address and allow notifications.'],
    unsupported: ['Not available in this browser', 'Use the home-screen app on iPhone (iOS 16.4 or later), or Chrome, Edge or Safari on a computer.'],
    unconfigured: ['Not configured', 'Notifications aren’t set up on the server.']
  };
  ES.pushCard = function (host) {
    function draw(state, extra) {
      var m = MSG[state] || MSG.off;
      host.innerHTML = '<div class="pn"><span class="pn__ic">' + I('bell', 18) + '</span><span class="pn__t"><b>Phone notifications</b> ' + ES.pill(m[0], state === 'on' ? 'green' : state === 'blocked' ? 'red' : state === 'install' ? 'amber' : 'grey') + '<em>' + (extra ? esc(extra) : m[1]) + '</em></span><span class="pn__a">' +
        (state === 'off' ? '<button class="btn btn--dark btn--sm" data-pn="on">' + I('bell', 14) + 'Turn on</button>' : '') +
        (state === 'on' ? '<button class="btn btn--sm" data-pn="test">Send test</button><button class="btn btn--sm btn--ghost" data-pn="off">Turn off</button>' : '') + '</span></div>';
    }
    host.onclick = function (e) {
      var b = e.target.closest('[data-pn]'); if (!b) return;
      b.disabled = true;
      var k = b.dataset.pn;
      var run = k === 'on' ? ES.pushEnable() : k === 'off' ? ES.pushDisable() : pushApi('test').then(function (r) { if (!r.ok) throw new Error(r.error || 'Not delivered'); });
      run.then(function () { ES.pushState().then(function (s) { draw(s, k === 'test' || k === 'on' ? 'Test notification sent — it should arrive in a few seconds.' : ''); }); })
        .catch(function (err) { ES.pushState().then(function (s) { draw(s, err.message); }); });
    };
    ES.pushState().then(function (s) { draw(s); });
  };
  ES.pushSettings = function () {
    var p = ES.open('modal', ES.head('Phone notifications') + '<div class="ovl__body"><div id="pnHost"></div></div>', { label: 'Phone notifications' });
    ES.pushCard(p.querySelector('#pnHost'));
  };

  /* ---------- home-screen app badge ---------- */
  st.subscribe && setTimeout(function () {
    st.subscribe(function () {
      if (!('setAppBadge' in navigator)) return;
      var n = st.newLeadCount() + st.unreadCount();
      n ? navigator.setAppBadge(n).catch(function () { }) : navigator.clearAppBadge().catch(function () { });
    });
  }, 0);
})();
