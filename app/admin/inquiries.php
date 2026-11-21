<?php
/** admin/inquiries.php — view and manage inquiries. */
require __DIR__ . '/../includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) input('action', '');
    $id = input_int('id');
    if ($action === 'read' && $id) { Database::run("UPDATE inquiries SET is_read=1 WHERE id=?", [$id]); }
    elseif ($action === 'unread' && $id) { Database::run("UPDATE inquiries SET is_read=0 WHERE id=?", [$id]); }
    elseif ($action === 'delete' && $id) { require_role(['super_admin', 'admin']); Database::run("DELETE FROM inquiries WHERE id=?", [$id]); flash('success', 'Inquiry deleted.'); }
    elseif ($action === 'read_all') { Database::run("UPDATE inquiries SET is_read=1 WHERE is_read=0"); flash('success', 'All marked read.'); }
    redirect('admin/inquiries.php');
}

$filter = (string) input('filter', '');
$where = $filter === 'unread' ? "WHERE i.is_read=0" : ($filter && in_array($filter, ['contact', 'viewing', 'agent', 'general'], true) ? "WHERE i.type=" . Database::conn()->quote($filter) : "");
$rows = Database::all("SELECT i.*, p.title prop_title, p.slug prop_slug FROM inquiries i LEFT JOIN properties p ON p.id=i.property_id $where ORDER BY i.created_at DESC LIMIT 200");
$pageTitle = 'Inquiries';
$activeAdmin = 'inquiries';
include __DIR__ . '/templates/layout-head.php';
?>
<div class="panel">
  <div class="panel-head">
    <h2><?= count($rows) ?> inquiries</h2>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
      <a class="a-btn sm <?= $filter === '' ? 'gold' : '' ?>" href="?">All</a>
      <a class="a-btn sm <?= $filter === 'unread' ? 'gold' : '' ?>" href="?filter=unread">Unread</a>
      <a class="a-btn sm <?= $filter === 'viewing' ? 'gold' : '' ?>" href="?filter=viewing">Viewings</a>
      <a class="a-btn sm <?= $filter === 'agent' ? 'gold' : '' ?>" href="?filter=agent">Agent</a>
      <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="read_all"><button class="a-btn sm">Mark all read</button></form>
    </div>
  </div>
  <div class="table-wrap"><table class="data">
    <thead><tr><th>From</th><th>Type</th><th>Property</th><th>Message</th><th>Date</th><th></th></tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="6" class="muted">No inquiries.</td></tr><?php endif; ?>
      <?php foreach ($rows as $i): ?>
        <tr style="<?= $i['is_read'] ? '' : 'background:rgba(200,167,93,.05)' ?>">
          <td>
            <strong><?= e($i['name']) ?></strong><?php if (!$i['is_read']): ?> <span class="pill warn">new</span><?php endif; ?>
            <div class="muted" style="font-size:.78rem">
              <?php if ($i['email']): ?><a href="mailto:<?= e($i['email']) ?>"><?= e($i['email']) ?></a><?php endif; ?>
              <?php if ($i['phone']): ?><br><a href="tel:<?= e($i['phone']) ?>"><?= e($i['phone']) ?></a><?php endif; ?>
            </div>
          </td>
          <td><span class="pill"><?= e($i['type']) ?></span><?php if ($i['preferred_date']): ?><div class="muted" style="font-size:.72rem"><?= e(date('d M Y', strtotime($i['preferred_date']))) ?></div><?php endif; ?></td>
          <td class="muted"><?php if ($i['prop_slug']): ?><a href="<?= e(url('property.php?slug=' . eurl($i['prop_slug']))) ?>" target="_blank" rel="noopener"><?= e($i['prop_title']) ?></a><?php else: ?>—<?php endif; ?></td>
          <td class="muted" style="max-width:280px"><?= nl2br(e(mb_strimwidth((string) $i['message'], 0, 160, '…'))) ?></td>
          <td class="muted" style="white-space:nowrap"><?= e(date('d M, H:i', strtotime($i['created_at']))) ?></td>
          <td><div class="a-actions">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $i['is_read'] ? 'unread' : 'read' ?>"><input type="hidden" name="id" value="<?= (int) $i['id'] ?>"><button class="a-btn sm"><?= $i['is_read'] ? 'Unread' : 'Read' ?></button></form>
            <?php if (user_can('super_admin', 'admin')): ?><form method="post" data-confirm="Delete?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $i['id'] ?>"><button class="a-btn sm danger">×</button></form><?php endif; ?>
          </div></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php include __DIR__ . '/templates/layout-foot.php'; ?>
