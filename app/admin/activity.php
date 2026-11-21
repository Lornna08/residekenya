<?php
/** admin/activity.php — read-only activity log. */
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$page = max(1, input_int('page', 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;
$total = (int) (Database::row("SELECT COUNT(*) n FROM activity_log")['n'] ?? 0);
$pages = max(1, (int) ceil($total / $perPage));

$rows = Database::all(
    "SELECT a.*, u.name AS user_name FROM activity_log a LEFT JOIN users u ON u.id=a.user_id
     ORDER BY a.created_at DESC LIMIT $perPage OFFSET $offset"
);
$pageTitle = 'Activity Log';
$activeAdmin = 'activity';
include __DIR__ . '/templates/layout-head.php';
?>
<div class="panel">
  <div class="panel-head"><h2><?= number_format($total) ?> events</h2></div>
  <div class="table-wrap"><table class="data">
    <thead><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>IP</th></tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="5" class="muted">No activity recorded yet.</td></tr><?php endif; ?>
      <?php foreach ($rows as $a): ?>
        <tr>
          <td class="muted" style="white-space:nowrap"><?= e(date('d M Y, H:i:s', strtotime($a['created_at']))) ?></td>
          <td><?= e($a['user_name'] ?? 'System') ?></td>
          <td><span class="pill"><?= e($a['action']) ?></span></td>
          <td class="muted"><?= e($a['entity_type'] ?? '') ?><?= $a['entity_id'] ? ' #' . (int) $a['entity_id'] : '' ?></td>
          <td class="muted" style="font-size:.78rem"><?= e($a['ip_address'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if ($pages > 1): ?>
  <div class="panel-body">
    <div class="a-pager">
      <?php for ($i = max(1, $page - 3); $i <= min($pages, $page + 3); $i++): ?>
        <?php if ($i === $page): ?><span class="cur"><?= $i ?></span><?php else: ?><a href="?page=<?= $i ?>"><?= $i ?></a><?php endif; ?>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/templates/layout-foot.php'; ?>
