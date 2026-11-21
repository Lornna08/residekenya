<?php
/** admin/users.php — manage staff/admin users. */
require __DIR__ . '/../includes/bootstrap.php';
require_role(['super_admin', 'admin']);

$me = current_user();
$roles = ['super_admin' => 'Super Admin', 'admin' => 'Admin', 'editor' => 'Editor', 'agent' => 'Agent'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) input('action', '');

    if ($action === 'save') {
        $id    = input_int('id');
        $name  = trim((string) input('name', ''));
        $email = trim((string) input('email', ''));
        $role  = in_array(input('role'), array_keys($roles), true) ? input('role') : 'editor';
        $pass  = (string) input('password', '');
        $active = input('is_active') ? 1 : 0;

        // Only super_admin may create/assign super_admin or admin roles
        if (in_array($role, ['super_admin', 'admin'], true) && $me['role'] !== 'super_admin') {
            $role = 'editor';
        }

        if ($name === '' || !is_email($email)) {
            flash('error', 'Valid name and email are required.');
            redirect('admin/users.php');
        }

        // Uniqueness check
        $dup = Database::row("SELECT id FROM users WHERE email=? AND id<>?", [$email, $id ?: 0]);
        if ($dup) { flash('error', 'That email is already in use.'); redirect('admin/users.php'); }

        if ($id) {
            // Prevent self-lockout: can't deactivate or demote yourself
            if ($id === (int) $me['id']) { $active = 1; $role = $me['role']; }
            $sets = ['name=?', 'email=?', 'role=?', 'is_active=?'];
            $vals = [$name, $email, $role, $active];
            if ($pass !== '') {
                if (strlen($pass) < 8) { flash('error', 'Password must be at least 8 characters.'); redirect('admin/users.php'); }
                $sets[] = 'password_hash=?'; $vals[] = password_hash($pass, PASSWORD_DEFAULT);
            }
            $vals[] = $id;
            Database::run("UPDATE users SET " . implode(',', $sets) . " WHERE id=?", $vals);
            log_activity('update_user', 'user', $id);
            flash('success', 'User updated.');
        } else {
            if (strlen($pass) < 8) { flash('error', 'New users need a password of at least 8 characters.'); redirect('admin/users.php'); }
            Database::run("INSERT INTO users (name, email, role, password_hash, is_active) VALUES (?,?,?,?,?)",
                [$name, $email, $role, password_hash($pass, PASSWORD_DEFAULT), $active]);
            log_activity('create_user', 'user', (int) Database::conn()->lastInsertId());
            flash('success', 'User created.');
        }
    } elseif ($action === 'delete') {
        require_role(['super_admin']);
        $id = input_int('id');
        if ($id === (int) $me['id']) { flash('error', "You can't delete your own account."); }
        else { Database::run("DELETE FROM users WHERE id=?", [$id]); log_activity('delete_user', 'user', $id); flash('success', 'User deleted.'); }
    }
    redirect('admin/users.php');
}

$rows = Database::all("SELECT * FROM users ORDER BY FIELD(role,'super_admin','admin','editor','agent'), name");
$pageTitle = 'Users';
$activeAdmin = 'users';
include __DIR__ . '/templates/layout-head.php';
?>
<div style="display:grid;grid-template-columns:1fr 360px;gap:1.6rem" class="split-grid">
  <div class="panel">
    <div class="panel-head"><h2><?= count($rows) ?> users</h2></div>
    <div class="table-wrap"><table class="data">
      <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Active</th><th>Last login</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= e($r['name']) ?><?php if ((int) $r['id'] === (int) $me['id']): ?> <span class="pill info">you</span><?php endif; ?></td>
            <td class="muted"><?= e($r['email']) ?></td>
            <td><span class="pill <?= in_array($r['role'], ['super_admin', 'admin']) ? 'warn' : '' ?>"><?= e($roles[$r['role']] ?? $r['role']) ?></span></td>
            <td><span class="pill <?= $r['is_active'] ? 'ok' : 'off' ?>"><?= $r['is_active'] ? 'Yes' : 'No' ?></span></td>
            <td class="muted" style="font-size:.78rem"><?= $r['last_login_at'] ? e(date('d M Y, H:i', strtotime($r['last_login_at']))) : '—' ?></td>
            <td><div class="a-actions">
              <button class="a-btn sm" type="button" onclick='editUser(<?= json_encode(['id' => $r['id'], 'name' => $r['name'], 'email' => $r['email'], 'role' => $r['role'], 'is_active' => $r['is_active']], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
              <?php if ($me['role'] === 'super_admin' && (int) $r['id'] !== (int) $me['id']): ?>
                <form method="post" data-confirm="Delete this user permanently?" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="a-btn sm danger">Delete</button></form>
              <?php endif; ?>
            </div></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2 id="uTitle">Add user</h2></div>
    <div class="panel-body">
      <form method="post" id="uForm">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="uId">
        <div class="a-field"><label>Full name</label><input class="a-input" name="name" id="uName" required></div>
        <div class="a-field"><label>Email</label><input class="a-input" type="email" name="email" id="uEmail" required></div>
        <div class="a-field"><label>Role</label><select class="a-select" name="role" id="uRole">
          <?php foreach ($roles as $k => $lbl):
            if (in_array($k, ['super_admin', 'admin'], true) && $me['role'] !== 'super_admin') continue; ?>
            <option value="<?= $k ?>"><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select></div>
        <div class="a-field"><label>Password <span class="muted" style="font-size:.72rem">(leave blank to keep unchanged when editing)</span></label><input class="a-input" type="password" name="password" id="uPass" autocomplete="new-password"></div>
        <label class="a-check"><input type="checkbox" name="is_active" id="uActive" value="1" checked> Active</label>
        <div style="display:flex;gap:.6rem;margin-top:.6rem"><button class="a-btn gold">Save</button><button class="a-btn ghost" type="button" onclick="resetU()">Clear</button></div>
      </form>
    </div>
  </div>
</div>
<style>@media(max-width:820px){.split-grid{grid-template-columns:1fr!important}}</style>
<script>
function editUser(u){
  document.getElementById('uTitle').textContent='Edit user';
  document.getElementById('uId').value=u.id;
  document.getElementById('uName').value=u.name||'';
  document.getElementById('uEmail').value=u.email||'';
  document.getElementById('uRole').value=u.role||'editor';
  document.getElementById('uActive').checked=u.is_active==1;
  document.getElementById('uPass').value='';
  window.scrollTo({top:0,behavior:'smooth'});
}
function resetU(){document.getElementById('uForm').reset();document.getElementById('uId').value='';document.getElementById('uTitle').textContent='Add user';}
</script>
<?php include __DIR__ . '/templates/layout-foot.php'; ?>
