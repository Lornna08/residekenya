<?php
/** admin/properties.php — list, filter, bulk actions. */
require __DIR__ . '/../includes/bootstrap.php';
require_login();

// Handle single-action POSTs (feature toggle, delete, duplicate, status)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) input('action', '');
    $id = input_int('id');

    // Bulk
    $bulkIds = array_filter(array_map('intval', (array) input('ids', [])));
    if ($action === 'bulk' && $bulkIds) {
        $op = (string) input('bulk_op', '');
        $in = implode(',', array_fill(0, count($bulkIds), '?'));
        if ($op === 'delete') {
            require_role(['super_admin', 'admin']);
            Database::run("DELETE FROM properties WHERE id IN ($in)", $bulkIds);
            log_activity('bulk_delete_properties', 'property', null);
            flash('success', count($bulkIds) . ' properties deleted.');
        } elseif (in_array($op, ['available', 'sold', 'rented', 'archived', 'draft'], true)) {
            Database::run("UPDATE properties SET status=? WHERE id IN ($in)", array_merge([$op], $bulkIds));
            flash('success', count($bulkIds) . ' properties updated to ' . $op . '.');
        } elseif ($op === 'feature' || $op === 'unfeature') {
            Database::run("UPDATE properties SET is_featured=? WHERE id IN ($in)", array_merge([$op === 'feature' ? 1 : 0], $bulkIds));
            flash('success', 'Featured flag updated.');
        }
        redirect('admin/properties.php');
    }

    if ($id) {
        if ($action === 'feature') {
            Database::run("UPDATE properties SET is_featured = 1 - is_featured WHERE id = ?", [$id]);
            log_activity('toggle_feature', 'property', $id);
            flash('success', 'Featured status updated.');
        } elseif ($action === 'luxury') {
            Database::run("UPDATE properties SET is_luxury = 1 - is_luxury WHERE id = ?", [$id]);
            flash('success', 'Luxury status updated.');
        } elseif ($action === 'delete') {
            require_role(['super_admin', 'admin']);
            Database::run("DELETE FROM properties WHERE id = ?", [$id]);
            log_activity('delete_property', 'property', $id);
            flash('success', 'Property deleted.');
        } elseif ($action === 'duplicate') {
            $src = Property::findById($id);
            if ($src) {
                unset($src['id']);
                $src['ref_code'] = Property::generateRef();
                $src['slug'] = unique_slug('properties', $src['title'] . '-copy');
                $src['title'] = $src['title'] . ' (Copy)';
                $src['status'] = 'draft';
                $src['is_featured'] = 0;
                $src['views'] = 0;
                $cols = array_keys($src);
                $ph = implode(',', array_fill(0, count($cols), '?'));
                Database::run("INSERT INTO properties (" . implode(',', $cols) . ") VALUES ($ph)", array_values($src));
                flash('success', 'Property duplicated as draft.');
            }
        }
    }
    redirect('admin/properties.php' . (input('qs') ? '?' . input('qs') : ''));
}

// Filters
$f = [
    'keyword'  => input('keyword'),
    'purpose'  => input('purpose'),
    'status'   => input('status'),
    'per_page' => 20,
    'page'     => input_int('page', 1),
    'sort'     => 'newest',
];
// Custom query to include all statuses (admin sees drafts/archived too)
$where = ['1=1'];
$params = [];
if (!empty($f['keyword'])) { $kw = '%' . $f['keyword'] . '%'; $where[] = '(p.title LIKE ? OR p.ref_code LIKE ?)'; array_push($params, $kw, $kw); }
if (!empty($f['purpose'])) { $where[] = 'p.purpose = ?'; $params[] = $f['purpose']; }
if (!empty($f['status']))  { $where[] = 'p.status = ?'; $params[] = $f['status']; }
$whereSql = implode(' AND ', $where);
$total = (int) (Database::row("SELECT COUNT(*) n FROM properties p WHERE $whereSql", $params)['n'] ?? 0);
$perPage = 20; $page = max(1, (int) $f['page']); $pages = max(1, (int) ceil($total / $perPage)); $offset = ($page - 1) * $perPage;
$items = Database::all(
    "SELECT p.*, co.name AS county_name, c.name AS category_name
     FROM properties p LEFT JOIN counties co ON co.id=p.county_id LEFT JOIN categories c ON c.id=p.category_id
     WHERE $whereSql ORDER BY p.created_at DESC LIMIT $perPage OFFSET $offset",
    $params
);
$qs = http_build_query(array_filter(['keyword' => $f['keyword'], 'purpose' => $f['purpose'], 'status' => $f['status']]));

$pageTitle = 'Properties';
$activeAdmin = 'properties';
include __DIR__ . '/templates/layout-head.php';
?>
<div class="panel">
  <div class="panel-head">
    <h2><?= number_format($total) ?> properties</h2>
    <a class="a-btn gold sm" href="<?= e(url('admin/property-edit.php')) ?>">+ Add Property</a>
  </div>
  <div class="panel-body">
    <form method="get" style="display:flex;gap:.7rem;flex-wrap:wrap;align-items:end;margin-bottom:1.2rem">
      <div class="a-field" style="margin:0;min-width:200px;flex:1"><label>Search</label><input class="a-input" type="text" name="keyword" value="<?= e((string) $f['keyword']) ?>" placeholder="Title or ref…"></div>
      <div class="a-field" style="margin:0"><label>Purpose</label><select class="a-select" name="purpose">
        <option value="">All</option><?php foreach (['sale', 'rent', 'airbnb', 'commercial', 'land'] as $o): ?><option value="<?= $o ?>" <?= $f['purpose'] === $o ? 'selected' : '' ?>><?= ucfirst($o) ?></option><?php endforeach; ?>
      </select></div>
      <div class="a-field" style="margin:0"><label>Status</label><select class="a-select" name="status">
        <option value="">All</option><?php foreach (['available', 'under_offer', 'sold', 'rented', 'archived', 'draft'] as $o): ?><option value="<?= $o ?>" <?= $f['status'] === $o ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $o)) ?></option><?php endforeach; ?>
      </select></div>
      <button class="a-btn" type="submit">Filter</button>
      <?php if ($qs): ?><a class="a-btn ghost" href="<?= e(url('admin/properties.php')) ?>">Reset</a><?php endif; ?>
    </form>

    <form method="post" id="bulkForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="bulk">
      <div style="display:flex;gap:.6rem;align-items:center;margin-bottom:.8rem;flex-wrap:wrap">
        <select class="a-select" name="bulk_op" style="width:auto">
          <option value="">Bulk actions…</option>
          <option value="feature">Mark featured</option>
          <option value="unfeature">Remove featured</option>
          <option value="available">Set available</option>
          <option value="sold">Set sold</option>
          <option value="archived">Archive</option>
          <?php if (user_can('super_admin', 'admin')): ?><option value="delete">Delete</option><?php endif; ?>
        </select>
        <button class="a-btn sm" type="submit" onclick="return confirm('Apply bulk action to selected?')">Apply</button>
      </div>
      <div class="table-wrap">
        <table class="data">
          <thead><tr>
            <th><input type="checkbox" id="checkAll"></th><th></th><th>Title</th><th>Ref</th><th>Purpose</th><th>Price</th><th>County</th><th>Views</th><th>Status</th><th>Flags</th><th>Actions</th>
          </tr></thead>
          <tbody>
            <?php if (!$items): ?><tr><td colspan="11" class="muted">No properties found.</td></tr><?php endif; ?>
            <?php foreach ($items as $p): ?>
              <tr>
                <td><input type="checkbox" name="ids[]" value="<?= (int) $p['id'] ?>" class="rowchk"></td>
                <td><img class="thumb" src="<?= e(asset_url($p['featured_image'] ?? null)) ?>" alt="" onerror="this.style.opacity=.2"></td>
                <td><a href="<?= e(url('admin/property-edit.php?id=' . (int) $p['id'])) ?>"><?= e($p['title']) ?></a></td>
                <td class="muted"><?= e($p['ref_code']) ?></td>
                <td><span class="pill"><?= e($p['purpose']) ?></span></td>
                <td><?= e(money($p['price'] ?? null, $p['currency'] ?? 'KES')) ?></td>
                <td class="muted"><?= e($p['county_name'] ?? '—') ?></td>
                <td class="muted"><?= number_format($p['views']) ?></td>
                <td><span class="pill <?= $p['status'] === 'available' ? 'ok' : ($p['status'] === 'draft' ? 'warn' : 'off') ?>"><?= e(str_replace('_', ' ', $p['status'])) ?></span></td>
                <td>
                  <?php if ($p['is_featured']): ?><span class="pill warn">★</span><?php endif; ?>
                  <?php if ($p['is_luxury']): ?><span class="pill info">Lux</span><?php endif; ?>
                </td>
                <td>
                  <div class="a-actions">
                    <a class="a-btn sm" href="<?= e(url('property.php?slug=' . eurl($p['slug']))) ?>" target="_blank" rel="noopener">View</a>
                    <a class="a-btn sm" href="<?= e(url('admin/property-edit.php?id=' . (int) $p['id'])) ?>">Edit</a>
                  </div>
                </td>
              </tr>
              <tr><td colspan="11" style="padding-top:0;border-bottom:1px solid var(--a-line)">
                <div class="a-actions" style="justify-content:flex-end">
                  <?php $mk = function ($act, $lbl, $cls = 'sm', $confirm = '') use ($p, $qs) { ?>
                    <form method="post" style="display:inline" <?= $confirm ? 'data-confirm="' . e($confirm) . '"' : '' ?>>
                      <?= csrf_field() ?><input type="hidden" name="action" value="<?= e($act) ?>"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="qs" value="<?= e($qs) ?>">
                      <button class="a-btn <?= e($cls) ?>" type="submit"><?= e($lbl) ?></button>
                    </form>
                  <?php }; ?>
                  <?php $mk('feature', $p['is_featured'] ? 'Unfeature' : 'Feature'); ?>
                  <?php $mk('luxury', $p['is_luxury'] ? 'Un-luxury' : 'Mark luxury'); ?>
                  <?php $mk('duplicate', 'Duplicate'); ?>
                  <?php if (user_can('super_admin', 'admin')) $mk('delete', 'Delete', 'sm danger', 'Delete this property permanently?'); ?>
                </div>
              </td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </form>

    <?php if ($pages > 1): ?>
    <div class="a-pager">
      <?php for ($i = 1; $i <= $pages; $i++): $u = url('admin/properties.php?' . http_build_query(array_filter(['keyword' => $f['keyword'], 'purpose' => $f['purpose'], 'status' => $f['status'], 'page' => $i]))); ?>
        <?php if ($i === $page): ?><span class="cur"><?= $i ?></span><?php else: ?><a href="<?= e($u) ?>"><?= $i ?></a><?php endif; ?>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<script>
document.getElementById('checkAll').addEventListener('change', function(){
  document.querySelectorAll('.rowchk').forEach(function(c){ c.checked = this.checked; }, this);
});
</script>
<?php include __DIR__ . '/templates/layout-foot.php'; ?>
