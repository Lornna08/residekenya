<?php
/** admin/submissions.php — client property submissions. */
require __DIR__ . '/../includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) input('action', '');
    $id = input_int('id');
    if ($id && in_array($action, ['reviewed', 'published', 'rejected', 'new'], true)) {
        Database::run("UPDATE submissions SET status=? WHERE id=?", [$action, $id]);
        flash('success', 'Submission marked ' . $action . '.');
    } elseif ($action === 'delete' && $id) {
        require_role(['super_admin', 'admin']);
        Database::run("DELETE FROM submissions WHERE id=?", [$id]);
        flash('success', 'Submission deleted.');
    }
    redirect('admin/submissions.php');
}

$rows = Database::all("SELECT s.*, c.name county_name FROM submissions s LEFT JOIN counties c ON c.id=s.county_id ORDER BY s.created_at DESC LIMIT 200");
$pageTitle = 'Submissions';
$activeAdmin = 'submissions';
include __DIR__ . '/templates/layout-head.php';
?>
<div class="panel">
  <div class="panel-head"><h2><?= count($rows) ?> submissions</h2></div>
  <div class="table-wrap"><table class="data">
    <thead><tr><th>Submitter</th><th>Property</th><th>Location</th><th>Price</th><th>Photos</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="7" class="muted">No submissions yet.</td></tr><?php endif; ?>
      <?php foreach ($rows as $s):
        $photos = $s['photos_json'] ? json_decode($s['photos_json'], true) : [];
      ?>
        <tr>
          <td><strong><?= e($s['full_name']) ?></strong><div class="muted" style="font-size:.78rem"><?= e($s['phone']) ?><?php if ($s['email']): ?><br><?= e($s['email']) ?><?php endif; ?></div><div class="muted" style="font-size:.72rem">Prefers: <?= e($s['preferred_contact']) ?></div></td>
          <td><span class="pill"><?= e(['selling' => 'Sell', 'renting' => 'Rent', 'airbnb' => 'Airbnb'][$s['property_status']] ?? $s['property_status']) ?></span> <?= e($s['property_type'] ?? '') ?><div class="muted" style="font-size:.78rem;max-width:220px"><?= e(mb_strimwidth((string) $s['description'], 0, 90, '…')) ?></div><div class="muted" style="font-size:.72rem"><?= (int) $s['bedrooms'] ?>bd · <?= (int) $s['bathrooms'] ?>ba</div></td>
          <td class="muted"><?= e($s['location'] ?? '—') ?><?php if ($s['county_name']): ?><div style="font-size:.72rem"><?= e($s['county_name']) ?></div><?php endif; ?><?php if ($s['maps_url']): ?><br><a href="<?= e($s['maps_url']) ?>" target="_blank" rel="noopener">Map</a><?php endif; ?></td>
          <td><?= e($s['expected_price'] !== null ? money((float) $s['expected_price']) : '—') ?></td>
          <td>
            <?php if ($photos): ?>
              <div class="img-preview"><?php foreach (array_slice($photos, 0, 3) as $ph): ?><div class="ip" style="width:44px;height:34px"><a href="<?= e(asset_url($ph)) ?>" target="_blank" rel="noopener"><img src="<?= e(asset_url($ph)) ?>" alt=""></a></div><?php endforeach; ?></div>
              <span class="muted" style="font-size:.72rem"><?= count($photos) ?> total</span>
            <?php else: ?><span class="muted">—</span><?php endif; ?>
          </td>
          <td><span class="pill <?= $s['status'] === 'published' ? 'ok' : ($s['status'] === 'new' ? 'warn' : ($s['status'] === 'rejected' ? 'off' : 'info')) ?>"><?= e($s['status']) ?></span></td>
          <td><div class="a-actions">
            <?php if ($s['status'] === 'new'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reviewed"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="a-btn sm">Reviewed</button></form><?php endif; ?>
            <a class="a-btn sm gold" href="<?= e(url('admin/property-edit.php')) ?>" title="Create a listing from this">+ Listing</a>
            <?php if ($s['status'] !== 'rejected'): ?><form method="post" data-confirm="Reject submission?"><?= csrf_field() ?><input type="hidden" name="action" value="rejected"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="a-btn sm danger">Reject</button></form><?php endif; ?>
            <?php if (user_can('super_admin', 'admin')): ?><form method="post" data-confirm="Delete?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="a-btn sm danger">×</button></form><?php endif; ?>
          </div></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php include __DIR__ . '/templates/layout-foot.php'; ?>
