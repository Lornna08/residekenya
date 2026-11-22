/* Reside Ke Admin — web-app glue: service worker, push notifications, live badges. */
(function () {
  var cfg = window.RESIDE_ADMIN || {};
  var csrf = cfg.csrf || '';
  var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  var isIOS = /iP(hone|ad|od)/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var hasPush = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
  document.documentElement.classList.toggle('is-app', isStandalone);

  var swReady = 'serviceWorker' in navigator
    ? navigator.serviceWorker.register('/admin/sw.js', { scope: '/admin/' }).then(function () { return navigator.serviceWorker.ready; })
    : Promise.reject(new Error('no sw'));
  swReady.catch(function () {});

  function api(action, payload) {
    return fetch('/admin/push.php?action=' + action, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify(payload || {})
    }).then(function (r) { return r.json(); });
  }
  function keyToBytes(b64) {
    var pad = '='.repeat((4 - b64.length % 4) % 4);
    var raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
    var out = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
    return out;
  }

  /* ---------- Notifications card ---------- */
  var card = document.getElementById('notifyCard');
  function render(state, msg) {
    if (!card) return;
    var status = card.querySelector('[data-n-status]'), btn = card.querySelector('[data-n-toggle]'), test = card.querySelector('[data-n-test]'), hint = card.querySelector('[data-n-hint]');
    hint.textContent = '';
    test.hidden = state !== 'on';
    btn.hidden = false; btn.disabled = false;
    if (state === 'on') { status.textContent = 'On for this device'; status.className = 'pill ok'; btn.textContent = 'Turn off'; }
    else if (state === 'off') { status.textContent = 'Off on this device'; status.className = 'pill'; btn.textContent = 'Turn on notifications'; }
    else if (state === 'install') {
      status.textContent = 'Add to Home Screen first'; status.className = 'pill warn'; btn.hidden = true;
      hint.innerHTML = 'On iPhone: open this page in <b>Safari</b>, tap <b>Share</b> <span aria-hidden="true">⎋</span> → <b>Add to Home Screen</b>, then open <b>Reside Admin</b> from your home screen and turn notifications on there.';
    }
    else if (state === 'blocked') { status.textContent = 'Blocked'; status.className = 'pill off'; btn.hidden = true; hint.textContent = 'Notifications were blocked. On iPhone: Settings → Notifications → Reside Admin → Allow Notifications. Then reopen the app.'; }
    else if (state === 'unsupported') { status.textContent = 'Not supported here'; status.className = 'pill'; btn.hidden = true; hint.textContent = 'This browser cannot receive notifications. Use the installed app on iPhone (iOS 16.4+), or Chrome/Edge/Safari on a computer.'; }
    if (msg) hint.textContent = msg;
  }
  function refresh() {
    if (!card) return;
    if (!cfg.vapid) return render('unsupported', 'Notifications are not configured on the server yet.');
    if (isIOS && !isStandalone) return render('install');
    if (!hasPush) return render('unsupported');
    if (Notification.permission === 'denied') return render('blocked');
    swReady.then(function (reg) { return reg.pushManager.getSubscription(); })
      .then(function (sub) { render(sub && Notification.permission === 'granted' ? 'on' : 'off'); })
      .catch(function () { render('off'); });
  }
  function enable() {
    var btn = card.querySelector('[data-n-toggle]'); btn.disabled = true; btn.textContent = 'Turning on…';
    Notification.requestPermission().then(function (perm) {
      if (perm !== 'granted') { refresh(); throw new Error('denied'); }
      return swReady;
    }).then(function (reg) {
      return reg.pushManager.getSubscription().then(function (old) {
        return old || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyToBytes(cfg.vapid) });
      });
    }).then(function (sub) {
      return api('subscribe', { subscription: sub.toJSON() });
    }).then(function (r) {
      if (!r.ok) throw new Error(r.error || 'Could not save');
      return api('test');
    }).then(function () { refresh(); })
      .catch(function (e) { if (e.message !== 'denied') render('off', 'Could not turn on: ' + e.message); });
  }
  function disable() {
    swReady.then(function (reg) { return reg.pushManager.getSubscription(); }).then(function (sub) {
      if (!sub) return;
      return api('unsubscribe', { subscription: sub.toJSON() }).then(function () { return sub.unsubscribe(); });
    }).then(refresh, refresh);
  }
  if (card) {
    card.querySelector('[data-n-toggle]').addEventListener('click', function () {
      this.textContent === 'Turn off' ? disable() : enable();
    });
    card.querySelector('[data-n-test]').addEventListener('click', function () {
      var b = this; b.disabled = true;
      api('test').then(function (r) { b.disabled = false; render('on', r.ok ? 'Test sent — it should appear in a few seconds.' : (r.error || 'Not delivered.')); });
    });
    refresh();
  }

  /* ---------- Live unread badges (tab bar, sidebar, app icon) ---------- */
  function setBadge(el, n) {
    if (!el) return;
    el.textContent = n > 99 ? '99+' : String(n);
    el.hidden = !n;
  }
  function poll() {
    if (document.visibilityState !== 'visible') return;
    fetch('/admin/push.php?action=counts', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
      if (!d.ok) return;
      setBadge(document.querySelector('[data-badge="inbox"]'), d.inquiries + d.bookings + d.submissions);
      if ('setAppBadge' in navigator) { d.total ? navigator.setAppBadge(d.total) : navigator.clearAppBadge(); }
    }).catch(function () {});
  }
  poll();
  setInterval(poll, 45000);
  document.addEventListener('visibilitychange', poll);

  /* ---------- Sidebar overlay on phones ---------- */
  var side = document.getElementById('adminSide'), scrim = document.getElementById('adminScrim');
  if (side && scrim) {
    new MutationObserver(function () { scrim.classList.toggle('open', side.classList.contains('open')); }).observe(side, { attributes: true, attributeFilter: ['class'] });
    scrim.addEventListener('click', function () { side.classList.remove('open'); });
  }
  var more = document.getElementById('tabMore');
  if (more && side) more.addEventListener('click', function (e) { e.preventDefault(); side.classList.toggle('open'); });
})();
