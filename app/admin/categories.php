<?php
/** admin/categories.php — manage property categories. */
require __DIR__ . '/../includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) input('action', '');
    if ($action === 'save') {
        $id = input_int('id');
        $name = trim((string) input('name', ''));
        $desc = trim((string) input('description', '')) ?: null;
        $sort = (int) input_int('sort_order', 0);
        $active = input('is_active') ? 1 : 0;
        $image = null;
        if (!empty($_FILES['image']['name'])) {
            $image = save_upload($_FILES['image'], 'properties', ALLOWED_IMAGE_EXT);
        }
        if ($name !== '') {
            if ($id) {
                if ($image) {
                    Database::run("UPDATE categories SET name=?, description=?, sort_order=?, is_active=?, image=? WHERE id=?", [$name, $desc, $sort, $active, $image, $id]);
                } else {
                    Database::run("UPDATE categories SET name=?, description=?, sort_order=?, is_active=? WHERE id=?", [$name, $desc, $sort, $active, $id]);
                }
                flash('success', 'Category updated.');
            } else {
                $slug = unique_slug('categories', $name);
                Database::run("INSERT INTO categories (name, slug, description, sort_order, is_active, image) VALUES (?,?,?,?,?,?)", [$name, $slug, $desc, $sort, $active, $image]);
                flash('success', 'Category added.');
            }
        }
    } elseif ($action === 'delete') {
        require_role(['super_admin', 'admin']);
        Database::run("DELETE FROM categories WHERE id=?", [input_int('id')]);
        flash('success', 'Category deleted.');
    }
    redirect('admin/categories.php');
}

$rows = Database::all("SELECT c.*, (SELECT COUNT(*) FROM properties p WHERE p.category_id=c.id) AS uses FROM categories c ORDER BY sort_order, name");
$pageTitle = 'Categories';
$activeAdmin = 'categories';
include __DIR__ . '/templates/layout-head.php';
?>
<div style="display:grid;grid-template-columns:1fr 340px;gap:1.6rem" class="split-grid">
  <div class="panel">
    <div class="panel-head"><h2><?= count($rows) ?> categories</h2></div>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th></th><th>Name</th><th>Slug</th><th>Listings</th><th>Active</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?php if (!empty($r['image'])): ?><img class="thumb" src="<?= e(asset_url($r['image'])) ?>" alt=""><?php else: ?><span class="muted" style="font-size:.7rem">—</span><?php endif; ?></td>
              <td><?= e($r['name']) ?></td>
              <td class="muted"><?= e($r['slug']) ?></td>
              <td class="muted"><?= (int) $r['uses'] ?></td>
              <td><span class="pill <?= $r['is_active'] ? 'ok' : 'off' ?>"><?= $r['is_active'] ? 'Yes' : 'No' ?></span></td>
              <td><div class="a-actions">
                <button class="a-btn sm" type="button" onclick='editCat(<?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
                <?php if (user_can('super_admin', 'admin')): ?>
                <form method="post" data-confirm="Delete this category?" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="a-btn sm danger">Delete</button></form>
                <?php endif; ?>
              </div></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2 id="catFormTitle">Add category</h2></div>
    <div class="panel-body">
      <form method="post" id="catForm" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="catId">
        <div class="a-field"><label>Name</label><input class="a-input" name="name" id="catName" required></div>
        <div class="a-field"><label>Description</label><textarea class="a-textarea" name="description" id="catDesc" style="min-height:70px"></textarea></div>
        <div class="a-field"><label>Tile image (shown in "Browse by Property Type")</label><input class="a-input" type="file" name="image" accept="image/*"><div id="catImgPreview" style="margin-top:.5rem"></div></div>
        <div class="a-field"><label>Sort order</label><input class="a-input" type="number" name="sort_order" id="catSort" value="0"></div>
        <label class="a-check"><input type="checkbox" name="is_active" id="catActive" value="1" checked> Active</label>
        <div style="display:flex;gap:.6rem;margin-top:.6rem">
          <button class="a-btn gold" type="submit">Save</button>
          <button class="a-btn ghost" type="button" onclick="resetCat()">Clear</button>
        </div>
      </form>
    </div>
  </div>
</div>
<style>@media(max-width:820px){.split-grid{grid-template-columns:1fr!important}}</style>
<script>
function editCat(c){
  document.getElementById('catFormTitle').textContent='Edit category';
  document.getElementById('catId').value=c.id;
  document.getElementById('catName').value=c.name||'';
  document.getElementById('catDesc').value=c.description||'';
  var prev=document.getElementById('catImgPreview');
  prev.innerHTML = c.image ? '<img src="'+(/^https?:\/\//.test(c.image)?'':<?= json_encode(rtrim(BASE_URL,'/').'/') ?>)+c.image+'" style="width:90px;height:68px;object-fit:cover;border-radius:6px;border:1px solid var(--a-line)">' : '';
  document.getElementById('catSort').value=c.sort_order||0;
  document.getElementById('catActive').checked=c.is_active==1;
  window.scrollTo({top:0,behavior:'smooth'});
}
function resetCat(){document.getElementById('catForm').reset();document.getElementById('catId').value='';document.getElementById('catFormTitle').textContent='Add category';document.getElementById('catImgPreview').innerHTML='';}
</script>
<?php include __DIR__ . '/templates/layout-foot.php'; ?>
