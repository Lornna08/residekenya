<?php
/** admin/agents.php — manage agent profiles. */
require __DIR__ . '/../includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) input('action', '');
    if ($action === 'save') {
        $id = input_int('id');
        $fields = [
            'name'     => trim((string) input('name', '')),
            'title'    => trim((string) input('title', '')) ?: null,
            'email'    => trim((string) input('email', '')) ?: null,
            'phone'    => trim((string) input('phone', '')) ?: null,
            'whatsapp' => trim((string) input('whatsapp', '')) ?: null,
            'bio'      => trim((string) input('bio', '')) ?: null,
            'is_active'=> input('is_active') ? 1 : 0,
        ];
        if ($fields['name'] !== '') {
            $photo = null;
            if (!empty($_FILES['photo']['name'])) $photo = save_upload($_FILES['photo'], 'properties', ALLOWED_IMAGE_EXT);
            if ($id) {
                $sets = []; $vals = [];
                foreach ($fields as $k => $v) { $sets[] = "$k=?"; $vals[] = $v; }
                if ($photo) { $sets[] = "photo=?"; $vals[] = $photo; }
                $vals[] = $id;
                Database::run("UPDATE agents SET " . implode(',', $sets) . " WHERE id=?", $vals);
                flash('success', 'Agent updated.');
            } else {
                $fields['photo'] = $photo;
                $cols = array_keys($fields); $ph = implode(',', array_fill(0, count($cols), '?'));
                Database::run("INSERT INTO agents (" . implode(',', $cols) . ") VALUES ($ph)", array_values($fields));
                flash('success', 'Agent added.');
            }
        }
    } elseif ($action === 'delete') {
        require_role(['super_admin', 'admin']);
        Database::run("DELETE FROM agents WHERE id=?", [input_int('id')]);
        flash('success', 'Agent deleted.');
    }
    redirect('admin/agents.php');
}

$rows = Database::all("SELECT a.*, (SELECT COUNT(*) FROM properties p WHERE p.agent_id=a.id) uses FROM agents a ORDER BY name");
$pageTitle = 'Agents';
$activeAdmin = 'agents';
include __DIR__ . '/templates/layout-head.php';
?>
<div style="display:grid;grid-template-columns:1fr 360px;gap:1.6rem" class="split-grid">
  <div class="panel">
    <div class="panel-head"><h2><?= count($rows) ?> agents</h2></div>
    <div class="table-wrap"><table class="data">
      <thead><tr><th></th><th>Name</th><th>Contact</th><th>Listings</th><th>Active</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><img class="thumb" style="border-radius:50%;width:40px;height:40px" src="<?= e(asset_url($r['photo'] ?? null)) ?>" alt="" onerror="this.style.opacity=.2"></td>
            <td><?= e($r['name']) ?><div class="muted" style="font-size:.78rem"><?= e($r['title'] ?? '') ?></div></td>
            <td class="muted"><?= e($r['phone'] ?? $r['email'] ?? '—') ?></td>
            <td class="muted"><?= (int) $r['uses'] ?></td>
            <td><span class="pill <?= $r['is_active'] ? 'ok' : 'off' ?>"><?= $r['is_active'] ? 'Yes' : 'No' ?></span></td>
            <td><div class="a-actions">
              <button class="a-btn sm" type="button" onclick='editAgent(<?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
              <?php if (user_can('super_admin', 'admin')): ?><form method="post" data-confirm="Delete agent?" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="a-btn sm danger">Delete</button></form><?php endif; ?>
            </div></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <div class="panel">
    <div class="panel-head"><h2 id="agTitle">Add agent</h2></div>
    <div class="panel-body">
      <form method="post" enctype="multipart/form-data" id="agForm">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="agId">
        <div class="a-field"><label>Name</label><input class="a-input" name="name" id="agName" required></div>
        <div class="a-field"><label>Title</label><input class="a-input" name="title" id="agTitle2" placeholder="Property Consultant"></div>
        <div class="a-field"><label>Email</label><input class="a-input" type="email" name="email" id="agEmail"></div>
        <div class="a-field"><label>Phone</label><input class="a-input" name="phone" id="agPhone"></div>
        <div class="a-field"><label>WhatsApp (digits only)</label><input class="a-input" name="whatsapp" id="agWa" placeholder="2547…"></div>
        <div class="a-field"><label>Bio</label><textarea class="a-textarea" name="bio" id="agBio" style="min-height:70px"></textarea></div>
        <div class="a-field"><label>Photo</label><input class="a-input" type="file" name="photo" accept="image/*"></div>
        <label class="a-check"><input type="checkbox" name="is_active" id="agActive" value="1" checked> Active</label>
        <div style="display:flex;gap:.6rem;margin-top:.6rem"><button class="a-btn gold">Save</button><button class="a-btn ghost" type="button" onclick="document.getElementById('agForm').reset();document.getElementById('agId').value='';document.getElementById('agTitle').textContent='Add agent'">Clear</button></div>
      </form>
    </div>
  </div>
</div>
<style>@media(max-width:820px){.split-grid{grid-template-columns:1fr!important}}</style>
<script>
function editAgent(a){
  document.getElementById('agTitle').textContent='Edit agent';
  document.getElementById('agId').value=a.id;
  document.getElementById('agName').value=a.name||'';
  document.getElementById('agTitle2').value=a.title||'';
  document.getElementById('agEmail').value=a.email||'';
  document.getElementById('agPhone').value=a.phone||'';
  document.getElementById('agWa').value=a.whatsapp||'';
  document.getElementById('agBio').value=a.bio||'';
  document.getElementById('agActive').checked=a.is_active==1;
  window.scrollTo({top:0,behavior:'smooth'});
}
</script>
<?php include __DIR__ . '/templates/layout-foot.php'; ?>
