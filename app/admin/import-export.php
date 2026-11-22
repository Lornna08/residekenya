<?php
/**
 * admin/import-export.php — bring listings in from a spreadsheet or another
 * Reside Kenya export (photos are copied into our storage), and download data.
 */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/transfer.php';
require_role(['super_admin', 'admin']);
crm_schema();

// Import endpoint: the page sends a couple of rows at a time so big files never time out.
if (input('action') === 'rows' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $body = json_decode((string) file_get_contents('php://input'), true) ?: [];
    if (!hash_equals(csrf_token(), (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) { http_response_code(419); echo json_encode(['ok' => false, 'error' => 'Session expired — reload the page.']); exit; }
    @set_time_limit(55);
    $uid = (int) current_user()['id'];
    $out = [];
    foreach (array_slice((array) ($body['rows'] ?? []), 0, 5) as $row) {
        try { $out[] = tx_import_row((array) $row, $uid, !empty($body['rehost'])); }
        catch (Throwable $e) { error_log('import row: ' . $e->getMessage()); $out[] = ['ok' => false, 'action' => 'skipped', 'title' => (string) ($row['title'] ?? ''), 'error' => APP_ENV === 'production' ? 'Could not save this row' : $e->getMessage()]; }
    }
    echo json_encode(['ok' => true, 'results' => $out]);
    exit;
}

// Blank template for spreadsheets.
if (input('template') === 'csv') {
    tx_csv_download('reside-kenya-import-template.csv', TX_COLS, [[
        'ref' => '', 'title' => '3 Bedroom Apartment in Kilimani', 'purpose' => 'rent', 'type' => 'Apartment', 'status' => 'active', 'price' => '120000',
        'rent_period' => 'month', 'county' => 'Nairobi', 'area' => 'Kilimani', 'address' => 'Argwings Kodhek Rd', 'bedrooms' => 3, 'bathrooms' => 3,
        'floor_area_sqm' => 160, 'plot' => '', 'floors' => 1, 'parking' => 2, 'featured' => 0, 'luxury' => 0, 'amenities' => 'Lift/Elevator|Backup Generator|Swimming Pool',
        'images' => 'https://example.com/photo-1.jpg|https://example.com/photo-2.jpg', 'description' => 'Spacious, bright apartment close to Yaya Centre…',
        'youtube_url' => '', 'latitude' => '', 'longitude' => '', 'owner_name' => 'Jane Owner', 'owner_contact' => '+254 700 000 000', 'source_url' => '', 'website_link' => '', 'listed_at' => '',
    ]]);
}

$total = (int) (Database::row("SELECT COUNT(*) n FROM properties WHERE status <> 'archived'")['n'] ?? 0);
$pageTitle = 'Import & export';
$pageLead = 'Move listings in and out with photos and full descriptions';
$activeAdmin = 'import';
include __DIR__ . '/templates/layout-head.php';
?>
<div class="ie-grid">
  <section class="panel">
    <div class="panel-head"><h2><?= a_icon('download', 16) ?> Export</h2><span class="muted"><?= $total ?> listings</span></div>
    <div class="panel-body">
      <p class="muted">Listings come with every photo link, the full description, amenities, owner details and the website link. Open the CSV in Excel or Google Sheets, or keep the JSON as a backup — both can be imported back.</p>
      <div class="ie-dl">
        <a class="ie-card" href="/admin/export.php?what=listings&format=csv"><?= a_icon('file', 20) ?><b>Listings · CSV</b><span>Excel / Google Sheets</span></a>
        <a class="ie-card" href="/admin/export.php?what=listings&format=json"><?= a_icon('file', 20) ?><b>Listings · JSON</b><span>Exact copy, re-importable</span></a>
        <a class="ie-card" href="/admin/export.php?what=leads&format=csv"><?= a_icon('user', 20) ?><b>Leads · CSV</b><span>Website & manual leads</span></a>
        <a class="ie-card" href="/admin/export.php?what=contacts&format=csv"><?= a_icon('users', 20) ?><b>Contacts · CSV</b><span>Owners, buyers, tenants</span></a>
        <a class="ie-card" href="/admin/export.php?what=deals&format=csv"><?= a_icon('board', 20) ?><b>Deals · CSV</b><span>Pipeline & commissions</span></a>
        <a class="ie-card ie-card--dark" href="/admin/export.php?what=all&format=json"><?= a_icon('download', 20) ?><b>Full backup</b><span>Everything, one JSON file</span></a>
      </div>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head"><h2><?= a_icon('upload', 16) ?> Import listings</h2><a class="a-btn sm" href="?template=csv"><?= a_icon('download', 14) ?> Template CSV</a></div>
    <div class="panel-body">
      <div id="ieStart">
        <p class="muted">Use a CSV from Excel/Google Sheets (download the template for the column names) or a Reside Kenya JSON export. Rows with a matching <b>ref</b> or title update that listing; the rest are added. Photo links are downloaded and stored on your site, so they keep working.</p>
        <label class="drop" id="ieDrop"><?= a_icon('upload', 22) ?><b>Choose a CSV or JSON file</b><span>or drop it here</span><input type="file" id="ieFile" accept=".csv,.json,text/csv,application/json" hidden></label>
      </div>
      <div id="iePreview" hidden>
        <div class="ie-sum" id="ieSum"></div>
        <div class="table-wrap ie-tbl"><table class="data"><thead><tr><th>Title</th><th>Type</th><th>Price</th><th>Location</th><th>Photos</th><th>Description</th></tr></thead><tbody id="ieRows"></tbody></table></div>
        <label class="a-check" style="margin-top:14px"><input type="checkbox" id="ieRehost" checked> Copy photos into Reside Kenya storage (recommended)</label>
        <div class="a-actions" style="margin-top:6px"><button class="a-btn gold" id="ieGo" type="button"><?= a_icon('upload', 15) ?> Import listings</button><button class="a-btn ghost" id="ieReset" type="button">Choose another file</button></div>
      </div>
      <div id="ieRun" hidden>
        <div class="ie-prog"><i id="ieBar"></i></div>
        <p class="ie-st" id="ieSt">Starting…</p>
        <ul class="ie-log" id="ieLog"></ul>
        <div class="a-actions" id="ieDone" hidden><a class="a-btn gold" href="/admin/#/listings"><?= a_icon('building', 15) ?> Open properties</a><button class="a-btn" type="button" onclick="location.reload()">Import another file</button></div>
      </div>
    </div>
  </section>
</div>
<style>
#ieStart p{margin-bottom:14px}.ie-grid{display:grid;grid-template-columns:1fr 1.25fr;gap:18px;align-items:start}
.panel-head h2{display:flex;align-items:center;gap:8px}
.ie-dl{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:6px}
.ie-card{display:flex;flex-direction:column;gap:3px;padding:14px;border:1px solid var(--line);border-radius:14px;background:var(--card-2);transition:border-color .15s,background .15s}
.ie-card:hover{border-color:var(--line-2);background:#fff}
.ie-card svg{color:var(--blue);margin-bottom:6px}
.ie-card b{font-size:13.5px}.ie-card span{font-size:12px;color:var(--ink-3)}
.ie-card--dark{background:#0F1729;border-color:#0F1729;color:#fff}.ie-card--dark svg{color:#9DB5FF}.ie-card--dark span{color:#B8C0D2}.ie-card--dark:hover{background:#1E293F}
.ie-sum{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}
.ie-tbl{max-height:360px;overflow:auto;border:1px solid var(--line);border-radius:14px}
.ie-tbl td{font-size:12.5px}.ie-tbl td:last-child{max-width:260px;color:var(--ink-3)}
.ie-prog{height:10px;border-radius:5px;background:var(--card-2);overflow:hidden}.ie-prog i{display:block;height:100%;width:0;background:var(--blue);transition:width .3s}
.ie-st{margin:10px 0;font-weight:600}
.ie-log{list-style:none;margin:0 0 14px;padding:0;max-height:300px;overflow:auto;font-size:12.5px;border:1px solid var(--line);border-radius:12px}
.ie-log li{display:flex;gap:8px;align-items:center;padding:8px 12px;border-bottom:1px solid var(--line)}.ie-log li:last-child{border:0}
.ie-log em{font-style:normal;color:var(--ink-3);margin-left:auto;white-space:nowrap}
@media(max-width:980px){.ie-grid{grid-template-columns:1fr}}
@media(max-width:520px){.ie-dl{grid-template-columns:1fr}}
</style>
<script>
(function () {
  var CSRF = window.RESIDE_ADMIN.csrf, rows = [], file = document.getElementById('ieFile'), drop = document.getElementById('ieDrop');
  function $(id) { return document.getElementById(id); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function parseCSV(t) {
    t = t.replace(/^﻿/, ''); var out = [], row = [], f = '', q = false, sep = ',';
    var first = t.split(/\r?\n/)[0] || ''; if ((first.match(/;/g) || []).length > (first.match(/,/g) || []).length) sep = ';';
    for (var i = 0; i < t.length; i++) {
      var c = t[i];
      if (q) { if (c === '"') { if (t[i + 1] === '"') { f += '"'; i++; } else q = false; } else f += c; }
      else if (c === '"') q = true; else if (c === sep) { row.push(f); f = ''; }
      else if (c === '\n' || c === '\r') { if (c === '\r' && t[i + 1] === '\n') i++; row.push(f); f = ''; if (row.some(function (x) { return x.trim() !== ''; })) out.push(row); row = []; }
      else f += c;
    }
    row.push(f); if (row.some(function (x) { return x.trim() !== ''; })) out.push(row);
    var head = (out.shift() || []).map(function (h) { return h.trim(); });
    return out.map(function (r) { var o = {}; head.forEach(function (h, k) { if (h) o[h] = r[k] == null ? '' : r[k]; }); return o; });
  }
  function pick(o, keys) { for (var k in o) { var n = k.toLowerCase().replace(/[^a-z0-9]+/g, '_'); if (keys.indexOf(n) > -1 && o[k] !== '') return o[k]; } return ''; }
  function load(f) {
    var r = new FileReader();
    r.onload = function () {
      try {
        var txt = String(r.result);
        if (/\.json$/i.test(f.name) || /^\s*[\[{]/.test(txt)) { var j = JSON.parse(txt); rows = Array.isArray(j) ? j : (j.listings || j.properties || []); }
        else rows = parseCSV(txt);
      } catch (e) { alert('That file couldn’t be read: ' + e.message); return; }
      rows = rows.filter(function (x) { return x && typeof x === 'object' && String(pick(x, ['title', 'name', 'listing', 'property'])).trim().length > 2; });
      if (!rows.length) { alert('No listings found. Make sure the first row has column names and there is a "title" column.'); return; }
      var photos = 0; rows.forEach(function (x) { var im = pick(x, ['images', 'photos', 'image_urls', 'photo_urls', 'pictures']); photos += (Array.isArray(im) ? im : String(im).split(/[|\n;]+/)).filter(function (u) { return /^https?:/.test(String(u).trim()); }).length; });
      $('ieSum').innerHTML = '<span class="pill info">' + rows.length + ' listings</span><span class="pill">' + photos + ' photo links</span><span class="pill">' + esc(f.name) + '</span>';
      $('ieRows').innerHTML = rows.slice(0, 50).map(function (x) {
        var im = pick(x, ['images', 'photos', 'image_urls', 'photo_urls', 'pictures']), n = (Array.isArray(im) ? im : String(im).split(/[|\n;]+/)).filter(function (u) { return String(u).trim(); }).length, d = String(pick(x, ['description']));
        return '<tr><td><b>' + esc(pick(x, ['title', 'name', 'listing', 'property'])) + '</b></td><td>' + esc(pick(x, ['purpose', 'listing_type'])) + ' ' + esc(pick(x, ['type', 'category', 'property_type'])) + '</td><td>' + esc(pick(x, ['price', 'asking_price', 'rent', 'sale_price', 'rent_price'])) + '</td><td>' + esc([pick(x, ['area', 'town', 'location']), pick(x, ['county', 'city'])].filter(Boolean).join(', ')) + '</td><td>' + n + '</td><td>' + esc(d.slice(0, 90)) + (d.length > 90 ? '…' : '') + '</td></tr>';
      }).join('') + (rows.length > 50 ? '<tr><td colspan="6" class="muted">…and ' + (rows.length - 50) + ' more</td></tr>' : '');
      $('ieGo').innerHTML = $('ieGo').innerHTML.replace(/Import.*$/, 'Import ' + rows.length + ' listing' + (rows.length > 1 ? 's' : ''));
      $('ieStart').hidden = true; $('iePreview').hidden = false;
    };
    r.readAsText(f);
  }
  file.onchange = function () { if (file.files[0]) load(file.files[0]); };
  ['dragenter', 'dragover'].forEach(function (n) { drop.addEventListener(n, function (e) { e.preventDefault(); drop.classList.add('is-over'); }); });
  ['dragleave', 'drop'].forEach(function (n) { drop.addEventListener(n, function () { drop.classList.remove('is-over'); }); });
  drop.addEventListener('drop', function (e) { e.preventDefault(); if (e.dataTransfer.files[0]) load(e.dataTransfer.files[0]); });
  $('ieReset').onclick = function () { rows = []; file.value = ''; $('ieStart').hidden = false; $('iePreview').hidden = true; };
  $('ieGo').onclick = function () {
    var rehost = $('ieRehost').checked, i = 0, done = { created: 0, updated: 0, skipped: 0, photos: 0, bad: 0 }, size = rehost ? 2 : 5;
    $('iePreview').hidden = true; $('ieRun').hidden = false;
    window.onbeforeunload = function () { return 'Import still running'; };
    function step() {
      if (i >= rows.length) {
        window.onbeforeunload = null; $('ieBar').style.width = '100%';
        $('ieSt').textContent = 'Done — ' + done.created + ' added, ' + done.updated + ' updated' + (done.skipped ? ', ' + done.skipped + ' skipped' : '') + ' · ' + done.photos + ' photos saved' + (done.bad ? ' (' + done.bad + ' photo links didn’t work)' : '') + '.';
        $('ieDone').hidden = false; return;
      }
      var chunk = rows.slice(i, i + size);
      $('ieSt').textContent = 'Importing ' + (i + 1) + '–' + Math.min(rows.length, i + size) + ' of ' + rows.length + (rehost ? ' · copying photos…' : '…');
      fetch('import-export.php?action=rows', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF }, body: JSON.stringify({ rows: chunk, rehost: rehost }) })
        .then(function (r) { return r.json(); })
        .then(function (r) {
          if (!r.ok) throw new Error(r.error || 'Import failed');
          r.results.forEach(function (x) {
            done[x.action] = (done[x.action] || 0) + 1; done.photos += x.photos || 0; done.bad += x.photoErrors || 0;
            var li = document.createElement('li');
            li.innerHTML = '<span class="pill ' + (x.ok ? (x.action === 'created' ? 'ok' : 'info') : 'off') + '">' + (x.ok ? x.action : 'skipped') + '</span><b>' + esc(x.title || '(no title)') + '</b><em>' + (x.ok ? (x.photos || 0) + ' photos' + (x.photoErrors ? ' · ' + x.photoErrors + ' failed' : '') : esc(x.error || '')) + '</em>';
            $('ieLog').prepend(li);
          });
          i += size; $('ieBar').style.width = Math.round(i / rows.length * 100) + '%'; step();
        })
        .catch(function (e) { window.onbeforeunload = null; $('ieSt').textContent = 'Stopped at row ' + (i + 1) + ': ' + e.message + ' — rows before this were saved. You can import the same file again; existing listings are updated, not duplicated.'; $('ieDone').hidden = false; });
    }
    step();
  };
})();
</script>
<?php include __DIR__ . '/templates/layout-foot.php'; ?>
