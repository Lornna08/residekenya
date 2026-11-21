<?php
/** admin/testimonials.php — manage testimonials. */
require __DIR__ . '/../includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) input('action', '');
    if ($action === 'save') {
        $id = input_int('id');
        $f = [
            'name'    => trim((string) input('name', '')),
            'role'    => trim((string) input('role', '')) ?: null,
            'rating'  => max(1, min(5, (int) input_int('rating', 5))),
            'content' => trim((string) input('content', '')),
            'is_active' => input('is_active') ? 1 : 0,
            'sort_order' => (int) input_int('sort_order', 0),
        ];
        if ($f['name'] !== '' && $f['content'] !== '') {
            $photo = null;
            if (!empty($_FILES['photo']['name'])) $photo = save_upload($_FILES['photo'], 'properties', ALLOWED_IMAGE_EXT);
            if ($id) {
                $sets = []; $vals = [];
                foreach ($f as $k => $v) { $sets[] = "$k=?"; $vals[] = $v; }
                if ($photo) { $sets[] = "photo=?"; $vals[] = $photo; }
                $vals[] = $id;
                Database::run("UPDATE testimonials SET " . implode(',', $sets) . " WHERE id=?", $vals);
                flash('success', 'Testimonial updated.');
            } else {
                $f['photo'] = $photo;
                $cols = array_keys($f); $ph = implode(',', array_fill(0, count($cols), '?'));
                Database::run("INSERT INTO testimonials (" . implode(',', $cols) . ") VALUES ($ph)", array_values($f));
                flash('success', 'Testimonial added.');
            }
        }
    } elseif ($action === 'delete') {
        require_role(['super_admin', 'admin']);
        Database::run("DELETE FROM testimonials WHERE id=?", [input_int('id')]);
        flash('success', 'Testimonial deleted.');
    }
    redirect('admin/testimonials.php');
}

$rows = Database::all("SELECT * FROM testimonials ORDER BY sort_order, id DESC");
$pageTitle = 'Testimonials';
$activeAdmin = 'testimonials';
include __DIR__ . '/templates/layout-head.php';
?>
<div style="display:grid;grid-template-columns:1fr 360px;gap:1.6rem" class="split-grid">
  <div class="panel">
    <div class="panel-head"><h2><?= count($rows) ?> testimonials</h2></div>
    <div class="table-wrap"><table class="data">
      <thead><tr><th>Name</th><th>Rating</th><th>Content</th><th>Active</th><th></th></tr></thead>
      <tbody>
        <?php if (!$rows): ?><tr><td colspan="5" class="muted">None yet.</td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><strong><?= e($r['name']) ?></strong><div class="muted" style="font-size:.78rem"><?= e($r['role'] ?? '') ?></div></td>
            <td class="gold"><?= str_repeat('★', (int) $r['rating']) ?></td>
            <td class="muted" style="max-width:280px"><?= e(mb_strimwidth((string) $r['content'], 0, 120, '…')) ?></td>
            <td><span class="pill <?= $r['is_active'] ? 'ok' : 'off' ?>"><?= $r['is_active'] ? 'Yes' : 'No' ?></span></td>
            <td><div class="a-actions">
              <button class="a-btn sm" type="button" onclick='editT(<?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
              <?php if (user_can('super_admin', 'admin')): ?><form method="post" data-confirm="Delete?" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="a-btn sm danger">×</button></form><?php endif; ?>
            </div></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <div class="panel">
    <div class="panel-head"><h2 id="tTitle">Add testimonial</h2></div>
    <div class="panel-body">
      <form method="post" enctype="multipart/form-data" id="tForm">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="tId">
        <div class="a-field"><label>Name</label><input class="a-input" name="name" id="tName" required></div>
        <div class="a-field"><label>Role / location</label><input class="a-input" name="role" id="tRole" placeholder="Homeowner, Nairobi"></div>
        <div class="a-field"><label>Rating</label><select class="a-select" name="rating" id="tRating"><?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= str_repeat('★', $i) ?></option><?php endfor; ?></select></div>
        <div class="a-field"><label>Content</label><textarea class="a-textarea" name="content" id="tContent" required></textarea></div>
        <div class="a-field"><label>Photo</label><input class="a-input" type="file" name="photo" accept="image/*"></div>
        <div class="a-field"><label>Sort order</label><input class="a-input" type="number" name="sort_order" id="tSort" value="0"></div>
        <label class="a-check"><input type="checkbox" name="is_active" id="tActive" value="1" checked> Active</label>
        <div style="display:flex;gap:.6rem;margin-top:.6rem"><button class="a-btn gold">Save</button><button class="a-btn ghost" type="button" onclick="document.getElementById('tForm').reset();document.getElementById('tId').value='';document.getElementById('tTitle').textContent='Add testimonial'">Clear</button></div>
      </form>
    </div>
  </div>
</div>
<style>@media(max-width:820px){.split-grid{grid-template-columns:1fr!important}}</style>
<script>
function editT(t){document.getElementById('tTitle').textContent='Edit testimonial';document.getElementById('tId').value=t.id;document.getElementById('tName').value=t.name||'';document.getElementById('tRole').value=t.role||'';document.getElementById('tRating').value=t.rating||5;document.getElementById('tContent').value=t.content||'';document.getElementById('tSort').value=t.sort_order||0;document.getElementById('tActive').checked=t.is_active==1;window.scrollTo({top:0,behavior:'smooth'});}
</script>
<?php include __DIR__ . '/templates/layout-foot.php'; ?>
