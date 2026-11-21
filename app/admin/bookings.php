<?php
/** admin/bookings.php — manage Airbnb bookings. */
require __DIR__ . '/../includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) input('action', '');
    $id = input_int('id');
    if ($id && in_array($action, ['confirm', 'decline', 'cancel'], true)) {
        $map = ['confirm' => 'confirmed', 'decline' => 'declined', 'cancel' => 'cancelled'];
        $newStatus = $map[$action];
        $note = trim((string) input('note', ''));
        Database::run("UPDATE bookings SET status=?, notes=COALESCE(notes,'') || CAST(? AS TEXT) WHERE id=?",
            [$newStatus, $note ? "\n[Admin] " . $note : '', $id]);
        // Notify guest
        $b = Database::row("SELECT b.*, p.title FROM bookings b JOIN properties p ON p.id=b.property_id WHERE b.id=?", [$id]);
        if ($b && is_email($b['guest_email'])) {
            $verb = $newStatus === 'confirmed' ? 'confirmed' : ($newStatus === 'declined' ? 'could not be confirmed' : 'cancelled');
            send_mail($b['guest_email'], 'Your booking has been ' . $newStatus . ' — ' . SITE_NAME,
                '<h2 style="font-family:Georgia,serif">Booking ' . e($newStatus) . '</h2>'
                . '<p style="font-family:Arial,sans-serif;font-size:14px">Hi ' . e($b['guest_name']) . ', your booking request for <strong>' . e($b['title']) . '</strong> ('
                . e(date('d M', strtotime($b['check_in']))) . ' – ' . e(date('d M Y', strtotime($b['check_out']))) . ') has ' . e($verb) . '.'
                . ($note ? '<br><br>' . nl2br(e($note)) : '') . '</p>');
        }
        log_activity('booking_' . $newStatus, 'booking', $id);
        flash('success', 'Booking ' . $newStatus . '.');
    } elseif ($action === 'delete' && $id) {
        require_role(['super_admin', 'admin']);
        Database::run("DELETE FROM bookings WHERE id=?", [$id]);
        flash('success', 'Booking deleted.');
    }
    redirect('admin/bookings.php');
}

$filter = (string) input('filter', '');
$where = in_array($filter, ['pending', 'confirmed', 'declined', 'cancelled'], true) ? "WHERE b.status=" . Database::conn()->quote($filter) : "";
$rows = Database::all("SELECT b.*, p.title, p.slug FROM bookings b JOIN properties p ON p.id=b.property_id $where ORDER BY b.created_at DESC LIMIT 200");
$pageTitle = 'Bookings';
$activeAdmin = 'bookings';
include __DIR__ . '/templates/layout-head.php';
?>
<div class="panel">
  <div class="panel-head">
    <h2><?= count($rows) ?> bookings</h2>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
      <?php foreach (['' => 'All', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'declined' => 'Declined'] as $k => $lbl): ?>
        <a class="a-btn sm <?= $filter === $k ? 'gold' : '' ?>" href="?<?= $k ? 'filter=' . $k : '' ?>"><?= $lbl ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="table-wrap"><table class="data">
    <thead><tr><th>Guest</th><th>Property</th><th>Dates</th><th>Guests</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="7" class="muted">No bookings.</td></tr><?php endif; ?>
      <?php foreach ($rows as $b): ?>
        <tr>
          <td><strong><?= e($b['guest_name']) ?></strong><div class="muted" style="font-size:.78rem"><?= e($b['guest_email'] ?: $b['guest_phone']) ?></div></td>
          <td class="muted"><a href="<?= e(url('property.php?slug=' . eurl($b['slug']))) ?>" target="_blank" rel="noopener"><?= e($b['title']) ?></a></td>
          <td class="muted" style="white-space:nowrap"><?= e(date('d M', strtotime($b['check_in']))) ?> – <?= e(date('d M Y', strtotime($b['check_out']))) ?><div style="font-size:.72rem"><?= (int) $b['nights'] ?> nights</div></td>
          <td class="muted"><?= (int) $b['guests'] ?></td>
          <td><?= e(money($b['total_amount'] ?? null)) ?></td>
          <td><span class="pill <?= $b['status'] === 'confirmed' ? 'ok' : ($b['status'] === 'pending' ? 'warn' : 'off') ?>"><?= e($b['status']) ?></span></td>
          <td>
            <?php if ($b['status'] === 'pending'): ?>
              <div class="a-actions">
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="confirm"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><button class="a-btn sm gold">Confirm</button></form>
                <form method="post" onsubmit="var n=prompt('Reason (optional, emailed to guest):');if(n===null)return false;this.note.value=n;"><?= csrf_field() ?><input type="hidden" name="action" value="decline"><input type="hidden" name="note" value=""><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><button class="a-btn sm danger">Decline</button></form>
              </div>
            <?php else: ?>
              <div class="a-actions">
                <?php if ($b['status'] !== 'cancelled'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><button class="a-btn sm">Cancel</button></form><?php endif; ?>
                <?php if (user_can('super_admin', 'admin')): ?><form method="post" data-confirm="Delete?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><button class="a-btn sm danger">×</button></form><?php endif; ?>
              </div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php include __DIR__ . '/templates/layout-foot.php'; ?>
