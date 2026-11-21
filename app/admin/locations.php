<?php
/** admin/locations.php — manage counties and towns. */
require __DIR__ . '/../includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) input('action', '');
    switch ($action) {
        case 'save_county':
            $id = input_int('id'); $name = trim((string) input('name', '')); $sort = (int) input_int('sort_order', 0); $active = input('is_active') ? 1 : 0;
            if ($name !== '') {
                if ($id) { Database::run("UPDATE counties SET name=?, sort_order=?, is_active=? WHERE id=?", [$name, $sort, $active, $id]); flash('success', 'County updated.'); }
                else { Database::run("INSERT INTO counties (name, slug, sort_order, is_active) VALUES (?,?,?,?)", [$name, unique_slug('counties', $name), $sort, $active]); flash('success', 'County added.'); }
            }
            break;
        case 'delete_county':
            require_role(['super_admin', 'admin']);
            Database::run("DELETE FROM counties WHERE id=?", [input_int('id')]); flash('success', 'County deleted.');
            break;
        case 'save_town':
            $cid = input_int('county_id'); $name = trim((string) input('name', ''));
            if ($cid && $name !== '') {
                Database::run("INSERT INTO towns (county_id, name, slug) VALUES (?,?,?)", [$cid, $name, unique_slug('towns', $name)]);
                flash('success', 'Town added.');
            }
            break;
        case 'delete_town':
            require_role(['super_admin', 'admin']);
            Database::run("DELETE FROM towns WHERE id=?", [input_int('id')]); flash('success', 'Town deleted.');
            break;
    }
    redirect('admin/locations.php');
}

$counties = Database::all("SELECT c.*, (SELECT COUNT(*) FROM towns t WHERE t.county_id=c.id) towns_n, (SELECT COUNT(*) FROM properties p WHERE p.county_id=c.id) uses FROM counties c ORDER BY sort_order, name");
$towns = Database::all("SELECT t.*, c.name county_name FROM towns t JOIN counties c ON c.id=t.county_id ORDER BY c.name, t.name");
$pageTitle = 'Counties & Towns';
$activeAdmin = 'locations';
include __DIR__ . '/templates/layout-head.php';
?>
<div style="display:grid;grid-template-columns:1fr 340px;gap:1.6rem" class="split-grid">
  <div>
    <div class="panel">
      <div class="panel-head"><h2><?= count($counties) ?> counties</h2></div>
      <div class="table-wrap"><table class="data">
        <thead><tr><th>Name</th><th>Towns</th><th>Listings</th><th>Active</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($counties as $c): ?>
            <tr>
              <td><?= e($c['name']) ?></td><td class="muted"><?= (int) $c['towns_n'] ?></td><td class="muted"><?= (int) $c['uses'] ?></td>
              <td><span class="pill <?= $c['is_active'] ? 'ok' : 'off' ?>"><?= $c['is_active'] ? 'Yes' : 'No' ?></span></td>
              <td><div class="a-actions">
                <button class="a-btn sm" type="button" onclick='editCounty(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
                <?php if (user_can('super_admin', 'admin')): ?><form method="post" data-confirm="Delete county and its towns?" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete_county"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="a-btn sm danger">Delete</button></form><?php endif; ?>
              </div></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>

    <div class="panel">
      <div class="panel-head"><h2><?= count($towns) ?> towns</h2></div>
      <div class="table-wrap"><table class="data">
        <thead><tr><th>Town</th><th>County</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($towns as $t): ?>
            <tr><td><?= e($t['name']) ?></td><td class="muted"><?= e($t['county_name']) ?></td>
            <td><?php if (user_can('super_admin', 'admin')): ?><form method="post" data-confirm="Delete town?" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete_town"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><button class="a-btn sm danger">Delete</button></form><?php endif; ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>

  <div>
    <div class="panel">
      <div class="panel-head"><h2 id="cTitle">Add county</h2></div>
      <div class="panel-body">
        <form method="post" id="cForm">
          <?= csrf_field() ?><input type="hidden" name="action" value="save_county"><input type="hidden" name="id" id="cId">
          <div class="a-field"><label>Name</label><input class="a-input" name="name" id="cName" required></div>
          <div class="a-field"><label>Sort order</label><input class="a-input" type="number" name="sort_order" id="cSort" value="0"></div>
          <label class="a-check"><input type="checkbox" name="is_active" id="cActive" value="1" checked> Active</label>
          <div style="display:flex;gap:.6rem;margin-top:.6rem"><button class="a-btn gold">Save</button><button class="a-btn ghost" type="button" onclick="document.getElementById('cForm').reset();document.getElementById('cId').value='';document.getElementById('cTitle').textContent='Add county'">Clear</button></div>
        </form>
      </div>
    </div>
    <div class="panel">
      <div class="panel-head"><h2>Add town</h2></div>
      <div class="panel-body">
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="save_town">
          <div class="a-field"><label>County</label><select class="a-select" name="county_id" required><option value="">Select…</option><?php foreach ($counties as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
          <div class="a-field"><label>Town name</label><input class="a-input" name="name" required></div>
          <button class="a-btn gold">Add Town</button>
        </form>
      </div>
    </div>
  </div>
</div>
<style>@media(max-width:820px){.split-grid{grid-template-columns:1fr!important}}</style>
<script>
function editCounty(c){document.getElementById('cTitle').textContent='Edit county';document.getElementById('cId').value=c.id;document.getElementById('cName').value=c.name||'';document.getElementById('cSort').value=c.sort_order||0;document.getElementById('cActive').checked=c.is_active==1;window.scrollTo({top:0,behavior:'smooth'});}
</script>
<?php include __DIR__ . '/templates/layout-foot.php'; ?>
