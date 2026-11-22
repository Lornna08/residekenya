<?php
/** admin/index.php — dashboard */
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$stats = [
    'properties' => (int) (Database::row("SELECT COUNT(*) n FROM properties")['n'] ?? 0),
    'available'  => (int) (Database::row("SELECT COUNT(*) n FROM properties WHERE status='available'")['n'] ?? 0),
    'featured'   => (int) (Database::row("SELECT COUNT(*) n FROM properties WHERE is_featured=1")['n'] ?? 0),
    'inquiries'  => (int) (Database::row("SELECT COUNT(*) n FROM inquiries WHERE is_read=0")['n'] ?? 0),
    'bookings'   => (int) (Database::row("SELECT COUNT(*) n FROM bookings WHERE status='pending'")['n'] ?? 0),
    'submissions'=> (int) (Database::row("SELECT COUNT(*) n FROM submissions WHERE status='new'")['n'] ?? 0),
    'subscribers'=> (int) (Database::row("SELECT COUNT(*) n FROM subscribers")['n'] ?? 0),
    'views'      => (int) (Database::row("SELECT COALESCE(SUM(views),0) n FROM properties")['n'] ?? 0),
];

$recentInquiries = Database::all("SELECT i.*, p.title AS prop_title FROM inquiries i LEFT JOIN properties p ON p.id=i.property_id ORDER BY i.created_at DESC LIMIT 6");
$recentProps = Database::all("SELECT p.*, co.name AS county_name FROM properties p LEFT JOIN counties co ON co.id=p.county_id ORDER BY p.created_at DESC LIMIT 6");
$topViewed = Database::all("SELECT title, slug, views FROM properties ORDER BY views DESC LIMIT 5");

$pageTitle = 'Dashboard';
$activeAdmin = 'dashboard';
include __DIR__ . '/templates/layout-head.php';
?>
<div class="quick-row">
  <a class="a-btn gold quick-cta" href="<?= e(url('admin/quick-listing.php')) ?>">+ New listing</a>
  <div class="panel notify-card" id="notifyCard">
    <div class="nc-text">
      <strong>Phone notifications</strong> <span class="pill" data-n-status>Checking…</span>
      <p class="muted" data-n-hint></p>
    </div>
    <div class="nc-actions">
      <button type="button" class="a-btn sm gold" data-n-toggle hidden>Turn on notifications</button>
      <button type="button" class="a-btn sm" data-n-test hidden>Send test</button>
    </div>
  </div>
</div>
<div class="stat-cards">
  <a class="a-card link" href="<?= e(url('admin/properties.php')) ?>"><div class="lbl">Total Properties</div><div class="num gold"><?= number_format($stats['properties']) ?></div></a>
  <a class="a-card link" href="<?= e(url('admin/properties.php?status=available')) ?>"><div class="lbl">Available</div><div class="num"><?= number_format($stats['available']) ?></div></a>
  <a class="a-card link" href="<?= e(url('admin/inquiries.php')) ?>"><div class="lbl">Unread Inquiries</div><div class="num"><?= number_format($stats['inquiries']) ?></div></a>
  <a class="a-card link" href="<?= e(url('admin/bookings.php')) ?>"><div class="lbl">Pending Bookings</div><div class="num"><?= number_format($stats['bookings']) ?></div></a>
  <a class="a-card link" href="<?= e(url('admin/submissions.php')) ?>"><div class="lbl">New Submissions</div><div class="num"><?= number_format($stats['submissions']) ?></div></a>
  <a class="a-card link" href="<?= e(url('admin/subscribers.php')) ?>"><div class="lbl">Subscribers</div><div class="num"><?= number_format($stats['subscribers']) ?></div></a>
  <div class="a-card"><div class="lbl">Total Views</div><div class="num"><?= number_format($stats['views']) ?></div></div>
  <div class="a-card"><div class="lbl">Featured</div><div class="num"><?= number_format($stats['featured']) ?></div></div>
</div>

<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:1.6rem" class="dash-grid">
  <div class="panel">
    <div class="panel-head"><h2>Recent inquiries</h2><a class="a-btn sm" href="<?= e(url('admin/inquiries.php')) ?>">View all</a></div>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Name</th><th>Type</th><th>Property</th><th>When</th></tr></thead>
        <tbody>
          <?php if (!$recentInquiries): ?><tr><td colspan="4" class="muted">No inquiries yet.</td></tr><?php endif; ?>
          <?php foreach ($recentInquiries as $i): ?>
            <tr>
              <td><?= e($i['name']) ?><?php if (!$i['is_read']): ?> <span class="pill warn">new</span><?php endif; ?><div class="muted" style="font-size:.78rem"><?= e($i['email'] ?: $i['phone']) ?></div></td>
              <td><span class="pill"><?= e($i['type']) ?></span></td>
              <td class="muted"><?= e($i['prop_title'] ?? '—') ?></td>
              <td class="muted"><?= e(date('d M, H:i', strtotime($i['created_at']))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Most viewed</h2></div>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Property</th><th>Views</th></tr></thead>
        <tbody>
          <?php if (!$topViewed): ?><tr><td colspan="2" class="muted">No data yet.</td></tr><?php endif; ?>
          <?php foreach ($topViewed as $t): ?>
            <tr><td><a href="<?= e(url('property.php?slug=' . eurl($t['slug']))) ?>" target="_blank" rel="noopener"><?= e($t['title']) ?></a></td><td class="gold"><?= number_format($t['views']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h2>Recently added properties</h2><a class="a-btn gold sm" href="<?= e(url('admin/property-edit.php')) ?>">+ Add Property</a></div>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th></th><th>Title</th><th>Ref</th><th>Purpose</th><th>Price</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php if (!$recentProps): ?><tr><td colspan="7" class="muted">No properties yet. <a href="<?= e(url('admin/property-edit.php')) ?>">Add your first →</a></td></tr><?php endif; ?>
        <?php foreach ($recentProps as $p): ?>
          <tr>
            <td><img class="thumb" src="<?= e(asset_url($p['featured_image'] ?? null)) ?>" alt="" onerror="this.style.opacity=.2"></td>
            <td><?= e($p['title']) ?></td>
            <td class="muted"><?= e($p['ref_code']) ?></td>
            <td><span class="pill"><?= e($p['purpose']) ?></span></td>
            <td><?= e(money($p['price'] ?? null, $p['currency'] ?? 'KES')) ?></td>
            <td><span class="pill <?= $p['status'] === 'available' ? 'ok' : 'off' ?>"><?= e($p['status']) ?></span></td>
            <td><a class="a-btn sm" href="<?= e(url('admin/property-edit.php?id=' . (int) $p['id'])) ?>">Edit</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<style>@media(max-width:900px){.dash-grid{grid-template-columns:1fr!important}}</style>
<?php include __DIR__ . '/templates/layout-foot.php'; ?>
