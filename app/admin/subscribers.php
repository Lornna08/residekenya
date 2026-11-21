<?php
/** admin/subscribers.php — newsletter subscribers + CSV export. */
require __DIR__ . '/../includes/bootstrap.php';
require_login();

// CSV export
if (input('export') === 'csv') {
    $all = Database::all("SELECT email, created_at FROM subscribers ORDER BY created_at DESC");
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="reside-subscribers-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Email', 'Subscribed At']);
    foreach ($all as $r) fputcsv($out, [$r['email'], $r['created_at']]);
    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (input('action') === 'delete') {
        require_role(['super_admin', 'admin']);
        Database::run("DELETE FROM subscribers WHERE id=?", [input_int('id')]);
        flash('success', 'Subscriber removed.');
    }
    redirect('admin/subscribers.php');
}

$rows = Database::all("SELECT * FROM subscribers ORDER BY created_at DESC LIMIT 500");
$pageTitle = 'Subscribers';
$activeAdmin = 'subscribers';
include __DIR__ . '/templates/layout-head.php';
?>
<div class="panel">
  <div class="panel-head"><h2><?= count($rows) ?> subscribers</h2><a class="a-btn gold sm" href="?export=csv">Export CSV</a></div>
  <div class="table-wrap"><table class="data">
    <thead><tr><th>Email</th><th>Subscribed</th><th></th></tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="3" class="muted">No subscribers yet.</td></tr><?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a></td>
          <td class="muted"><?= e(date('d M Y, H:i', strtotime($r['created_at']))) ?></td>
          <td><?php if (user_can('super_admin', 'admin')): ?><form method="post" data-confirm="Remove subscriber?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="a-btn sm danger">×</button></form><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php include __DIR__ . '/templates/layout-foot.php'; ?>
