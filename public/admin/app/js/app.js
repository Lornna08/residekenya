/* ============================================================
   Reside Kenya — application shell: navigation, routing, search,
   notifications and the live team chat panel
   ============================================================ */
(function () {
  'use strict';
  var I = ES.icon, st = ES.store, f = ES.fmt, esc = f.esc;
  ES.pages = {};
  ES.ui = { listings: { view: 'grid', q: '', type: '', cat: '', status: '', area: '', beds: '', agent: '', min: '', max: '', listed: '', sort: 'newest', page: 1 }, deals: { agent: '', kind: 'all', showLost: false }, leads: { q: '', status: '', source: '', agent: '', sort: 'recent' }, contacts: { q: '', type: '' }, cal: { view: 'month', cursor: null }, chat: { conv: 'cv1', open: true, drafts: {} }, period: 12 };

  // Left menu: workspace pages, then the website pages (server pages with the same look).
  function wc(k) { var c = (st.s.website || {}).counts || {}; return c[k] || 0; }
  var NAV = [
    { g: 'Workspace', items: [
      { id: 'overview', label: 'Overview', icon: 'home' },
      { id: 'listings', label: 'Properties', icon: 'building', sub: 'props' },
      { id: 'rentals', label: 'Rentals', icon: 'key' },
      { id: 'sales', label: 'Sales', icon: 'tag' },
      { id: 'deals', label: 'Deals', icon: 'board' },
      { id: 'leads', label: 'Leads', icon: 'user', badge: function () { return st.newLeadCount(); } },
      { id: 'contacts', label: 'Contacts', icon: 'users' },
      { id: 'tasks', label: 'Tasks & follow-ups', icon: 'task', badge: function () { return st.overdueFollowups().length; } },
      { id: 'calendar', label: 'Calendar', icon: 'cal' },
      { id: 'chat', label: 'Team chat', icon: 'chat', badge: function () { return st.unreadCount(); } }
    ] },
    { g: 'Team & reports', items: [
      { id: 'team', label: 'Team', icon: 'users' },
      { id: 'commissions', label: 'Commission reports', icon: 'wallet' },
      { id: 'analytics', label: 'Analytics', icon: 'chart' },
      { id: 'documents', label: 'Documents', icon: 'file' },
      { id: 'settings', label: 'Settings', icon: 'gear' }
    ] }
  ];
  var SITE = [
    { href: '/admin/inquiries', label: 'Website inquiries', icon: 'mail', badge: function () { return wc('inquiries'); } },
    { href: '/admin/bookings', label: 'Airbnb bookings', icon: 'cal', badge: function () { return wc('bookings'); } },
    { href: '/admin/submissions', label: 'Property submissions', icon: 'upload', badge: function () { return wc('submissions'); } },
    { href: '/admin/homepage', label: 'Homepage editor', icon: 'home' },
    { href: '/admin/site-editor', label: 'Pages & policies', icon: 'file' },
    { href: '/admin/site-editor?p=details', label: 'Contact & socials', icon: 'phone' },
    { href: '/admin/quick-listing', label: 'Quick listing', icon: 'plus' },
    { href: '/admin/settings', label: 'Website settings', icon: 'gear' },
    { href: '/admin/locations', label: 'Locations', icon: 'pin' },
    { href: '/admin/categories', label: 'Property types', icon: 'building' },
    { href: '/admin/amenities', label: 'Amenities', icon: 'check' },
    { href: '/admin/testimonials', label: 'Testimonials', icon: 'star' },
    { href: '/admin/agents', label: 'Website agents', icon: 'users' },
    { href: '/admin/subscribers', label: 'Subscribers', icon: 'mail' },
    { href: '/admin/import-export', label: 'Import & export', icon: 'download' },
    { href: '/admin/users', label: 'Users & access', icon: 'user' },
    { href: '/admin/activity', label: 'Activity log', icon: 'clock' }
  ];
  ES.SITE_MENU = SITE;
  var TAB_OF = { property: 'listings', 'listing-new': 'listings', 'listing-edit': 'listings', deal: 'deals', contact: 'contacts', lead: 'leads' };
  var LS = { get: function (k, d) { try { var v = localStorage.getItem(k); return v === null ? d : v; } catch (e) { return d; } }, set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) { } } };
  function navState() { try { return JSON.parse(LS.get('reside_nav', '{}')) || {}; } catch (e) { return {}; } }
  function applyNavState() { var n = navState(); ['props', 'site'].forEach(function (g) { document.documentElement.classList.toggle('g-' + g + '-off', !!n[g]); }); }
  ES.theme = function (t) {
    t = t === 'dark' ? 'dark' : 'light'; document.documentElement.dataset.theme = t; LS.set('reside_admin_theme', t);
    var m = document.querySelector('meta[name=theme-color]'); if (m) m.content = t === 'dark' ? '#0F1115' : '#EEF2F8';
    document.querySelectorAll('[data-act=theme]').forEach(function (b) { b.classList.toggle('is-on', b.dataset.v === t); b.setAttribute('aria-pressed', b.dataset.v === t); });
  };

  /* ---------------- shell ---------------- */
  function shell() {
    document.body.innerHTML =
      '<div class="app" id="app">' +
      '<aside class="side" id="side" aria-label="Main menu"></aside><div class="side-scrim" data-act="navclose"></div>' +
      '<div class="main">' +
      '<header class="top"><button class="ib top__menu" data-act="drawer" aria-label="Open menu">' + I('menu', 20) + '</button>' +
      '<a class="brand top__brand" href="#/overview" aria-label="Reside Kenya overview"><span class="brand__m"><img src="/assets/img/favicon-64.png" alt=""></span><span class="brand__w">Reside<em>Kenya</em></span></a>' +
      '<span class="top__title" id="topTitle"></span>' +
      '<span class="top__save" id="saveState" aria-live="polite"></span><span class="top__sp"></span>' +
      '<button class="ib ib--round" data-act="search" aria-label="Search (Ctrl+K)">' + I('search', 18) + '</button>' +
      '<button class="ib ib--round" data-act="notes" aria-label="Notifications">' + I('bell', 18) + '<i class="ib__dot" id="noteDot" hidden></i></button>' +
      '<a class="ib ib--round top__hide-s" href="/" target="_blank" rel="noopener" aria-label="View website" title="View website">' + I('globe', 18) + '</a>' +
      '<button class="ib ib--round top__hide-s" data-act="themeflip" aria-label="Switch light or dark" title="Light / dark">' + I('moon', 18) + '</button>' +
      '<button class="me" data-act="me" aria-label="Your profile">' + ES.av(st.me(), 36) + '</button></header>' +
      '<main class="view" id="view" tabindex="-1"></main></div>' +
      '<aside class="chat" id="chat" aria-label="Team chat"></aside></div>' +
      '<button class="chat-fab" data-act="chatm" aria-label="Open team chat">' + I('chat', 22) + '<i id="fabDot" hidden></i></button>';
    document.body.addEventListener('click', onClick);
    document.addEventListener('keydown', function (e) {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); openSearch(); }
      if (e.key === '/' && !/input|textarea|select/i.test(document.activeElement.tagName) && !document.querySelector('.ovl')) { e.preventDefault(); openSearch(); }
      if (e.key === 'Escape') document.documentElement.classList.remove('is-nav');
    });
    window.addEventListener('resize', debounce(function () { var r = route(); if (r.redrawOnResize) render(); }, 200));
    applyNavState(); ES.theme(LS.get('reside_admin_theme', 'light'));
  }

  function badge(n) { return n ? '<i class="side__b">' + (n > 99 ? '99+' : n) + '</i>' : ''; }
  function sideHTML() {
    var cur = current.name, active = TAB_OF[cur] || cur, U = ES.ui.listings;
    var h = '<div class="side__h"><a class="brand" href="#/overview" aria-label="Reside Kenya overview"><span class="brand__m"><img src="/assets/img/favicon-64.png" alt=""></span><span class="brand__w">Reside<em>Kenya</em></span></a>' +
      '<button class="side__min" data-act="sidemin" aria-label="Collapse menu" title="Collapse menu">' + I('left', 16) + '</button><button class="side__x" data-act="navclose" aria-label="Close menu">' + I('x', 18) + '</button></div><nav class="side__nav">';
    NAV.forEach(function (grp) {
      h += '<div class="side__g">' + grp.g + '</div>';
      grp.items.forEach(function (t) {
        var on = active === t.id, b = t.badge ? t.badge() : 0;
        var link = '<a href="#/' + t.id + '" class="side__a' + (on ? ' is-on' : '') + '" title="' + t.label + '"' + (on ? ' aria-current="page"' : '') + '>' + I(t.icon, 18) + '<span>' + t.label + '</span>' + badge(b) + '</a>';
        if (t.sub) {
          h += '<div class="side__grp" data-g="' + t.sub + '"><div class="side__row">' + link + '<button class="side__car" data-act="grp" data-g="' + t.sub + '" aria-label="Show or hide property types">' + I('down', 15) + '</button></div><div class="side__sub">' +
            [['', 'All properties']].concat(ES.PURPOSES || []).map(function (o) { var son = cur === 'listings' && (U.cat || '') === o[0]; return '<a href="#/listings' + (o[0] ? '/' + o[0] : '/all') + '" class="' + (son ? 'is-on' : '') + '">' + o[1] + '</a>'; }).join('') +
            '<a href="#/listings/new" class="side__new">' + I('plus', 13) + 'New listing</a></div></div>';
        } else h += link;
      });
    });
    h += '<button class="side__g side__g--btn" data-act="grp" data-g="site">Website' + badge(SITE.reduce(function (a, m) { return a + (m.badge ? m.badge() : 0); }, 0)) + I('down', 14) + '</button><div class="side__gb" data-gb="site">' +
      SITE.map(function (t) { var b = t.badge ? t.badge() : 0; return '<a href="' + t.href + '" class="side__a" title="' + t.label + '">' + I(t.icon, 18) + '<span>' + t.label + '</span>' + badge(b) + '</a>'; }).join('') + '</div>';
    h += '</nav><div class="side__f"><div class="side__thm" role="group" aria-label="Theme"><button data-act="theme" data-v="light" title="Light">' + I('sun', 15) + '<span>Light</span></button><button data-act="theme" data-v="dark" title="Dark">' + I('moon', 15) + '<span>Dark</span></button></div>' +
      '<a class="side__a" href="/" target="_blank" rel="noopener" title="View website">' + I('globe', 18) + '<span>View website</span></a><a class="side__a" href="/admin/logout" title="Sign out">' + I('out', 18) + '<span>Sign out</span></a></div>';
    return h;
  }
  function tabs() {
    var side = document.getElementById('side'), y = side.querySelector('.side__nav') ? side.querySelector('.side__nav').scrollTop : 0;
    side.innerHTML = sideHTML(); var nv = side.querySelector('.side__nav'); if (nv) nv.scrollTop = y;
    ES.theme(document.documentElement.dataset.theme);
    var nd = document.getElementById('noteDot'); if (nd) nd.hidden = !st.s.notifications.some(function (n) { return !n.read; });
    var un = st.unreadCount(), fd = document.getElementById('fabDot'); if (fd) { fd.hidden = !un; fd.textContent = un; }
    var tt = document.getElementById('topTitle'), lab = '';
    NAV.forEach(function (g) { g.items.forEach(function (t) { if (t.id === (TAB_OF[current.name] || current.name)) lab = t.label; }); });
    if (tt) tt.textContent = lab;
  }

  /* ---------------- router ---------------- */
  var current = { name: 'overview', params: [] };
  var ROUTES = [
    [/^overview$/, 'overview'], [/^listings$/, 'listings'], [/^listings\/new$/, 'listing-new'], [/^listings\/(sale|rent|airbnb|commercial|land|all)$/, 'listings'], [/^property\/([\w-]+)$/, 'property'], [/^property\/([\w-]+)\/edit$/, 'listing-edit'], [/^property\/([\w-]+)\/(\w+)$/, 'property'],
    [/^rentals$/, 'rentals'], [/^rentals\/(\w+)$/, 'rentals'], [/^sales$/, 'sales'], [/^deals$/, 'deals'], [/^deal\/([\w-]+)$/, 'deal'],
    [/^leads$/, 'leads'], [/^contacts$/, 'contacts'], [/^contact\/([\w-]+)$/, 'contact'], [/^calendar$/, 'calendar'], [/^tasks$/, 'tasks'],
    [/^team$/, 'team'], [/^commissions$/, 'commissions'], [/^analytics$/, 'analytics'], [/^documents$/, 'documents'], [/^settings$/, 'settings'], [/^chat$/, 'chat']
  ];
  function parse() {
    var h = location.hash.replace(/^#\/?/, '').split('?')[0];
    for (var i = 0; i < ROUTES.length; i++) { var m = h.match(ROUTES[i][0]); if (m) return { name: ROUTES[i][1], params: m.slice(1) }; }
    return { name: 'overview', params: [] };
  }
  function route() { return ES.pages[current.name] || {}; }
  var lastKey = '';
  function render(keepScroll) {
    var v = document.getElementById('view'), p = route(), y = window.scrollY;
    if (!p.render) { v.innerHTML = ES.empty('alert', 'Page not found', 'That page doesn’t exist.', '<a class="btn" href="#/overview">Back to overview</a>'); return; }
    v.className = 'view view--' + current.name;
    p.render(v, current.params);
    tabs();
    if (keepScroll) window.scrollTo(0, y);
  }
  function go() {
    var next = parse(), key = next.name + '/' + next.params.join('/');
    var same = key === lastKey; lastKey = key; current = next;
    ES.closeMenu(); if (document.querySelector('.ovl') && !same) ES.close();
    document.documentElement.classList.remove('is-drawer');
    render(same);
    if (!same) { window.scrollTo(0, 0); var v = document.getElementById('view'); try { v.focus({ preventScroll: true }); } catch (e) { } }
    var t = (TAB_OF[current.name] || current.name); document.title = (t.charAt(0).toUpperCase() + t.slice(1)) + ' · Reside Kenya';
    document.documentElement.classList.toggle('is-chatroute', current.name === 'chat');
    var q = (location.hash.split('?')[1] || '');
    if (/(^|&)open=search/.test(q)) setTimeout(openSearch, 60);
    if (/(^|&)open=notes/.test(q)) setTimeout(function () { openNotes(); }, 60);
    var lq = q.match(/(^|&)lead=([\w-]+)/); if (lq && current.name === 'leads') setTimeout(function () { if (st.lead(lq[2])) ES.pages.leads.open(lq[2]); }, 80);
  }
  ES.app = { go: function (h) { if (location.hash === h) go(); else location.hash = h; }, refresh: function () { render(true); }, current: function () { return current; } };

  /* ---------------- clicks ---------------- */
  function setChat(open) { ES.ui.chat.open = open; document.documentElement.classList.toggle('is-chat-off', !open); LS.set('reside_chat_open', open ? '1' : '0'); }
  function onClick(e) {
    if (e.target.closest('.side a')) document.documentElement.classList.remove('is-nav');
    var a = e.target.closest('[data-act]'); if (!a) return;
    var act = a.dataset.act;
    if (act === 'search') openSearch();
    else if (act === 'notes') openNotes(a);
    else if (act === 'help') openHelp();
    else if (act === 'me') ES.menu(a, [{ label: st.me().name + ' — ' + st.me().role, icon: 'user', disabled: true }, '-', { label: 'My profile & photo', icon: 'image', k: 'profile' }, { label: 'Phone notifications', icon: 'bell', k: 'push' }, { label: 'Settings', icon: 'gear', go: '#/settings' }, { label: 'Team', icon: 'users', go: '#/team' }, '-', { label: 'View website', icon: 'out', href: '/' }, { label: 'Sign out', icon: 'x', href: '/admin/logout' }], function (it) { if (it.go) ES.app.go(it.go); else if (it.href) { if (it.href === '/') window.open('/', '_blank'); else location.href = it.href; } else if (it.k === 'profile') ES.profile(); else if (it.k === 'push') ES.pushSettings(); });
    else if (act === 'drawer') document.documentElement.classList.add('is-nav');
    else if (act === 'navclose') document.documentElement.classList.remove('is-nav');
    else if (act === 'sidemin') { var mn = !document.documentElement.classList.contains('is-side-min'); document.documentElement.classList.toggle('is-side-min', mn); LS.set('reside_side_min', mn ? '1' : '0'); a.title = mn ? 'Expand menu' : 'Collapse menu'; window.dispatchEvent(new Event('resize')); }
    else if (act === 'grp') { if (document.documentElement.classList.contains('is-side-min') && innerWidth > 900) { document.documentElement.classList.remove('is-side-min'); LS.set('reside_side_min', '0'); } var n = navState(); n[a.dataset.g] = !n[a.dataset.g]; LS.set('reside_nav', JSON.stringify(n)); applyNavState(); }
    else if (act === 'theme') ES.theme(a.dataset.v);
    else if (act === 'themeflip') ES.theme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark');
    else if (act === 'chatm') { if (innerWidth > 1180) { setChat(!ES.ui.chat.open); if (ES.ui.chat.open) { renderChat(); ES.sync.refresh(); setTimeout(function () { var i = document.querySelector('.chat__f input'); if (i) i.focus(); }, 60); } } else ES.app.go('#/chat'); }
  }

  /* ---------------- search ---------------- */
  function openSearch() {
    var p = ES.open('modal', '<div class="srch"><div class="srch__in">' + I('search', 18) + '<input type="search" placeholder="Search listings, contacts, leads, deals…" aria-label="Search" autofocus><kbd>Esc</kbd></div><div class="srch__res" id="srchRes"></div></div>', { label: 'Search', cls: 'ovl--top' });
    var input = p.querySelector('input'), res = p.querySelector('#srchRes'), sel = 0, items = [];
    function run() {
      var q = input.value.trim().toLowerCase(); items = [];
      function m(s) { return s && String(s).toLowerCase().indexOf(q) > -1; }
      if (!q) {
        items = st.listings().slice(0, 4).map(function (x) { return { g: 'Recent listings', h: '#/property/' + x.id, t: x.title, s: x.area + ' · ' + f.money(x.price, true), img: x.images[0] }; });
      } else {
        st.listings().forEach(function (x) { if (m(x.title) || m(x.ref) || m(x.area) || m(x.address) || m(x.type)) items.push({ g: 'Listings', h: '#/property/' + x.id, t: x.title, s: x.ref + ' · ' + x.area + ' · ' + f.money(x.price, true), img: x.images[0] }); });
        st.s.contacts.forEach(function (c) { if (m(c.name) || m(c.phone) || m(c.email) || m(c.company)) items.push({ g: 'Contacts', h: '#/contact/' + c.id, t: c.name, s: c.types.join(', ') + ' · ' + c.phone, c: c }); });
        st.s.leads.forEach(function (l) { if (m(l.name) || m(l.phone) || m(l.email)) items.push({ g: 'Leads', h: '#/leads?lead=' + l.id, lead: l.id, t: l.name, s: l.status + ' · ' + l.source }); });
        st.s.deals.forEach(function (d) { var p2 = st.prop(d.propertyId), b = st.contact(d.buyerId); if (m(d.ref) || m(p2.title) || (b && m(b.name))) items.push({ g: 'Deals', h: '#/deal/' + d.id, t: d.ref + ' — ' + p2.title, s: st.stage(d.stage).short + ' · ' + f.money(d.value, true) }); });
        st.s.docs.forEach(function (d) { if (m(d.name)) items.push({ g: 'Documents', h: '#/documents', t: d.name, s: ES.DOC_KINDS[d.kind] }); });
        items = items.slice(0, 14);
      }
      sel = 0;
      var g = '';
      res.innerHTML = items.length ? items.map(function (it, i) {
        var head = it.g !== g ? '<div class="srch__g">' + it.g + '</div>' : ''; g = it.g;
        return head + '<a href="' + it.h + '" class="srch__i' + (i === 0 ? ' is-sel' : '') + '" data-i="' + i + '">' + (it.img ? '<img src="' + it.img + '" alt="">' : it.c ? ES.cav(it.c, 32) : '<span class="srch__ic">' + I(it.g === 'Deals' ? 'board' : it.g === 'Leads' ? 'user' : 'file', 16) + '</span>') + '<span><b>' + esc(it.t) + '</b><em>' + esc(it.s) + '</em></span>' + I('right', 14) + '</a>';
      }).join('') : ES.empty('search', 'No matches', 'Try a name, area, phone number or reference like EST-1041.');
    }
    function pick(i) { var it = items[i]; if (!it) return; ES.close(); if (it.lead) { ES.app.go('#/leads'); setTimeout(function () { ES.pages.leads.open(it.lead); }, 60); } else ES.app.go(it.h); }
    input.addEventListener('input', run);
    input.addEventListener('keydown', function (e) {
      var els = res.querySelectorAll('.srch__i');
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); sel = Math.max(0, Math.min(els.length - 1, sel + (e.key === 'ArrowDown' ? 1 : -1))); els.forEach(function (x, i) { x.classList.toggle('is-sel', i === sel); }); if (els[sel]) els[sel].scrollIntoView({ block: 'nearest' }); }
      if (e.key === 'Enter') { e.preventDefault(); pick(sel); }
    });
    res.addEventListener('click', function (e) { var a = e.target.closest('.srch__i'); if (a) { e.preventDefault(); pick(+a.dataset.i); } });
    run();
  }

  /* ---------------- notifications ---------------- */
  function openNotes(anchor) {
    var N = st.s.notifications, ic = { lead: 'user', overdue: 'clock', offer: 'tag', lease: 'key', rent: 'wallet' };
    var p = ES.open('drawer', ES.head('Notifications', N.filter(function (n) { return !n.read; }).length + ' unread') +
      '<div class="ovl__body"><div class="nt-acts"><button class="btn btn--sm" data-all>' + I('check', 14) + 'Mark all as read</button></div>' +
      (N.length ? N.map(function (n) { return '<button class="nt' + (n.read ? '' : ' is-new') + '" data-id="' + n.id + '"><span class="nt__ic">' + I(ic[n.kind] || 'bell', 16) + '</span><span class="nt__b"><b>' + esc(n.text) + '</b><em>' + f.ago(n.at) + '</em></span>' + (n.read ? '' : '<i class="nt__dot"></i>') + '</button>'; }).join('') : ES.empty('bell', 'You’re all caught up', 'New leads, offers and lease reminders will show here.')) + '</div>', { label: 'Notifications' });
    p.addEventListener('click', function (e) {
      if (e.target.closest('[data-all]')) { st.readAll(); ES.close(); ES.toast('All notifications marked as read'); return; }
      var b = e.target.closest('.nt'); if (!b) return;
      var n = N.filter(function (x) { return x.id === b.dataset.id; })[0]; st.readNote(n.id); ES.close();
      var r = n.ref || {}; if (r.type === 'lead') { ES.app.go('#/leads'); setTimeout(function () { ES.pages.leads.open(r.id); }, 60); } else if (r.type === 'deal') ES.app.go('#/deal/' + r.id); else if (r.type === 'property') ES.app.go('#/property/' + r.id); else if (r.type === 'rentals') ES.app.go('#/rentals/payments');
    });
  }
  function openHelp() {
    ES.open('modal', ES.head('Getting around Reside Kenya') + '<div class="ovl__body help"><ul>' +
      ['<b>New listing</b> on the overview adds a property — it goes live on residekenya.com as soon as it’s Active. Every listing also has <b>Edit website details</b> for SEO, video and floor plans.',
       'Website inquiries, viewing requests, Airbnb bookings and “list my property” forms arrive in <a href="#/leads">Leads</a> automatically, and you get a phone notification.',
       'Drag a card on the <a href="#/deals">Deals board</a> to a new stage. Closing a deal as won records the commission and marks the listing sold or let.',
       'Schedule follow-ups on any lead; they show on the overview, in <a href="#/tasks">Tasks</a> and on the <a href="#/calendar">Calendar</a>.',
       'Press <kbd>Ctrl</kbd> + <kbd>K</kbd> (or <kbd>/</kbd>) to search everything. The <b>More</b> menu has the website pages: homepage editor, settings, locations, import & export.'].map(function (t) { return '<li>' + t + '</li>'; }).join('') +
      '</ul><p class="muted">Changes save automatically and your team sees them within a few seconds.</p></div>', { label: 'Help' }).addEventListener('click', function (e) { if (e.target.closest('a')) ES.close(); });
  }

  /* ---------------- chat panel ---------------- */
  function chatHTML(mobile) {
    var c = ES.ui.chat, convs = st.s.convs, conv = convs.filter(function (x) { return x.id === c.conv; })[0] || convs[0];
    if (!conv) return '<div class="chat__h">' + (mobile ? '<a href="#/overview" class="ib" aria-label="Back">' + I('left', 18) + '</a>' : '<button class="ib" data-chat="toggle" aria-label="Collapse chat">' + I('collapse', 16) + '</button>') + '<b>Team chat</b></div>' + ES.empty('users', 'No teammates yet', 'Add people in Users & access and you can chat with them here.', '<a class="btn btn--sm" href="/admin/users.php">Add a teammate</a>');
    c.conv = conv.id;
    var other = st.user(conv.userId), online = st.s.team.filter(function (u) { return u.online; }).length;
    var msgs = st.s.msgs.filter(function (m) { return m.convId === conv.id; }).sort(function (a, b) { return new Date(a.at) - new Date(b.at); });
    var pinned = msgs.filter(function (m) { return m.propertyId; })[0], pp = pinned ? st.prop(pinned.propertyId) : null;
    var html = '<div class="chat__h">' + (mobile ? '<a href="#/overview" class="ib" aria-label="Back">' + I('left', 18) + '</a>' : '<button class="ib" data-chat="toggle" aria-label="Collapse chat">' + I('collapse', 16) + '</button>') + '<b>Team chat</b><span class="chat__on"><i></i>' + online + ' online</span></div>' +
      '<div class="chat__people" role="tablist" aria-label="Conversations">' + convs.map(function (cv) { var u = st.user(cv.userId); return '<button role="tab" aria-selected="' + (cv.id === conv.id) + '" class="chat__p' + (cv.id === conv.id ? ' is-on' : '') + '" data-chat="conv" data-id="' + cv.id + '">' + ES.av(u, 38, u.online ? 'is-online' : '') + (cv.unread ? '<i class="chat__u">' + cv.unread + '</i>' : '') + '<span>' + esc(u.name.split(' ')[0]) + '</span></button>'; }).join('') + '</div>' +
      (pp ? '<a class="chat__pin" href="#/property/' + pp.id + '"><img src="' + ES.img(pp.images[0], 640) + '" alt=""><span><b>' + esc(pp.title) + '</b><em>' + esc(pp.area) + ' · ' + f.money(pp.price, true) + '</em></span>' + I('right', 14) + '</a>' : '') +
      '<div class="chat__log" id="chatLog" aria-live="polite">';
    var lastDay = '';
    msgs.forEach(function (m) {
      var day = f.day(m.at); if (day !== lastDay) { html += '<div class="chat__day"><span>' + day + '</span></div>'; lastDay = day; }
      var mine = m.from === ES.config.userId, u = st.user(m.from);
      html += '<div class="msg' + (mine ? ' is-me' : '') + (Date.now() - new Date(m.at) < 4000 ? ' is-new' : '') + '">' + (mine ? '<div class="msg__meta"><em>' + f.time(m.at) + '</em><b>' + esc(u.name.split(' ')[0]) + '</b>' + ES.av(u, 22) + '</div>' : '<div class="msg__meta">' + ES.av(u, 22) + '<b>' + esc(u.name) + '</b><em>' + f.time(m.at) + '</em></div>');
      if (m.voice) html += '<div class="bub bub--voice"><span class="vplay">' + I('play', 12) + '</span><span class="wave">' + Array.apply(null, Array(26)).map(function (_, i) { return '<i style="height:' + (4 + Math.abs(Math.sin(i * 1.7 + m.voice)) * 14).toFixed(0) + 'px"></i>'; }).join('') + '</span><em>' + Math.floor(m.voice / 60) + ':' + ('0' + m.voice % 60).slice(-2) + '</em></div>';
      if (m.photos) html += '<div class="bub bub--photos"><div class="phg"><img src="' + m.photos[0] + '" alt=""><span class="phg__s"><img src="' + m.photos[1] + '" alt=""><b>+' + (m.count - 2) + '</b></span></div><em>' + m.count + ' photos · ' + m.size + '</em></div>' + (m.reactions ? '<div class="rx">' + Object.keys(m.reactions).map(function (k) { return '<span>' + k + ' ' + m.reactions[k] + '</span>'; }).join('') + '</div>' : '');
      if (m.text) html += '<div class="bub">' + esc(m.text) + '</div>';
      html += '</div>';
    });
    if (c.typing === conv.id) html += '<div class="msg"><div class="msg__meta">' + ES.av(other, 22) + '<b>' + esc(other.name) + '</b></div><div class="bub bub--typing"><i></i><i></i><i></i></div></div>';
    html += '</div><form class="chat__f" data-chat="form"><input name="m" placeholder="Enter your message" aria-label="Message ' + esc(other.name) + '" autocomplete="off" value="' + esc(c.drafts[conv.id] || '') + '"><button type="button" class="ib" data-chat="attach" aria-label="Share a listing">' + I('clip', 17) + '</button><button class="btn btn--p btn--sm" aria-label="Send">' + I('send', 14) + 'Send</button></form>' +
      '';
    return html;
  }
  function renderChat() {
    var el = document.getElementById('chat');
    var cv = st.s.convs.filter(function (x) { return x.id === ES.ui.chat.conv; })[0];
    if (cv && cv.unread && innerWidth > 1180 && ES.ui.chat.open) { cv.unread = 0; }
    el.innerHTML = chatHTML(false);
    var log = el.querySelector('#chatLog'); log.scrollTop = log.scrollHeight;
  }
  ES.chatHTML = chatHTML;
  function chatEvents(root) {
    root.addEventListener('click', function (e) {
      var b = e.target.closest('[data-chat]'); if (!b) return;
      var k = b.dataset.chat;
      if (k === 'conv') { ES.ui.chat.conv = b.dataset.id; st.markRead(b.dataset.id); refreshChat(); setTimeout(function () { var i = document.querySelector('.chat__f input'); if (i) i.focus(); }, 0); }
      if (k === 'toggle') setChat(false);
      if (k === 'attach') {
        ES.menu(b, st.listings().slice(0, 30).map(function (p) { return { label: p.title, icon: 'building', id: p.id }; }), function (it) {
          var p = st.prop(it.id), ph = p.images.slice(0, 3); while (ph.length < 2) ph.push(ph[0]);
          st.sendMessage(ES.ui.chat.conv, '', { photos: ph, count: Math.max(2, p.images.length), size: p.area + ' · ' + f.money(p.price, true), propertyId: p.id }); afterSend();
        });
      }
    });
    root.addEventListener('submit', function (e) {
      var form = e.target.closest('[data-chat=form]'); if (!form) return; e.preventDefault();
      var v = form.m.value; if (!v.trim()) return;
      ES.ui.chat.drafts[ES.ui.chat.conv] = '';
      st.sendMessage(ES.ui.chat.conv, v); afterSend();
    });
    root.addEventListener('input', function (e) { if (e.target.name === 'm') ES.ui.chat.drafts[ES.ui.chat.conv] = e.target.value; });
  }
  function afterSend() {
    refreshChat();
    setTimeout(function () { var i = document.querySelector('.chat__f input'); if (i) i.focus(); }, 0);
  }
  function refreshMobileChat() { var v = document.querySelector('.mchat'); if (v) { v.innerHTML = chatHTML(true); var l = v.querySelector('#chatLog'); l.scrollTop = l.scrollHeight; } }
  function refreshChat() {
    if (current.name === 'chat') { var v = document.querySelector('.mchat'); if (v) { v.innerHTML = chatHTML(true); var l = v.querySelector('#chatLog'); l.scrollTop = l.scrollHeight; } }
    renderChat();
  }
  ES.pages.chat = {
    render: function (v) {
      st.markRead(ES.ui.chat.conv);
      v.innerHTML = '<div class="mchat">' + chatHTML(true) + '</div>';
      var l = v.querySelector('#chatLog'); l.scrollTop = l.scrollHeight;
    }
  };

  /* ---------------- boot ---------------- */
  function debounce(fn, ms) { var t; return function () { clearTimeout(t); t = setTimeout(fn, ms); }; }
  ES.boot = function () {
    st.init(); shell();
    chatEvents(document.getElementById('chat')); chatEvents(document.getElementById('view'));
    if (innerWidth <= 1180) ES.ui.chat.open = false; else setChat(LS.get('reside_chat_open', '1') === '1');
    var pend = {}, flush = debounce(function () { var k = pend; pend = {}; var typing = document.activeElement && /input|textarea|select/i.test(document.activeElement.tagName) && document.getElementById('view').contains(document.activeElement); if ((k.data || (k.remote && !typing)) && !route().static) render(true); else tabs(); if (k.chat || k.data) { if (current.name === 'chat' && k.chat) refreshMobileChat(); renderChat(); } }, 30);
    st.subscribe(function (kind) { pend[kind || 'data'] = 1; flush(); });
    window.addEventListener('hashchange', go);
    if (!location.hash) history.replaceState(null, '', '#/overview');
    go(); renderChat();
    // Live updates: website leads, teammates’ edits and chat.
    var chatOpen = function () { return current.name === 'chat' || (innerWidth > 1180 && ES.ui.chat.open); };
    (function poll() { setTimeout(function () { if (document.visibilityState === 'visible') ES.sync.refresh().then(poll, poll); else poll(); }, chatOpen() ? 4000 : 8000); })();
    // A new chat message while the chat is closed: show it so nothing is missed.
    var lastUnread = st.unreadCount();
    st.subscribe(function (kind) {
      if (kind !== 'chat') return;
      var un = st.unreadCount();
      if (un > lastUnread && !chatOpen()) {
        var m = st.s.msgs.filter(function (x) { return x.from !== ES.config.userId; }).sort(function (a, b) { return new Date(b.at) - new Date(a.at); })[0];
        if (m) ES.toast(st.user(m.from).name.split(' ')[0] + ': ' + (m.text || 'shared a listing').slice(0, 80), 'info');
        var fab = document.querySelector('.chat-fab'); if (fab) { fab.classList.remove('is-ping'); void fab.offsetWidth; fab.classList.add('is-ping'); }
      }
      lastUnread = un;
    });
    document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') ES.sync.refresh(); });
    var topEl = document.querySelector('.top'); addEventListener('scroll', function () { topEl.classList.toggle('is-stuck', scrollY > 4); }, { passive: true });
  };
  var saveT;
  ES.savingState = function (on) {
    var el = document.getElementById('saveState'); if (!el) return;
    clearTimeout(saveT);
    if (on) { el.textContent = 'Saving…'; el.className = 'top__save is-busy'; }
    else saveT = setTimeout(function () { el.textContent = st.persistOk() ? 'All changes saved' : 'Not saved yet — retrying'; el.className = 'top__save' + (st.persistOk() ? ' is-ok' : ' is-err'); saveT = setTimeout(function () { el.className = 'top__save'; el.textContent = ''; }, 2500); }, 250);
  };
  document.addEventListener('error', function (e) { var t = e.target; if (t && t.tagName === 'IMG' && !t.dataset.fb && !/placeholder/.test(t.src)) { t.dataset.fb = 1; t.src = '/assets/img/placeholder.jpg'; } }, true);
  document.addEventListener('DOMContentLoaded', function () { ES.boot(); edgeFades(); });

  /* Horizontal scrollers (tab strips, boards, wide tables) fade at the edge that has more
     content, so a word cut by the screen edge reads as "swipe for more", not as broken. */
  var SCROLLERS = '.tabs,.stabs,.bd,.stagebar,.thumbs,.chat__people,.t-wrap';
  function fadeOne(el) {
    var max = el.scrollWidth - el.clientWidth, x = el.scrollLeft, f = '';
    if (max > 2) { if (x > 2) f += 'l '; if (x < max - 2) f += 'r'; }
    f = f.trim(); if ((el.getAttribute('data-fade') || '') !== f) { if (f) el.setAttribute('data-fade', f); else el.removeAttribute('data-fade'); }
  }
  function scan() { document.querySelectorAll(SCROLLERS).forEach(fadeOne); }
  function edgeFades() {
    var raf = 0, queue = function () { if (!raf) raf = requestAnimationFrame(function () { raf = 0; scan(); }); };
    document.addEventListener('scroll', function (e) { var t = e.target; if (t && t.matches && t.matches(SCROLLERS)) fadeOne(t); }, true);
    addEventListener('resize', queue);
    new MutationObserver(queue).observe(document.body, { childList: true, subtree: true });
    scan();
  }
})();
