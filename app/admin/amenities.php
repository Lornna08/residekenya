<?php
/** admin/amenities.php — manage amenities. */
require __DIR__ . '/../includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) input('action', '');
    if ($action === 'save') {
        $id = input_int('id');
        $name = trim((string) input('name', ''));
        if ($name !== '') {
            if ($id) { Database::run("UPDATE amenities SET name=? WHERE id=?", [$name, $id]); flash('success', 'Amenity updated.'); }
            else {
                try { Database::run("INSERT INTO amenities (name) VALUES (?)", [$name]); flash('success', 'Amenity added.'); }
                catch (Throwable $e) { flash('error', 'That amenity already exists.'); }
            }
        }
    } elseif ($action === 'delete') {
        require_role(['super_admin', 'admin']);
        Database::run("DELETE FROM amenities WHERE id=?", [input_int('id')]);
        flash('success', 'Amenity deleted.');
    }
    redirect('admin/amenities.php');
}

$rows = Database::all("SELECT a.*, (SELECT COUNT(*) FROM property_amenities pa WHERE pa.amenity_id=a.id) uses FROM amenities a ORDER BY name");
$pageTitle = 'Amenities';
$activeAdmin = 'amenities';
include __DIR__ . '/templates/layout-head.php';
?>
<div style="display:grid;grid-template-columns:1fr 340px;gap:1.6rem" class="split-grid">
  <div class="panel">
    <div class="panel-head"><h2><?= count($rows) ?> amenities</h2></div>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Name</th><th>Used by</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= e($r['name']) ?></td>
              <td class="muted"><?= (int) $r['uses'] ?> listings</td>
              <td><div class="a-actions">
                <button class="a-btn sm" type="button" onclick='editAm(<?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
                <?php if (user_can('super_admin', 'admin')): ?>
                <form method="post" data-confirm="Delete amenity?" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="a-btn sm danger">Delete</button></form>
                <?php endif; ?>
              </div></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="panel">
    <div class="panel-head"><h2 id="amTitle">Add amenity</h2></div>
    <div class="panel-body">
      <form method="post" id="amForm">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="amId">
        <div class="a-field"><label>Name</label><input class="a-input" name="name" id="amName" required></div>
        <div style="display:flex;gap:.6rem"><button class="a-btn gold" type="submit">Save</button><button class="a-btn ghost" type="button" onclick="document.getElementById('amForm').reset();document.getElementById('amId').value='';document.getElementById('amTitle').textContent='Add amenity'">Clear</button></div>
      </form>
    </div>
  </div>
</div>
<style>@media(max-width:820px){.split-grid{grid-template-columns:1fr!important}}</style>
<script>
function editAm(a){document.getElementById('amTitle').textContent='Edit amenity';document.getElementById('amId').value=a.id;document.getElementById('amName').value=a.name||'';window.scrollTo({top:0,behavior:'smooth'});}
</script>
<?php include __DIR__ . '/templates/layout-foot.php'; ?>
