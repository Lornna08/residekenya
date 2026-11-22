/* ============================================================
   Reside Kenya — Overview dashboard
   ============================================================ */
(function () {
  'use strict';
  var I = ES.icon, st = ES.store, f = ES.fmt, esc = f.esc;
  var ui = ES.ui.overview = { period: 12, fi: 0 };

  function greet() { var h = new Date().getHours(); return h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening'; }
  ES.refLink = function (r) { if (!r) return null; return r.type === 'property' ? '#/property/' + r.id : r.type === 'deal' ? '#/deal/' + r.id : r.type === 'contact' ? '#/contact/' + r.id : r.type === 'lead' ? '#/leads?lead=' + r.id : null; };
  ES.actIcon = { lead: 'user', photo: 'image', offer: 'tag', viewing: 'cal', stage: 'board', commission: 'wallet', listing: 'building', note: 'edit', contact: 'users', rent: 'key', doc: 'file', settings: 'gear' };

  ES.pages.overview = {
    redrawOnResize: true,
    render: function (v) {
      var s = st.s, k = st.kpis(), me = st.me();
      var series = st.commissionSeries(ui.period), total = series.reduce(function (a, x) { return a + x.value; }, 0);
      var target = s.settings.monthlyTarget, hit = series.filter(function (x) { return x.value >= target; }).length;
      var prevMonth = st.commissionSeries(2)[0].value;
      var open = st.openDeals(), weighted = open.reduce(function (a, d) { return a + st.weighted(d); }, 0), ifAll = open.reduce(function (a, d) { return a + st.commission(d); }, 0);
      var live = st.listings().filter(function (p) { return p.status === 'active' || p.status === 'under_offer'; });
      var featAll = live.filter(function (p) { return p.featured; }); if (!featAll.length) featAll = live.length ? live : st.listings();
      if (ui.fi == null || ui.fi >= featAll.length) ui.fi = 0;
      var feat = featAll[ui.fi];
      var web = s.website || { inquiries: [] };
      var recent = st.listings().slice().sort(function (a, b) { return new Date(b.listedAt) - new Date(a.listedAt); }).slice(0, 6);

      var kp = [
        { ic: 'home', v: k.active, l: 'Active listings', h: '#/listings' },
        { ic: 'tag', v: k.under, l: 'Under offer', h: '#/listings' },
        { ic: 'trophy', v: f.money(k.commissionMonth, true), l: 'Commission · ' + f.mon(new Date()), h: '#/commissions' },
        { ic: 'clock', v: k.dom, l: 'Avg. days on market', h: '#/analytics' },
        { ic: 'user', v: k.newLeads, l: 'New leads this week', h: '#/leads' },
        { ic: 'cal', v: k.closing, l: 'Closing in 30 days', h: '#/deals' }
      ];

      // pipeline groups
      var groups = ES.STAGES.filter(function (x) { return x.id !== 'won' && x.id !== 'lost'; }).map(function (g) {
        var ds = open.filter(function (d) { return d.stage === g.id; });
        return { g: g, n: ds.length, value: ds.reduce(function (a, d) { return a + d.value; }, 0), w: ds.reduce(function (a, d) { return a + st.weighted(d); }, 0) };
      });

      var events = s.events.filter(function (e) { return e.status === 'scheduled' && new Date(e.start) > Date.now() - 3600e3; }).sort(function (a, b) { return new Date(a.start) - new Date(b.start); }).slice(0, 5);
      var follow = s.leads.filter(function (l) { return l.nextFollowUp && l.status !== 'converted' && l.status !== 'lost'; }).sort(function (a, b) { return new Date(a.nextFollowUp) - new Date(b.nextFollowUp); }).slice(0, 5);

      v.innerHTML =
        '<section class="ov-head">' +
        '<h1 class="ov-greet">' + greet() + ', ' + esc(me.name.split(' ')[0]) + '</h1>' +
        '<div class="ov-kpis">' + kp.map(function (x) { return '<a class="kq" href="' + x.h + '"><span class="kq__ic">' + I(x.ic, 17) + '</span><span class="kq__b"><b>' + x.v + '</b>' + (x.sub || '') + '<em>' + x.l + '</em></span></a>'; }).join('') + '</div>' +
        '<div class="ov-acts"><a class="btn btn--dark" href="#/listings/new">' + I('plus', 15) + 'New listing</a><button class="ib ib--round ib--card" data-ov="more" aria-label="More actions">' + I('more', 18) + '</button></div>' +
        '</section>' +

        '<section class="ov-grid">' +
        '<div class="ov-col">' +
        // commission
        '<article class="card cm">' +
        '<header class="card__h"><span class="card__t">' + I('trend', 16) + 'Commission</span><span class="card__x"><span class="seg" role="group" aria-label="Period"><button class="' + (ui.period === 6 ? 'is-on' : '') + '" data-ov="p" data-p="6">6M</button><button class="' + (ui.period === 12 ? 'is-on' : '') + '" data-ov="p" data-p="12">12M</button></span><a class="ib ib--round ib--card" href="#/commissions" aria-label="Open commission reports">' + I('out', 15) + '</a></span></header>' +
        '<div class="cm__big"><b>' + f.money(total, true).replace('KES ', '<small>KES</small>') + '</b><span>earned ' + (ui.period === 12 ? 'over 12 months' : 'over 6 months') + ' · <strong>' + hit + '</strong> of ' + ui.period + ' months at target</span></div>' +
        '<div class="legend"><span><i class="lg-dot" style="background:var(--blue)"></i>Commission earned</span><span><i class="lg-dash"></i>Team target · ' + f.money(target, true) + ' /mo</span></div>' +
        '<div class="cm__chart" id="cmChart"></div></article>' +
        // pipeline
        '<article class="card pl">' +
        '<header class="card__h"><span class="card__t">' + I('layers', 16) + 'Pipeline by stage</span><span class="card__x"><a class="btn btn--sm btn--ghost" href="#/deals">Open board ' + I('out', 13) + '</a></span></header>' +
        '<div class="pl__big"><b>' + f.money(weighted, true).replace('KES ', '<small>KES</small>') + '</b><span>weighted commission · ' + open.length + ' open deals · ' + f.money(ifAll, true) + ' if all close</span></div>' +
        '<div class="pl__bar">' + groups.filter(function (x) { return x.n; }).map(function (x) { return '<a href="#/deals" style="flex:' + x.n + ';background:' + x.g.c + '" title="' + esc(x.g.name + ': ' + x.n + ' deals') + '"><span>' + x.n + '</span></a>'; }).join('') + '</div>' +
        '<div class="pl__rows">' + groups.map(function (x) { return '<div class="pl__r"><span><i style="background:' + x.g.c + '"></i>' + x.g.name + '</span><b>' + x.n + '</b><span class="num">' + f.money(x.value, true) + '</span><em>' + x.g.p + '%</em></div>'; }).join('') + '</div>' +
        '</article>' +
        '</div>' +
        // featured
        (feat ? featured(feat, featAll.length) : '<article class="card">' + ES.empty('building', 'No listings yet', 'Add your first property.', '<a class="btn btn--dark" href="#/listings/new">New listing</a>') + '</article>') +
        '</section>' +

        '<section class="ov-row">' +
        '<article class="card"><header class="card__h"><span class="card__t">' + I('bell', 16) + 'Live activity</span></header><div class="feed">' +
        s.activity.slice(0, 7).map(function (a) { var h = ES.refLink(a.ref); return '<' + (h ? 'a href="' + h + '"' : 'div') + ' class="feed__i"><span class="feed__ic feed__ic--' + a.kind + '">' + I(ES.actIcon[a.kind] || 'info', 14) + '</span><span class="feed__t">' + esc(a.text) + '<em>' + f.ago(a.at) + ' · ' + esc(st.user(a.by).name.split(' ')[0]) + '</em></span></' + (h ? 'a' : 'div') + '>'; }).join('') + '</div></article>' +
        '<article class="card"><header class="card__h"><span class="card__t">' + I('cal', 16) + 'Coming up</span><span class="card__x"><a class="lnk" href="#/calendar">Calendar ' + I('right', 13) + '</a></span></header><div class="up">' +
        (events.length ? events.map(function (e) { var d = new Date(e.start), p = e.propertyId ? st.prop(e.propertyId) : null; return '<a class="up__i" href="#/calendar"><span class="up__d"><b>' + d.getDate() + '</b><em>' + f.mon(d) + '</em></span><span class="up__t"><b>' + esc(e.title) + '</b><em>' + f.day(d) + ' · ' + f.time(d) + (p ? ' · ' + esc(p.area) : '') + '</em></span>' + ES.av(st.user(e.agentId), 24) + '</a>'; }).join('') : ES.empty('cal', 'Nothing scheduled', 'Book a viewing from any listing.')) + '</div></article>' +
        '<article class="card"><header class="card__h"><span class="card__t">' + I('phone', 16) + 'Follow-ups</span><span class="card__x"><a class="lnk" href="#/tasks">All tasks ' + I('right', 13) + '</a></span></header><div class="fu">' +
        (follow.length ? follow.map(function (l) { var od = new Date(l.nextFollowUp) < Date.now(), p = l.propertyId ? st.prop(l.propertyId) : null; return '<button class="fu__i" data-ov="lead" data-id="' + l.id + '">' + ES.cav({ name: l.name }, 32) + '<span class="fu__t"><b>' + esc(l.name) + '</b><em>' + (p ? esc(p.title) : esc(l.location || l.source || '')) + '</em></span><span class="fu__w' + (od ? ' is-od' : '') + '">' + (od ? 'Overdue' : f.day(l.nextFollowUp)) + '<em>' + f.time(l.nextFollowUp) + '</em></span></button>'; }).join('') : ES.empty('check', 'No follow-ups due', 'Schedule one from any lead.')) + '</div></article>' +
        '</section>' +

        // website inquiries
        '<section class="ov-row ov-row--web">' +
        '<article class="card wq"><header class="card__h"><span class="card__t">' + I('mail', 16) + 'Recent inquiries</span><span class="card__x">' + (web.counts && web.counts.inquiries ? ES.pill(web.counts.inquiries + ' unread', 'blue') : '') + '<a class="lnk" href="/admin/inquiries.php">All inquiries ' + I('right', 13) + '</a></span></header>' +
        (web.inquiries.length ? '<div class="wq__l">' + web.inquiries.map(function (q) {
          var tl = { viewing: 'Viewing request', agent: 'Property enquiry', contact: 'Contact form', general: 'Enquiry' }[q.type] || 'Enquiry';
          return '<button class="wq__i' + (q.read ? '' : ' is-new') + '" data-ov="inq" data-id="' + esc(q.leadId) + '">' + ES.cav({ name: q.name }, 34) + '<span class="wq__t"><b>' + esc(q.name) + (q.read ? '' : '<i class="nt__dot"></i>') + '</b><em>' + tl + (q.propertyTitle ? ' · ' + esc(q.propertyTitle) : '') + '</em>' + (q.message ? '<span class="wq__m">' + esc(q.message.slice(0, 120)) + '</span>' : '') + '</span><span class="wq__w">' + f.ago(q.at) + '<em>' + esc(q.phone || q.email || '') + '</em></span></button>';
        }).join('') + '</div>' : ES.empty('mail', 'No inquiries yet', 'Messages from residekenya.com will appear here.')) + '</article>' +
        '</section>' +

        // recently added
        '<section class="ov-recent"><header class="ov-recent__h"><h2>' + I('building', 18) + 'Recently added properties</h2><a class="lnk" href="#/listings">All listings ' + I('right', 13) + '</a></header>' +
        (recent.length ? '<div class="lg lg--recent">' + recent.map(function (p) {
          return '<a class="lc" href="#/property/' + p.id + '"><div class="lc__m"><img src="' + ES.img(p.images[0], 640) + '" alt="' + esc(p.title) + '" loading="lazy"><span class="lc__tag">' + ES.purposePill(p) + ES.statusPill(p.status) + '</span><span class="lc__ph">' + ES.av(st.user(p.agentId), 28) + '</span></div>' +
            '<div class="lc__b"><span class="lc__t">' + esc(p.title) + '</span><span class="lc__l">' + I('pin', 12) + esc(p.area || p.city || '—') + ' · ' + esc(p.type) + '</span><span class="lc__p">' + ES.priceLabel(p) + '</span>' +
            '<span class="lc__f"><span>' + esc(p.ref) + '</span><span>Added ' + f.ago(p.listedAt) + '</span></span></div></a>';
        }).join('') + '</div>' : ES.empty('building', 'No properties yet', '')) + '</section>';

      var el = v.querySelector('#cmChart');
      ES.chart.line(el, { h: innerWidth < 640 ? 170 : 196, labels: series.map(function (x) { return f.mon(x.date); }), tipLabels: series.map(function (x) { return f.mon(x.date) + ' ' + x.date.getFullYear() + ' · ' + x.count + ' closed'; }), series: [{ values: series.map(function (x) { return x.value; }), color: '#2F5BEA', label: 'Earned' }], target: target, vfmt: function (x) { return f.money(x, true); }, yfmt: function (x) { return x ? f.compact(x) : '0'; } });

      v.onclick = function (e) {
        var b = e.target.closest('[data-ov]'); if (!b) return;
        var a = b.dataset.ov;
        if (a === 'p') { ui.period = +b.dataset.p; ES.app.refresh(); }
        if (a === 'fp' || a === 'fn') { var n = featAll.length; ui.fi = (ui.fi + (a === 'fn' ? 1 : -1) + n) % n; ES.app.refresh(); }
        if (a === 'inq') { if (st.lead(b.dataset.id)) { ES.app.go('#/leads'); setTimeout(function () { ES.pages.leads.open(b.dataset.id); }, 60); } else location.href = '/admin/inquiries.php'; }
        if (a === 'lead') { ES.app.go('#/leads'); setTimeout(function () { ES.pages.leads.open(b.dataset.id); }, 60); }
        if (a === 'more') ES.menu(b, [{ label: 'Add a lead', icon: 'user', k: 'lead' }, { label: 'Add a contact', icon: 'users', k: 'contact' }, { label: 'Book a viewing', icon: 'cal', k: 'view' }, { label: 'Quick listing (phone)', icon: 'plus', k: 'quick' }, '-', { label: 'Export pipeline (CSV)', icon: 'download', k: 'csv' }, { label: 'Export listings (CSV, with photos)', icon: 'download', k: 'xl' }, { label: 'Export everything (backup)', icon: 'download', k: 'xall' }, { label: 'Import listings', icon: 'upload', k: 'imp' }], function (it) {
          if (it.k === 'contact') { ES.app.go('#/contacts'); setTimeout(function () { ES.pages.contacts.edit && ES.pages.contacts.edit(); }, 60); }
          if (it.k === 'quick') location.href = '/admin/quick-listing.php';
          if (it.k === 'xl') location.href = '/admin/export.php?what=listings&format=csv';
          if (it.k === 'xall') location.href = '/admin/export.php?what=all&format=json';
          if (it.k === 'imp') location.href = '/admin/import-export.php';
          if (it.k === 'csv') ES.csv('reside-ke-pipeline.csv', [['Ref', 'Property', 'Buyer/Tenant', 'Stage', 'Value (KES)', 'Probability %', 'Expected commission (KES)', 'Weighted (KES)', 'Expected close', 'Agent']].concat(st.openDeals().map(function (d) { return [d.ref, st.prop(d.propertyId).title, (st.contact(d.buyerId) || {}).name, st.stage(d.stage).name, d.value, st.stage(d.stage).p, st.commission(d), st.weighted(d), f.dateLong(d.expectedClose), st.user(d.agentId).name]; })));
          if (it.k === 'view') ES.pages.calendar.edit(null, { type: 'viewing' });
          if (it.k === 'lead') { ES.app.go('#/leads'); setTimeout(function () { ES.pages.leads.edit(); }, 60); }
        });
      };
    }
  };
  function pad(n) { return (n < 10 ? '0' : '') + n; }

  function featured(p, total) {
    var n = p.images.length;
    return '<article class="ft">' +
      '<img class="ft__img" src="' + ES.img(p.images[0], 1200) + '" alt="' + esc(p.title + ', ' + p.area) + '">' +
      '<div class="ft__top"><span class="chip chip--glass">' + I('star', 13) + 'Featured listing</span><span class="ft__nav"><span class="ft__n">' + pad(ui.fi + 1) + ' / ' + pad(total) + '</span>' + (total > 1 ? '<button class="ib ib--glass" data-ov="fp" aria-label="Previous listing">' + I('left', 15) + '</button><button class="ib ib--glass" data-ov="fn" aria-label="Next listing">' + I('right', 15) + '</button>' : '') + '</span></div>' +
      '<div class="ft__b"><div class="ft__chips"><span class="chip chip--w"><i class="dot dot--b"></i>' + ES.purposeLabel(p) + '</span>' + (p.area ? '<span class="chip chip--glass">' + I('pin', 12) + esc(p.area) + '</span>' : '') + '<span class="chip chip--glass">' + esc(p.ref) + '</span></div>' +
      '<h2>' + esc(p.title) + '</h2>' +
      '<div class="ft__row"><b>' + ES.priceLabel(p) + '</b>' + (p.beds ? '<span class="chip chip--glass">' + I('bed', 13) + p.beds + ' bd</span>' : '') + (p.baths ? '<span class="chip chip--glass">' + I('bath', 13) + p.baths + ' ba</span>' : '') + (p.size ? '<span class="chip chip--glass">' + I('area', 13) + f.n(p.size) + ' m²</span>' : '') + '</div>' +
      '<div class="ft__foot"><span>' + I('clock', 13) + st.daysOnMarket(p) + ' days listed · ' + f.n(p.views) + ' views · ' + n + ' photos</span><a class="btn btn--w btn--sm" href="#/property/' + p.id + '">View property ' + I('out', 13) + '</a></div></div></article>';
  }
})();
